<?php
namespace HC\Shop\Install;

class Activator
{
    public static function run(): void
    {
        if (!function_exists('wp_insert_post')) return;

        $pages = [
            ['title' => 'Shop',           'slug' => 'shop',           'content' => '[hc_shop]'],
            ['title' => 'Cart',           'slug' => 'cart',           'content' => '[hc_cart]'],
            ['title' => 'Checkout',       'slug' => 'checkout',       'content' => '[hc_checkout]'],
            ['title' => 'Order Complete', 'slug' => 'order-complete', 'content' => '[hc_order_complete]'],
            ['title' => 'Account',        'slug' => 'account',        'content' => ''],
            ['title' => 'Inquiry',        'slug' => 'inquiry',        'content' => '[hc_inquiry]'],
            ['title' => 'Cart Inquiry',   'slug' => 'cart-inquiry',   'content' => '[hc_cart_inquiry]'],
        ];

        foreach ($pages as $p) {
            $slug = (string)($p['slug'] ?? '');
            if ($slug === '') continue;
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_title'   => (string)$p['title'],
                    'post_name'    => $slug,
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_content' => (string)($p['content'] ?? ''),
                ]);
            }
        }

        // 환경 기본값
        if (!get_option('hc_shop_settings')) {
            update_option('hc_shop_settings', [
                'currency'            => 'KRW',
                'order_prefix'        => 'HC',
                'shipping_flat_fee'   => 3000,
                'shipping_free_over'  => 30000,
                'payments_enabled'    => 1,
                'inquiry_flow'        => 'cart_then_inquiry', // or 'combined'
                'pg_provider'         => 'none',
                'pg_mode'             => 'test',
            ], false);
        }

        // === DB 테이블 생성 ===
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $tProducts = $wpdb->prefix . 'hc_products';
        $sqlProducts = "CREATE TABLE {$tProducts} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          title VARCHAR(255) NOT NULL,
          sku VARCHAR(100) DEFAULT '' ,
          price INT NOT NULL DEFAULT 0,
          stock INT NOT NULL DEFAULT 0,
          status VARCHAR(20) NOT NULL DEFAULT 'publish',
          description LONGTEXT NULL,
          image_id BIGINT UNSIGNED DEFAULT 0,
          gallery_ids LONGTEXT NULL,
          categories LONGTEXT NULL,
          colors LONGTEXT NULL,
          options LONGTEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY sku (sku)
        ) {$charset};";

        $tOrders = $wpdb->prefix . 'hc_orders';
        $sqlOrders = "CREATE TABLE {$tOrders} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          order_number VARCHAR(50) DEFAULT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'pending',
          billing LONGTEXT NULL,
          shipping LONGTEXT NULL,
          shipping_data LONGTEXT NULL,
          notes LONGTEXT NULL,
          refunded_total INT NOT NULL DEFAULT 0,
          totals LONGTEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY status (status)
        ) {$charset};";

        $tOrderItems = $wpdb->prefix . 'hc_order_items';
        $sqlOrderItems = "CREATE TABLE {$tOrderItems} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          order_id BIGINT UNSIGNED NOT NULL,
          product_id BIGINT UNSIGNED NOT NULL,
          title VARCHAR(255) NOT NULL,
          sku VARCHAR(100) DEFAULT '' ,
          options LONGTEXT NULL,
          unit_price INT NOT NULL DEFAULT 0,
          qty INT NOT NULL DEFAULT 1,
          line_total INT NOT NULL DEFAULT 0,
          PRIMARY KEY (id),
          KEY order_id (order_id),
          KEY product_id (product_id)
        ) {$charset};";

        $tInquiries = $wpdb->prefix . 'hc_inquiries';
        $sqlInquiries = "CREATE TABLE {$tInquiries} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          name VARCHAR(190) NULL,
          phone VARCHAR(60) NULL,
          email VARCHAR(190) NULL,
          message LONGTEXT NULL,
          context LONGTEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
        ) {$charset};";

        dbDelta($sqlProducts);
        dbDelta($sqlOrders);
        dbDelta($sqlOrderItems);
        dbDelta($sqlInquiries);
    }
    public static function maybe_migrate(): void
    {
        // 버전 옵션으로 중복 실행 방지
        $target = \HC\Shop\Plugin::VERSION;
        $curr   = get_option('hc_shop_schema_version', '0');

        if (version_compare((string)$curr, (string)$target, '>=')) {
            return; // 이미 최신
        }

        // run()은 페이지 존재 체크 + dbDelta라서 재실행해도 안전
        self::run();

        update_option('hc_shop_schema_version', $target, false);
    }
}
