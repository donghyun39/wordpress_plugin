<?php
namespace HC\Shop\Notifications;

use HC\Shop\Data\OrderRepo;

final class Mailer
{
    private string $opt = 'hc_shop_settings';

    private function events(): array
    {
        $s = get_option($this->opt, []) ?: [];
        return (array)($s['email_events'] ?? []);
    }

    public function sendEvent(string $slug, array $order): bool
    {
        $id = (int)($order['id'] ?? 0);
        if ($id <= 0) return false;

        $events = $this->events();
        $enabled = $events[$slug] ?? 1;
        if ((int)$enabled !== 1) return false;

        [$subject, $html] = $this->render($slug, $id);
        if (!$html) return false;

        $to = '';
        if (str_contains($slug, '_admin')) {
            $to = sanitize_email(get_option($this->opt, [])['store_email'] ?? get_option('admin_email'));
        } else {
            $to = sanitize_email($order['billing']['email'] ?? '');
        }
        if (!$to || !is_email($to)) return false;

        return $this->send($to, $subject, $html, $slug);
    }

    public function render(string $slug, int $orderId): array
    {
        $s = get_option($this->opt, []) ?: [];
        $brand = (array)($s['email_brand'] ?? []);
        $tpls  = (array)($s['email_templates'] ?? []);

        // 보정(기본값 병합)
        $brand = array_merge([
            'from_name'    => get_bloginfo('name'),
            'from_email'   => get_option('admin_email'),
            'logo_id'      => 0,
            'brand_color'  => '#111827',
            'title_prefix' => '주문 알림 | ',
            'footer_text'  => "감사합니다.\n" . home_url('/'),
        ], $brand);

        $tpl = (array)($tpls[$slug] ?? null);
        if (!$tpl) return ['', ''];

        // 주문 데이터 로드
        $repo = new OrderRepo();
        $o = $repo->get($orderId);
        if (!$o) return ['', ''];

        // 토큰 빌드
        $tokens = $this->buildTokens($o);
        $subject = ($brand['title_prefix'] ?? ''). $this->replaceTokens($tpl['subject'] ?? '', $tokens);
        $body    = $this->replaceTokens($tpl['body'] ?? '', $tokens);
        $logoUrl = $brand['logo_id'] ? (wp_get_attachment_image_url((int)$brand['logo_id'], 'medium') ?: '') : '';

        // 레이아웃 합성(간단한 인라인 스타일)
        $html = $this->layout($brand, $logoUrl, $subject, $body);

        return [$subject, $html];
    }

    public function send(string $to, string $subject, string $html, string $type = ''): bool
    {
        $s = get_option($this->opt, []) ?: [];
        $brand = (array)($s['email_brand'] ?? []);
        $fromName  = $brand['from_name']  ?? get_bloginfo('name');
        $fromEmail = $brand['from_email'] ?? get_option('admin_email');

        add_filter('wp_mail_from',     fn($v) => $fromEmail);
        add_filter('wp_mail_from_name',fn($v) => $fromName);

        $headers = ["Content-Type: text/html; charset=UTF-8"];
        $ok = wp_mail($to, $subject, $html, $headers);

        // 원복
        remove_all_filters('wp_mail_from');
        remove_all_filters('wp_mail_from_name');

        return (bool)$ok;
    }

    // ===== tokens =====
    private function buildTokens(array $o): array
    {
        $siteName = get_bloginfo('name');
        $orderUrl = get_permalink(get_page_by_path('order-complete')) ?: home_url('/');
        if ($orderUrl) {
            $orderUrl = add_query_arg('order', (int)($o['id'] ?? 0), $orderUrl);
        }

        $subtotal = (int)($o['totals']['subtotal'] ?? 0);
        $shipping = (int)($o['totals']['shipping'] ?? 0);
        $grand    = (int)($o['totals']['grand'] ?? ($o['totals']['grand_total'] ?? 0));
        $currency = get_option($this->opt, [])['currency'] ?? 'KRW';

        $shipData = (array)($o['shipping_data'] ?? []);

        return [
            'store' => [
                'name' => $siteName,
                'email'=> get_option('admin_email'),
                'url'  => home_url('/'),
            ],
            'order' => [
                'id'     => (int)($o['id'] ?? 0),
                'number' => (string)($o['order_number'] ?? ('#'.$o['id'])),
                'date'   => $this->fmtDate($o['created_at'] ?? current_time('mysql')),
                'status' => (string)($o['status'] ?? ''),
                'url'    => $orderUrl,
            ],
            'customer' => [
                'name'  => (string)($o['billing']['name']  ?? ''),
                'email' => (string)($o['billing']['email'] ?? ''),
                'phone' => (string)($o['billing']['phone'] ?? ''),
            ],
            'shipping' => [
                'address1' => (string)($o['shipping']['address1'] ?? ''),
                'address2' => (string)($o['shipping']['address2'] ?? ''),
                'postcode' => (string)($o['shipping']['postcode'] ?? ''),
                'carrier'  => (string)($shipData['carrier'] ?? ''),
                'tracking' => (string)($shipData['tracking_number'] ?? ''),
                'shipped_at' => (string)($shipData['shipped_at'] ?? ''),
            ],
            'items' => [
                'table' => $this->itemsTable($o['items'] ?? [], $currency),
            ],
            'totals' => [
                'subtotal' => $this->money($subtotal, $currency),
                'shipping' => $this->money($shipping, $currency),
                'grand'    => $this->money($grand,    $currency),
                'refunded' => $this->money((int)($o['refunded_total'] ?? 0), $currency),
            ],
            'currency' => $currency,
        ];
    }

    private function replaceTokens(string $tpl, array $tok): string
    {
        // 플랫 루트 토큰
        $find = [
            '{{store.name}}','{{store.email}}','{{store.url}}',
            '{{order.number}}','{{order.date}}','{{order.status}}','{{order.url}}',
            '{{customer.name}}','{{customer.email}}','{{customer.phone}}',
            '{{shipping.address1}}','{{shipping.address2}}','{{shipping.postcode}}','{{shipping.carrier}}','{{shipping.tracking}}','{{shipping.shipped_at}}',
            '{{totals.subtotal}}','{{totals.shipping}}','{{totals.grand}}','{{totals.refunded}}',
            '{{currency}}','{{items.table}}'
        ];
        $repl = [
            $tok['store']['name'] ?? '',
            $tok['store']['email'] ?? '',
            $tok['store']['url'] ?? '',
            $tok['order']['number'] ?? '',
            $tok['order']['date'] ?? '',
            $tok['order']['status'] ?? '',
            $tok['order']['url'] ?? '',
            $tok['customer']['name'] ?? '',
            $tok['customer']['email'] ?? '',
            $tok['customer']['phone'] ?? '',
            $tok['shipping']['address1'] ?? '',
            $tok['shipping']['address2'] ?? '',
            $tok['shipping']['postcode'] ?? '',
            $tok['shipping']['carrier'] ?? '',
            $tok['shipping']['tracking'] ?? '',
            $tok['shipping']['shipped_at'] ?? '',
            $tok['totals']['subtotal'] ?? '',
            $tok['totals']['shipping'] ?? '',
            $tok['totals']['grand'] ?? '',
            $tok['totals']['refunded'] ?? '',
            $tok['currency'] ?? '',
            $tok['items']['table'] ?? '',
        ];
        return str_replace($find, $repl, $tpl);
    }

    private function itemsTable(array $items, string $currency): string
    {
        $rows = '';
        foreach ($items as $it) {
            $name = esc_html($it['title'] ?? $it['name'] ?? '');
            $opt  = esc_html($it['option_label'] ?? '-');
            $qty  = (int)($it['qty'] ?? 1);
            $up   = $this->money((int)($it['unit_price'] ?? 0), $currency);
            $lt   = $this->money((int)($it['line_total'] ?? ($qty*(int)($it['unit_price'] ?? 0))), $currency);
            $rows .= "<tr>
                <td style=\"padding:8px 6px;border-bottom:1px solid #eee;\">{$name}<br><small style=\"color:#6b7280\">{$opt}</small></td>
                <td style=\"padding:8px 6px;border-bottom:1px solid #eee;text-align:center;\">{$qty}</td>
                <td style=\"padding:8px 6px;border-bottom:1px solid #eee;text-align:right;\">{$up}</td>
                <td style=\"padding:8px 6px;border-bottom:1px solid #eee;text-align:right;\">{$lt}</td>
            </tr>";
        }
        return "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"border-collapse:collapse;margin:10px 0\">
            <thead>
              <tr>
                <th align=\"left\"  style=\"padding:8px 6px;border-bottom:2px solid #e5e7eb;\">상품</th>
                <th align=\"center\"style=\"padding:8px 6px;border-bottom:2px solid #e5e7eb;\">수량</th>
                <th align=\"right\" style=\"padding:8px 6px;border-bottom:2px solid #e5e7eb;\">단가</th>
                <th align=\"right\" style=\"padding:8px 6px;border-bottom:2px solid #e5e7eb;\">금액</th>
              </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>";
    }

    private function layout(array $brand, string $logoUrl, string $subject, string $body): string
    {
        $color = $brand['brand_color'] ?: '#111827';
        $footer = nl2br(esc_html($brand['footer_text'] ?? ''));
        $logo = $logoUrl ? "<img src=\"".esc_url($logoUrl)."\" alt=\"logo\" style=\"max-width:180px;height:auto\">" : "<strong style=\"font-size:18px;color:$color\">".esc_html(get_bloginfo('name'))."</strong>";

        return "<!doctype html><html><head><meta charset=\"utf-8\"></head><body style=\"margin:0;background:#f3f4f6\">
  <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#f3f4f6;padding:24px 0\">
    <tr><td align=\"center\">
      <table role=\"presentation\" width=\"640\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 3px 14px rgba(0,0,0,.06);overflow:hidden\">
        <tr>
          <td style=\"padding:20px;border-bottom:1px solid #f3f4f6;\">{$logo}</td>
        </tr>
        <tr>
          <td style=\"padding:20px\">
            <h1 style=\"margin:0 0 12px;font-size:18px;color:$color\">".esc_html($subject)."</h1>
            <div style=\"color:#111827;line-height:1.6;font-size:14px\">{$body}</div>
          </td>
        </tr>
        <tr>
          <td style=\"padding:16px 20px;border-top:1px solid #f3f4f6;color:#6b7280;font-size:12px;line-height:1.6\">{$footer}</td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>";
    }

    private function money(int $n, string $currency): string
    {
        // 간단 포맷 (KRW 기준 원 표시, 기타는 코드만)
        if ($currency === 'KRW') return number_format($n).'원';
        return number_format($n).' '.$currency;
    }
    private function fmtDate(string $mysql): string
    {
        $ts = strtotime($mysql) ?: time();
        return date('Y-m-d H:i', $ts);
    }
}
