<?php
namespace HC\Shop\Api;

use WP_REST_Request;
use WP_REST_Response;
use HC\Shop\Data\OrderRepo;
use HC\Shop\Services\Mailer;

final class PaymentController
{
    public function register_routes(): void
    {
        register_rest_route('hc/v1', '/payments/fake/complete', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'fakeComplete']
        ]);
    }

    public function fakeComplete(WP_REST_Request $req): WP_REST_Response
    {
        $p = $req->get_json_params() ?: [];
        $order_id = (int)($p['order_id'] ?? 0);
        if ($order_id <= 0) return new WP_REST_Response(['error'=>['code'=>'invalid_order']], 400);

        $repo = new OrderRepo();
        $o = $repo->get($order_id);
        if (!$o) return new WP_REST_Response(['error'=>['code'=>'not_found']], 404);
        if (($o['status'] ?? '') === 'paid') {
            return new WP_REST_Response(['order_id'=>$order_id,'status'=>'paid']); // idempotent
        }

        // 상태 변경(재고 자동 조정 포함)
        $ok = $repo->change_status($order_id, 'paid');
        if (!$ok) return new WP_REST_Response(['error'=>['code'=>'db_error']], 500);

        $o2 = $repo->get($order_id);

        // === 결제완료 메일 ===
        (new Mailer())->sendOrderPaidEmails($o2);

        return new WP_REST_Response(['order_id'=>$order_id,'status'=>'paid']);
    }
}
