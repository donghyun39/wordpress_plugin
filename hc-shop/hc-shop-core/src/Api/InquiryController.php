<?php
namespace HC\Shop\Api;

use WP_REST_Request;
use WP_REST_Response;

final class InquiryController
{
    private const COOKIE='hc_sid';

    public function register_routes(): void
    {
        register_rest_route('hc/v1', '/inquiry/submit', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this,'submit']
        ]);
    }

    public function submit(WP_REST_Request $req): WP_REST_Response
    {
        $p = $req->get_json_params() ?: [];
        $name  = sanitize_text_field($p['name']  ?? '');
        $phone = sanitize_text_field($p['phone'] ?? '');
        $email = sanitize_email($p['email'] ?? '');
        $msg   = wp_kses_post($p['message'] ?? '');

        if ($name==='' || $phone==='' || !is_email($email)) {
            return new WP_REST_Response(['error'=>['code'=>'invalid_input','message'=>'연락처 정보를 확인하세요.']], 422);
        }

        // 카트 스냅샷
        $cart = $this->load_cart();
        $context = [
            'cart'    => $cart,
            'source'  => sanitize_text_field($p['source'] ?? 'cart'), // 'product' or 'cart'
            'product' => (int)($p['product_id'] ?? 0),
        ];

        // 저장
        global $wpdb;
        $t = $wpdb->prefix . 'hc_inquiries';
        $wpdb->insert($t, [
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $email,
            'message' => $msg,
            'context' => wp_json_encode($context, JSON_UNESCAPED_UNICODE),
        ]);

        // 메일 전송
        $s = get_option('hc_shop_settings', []);
        $to = sanitize_email($s['store_email'] ?? get_option('admin_email'));
        if ($to) {
            $subject = '[문의] '.$name.'님 문의 접수';
            $lines = [];
            $lines[] = "이름: {$name}";
            $lines[] = "연락처: {$phone}";
            $lines[] = "이메일: {$email}";
            $lines[] = "메시지:\n{$msg}";
            $lines[] = "";
            $lines[] = "장바구니 내역:";
            foreach (($cart['items'] ?? []) as $it) {
                $lines[] = sprintf("- %s x %d (단가 %s)",
                    (string)$it['name'], (int)$it['qty'], number_format((int)$it['unit_price']));
            }
            $headers = ['Content-Type: text/plain; charset=UTF-8'];
            @wp_mail($to, $subject, implode("\n", $lines), $headers);
        }

        return new WP_REST_Response(['ok'=>true]);
    }

    private function sid(): string
    {
        if (isset($_COOKIE[self::COOKIE])) return $_COOKIE[self::COOKIE];
        return '';
    }
    private function load_cart(): array
    {
        $sid = $this->sid();
        if (!$sid) return ['items'=>[],'totals'=>[]];
        $cart = get_transient('hc_cart_'.$sid);
        if (!is_array($cart)) $cart = ['items'=>[]];
        return $cart;
    }
}
