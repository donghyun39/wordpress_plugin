<?php
namespace HC\Shop\Admin;

use HC\Shop\Data\OrderRepo;
use HC\Shop\Services\Mailer as ServiceMailer;

final class OrderAdmin
{
    public function register(): void
    {
        add_menu_page('HC Orders', 'HC Orders', 'manage_options', 'hc-orders', [$this,'render'], 'dashicons-clipboard', 27);

        // 관리자 에셋
        add_action('admin_enqueue_scripts', function($hook){
            if (strpos($hook,'hc-orders') === false) return;
            $base = plugin_dir_url(\HC\Shop\Plugin::$file);
            wp_enqueue_script('hc-admin-orders', $base.'assets/js/admin-orders.js', ['jquery'], \HC\Shop\Plugin::VERSION, true);
            wp_localize_script('hc-admin-orders', 'HCOrders', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('hc_orders'),
                'statuses'=> OrderRepo::statuses(),
            ]);
        });
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) wp_die('No permission');

        $repo = new OrderRepo();
        $id = (int)($_GET['id'] ?? 0);
        $q  = sanitize_text_field($_GET['s'] ?? '');

        if ($id > 0) {
            $o = $repo->get($id);
            if (!$o) { echo '<div class="wrap"><h1>HC Orders</h1><p>주문 없음.</p></div>'; return; }
            $status = (string)($o['status'] ?? 'pending');
            $ship = (array)($o['shipping_data'] ?? []);
            $notes = (array)($o['notes'] ?? []);
            $refunded = (int)($o['refunded_total'] ?? 0);
            $grand = (int)($o['totals']['grand'] ?? 0);
            ?>
            <div class="wrap">
              <h1 class="wp-heading-inline">Order #<?php echo (int)$id; ?> (<?php echo esc_html($o['order_number'] ?? ''); ?>)</h1>
              <a href="<?php echo esc_url(admin_url('admin.php?page=hc-orders')); ?>" class="page-title-action">Back</a>
              <hr class="wp-header-end">

              <h2>요약</h2>
              <table class="widefat striped">
                <tbody>
                  <tr>
                    <th>상태</th>
                    <td>
                      <select id="hc-order-status" data-id="<?php echo (int)$id; ?>">
                        <?php foreach (OrderRepo::statuses() as $st): ?>
                          <option value="<?php echo esc_attr($st); ?>" <?php selected($status,$st); ?>><?php echo esc_html($st); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="button button-primary" id="hc-order-status-save">변경</button>
                      <span id="hc-order-status-msg" style="margin-left:8px;color:#555"></span>
                    </td>
                  </tr>
                  <tr><th>구매자</th><td><?php echo esc_html(($o['billing']['name'] ?? '').' / '.($o['billing']['phone'] ?? '').' / '.($o['billing']['email'] ?? '')); ?></td></tr>
                  <tr><th>배송지</th><td><?php echo esc_html(($o['shipping']['postcode'] ?? '').' '.($o['shipping']['address1'] ?? '').' '.($o['shipping']['address2'] ?? '')); ?></td></tr>
                  <tr><th>금액</th><td><?php echo number_format((int)($o['totals']['grand'] ?? 0)); ?>원</td></tr>
                </tbody>
              </table>

              <h2 style="margin-top:20px">아이템</h2>
              <table class="widefat striped">
                <thead><tr><th>상품</th><th>SKU</th><th>수량</th><th>단가</th><th>금액</th></tr></thead>
                <tbody>
                <?php foreach (($o['items'] ?? []) as $it): ?>
                  <tr>
                    <td><?php echo esc_html($it['title']); ?></td>
                    <td><?php echo esc_html($it['sku']); ?></td>
                    <td><?php echo (int)$it['qty']; ?></td>
                    <td><?php echo number_format((int)$it['unit_price']); ?>원</td>
                    <td><?php echo number_format((int)$it['line_total']); ?>원</td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>

              <h2 style="margin-top:20px">배송 / 송장</h2>
              <table class="widefat striped">
                <tbody>
                  <tr><th>택배사</th><td><input type="text" id="hc-ship-carrier" value="<?php echo esc_attr($ship['carrier'] ?? ''); ?>" class="regular-text"></td></tr>
                  <tr><th>송장번호</th><td><input type="text" id="hc-ship-tracking" value="<?php echo esc_attr($ship['tracking_number'] ?? ''); ?>" class="regular-text"></td></tr>
                  <tr><th>발송일</th><td><input type="date" id="hc-ship-date" value="<?php echo esc_attr($ship['shipped_at'] ?? ''); ?>"></td></tr>
                </tbody>
              </table>
              <p><button class="button" id="hc-ship-save" data-id="<?php echo (int)$id; ?>">배송 정보 저장</button> <span id="hc-ship-msg" style="margin-left:8px;color:#555"></span></p>

              <h2 style="margin-top:20px">메모</h2>
              <table class="widefat striped">
                <tbody>
                  <tr><th>내부 메모</th><td><textarea id="hc-note-internal" rows="3" class="large-text"><?php echo esc_textarea($notes['internal'] ?? ''); ?></textarea></td></tr>
                  <tr><th>고객 메모</th><td><textarea id="hc-note-customer" rows="3" class="large-text"><?php echo esc_textarea($notes['customer'] ?? ''); ?></textarea></td></tr>
                </tbody>
              </table>
              <p><button class="button" id="hc-note-save" data-id="<?php echo (int)$id; ?>">메모 저장</button> <span id="hc-note-msg" style="margin-left:8px;color:#555"></span></p>

              <h2 style="margin-top:20px">환불</h2>
              <table class="widefat striped">
                <tbody>
                  <tr>
                    <th>환불 누계</th>
                    <td><?php echo number_format($refunded); ?>원 / <?php echo number_format($grand); ?>원</td>
                  </tr>
                  <tr>
                    <th>추가 환불 금액</th>
                    <td>
                      <input type="number" id="hc-refund-amount" value="0" min="0" step="1"> 원
                      <button class="button" id="hc-refund-btn" data-id="<?php echo (int)$id; ?>">환불 처리</button>
                      <span id="hc-refund-msg" style="margin-left:8px;color:#555"></span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php
            return;
        }

        $list = $repo->list(50, 1, $q);
        ?>
        <div class="wrap">
          <h1 class="wp-heading-inline">HC Orders</h1>
          <hr class="wp-header-end">

          <form method="get" style="margin-top:10px;">
            <input type="hidden" name="page" value="hc-orders">
            <p><input type="search" name="s" value="<?php echo esc_attr($q); ?>" placeholder="Order # or status">
               <button class="button">Search</button></p>
          </form>

          <form id="hc-order-bulk-form" style="margin:12px 0;">
            <label>선택 주문을</label>
            <select id="hc-bulk-status">
              <?php foreach (OrderRepo::statuses() as $st): ?>
                <option value="<?php echo esc_attr($st); ?>"><?php echo esc_html($st); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="button">일괄 변경</button>
            <span id="hc-bulk-msg" style="margin-left:8px;color:#555"></span>
          </form>

          <table class="widefat striped">
            <thead><tr><th><input type="checkbox" id="hc-bulk-all"></th><th>ID</th><th>Order #</th><th>Status</th><th>Total</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($list['items'] as $o): $tot = json_decode($o['totals'] ?? '[]', true) ?: []; ?>
              <tr>
                <td><input type="checkbox" class="hc-bulk-item" value="<?php echo (int)$o['id']; ?>"></td>
                <td><?php echo (int)$o['id']; ?></td>
                <td><?php echo esc_html($o['order_number']); ?></td>
                <td><?php echo esc_html($o['status']); ?></td>
                <td><?php echo number_format((int)($tot['grand'] ?? 0)); ?>원</td>
                <td><?php echo esc_html($o['created_at']); ?></td>
                <td><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hc-orders&id='.(int)$o['id'])); ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    // === AJAX: 상태 변경 ===
    public function ajaxUpdateStatus(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'no permission'], 403);
        check_ajax_referer('hc_orders');

        $id  = (int)($_POST['id'] ?? 0);
        $sts = sanitize_text_field($_POST['status'] ?? '');
        if ($id <= 0 || !in_array($sts, OrderRepo::statuses(), true)) {
            wp_send_json_error(['message'=>'invalid input'], 400);
        }

        $repo = new OrderRepo();
        $ok = $repo->change_status($id, $sts);
        if (!$ok) wp_send_json_error(['message'=>'db error'], 500);

        $o = $repo->get($id);
        $this->sendStatusMail($repo, $id, $sts);
        wp_send_json_success(['status'=>$o['status'] ?? $sts]);
    }

    public function ajaxBulkUpdateStatus(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'no permission'], 403);
        check_ajax_referer('hc_orders');

        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $ids = array_values(array_filter($ids, fn($v) => $v > 0));
        $sts = sanitize_text_field($_POST['status'] ?? '');
        if (!$ids || !in_array($sts, OrderRepo::statuses(), true)) {
            wp_send_json_error(['message'=>'invalid input'], 400);
        }

        $repo = new OrderRepo();
        $updated = 0;
        foreach ($ids as $id) {
            if ($repo->change_status($id, $sts)) {
                $updated++;
                $this->sendStatusMail($repo, $id, $sts);
            }
        }
        wp_send_json_success(['updated'=>$updated]);
    }

    public function ajaxSaveShipping(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'no permission'], 403);
        check_ajax_referer('hc_orders');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) wp_send_json_error(['message'=>'invalid id'], 400);

        $repo = new OrderRepo();
        if (!$repo->save_shipping($id, [
            'carrier' => $_POST['carrier'] ?? '',
            'tracking_number' => $_POST['tracking'] ?? '',
            'shipped_at' => $_POST['shipped_at'] ?? '',
        ])) {
            wp_send_json_error(['message'=>'db error'], 500);
        }

        if (sanitize_text_field($_POST['status'] ?? '') === 'shipped') {
            $repo->change_status($id, 'shipped');
            $this->sendStatusMail($repo, $id, 'shipped');
        }

        wp_send_json_success(['message'=>'saved']);
    }

    public function ajaxSaveNotes(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'no permission'], 403);
        check_ajax_referer('hc_orders');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) wp_send_json_error(['message'=>'invalid id'], 400);

        $repo = new OrderRepo();
        if (!$repo->save_notes($id, [
            'internal' => $_POST['internal'] ?? '',
            'customer' => $_POST['customer'] ?? '',
        ])) {
            wp_send_json_error(['message'=>'db error'], 500);
        }
        wp_send_json_success(['message'=>'saved']);
    }

    public function ajaxRefund(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'no permission'], 403);
        check_ajax_referer('hc_orders');

        $id = (int)($_POST['id'] ?? 0);
        $amount = max(0, (int)($_POST['amount'] ?? 0));
        if ($id <= 0 || $amount <= 0) wp_send_json_error(['message'=>'invalid input'], 400);

        $repo = new OrderRepo();
        $o = $repo->get($id);
        if (!$o) wp_send_json_error(['message'=>'not found'], 404);

        $repo->refund($id, $amount);
        $repo->change_status($id, 'refunded');
        $this->sendStatusMail($repo, $id, 'refunded');

        $o2 = $repo->get($id);
        wp_send_json_success([
            'refunded_total' => (int)($o2['refunded_total'] ?? 0),
            'status' => $o2['status'] ?? 'refunded',
        ]);
    }

    private function sendStatusMail(OrderRepo $repo, int $orderId, string $status): void
    {
        $o = $repo->get($orderId);
        if (!$o) return;
        $mailer = new ServiceMailer();
        switch ($status) {
            case 'paid':
                $mailer->sendOrderPaidEmails($o);
                break;
            case 'shipped':
                $mailer->sendOrderShippedEmails($o);
                break;
            case 'cancelled':
                $mailer->sendOrderCancelledEmails($o);
                break;
            case 'refunded':
                $mailer->sendOrderRefundedEmails($o);
                break;
        }
    }
}
