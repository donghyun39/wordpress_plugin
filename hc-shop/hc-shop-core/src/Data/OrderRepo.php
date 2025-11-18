<?php
namespace HC\Shop\Data;

class OrderRepo
{
    private string $tOrders;
    private string $tItems;
    private string $tProducts;
    private const STATUSES = [
        'pending',
        'paid',
        'processing',
        'shipped',
        'completed',
        'cancelled',
        'refunded',
    ];

    public function __construct()
    {
        global $wpdb;
        $this->tOrders = $wpdb->prefix . 'hc_orders';
        $this->tItems = $wpdb->prefix . 'hc_order_items';
        $this->tProducts = $wpdb->prefix . 'hc_products';
    }

    private function enc($v)
    {
        return $v === null ? null : wp_json_encode($v, JSON_UNESCAPED_UNICODE);
    }
    private function dec($s)
    {
        if (!$s)
            return null;
        $j = json_decode($s, true);
        return is_array($j) ? $j : null;
    }

    public function create(array $billing, array $shipping, array $totals): int
    {
        global $wpdb;
        $wpdb->insert($this->tOrders, [
            'status' => 'pending',
            'billing' => $this->enc($billing),
            'shipping' => $this->enc($shipping),
            'totals' => $this->enc($totals),
        ]);
        $id = (int) $wpdb->insert_id;
        if ($id > 0) {
            $s = get_option('hc_shop_settings', []);
            $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string) ($s['order_prefix'] ?? 'HC')) ?: 'HC';
            $orderNumber = $prefix . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
            $wpdb->update($this->tOrders, ['order_number' => $orderNumber], ['id' => $id]);
        }
        return $id;
    }

    public function add_item(int $order_id, array $item): bool
    {
        global $wpdb;
        return (bool) $wpdb->insert($this->tItems, [
            'order_id' => $order_id,
            'product_id' => (int) ($item['product_id'] ?? 0),
            'title' => sanitize_text_field($item['name'] ?? ''),
            'sku' => sanitize_text_field($item['sku'] ?? ''),
            'options' => $this->enc($item['options'] ?? []),
            'unit_price' => (int) ($item['unit_price'] ?? 0),
            'qty' => (int) ($item['qty'] ?? 1),
            'line_total' => (int) ($item['unit_price'] ?? 0) * (int) ($item['qty'] ?? 1),
        ]);
    }

    public function get(int $id): ?array
    {
        global $wpdb;
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tOrders} WHERE id=%d", $id), ARRAY_A);
        if (!$o)
            return null;
        $o['totals'] = $this->dec($o['totals']) ?: [];
        $o['billing'] = $this->dec($o['billing']) ?: [];
        $o['shipping'] = $this->dec($o['shipping']) ?: [];
        $o['shipping_data'] = $this->dec($o['shipping_data'] ?? '') ?: [];
        $o['notes'] = $this->dec($o['notes'] ?? '') ?: [];
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tItems} WHERE order_id=%d", $id), ARRAY_A);
        foreach ($items as &$it) {
            $it['options'] = $this->dec($it['options']) ?: [];
        }
        $o['items'] = $items;
        return $o;
    }

    public function list(int $per_page = 20, int $page = 1, string $q = ''): array
    {
        global $wpdb;
        $offset = max(0, ($page - 1) * $per_page);
        $where = "WHERE 1=1";
        $params = [];
        if ($q !== '') {
            $where .= " AND (order_number LIKE %s OR status LIKE %s)";
            $like = '%' . $wpdb->esc_like($q) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tOrders} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        );
        $items = $wpdb->get_results($sql, ARRAY_A);
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->tOrders} {$where}", ...$params));
        return ['items' => $items, 'total' => $total];
    }

    public function update_status(int $id, string $status): bool
    {
        global $wpdb;
        return (bool) $wpdb->update($this->tOrders, ['status' => $status], ['id' => $id]);
    }

    public function save_shipping(int $id, array $shipping): bool
    {
        global $wpdb;
        $payload = [
            'shipping_data' => $this->enc([
                'carrier' => sanitize_text_field($shipping['carrier'] ?? ''),
                'tracking_number' => sanitize_text_field($shipping['tracking_number'] ?? ''),
                'shipped_at' => sanitize_text_field($shipping['shipped_at'] ?? ''),
            ]),
        ];
        return (bool) $wpdb->update($this->tOrders, $payload, ['id' => $id]);
    }

    public function save_notes(int $id, array $notes): bool
    {
        global $wpdb;
        $payload = [
            'notes' => $this->enc([
                'internal' => sanitize_textarea_field($notes['internal'] ?? ''),
                'customer' => sanitize_textarea_field($notes['customer'] ?? ''),
            ]),
        ];
        return (bool) $wpdb->update($this->tOrders, $payload, ['id' => $id]);
    }

    public function refund(int $id, int $amount): bool
    {
        global $wpdb;
        $o = $this->get($id);
        if (!$o)
            return false;
        $grand = (int) ($o['totals']['grand'] ?? 0);
        $current = (int) ($o['refunded_total'] ?? 0);
        $amount = max(0, $amount);
        $newTotal = min($grand, $current + $amount);
        return (bool) $wpdb->update($this->tOrders, ['refunded_total' => $newTotal], ['id' => $id]);
    }

    /** 결제 완료 시 재고 차감 */
    public function decrement_stock_for_order(int $order_id): void
    {
        global $wpdb;
        $lines = $wpdb->get_results($wpdb->prepare("SELECT product_id, qty FROM {$this->tItems} WHERE order_id=%d", $order_id), ARRAY_A);
        foreach ($lines as $ln) {
            $pid = (int) $ln['product_id'];
            $qty = max(0, (int) $ln['qty']);
            if ($pid && $qty) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->tProducts} SET stock = GREATEST(0, stock - %d) WHERE id=%d",
                    $qty,
                    $pid
                ));
            }
        }
    }

    /** 취소/환불 시 재고 복원 */
    public function increment_stock_for_order(int $order_id): void
    {
        global $wpdb;
        $lines = $wpdb->get_results($wpdb->prepare("SELECT product_id, qty FROM {$this->tItems} WHERE order_id=%d", $order_id), ARRAY_A);
        foreach ($lines as $ln) {
            $pid = (int) $ln['product_id'];
            $qty = max(0, (int) $ln['qty']);
            if ($pid && $qty) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->tProducts} SET stock = stock + %d WHERE id=%d",
                    $qty,
                    $pid
                ));
            }
        }
    }

    /**
     * 상태 전환 시 재고 자동 조정
     * - (not paid -> paid 계열) : 차감
     * - (paid 계열 -> not paid) : 복원
     */
    public function change_status(int $id, string $new): bool
    {
        if ($new === 'canceled') $new = 'cancelled';
        if (!in_array($new, self::STATUSES, true))
            return false;
        $o = $this->get($id);
        if (!$o)
            return false;
        $old = (string) ($o['status'] ?? '');
        if ($old === $new)
            return true;

        $ok = $this->update_status($id, $new);
        if (!$ok)
            return false;

        $s = get_option('hc_shop_settings', []);
        $dec = (int) ($s['stock_decrement_on_paid'] ?? 1);
        $incCancel = (int) ($s['stock_restore_on_cancel'] ?? 1);
        $incRefund = (int) ($s['stock_restore_on_refund'] ?? 1);

        $paidStates = ['paid', 'processing', 'shipped', 'completed'];
        $oldPaid = in_array($old, $paidStates, true);
        $newPaid = in_array($new, $paidStates, true);

        if (!$oldPaid && $newPaid && $dec) {
            $this->decrement_stock_for_order($id);
        } elseif ($oldPaid && !$newPaid) {
            if ($new === 'cancelled' && $incCancel) {
                $this->increment_stock_for_order($id);
            }
            if ($new === 'refunded' && $incRefund) {
                $this->increment_stock_for_order($id);
            }
        }

        return true;
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

}
