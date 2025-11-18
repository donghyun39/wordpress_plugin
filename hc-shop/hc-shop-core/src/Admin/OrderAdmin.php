<?php
namespace HC\Shop\Admin;

use HC\Shop\Data\OrderRepo;

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
                        <option value="pending"  <?php selected($status,'pending');  ?>>pending</option>
                        <option value="paid"     <?php selected($status,'paid');     ?>>paid</option>
                        <option value="cancelled"<?php selected($status,'cancelled');?>>cancelled</option>
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

          <table class="widefat striped">
            <thead><tr><th>ID</th><th>Order #</th><th>Status</th><th>Total</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($list['items'] as $o): $tot = json_decode($o['totals'] ?? '[]', true) ?: []; ?>
              <tr>
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
        if ($id <= 0 || !in_array($sts, ['pending','paid','cancelled'], true)) {
            wp_send_json_error(['message'=>'invalid input'], 400);
        }

        $repo = new OrderRepo();
        $ok = $repo->change_status($id, $sts);
        if (!$ok) wp_send_json_error(['message'=>'db error'], 500);

        $o = $repo->get($id);
        wp_send_json_success(['status'=>$o['status'] ?? $sts]);
    }
}
