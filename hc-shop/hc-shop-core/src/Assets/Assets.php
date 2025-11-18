<?php
namespace HC\Shop\Assets;

final class Assets
{
public function enqueue(): void
{
    if (!function_exists('hc_shop_is_page') || !hc_shop_is_page()) return;

    $base = plugin_dir_url(\HC\Shop\Plugin::$file);
    $ver  = \HC\Shop\Plugin::VERSION;

    wp_enqueue_style('hc-shop-core',     $base.'assets/css/shop.css', [], $ver);
    wp_enqueue_style('hc-shop-frontend', $base.'assets/css/shop-frontend.css', [], $ver);
    wp_enqueue_style('hc-cart',          $base.'assets/css/cart.css', [], $ver);
    wp_enqueue_script('hc-shop-frontend',$base.'assets/js/shop-frontend.js', [], $ver, true);
    wp_enqueue_script('hc-shop-cart',    $base.'assets/js/cart.js', [], $ver, true);

    $s = get_option('hc_shop_settings', []);
    $cfg = [
        'payments_enabled' => isset($s['payments_enabled']) ? (int)$s['payments_enabled'] : 0,
        'inquiry_flow'     => (string)($s['inquiry_flow'] ?? 'cart_then_inquiry'),
        'urls' => [
            'shop'          => get_permalink(get_page_by_path('shop')) ?: home_url('/'),
            'cart'          => get_permalink(get_page_by_path('cart')) ?: home_url('/cart'),
            'checkout'      => get_permalink(get_page_by_path('checkout')) ?: home_url('/checkout'),
            'orderComplete' => get_permalink(get_page_by_path('order-complete')) ?: home_url('/order-complete'),
            'inquiry'       => get_permalink(get_page_by_path('inquiry')) ?: home_url('/inquiry'),
            'cartInquiry'   => get_permalink(get_page_by_path('cart-inquiry')) ?: home_url('/cart-inquiry'),
        ],
        'rest' => [
            'cart'     => rest_url('hc/v1/cart'),
            'add_item' => rest_url('hc/v1/cart/items'),
        ],
        'pg' => [
            'provider' => (string)($s['pg_provider'] ?? 'none'),
            'mode'     => (string)($s['pg_mode'] ?? 'test'),
            'toss'     => [
                'client_key' => (string)($s['pg_toss_client_key'] ?? ''),
            ],
        ],
    ];
    wp_localize_script('hc-shop-frontend', 'HCShop', $cfg);
    wp_localize_script('hc-shop-cart',     'HCShop', $cfg);

    if (is_page('checkout') && !empty($cfg['payments_enabled']) && $cfg['pg']['provider'] === 'toss') {
        wp_enqueue_script('toss-pw', 'https://js.tosspayments.com/v1/payment-widget', [], null, true);
        wp_enqueue_script('hc-checkout-toss', $base.'assets/js/checkout-toss.js', ['toss-pw'], $ver, true);
        wp_localize_script('hc-checkout-toss', 'HCShop', $cfg);
    }
}

}
