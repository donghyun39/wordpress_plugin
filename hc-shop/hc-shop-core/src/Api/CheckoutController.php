<?php
namespace HC\Shop\Api;

use WP_REST_Request;
use WP_REST_Response;
use HC\Shop\Data\OrderRepo;
use HC\Shop\Data\ProductRepo;
use HC\Shop\Services\Mailer;

final class CheckoutController
{
    private const COOKIE = 'hc_sid';
    private const TTL = 2 * DAY_IN_SECONDS;

    public function register_routes(): void
    {
        register_rest_route('hc/v1', '/checkout/create-order', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'createOrder']
        ]);

        register_rest_route('hc/v1', '/orders/(?P<id>\\d+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'getOrder']
        ]);
    }

    public function createOrder(WP_REST_Request $req): WP_REST_Response
    {
        $p = $req->get_json_params() ?: [];
        $billing = [
            'name' => sanitize_text_field($p['billing']['name'] ?? ''),
            'phone' => sanitize_text_field($p['billing']['phone'] ?? ''),
            'email' => sanitize_email($p['billing']['email'] ?? '')
        ];
        $shipping = [
            'address1' => sanitize_text_field($p['shipping']['address1'] ?? ''),
            'address2' => sanitize_text_field($p['shipping']['address2'] ?? ''),
            'postcode' => sanitize_text_field($p['shipping']['postcode'] ?? '')
        ];
        if ($billing['name'] === '' || $billing['phone'] === '' || !is_email($billing['email'])) {
            return new WP_REST_Response(['error' => ['code' => 'invalid_billing', 'message' => '구매자 정보를 확인하세요.']], 422);
        }

        $cart = $this->load_cart();
        $items = array_values($cart['items'] ?? []);
        if (!$items) {
            return new WP_REST_Response(['error' => ['code' => 'empty_cart', 'message' => '장바구니가 비어있습니다.']], 400);
        }

        $totals = $this->totals($cart);

        $orders = new OrderRepo();
        $order_id = $orders->create($billing, $shipping, $totals['totals']);
        if ($order_id <= 0)
            return new WP_REST_Response(['error' => ['code' => 'db_error']], 500);

        $pr = new ProductRepo();
        foreach ($items as $it) {
            $prod = $pr->get((int) $it['product_id']);
            $sku = (string) ($prod['sku'] ?? '');
            $orders->add_item($order_id, [
                'product_id' => (int) $it['product_id'],
                'name' => (string) ($it['name'] ?? ''),
                'sku' => $sku,
                'options' => (array) ($it['options'] ?? []),
                'unit_price' => (int) ($it['unit_price'] ?? 0),
                'qty' => (int) ($it['qty'] ?? 1),
            ]);
        }

        $this->clear_cart();

        $o = $orders->get($order_id);

        // === 주문 생성 알림 메일 ===
        (new Mailer())->sendOrderCreatedEmails($o);

        return new WP_REST_Response([
            'order_id' => $order_id,
            'order_number' => (string) ($o['order_number'] ?? ''),
            'status' => (string) ($o['status'] ?? 'pending')
        ], 201);
    }

    public function getOrder(WP_REST_Request $req): WP_REST_Response
    {
        $o = (new OrderRepo())->get((int) $req['id']);
        if (!$o)
            return new WP_REST_Response(['error' => ['code' => 'not_found']], 404);
        return new WP_REST_Response($o);
    }

    // ===== 내부 (Cart 복사) =====

    private function sid(): string
    {
        if (!isset($_COOKIE[self::COOKIE]) || !preg_match('/^[A-Za-z0-9]{12,}$/', $_COOKIE[self::COOKIE])) {
            $sid = bin2hex(random_bytes(8));
            setcookie(self::COOKIE, $sid, time() + self::TTL, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE[self::COOKIE] = $sid;
        }
        return $_COOKIE[self::COOKIE];
    }

    private function load_cart(): array
    {
        $sid = $this->sid();
        $cart = get_transient('hc_cart_' . $sid);
        if (!is_array($cart))
            $cart = ['items' => []];
        return $cart;
    }
    private function clear_cart(): void
    {
        delete_transient('hc_cart_' . $this->sid());
    }

    private function totals(array $cart): array
    {
        $s = get_option('hc_shop_settings', []);
        $flat = (int) ($s['shipping_flat_fee'] ?? 3000);
        $freeOver = (int) ($s['shipping_free_over'] ?? 30000);
        $currency = (string) ($s['currency'] ?? 'KRW');

        $subtotal = 0.0;
        $items = array_values($cart['items'] ?? []);
        foreach ($items as $it)
            $subtotal += (float) $it['unit_price'] * (int) $it['qty'];
        $shipping = ($freeOver > 0 && $subtotal >= $freeOver) ? 0 : $flat;
        $grand = $subtotal + $shipping;

        return [
            'items' => $items,
            'totals' => [
                'subtotal' => (int) round($subtotal, 0),
                'shipping' => (int) $shipping,
                'grand' => (int) round($grand, 0),
                'currency' => $currency
            ]
        ];
    }

}
