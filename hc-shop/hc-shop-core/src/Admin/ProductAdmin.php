<?php
namespace HC\Shop\Admin;

use HC\Shop\Data\ProductRepo;

final class ProductAdmin
{
    public function register(): void
    {
        add_menu_page(
            'HC Products',
            'HC Products',
            'manage_options',
            'hc-products',
            [$this, 'render'],
            'dashicons-products',
            26
        );

        add_action('admin_enqueue_scripts', function ($hook) {
            if (strpos($hook, 'hc-products') === false)
                return;
            wp_enqueue_media();

            $base = plugin_dir_url(\HC\Shop\Plugin::$file);
            wp_enqueue_style('hc-products-admin', $base . 'assets/css/admin-products.css', [], \HC\Shop\Plugin::VERSION);
            wp_enqueue_script('hc-products-admin', $base . 'assets/js/admin-products.js', ['jquery'], \HC\Shop\Plugin::VERSION, true);

            wp_localize_script('hc-products-admin', 'HCProducts', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hc_products'),
            ]);
        });
    }



    public function render(bool $is_new = false): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');

        $repo = new \HC\Shop\Data\ProductRepo();
        $q = sanitize_text_field($_GET['s'] ?? '');
        $list = $repo->list(50, 1, $q);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">HC Products</h1>
            <button class="page-title-action hc-open-modal" data-id="0">Add New Product</button>
            <hr class="wp-header-end">

            <form method="get">
                <input type="hidden" name="page" value="hc-products">
                <p><input type="search" name="s" value="<?php echo esc_attr($q); ?>" placeholder="Search by title or SKU">
                    <button class="button">Search</button>
                </p>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['items'] as $r): ?>
                        <tr>
                            <td><?php echo (int) $r['id']; ?></td>
                            <td><?php echo esc_html($r['title']); ?></td>
                            <td><?php echo esc_html($r['sku']); ?></td>
                            <td><?php echo number_format((int) $r['price']); ?></td>
                            <td><?php echo (int) $r['stock']; ?></td>
                            <td><?php echo esc_html($r['status']); ?></td>
                            <td>
                                <button class="button hc-open-modal" data-id="<?php echo (int) $r['id']; ?>">Edit</button>
                                <button class="button button-link-delete hc-del"
                                    data-id="<?php echo (int) $r['id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 모달(마크업만, 동작은 JS) -->
        <div class="hc-modal-backdrop" id="hc-modal-bg" style="display:none"></div>
        <div class="hc-modal" id="hc-modal" style="display:none">
            <header class="hc-modal-header">
                <strong id="hc-modal-title">Product</strong>
                <div>
                    <button class="button button-primary" id="hc-save">Save</button>
                    <button class="button" id="hc-close">Close</button>
                </div>
            </header>
            <div class="hc-modal-body">
                <input type="hidden" id="hc-id" value="0">

                <div class="row"><label>Title</label><input type="text" id="hc-title" class="regular-text"></div>
                <div class="row"><label>SKU</label><input type="text" id="hc-sku" class="regular-text"></div>
                <div class="row"><label>Price (KRW)</label><input type="number" id="hc-price" min="0" step="1"></div>
                <div class="row"><label>Stock</label><input type="number" id="hc-stock" min="0" step="1"></div>
                <div class="row"><label>Status</label>
                    <select id="hc-status">
                        <option value="publish">publish</option>
                        <option value="draft">draft</option>
                    </select>
                </div>

                <div class="row"><label>Cover Image</label>
                    <div>
                        <input type="hidden" id="hc-image-id">
                        <button type="button" class="button" id="hc-cover-select">Select Image</button>
                        <button type="button" class="button" id="hc-cover-remove" style="display:none">Remove</button>
                        <div id="hc-cover-preview" class="hc-cover-preview"></div>
                    </div>
                </div>

                <div class="row"><label>Gallery Images</label>
                    <div>
                        <input type="hidden" id="hc-gallery-ids" value="[]">
                        <button type="button" class="button" id="hc-gallery-select">Select Images</button>
                        <div class="hc-gallery" id="hc-gallery-preview"></div>
                    </div>
                </div>

                <div class="row"><label>Categories</label><input type="text" id="hc-categories" class="regular-text"
                        placeholder="예: 상의, 티셔츠"></div>
                <div class="row"><label>Colors</label><input type="text" id="hc-colors" class="regular-text"
                        placeholder="예: 블랙, 화이트"></div>

                <div class="row wide"><label>Options</label>
                    <div class="hc-opt-wrap">
                        <table class="hc-opt-table" id="hc-opt">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Price Δ</th>
                                    <th>SKU Suffix</th>
                                    <th>Stock Δ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <button type="button" class="button" id="hc-opt-add">+ Add Option</button>
                    </div>
                </div>

                <div class="row wide"><label>Description</label>
                    <textarea id="hc-description" rows="8" class="large-text" placeholder="설명 텍스트"></textarea>
                </div>
            </div>
        </div>
        <?php
    }


    public function ajaxGet(): void
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'no permission'], 403);
        check_ajax_referer('hc_products');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0)
            wp_send_json_error(['message' => 'invalid id'], 400);

        $repo = new \HC\Shop\Data\ProductRepo();
        $row = $repo->get($id);
        if (!$row)
            wp_send_json_error(['message' => 'not found'], 404);

        // 미디어 URL들
        $image_url = $row['image_id'] ? (wp_get_attachment_url((int) $row['image_id']) ?: '') : '';
        $gallery_ids = $row['gallery_ids'] ?? [];
        $gallery_urls = array_map(function ($gid) {
            return wp_get_attachment_url((int) $gid) ?: '';
        }, $gallery_ids);

        $row['image_url'] = $image_url;
        $row['gallery_urls'] = array_values(array_filter($gallery_urls));

        wp_send_json_success($row);
    }

    public function ajaxSave(): void
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'no permission'], 403);
        check_ajax_referer('hc_products');

        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'title' => $_POST['title'] ?? '',
            'sku' => $_POST['sku'] ?? '',
            'price' => (int) ($_POST['price'] ?? 0),
            'stock' => (int) ($_POST['stock'] ?? 0),
            'status' => $_POST['status'] ?? 'publish',
            'description' => $_POST['description'] ?? '',
            'image_id' => (int) ($_POST['image_id'] ?? 0),
            'gallery_ids' => json_decode(stripslashes($_POST['gallery_ids'] ?? '[]'), true) ?: [],
            'categories' => json_decode(stripslashes($_POST['categories'] ?? '[]'), true) ?: [],
            'colors' => json_decode(stripslashes($_POST['colors'] ?? '[]'), true) ?: [],
            'options' => json_decode(stripslashes($_POST['options'] ?? '[]'), true) ?: [],
        ];

        $repo = new \HC\Shop\Data\ProductRepo();
        if ($id > 0) {
            $ok = $repo->update($id, $data);
        } else {
            $id = $repo->create($data);
            $ok = $id > 0;
        }

        if (!$ok)
            wp_send_json_error(['message' => 'DB error'], 500);
        wp_send_json_success(['id' => $id]);
    }
    public function ajaxDelete(): void
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'no permission'], 403);
        check_ajax_referer('hc_products');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0)
            wp_send_json_error(['message' => 'invalid id'], 400);

        $ok = (new \HC\Shop\Data\ProductRepo())->delete($id);
        if (!$ok)
            wp_send_json_error(['message' => 'DB error'], 500);

        wp_send_json_success(['id' => $id]);
    }

}
