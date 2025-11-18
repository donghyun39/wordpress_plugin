<?php
namespace HC\Shop\Services;

final class Mailer
{
    private function settings(): array { return get_option('hc_shop_settings', []) ?: []; }

    private function fromHeaders(): array
    {
        $s = $this->settings();
        $name  = sanitize_text_field($s['store_name']  ?? get_bloginfo('name'));
        $email = sanitize_email($s['store_email'] ?? get_option('admin_email'));
        $headers = [];
        if ($email) $headers[] = 'From: ' . $name . ' <' . $email . '>';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        return $headers;
    }

    public function sendOrderCreatedEmails(array $order): void
    {
        $s = $this->settings();
        $sendAdmin = (int)($s['notify_admin_on_order']    ?? 1);
        $sendCust  = (int)($s['notify_customer_on_order'] ?? 1);
        if (!$sendAdmin && !$sendCust) return;

        $headers = $this->fromHeaders();
        $storeEmail = sanitize_email($s['store_email'] ?? get_option('admin_email'));
        $subject = '[주문생성] ' . ($order['order_number'] ?? ('#'.$order['id']));

        if ($sendAdmin && $storeEmail) {
            $bodyAdmin =
                "새 주문이 생성되었습니다.\n".
                "주문번호: ".($order['order_number'] ?? ('#'.$order['id']))."\n".
                "상태: ".($order['status'] ?? '')."\n".
                "합계: ".number_format((int)($order['totals']['grand'] ?? 0))."원\n";
            @wp_mail($storeEmail, $subject, $bodyAdmin, $headers);
        }

        if ($sendCust && is_email($order['billing']['email'] ?? '')) {
            $cust = $order['billing']['email'];
            $custSubj = '주문이 접수되었습니다 - '.$order['order_number'];
            $custBody =
                ($order['billing']['name'] ?? '고객').'님,\n'.
                "주문이 접수되었습니다.\n주문번호: ".$order['order_number']."\n".
                "결제 금액: ".number_format((int)($order['totals']['grand'] ?? 0))."원\n";
            @wp_mail($cust, $custSubj, $custBody, $headers);
        }
    }

    public function sendOrderPaidEmails(array $order): void
    {
        $s = $this->settings();
        $sendAdmin = (int)($s['notify_admin_on_paid']    ?? 1);
        $sendCust  = (int)($s['notify_customer_on_paid'] ?? 1);
        if (!$sendAdmin && !$sendCust) return;

        $headers = $this->fromHeaders();
        $storeEmail = sanitize_email($s['store_email'] ?? get_option('admin_email'));
        $subject = '[결제완료] ' . ($order['order_number'] ?? ('#'.$order['id']));

        if ($sendAdmin && $storeEmail) {
            $bodyAdmin =
                "주문 결제가 완료되었습니다.\n".
                "주문번호: ".($order['order_number'] ?? ('#'.$order['id']))."\n".
                "합계: ".number_format((int)($order['totals']['grand'] ?? 0))."원\n";
            @wp_mail($storeEmail, $subject, $bodyAdmin, $headers);
        }

        if ($sendCust && is_email($order['billing']['email'] ?? '')) {
            $cust = $order['billing']['email'];
            $custSubj = '결제가 완료되었습니다 - '.$order['order_number'];
            $custBody =
                ($order['billing']['name'] ?? '고객').'님,\n'.
                "결제가 완료되었습니다.\n주문번호: ".$order['order_number']."\n".
                "결제 금액: ".number_format((int)($order['totals']['grand'] ?? 0))."원\n";
            @wp_mail($cust, $custSubj, $custBody, $headers);
        }
    }
}
