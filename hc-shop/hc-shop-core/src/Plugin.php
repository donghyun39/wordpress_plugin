<?php
namespace HC\Shop;

final class Plugin
{
    public const VERSION = '0.3.0';
    public static string $file;

    public static function boot(string $file): void
    {
        self::$file = $file;

        /**
         * 1) 설치/마이그레이션
         */
        add_action('admin_init', ['\\HC\\Shop\\Install\\Activator', 'maybe_migrate']);

        /**
         * 2) REST API (한 군데서 모두 등록)
         */
        add_action('rest_api_init', function () {
            $controllers = [
                \HC\Shop\Api\HealthController::class,
                \HC\Shop\Api\ProductController::class,
                \HC\Shop\Api\CartController::class,
                \HC\Shop\Api\CheckoutController::class,
                \HC\Shop\Api\PaymentController::class,
                \HC\Shop\Api\InquiryController::class,
                \HC\Shop\Api\AuthController::class,
            ];
            foreach ($controllers as $cls) {
                (new $cls())->register_routes();
            }
        });

        /**
         * 3) 프런트: 스코프 CSS/JS + 컨텐트 래핑
         */
        add_filter('body_class', function ($classes) {
            if (function_exists('hc_shop_is_page') && hc_shop_is_page()) {
                $classes[] = 'hc-shop';
            }
            return $classes;
        });

        add_filter('the_content', function ($content) {
            if (function_exists('hc_shop_is_page') && hc_shop_is_page()) {
                return '<div class="hc-shop__container">' . $content . '</div>';
            }
            return $content;
        }, 9);

        add_action('wp_enqueue_scripts', function () {
            (new \HC\Shop\Assets\Assets())->enqueue();
        });

        /**
         * 4) 어드민 메뉴(한 번에 등록)
         *    각 Admin 클래스의 register() 안에서 add_menu_page / add_submenu_page 등을 처리함
         */
        add_action('admin_menu', function () {
            (new \HC\Shop\Admin\ProductAdmin())->register();
            (new \HC\Shop\Admin\OrderAdmin())->register();
            (new \HC\Shop\Admin\Settings())->register();
            (new \HC\Shop\Admin\Emails())->register();
        });

        /**
         * 5) 어드민 에셋 – 설정 화면용 스크립트
         */
        add_action('admin_enqueue_scripts', function ($hook) {
            if (strpos($hook, 'hc-settings') === false)
                return;
            $base = plugin_dir_url(self::$file);
            wp_enqueue_script('hc-admin-settings', $base . 'assets/js/admin-settings.js', ['jquery'], self::VERSION, true);
        });

        /**
         * 6) 어드민 AJAX 엔드포인트
         */
        add_action('wp_ajax_hc_product_get', fn() => (new \HC\Shop\Admin\ProductAdmin())->ajaxGet());
        add_action('wp_ajax_hc_product_save', fn() => (new \HC\Shop\Admin\ProductAdmin())->ajaxSave());
        add_action('wp_ajax_hc_product_delete', fn() => (new \HC\Shop\Admin\ProductAdmin())->ajaxDelete());
        add_action('wp_ajax_hc_order_update_status', fn() => (new \HC\Shop\Admin\OrderAdmin())->ajaxUpdateStatus());
        add_action('wp_ajax_hc_order_bulk_status', fn() => (new \HC\Shop\Admin\OrderAdmin())->ajaxBulkUpdateStatus());
        add_action('wp_ajax_hc_order_save_shipping', fn() => (new \HC\Shop\Admin\OrderAdmin())->ajaxSaveShipping());
        add_action('wp_ajax_hc_order_save_notes', fn() => (new \HC\Shop\Admin\OrderAdmin())->ajaxSaveNotes());
        add_action('wp_ajax_hc_order_refund', fn() => (new \HC\Shop\Admin\OrderAdmin())->ajaxRefund());
        // Emails 미리보기/테스트 (항상 가용하도록 여기서도 보강)
        add_action('wp_ajax_hc_emails_preview', fn() => (new \HC\Shop\Admin\Emails())->ajaxPreview());
        add_action('wp_ajax_hc_emails_test', fn() => (new \HC\Shop\Admin\Emails())->ajaxTest());

        /**
         * 7) 어드민 POST (폼 제출)
         */
        add_action('admin_post_hc_settings_save', fn() => (new \HC\Shop\Admin\Settings())->handleSave());

        /**
         * 8) 쇼트코드 (프런트)
         */
        add_action('init', function () {
            (new \HC\Shop\Shortcodes\Shop())->register();
            (new \HC\Shop\Shortcodes\CartPage())->register();
            (new \HC\Shop\Shortcodes\Checkout())->register();
            (new \HC\Shop\Shortcodes\OrderComplete())->register();
            (new \HC\Shop\Shortcodes\Inquiry())->register();
            (new \HC\Shop\Shortcodes\CartInquiry())->register();
            (new \HC\Shop\Shortcodes\Account())->register();
        });

        add_action('admin_post_nopriv_hc_register', function () {
            (new \HC\Shop\Api\AuthController())->handleRegisterPost();
        });

    }
}
