<?php
/*
Plugin Name: HC Dev Toolkit
Description: OceanWP + Elementor 개발 환경 자동 세팅 (테마/플러그인 설치, 자동업데이트, wp-config 디버그, OceanWP 페이지 기본값, Header/Footer 템플릿, 커스텀 CSS)
Version: 1.0.0
Author: Hicoding
*/

if (!defined('ABSPATH')) exit;

if (!class_exists('HC_Dev_Toolkit')) :

final class HC_Dev_Toolkit {

    const OPTION_CSS    = 'hc_dev_toolkit_custom_css';
    const OPTION_NOTICE = 'hc_dev_toolkit_last_notice';

    /* ===================== init ===================== */

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_page']);
        add_action('admin_init', [__CLASS__, 'handle_post']);
        add_action('admin_notices', [__CLASS__, 'admin_notices']);

        // OceanWP 페이지 메타 기본값
        add_action('save_post_page', [__CLASS__, 'apply_oceanwp_page_defaults'], 10, 3);

        // 커스텀 CSS 출력 (OceanWP 스타일 위에 얹기)
        add_action('wp_enqueue_scripts', [__CLASS__, 'print_custom_css'], 99);

        // 자동업데이트 강제
        add_filter('auto_update_plugin', [__CLASS__, 'force_plugin_auto_update'], 10, 2);
        add_filter('auto_update_theme', [__CLASS__, 'force_theme_auto_update'], 10, 2);
    }

    /* ===================== 고정 설정들 ===================== */

    // 사용할 테마 슬러그
    private static function get_theme_slug() {
        return 'oceanwp';
    }

    // 설치 / 활성화 / 자동업데이트 대상 플러그인 목록
    private static function get_plugins() {
        return [
            // Elementor (무료)
            'elementor' => [
                'file' => 'elementor/elementor.php',
                'repo' => true,
            ],
            // Ultimate Addons for Elementor Lite (Header, Footer & Blocks)
            'header-footer-elementor' => [
                'file' => 'header-footer-elementor/header-footer-elementor.php',
                'repo' => true,
            ],
            // Ocean Extra
            'ocean-extra' => [
                'file' => 'ocean-extra/ocean-extra.php',
                'repo' => true,
            ],
            // WP phpMyAdmin (무료 확장)
            'wp-phpmyadmin-extension' => [
                'file' => 'wp-phpmyadmin-extension/wp-phpmyadmin-extension.php',
                'repo' => true,
            ],
            // Yoast Duplicate Post
            'duplicate-post' => [
                'file' => 'duplicate-post/duplicate-post.php',
                'repo' => true,
            ],
            // Elementor Pro — 유료. ZIP으로 설치만 해두면 여기서 활성화+자동업데이트만 처리
            'elementor-pro' => [
                'file' => 'elementor-pro/elementor-pro.php',
                'repo' => false,
            ],
        ];
    }

    /* ===================== Admin 메뉴 & 화면 ===================== */

    public static function register_page() {
        add_menu_page(
            'HC Dev Toolkit',
            'HC Dev',
            'manage_options',
            'hc-dev-toolkit',
            [__CLASS__, 'render_page'],
            'dashicons-admin-tools',
            80
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $css = (string) get_option(self::OPTION_CSS, '');
        ?>
        <div class="wrap">
            <h1>HC Dev Toolkit</h1>
            <p>이 플러그인은 다음 작업을 한 번에 처리합니다.</p>
            <ul style="list-style:disc;margin-left:20px;">
                <li>OceanWP 테마 설치/전환 + 자동 업데이트 활성화</li>
                <li>Elementor, Elementor Pro(설치되어 있으면), Ocean Extra, UAEL(Header/Footer), WP phpMyAdmin, Yoast Duplicate Post 설치/활성화 + 자동업데이트</li>
                <li>wp-config.php 디버그 설정(WP_DEBUG 등) + 테마/플러그인 파일 편집 허용</li>
                <li>OceanWP 페이지 생성 시 Page Settings 자동 설정 (100% Full Width, Top Bar/헤더 Disable)</li>
                <li>UAEL Header/Footer 활성화 시 기본 Header/Footer 템플릿 생성</li>
                <li>아래 입력하는 커스텀 CSS를 OceanWP 스타일 위에 인라인으로 추가</li>
            </ul>

            <form method="post">
                <?php wp_nonce_field('hc_dev_toolkit_action', 'hc_dev_toolkit_nonce'); ?>

                <h2>커스텀 CSS (OceanWP 전역)</h2>
                <p>클릭 테두리, 폰트, 간격 등 원하는 CSS 를 입력하면 OceanWP 스타일 위에 붙습니다.</p>
                <textarea name="hc_dev_toolkit_css" rows="10" style="width:100%;max-width:900px;"><?php echo esc_textarea($css); ?></textarea>

                <p class="submit">
                    <button type="submit" name="hc_dev_toolkit_save_css" class="button button-secondary">
                        CSS 저장
                    </button>
                    <button type="submit" name="hc_dev_toolkit_run_all" class="button button-primary">
                        한 번에 실행 (테마 + 플러그인 + wp-config + Header/Footer 템플릿)
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /* ===================== POST 처리 & 알림 ===================== */

    public static function handle_post() {
        if (!isset($_POST['hc_dev_toolkit_nonce'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!wp_verify_nonce($_POST['hc_dev_toolkit_nonce'], 'hc_dev_toolkit_action')) {
            return;
        }

        $messages = [];

        // CSS 저장
        if (isset($_POST['hc_dev_toolkit_css'])) {
            $css = wp_unslash((string) $_POST['hc_dev_toolkit_css']);
            update_option(self::OPTION_CSS, $css);
            $messages[] = '커스텀 CSS가 저장되었습니다.';
        }

        // 전체 실행
        if (isset($_POST['hc_dev_toolkit_run_all'])) {
            $messages[] = self::run_all();
        }

        if (!empty($messages)) {
            update_option(self::OPTION_NOTICE, implode("\n", $messages));
        }
    }

    public static function admin_notices() {
        $msg = get_option(self::OPTION_NOTICE);
        if (!$msg) {
            return;
        }
        delete_option(self::OPTION_NOTICE);
        echo '<div class="notice notice-success is-dismissible"><p>' . nl2br(esc_html($msg)) . '</p></div>';
    }

    /* ===================== 메인 실행 루틴 ===================== */

    private static function run_all() {
        $messages = [];

        $messages[] = self::setup_theme();
        $messages[] = self::setup_plugins();
        $messages[] = self::update_wp_config();
        $messages[] = self::create_default_header_footer();

        $messages = array_filter($messages);
        if (!$messages) {
            return '실행할 작업이 없습니다.';
        }
        return implode("\n", $messages);
    }

    /* ===================== 테마 설치/전환 + 자동업데이트 ===================== */

    private static function setup_theme() {
        include_once ABSPATH . 'wp-admin/includes/theme.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/theme-install.php';

        $slug  = self::get_theme_slug();
        $theme = wp_get_theme();
        $available = wp_get_themes();

        if (!isset($available[$slug])) {
            $installed = self::install_theme_from_repo($slug);
            if (is_wp_error($installed)) {
                return "테마 '{$slug}' 설치 실패: " . $installed->get_error_message();
            }
            $available = wp_get_themes();
        }

        if (!isset($available[$slug])) {
            return "테마 '{$slug}' 를 찾을 수 없습니다. (관리자에서 직접 설치가 필요합니다)";
        }

        if ($theme->get_stylesheet() !== $slug && $theme->get_template() !== $slug) {
            switch_theme($slug);
            self::ensure_theme_auto_update($slug);
            return "테마 '{$slug}' 를 설치/전환하고 자동 업데이트를 활성화했습니다.";
        }

        self::ensure_theme_auto_update($slug);
        return "테마 '{$slug}' 가 이미 활성화되어 있습니다. 자동 업데이트만 확인했습니다.";
    }

    private static function install_theme_from_repo($slug) {
        $api = themes_api('theme_information', [
            'slug'   => $slug,
            'fields' => [ 'sections' => false ],
        ]);

        if (is_wp_error($api)) {
            return $api;
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader($skin);
        $result   = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            return $result;
        }
        if (!$result) {
            return new WP_Error('install_failed', '알 수 없는 이유로 테마 설치에 실패했습니다.');
        }
        return true;
    }

    private static function ensure_theme_auto_update($slug) {
        $auto = (array) get_option('auto_update_themes', []);
        if (!in_array($slug, $auto, true)) {
            $auto[] = $slug;
            update_option('auto_update_themes', $auto);
        }
    }

    /* ===================== 플러그인 설치/활성화 + 자동업데이트 ===================== */

    private static function setup_plugins() {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $msgs = [];

        foreach (self::get_plugins() as $slug => $cfg) {
            $file = $cfg['file'];
            $path = WP_PLUGIN_DIR . '/' . $file;

            // 설치 필요 & repo 플러그인 → WordPress.org 에서 설치
            if (!file_exists($path) && !empty($cfg['repo'])) {
                $installed = self::install_plugin_from_repo($slug);
                if (is_wp_error($installed)) {
                    $msgs[] = sprintf('[%s] 설치 실패: %s', $slug, $installed->get_error_message());
                    continue;
                }
            }

            // 파일이 있으면 활성화 + 자동업데이트
            if (file_exists($path)) {
                $result = activate_plugin($file);
                if (is_wp_error($result)) {
                    $msgs[] = sprintf('[%s] 활성화 실패: %s', $slug, $result->get_error_message());
                } else {
                    self::ensure_plugin_auto_update($file);
                    $msgs[] = sprintf('[%s] 활성화 및 자동업데이트 설정 완료.', $slug);
                }
            } else {
                if (empty($cfg['repo'])) {
                    $msgs[] = sprintf('[%s] 유료/외부 플러그인입니다. ZIP으로 먼저 설치해 주세요.', $slug);
                } else {
                    $msgs[] = sprintf('[%s] 플러그인을 찾을 수 없습니다.', $slug);
                }
            }
        }

        return implode("\n", $msgs);
    }

    private static function install_plugin_from_repo($slug) {
        $api = plugins_api('plugin_information', [
            'slug'   => $slug,
            'fields' => [ 'sections' => false ],
        ]);

        if (is_wp_error($api)) {
            return $api;
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result   = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            return $result;
        }
        if (!$result) {
            return new WP_Error('install_failed', '알 수 없는 이유로 플러그인 설치에 실패했습니다.');
        }
        return true;
    }

    private static function ensure_plugin_auto_update($plugin_file) {
        $auto = (array) get_option('auto_update_plugins', []);
        if (!in_array($plugin_file, $auto, true)) {
            $auto[] = $plugin_file;
            update_option('auto_update_plugins', $auto);
        }
    }

    // 자동업데이트 강제 (필터)
    public static function force_plugin_auto_update($update, $item) {
        $targets = self::get_plugins();
        if (isset($targets[$item->slug])) {
            return true;
        }
        return $update;
    }

    public static function force_theme_auto_update($update, $item) {
        $slug = self::get_theme_slug();
        if ($item->stylesheet === $slug || $item->template === $slug) {
            return true;
        }
        return $update;
    }

    /* ===================== wp-config.php 디버그 + 파일 편집 허용 ===================== */

    private static function update_wp_config() {
        $path = ABSPATH . 'wp-config.php';

        if (!file_exists($path) || !is_readable($path) || !is_writable($path)) {
            return 'wp-config.php 에 접근할 수 없어 디버그/파일 편집 설정을 변경하지 못했습니다.';
        }

        $code = file_get_contents($path);
        if ($code === false) {
            return 'wp-config.php 읽기에 실패했습니다.';
        }

        $orig = $code;

        // 디버그/로그/출력
        $code = self::set_define($code, 'WP_DEBUG', 'true');
        $code = self::set_define($code, 'WP_DEBUG_LOG', 'true');
        $code = self::set_define($code, 'WP_DEBUG_DISPLAY', 'true');

        // 테마/플러그인 편집 허용
        $code = self::set_define($code, 'DISALLOW_FILE_EDIT', 'false');
        $code = self::set_define($code, 'DISALLOW_FILE_MODS', 'false');

        // display_errors on
        if (strpos($code, "@ini_set('display_errors',") === false &&
            strpos($code, '@ini_set("display_errors",') === false) {
            $insert = "@ini_set('display_errors', 1);\n";
            $marker = "/* That's all, stop editing!";
            $pos    = strpos($code, $marker);
            if ($pos !== false) {
                $code = substr_replace($code, $insert, $pos, 0);
            } else {
                $code .= "\n" . $insert;
            }
        }

        if ($code !== $orig) {
            file_put_contents($path, $code);
            return 'wp-config.php 를 수정하여 WP_DEBUG 및 파일 편집을 활성화했습니다.';
        }

        return 'wp-config.php 설정은 이미 원하는 상태입니다.';
    }

    private static function set_define($code, $name, $value) {
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote($name, '/') .
                   '[\'"]\s*,\s*(true|false|null|\'[^\']*\'|"[^"]*")\s*\)\s*;/i';

        if (preg_match($pattern, $code)) {
            return preg_replace($pattern, "define( '" . $name . "', " . $value . " );", $code);
        }

        $insert = "define( '" . $name . "', " . $value . " );\n";
        $marker = "/* That's all, stop editing!";
        $pos    = strpos($code, $marker);
        if ($pos !== false) {
            return substr_replace($code, $insert, $pos, 0);
        }

        return $code . "\n" . $insert;
    }

    /* ===================== OceanWP 새 페이지 기본값 적용 ===================== */

    public static function apply_oceanwp_page_defaults($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        if ($post->post_type !== 'page') {
            return;
        }

        $theme = wp_get_theme();
        if ($theme->get_template() !== 'oceanwp' && $theme->get_stylesheet() !== 'oceanwp') {
            return;
        }

        // Content Layout = 100% Full Width (slug: full-screen)
        if (get_post_meta($post_id, 'ocean_post_layout', true) === '') {
            update_post_meta($post_id, 'ocean_post_layout', 'full-screen');
        }

        // Header > Display Top Bar = Disable
        if (get_post_meta($post_id, 'ocean_display_top_bar', true) === '') {
            update_post_meta($post_id, 'ocean_display_top_bar', 'disable');
        }

        // Header > Display Header = Disable
        if (get_post_meta($post_id, 'ocean_display_header', true) === '') {
            update_post_meta($post_id, 'ocean_display_header', 'disable');
        }
    }

    /* ===================== UAEL Header/Footer 기본 템플릿 생성 ===================== */

    private static function create_default_header_footer() {
        // UAEL Header/Footer 플러그인 활성화 필요 (post_type: elementor-hf)
        if (!post_type_exists('elementor-hf')) {
            return 'UAEL(Header/Footer) 플러그인을 아직 사용할 수 없습니다. (header-footer-elementor 활성화 후 다시 실행하세요)';
        }

        // 이미 템플릿이 있으면 새로 생성 안 함
        $existing = get_posts([
            'post_type'      => 'elementor-hf',
            'posts_per_page' => 2,
            'fields'         => 'ids',
        ]);

        if (!empty($existing)) {
            return '이미 Header/Footer 템플릿이 있어서 새로 생성하지 않았습니다.';
        }

        $header_id = wp_insert_post([
            'post_type'   => 'elementor-hf',
            'post_title'  => 'HC Default Header',
            'post_status' => 'publish',
        ]);

        $footer_id = wp_insert_post([
            'post_type'   => 'elementor-hf',
            'post_title'  => 'HC Default Footer',
            'post_status' => 'publish',
        ]);

        if ($header_id && !is_wp_error($header_id)) {
            add_post_meta($header_id, 'hfe_template_type', 'header', true);
            add_post_meta($header_id, '_elementor_edit_mode', 'builder', true);
        }

        if ($footer_id && !is_wp_error($footer_id)) {
            add_post_meta($footer_id, 'hfe_template_type', 'footer', true);
            add_post_meta($footer_id, '_elementor_edit_mode', 'builder', true);
        }

        return 'UAEL(Header/Footer)용 기본 Header / Footer 템플릿을 1개씩 생성했습니다.';
    }

    /* ===================== 커스텀 CSS 출력 ===================== */

    public static function print_custom_css() {
        $css = trim((string) get_option(self::OPTION_CSS, ''));
        if ($css === '') {
            return;
        }

        // OceanWP 메인 스타일 핸들 위에 인라인 추가
        if (wp_style_is('oceanwp-style', 'enqueued')) {
            wp_add_inline_style('oceanwp-style', $css);
        } else {
            // 혹시 모를 경우를 위해 fallback
            wp_register_style('hc-dev-toolkit-inline', false);
            wp_enqueue_style('hc-dev-toolkit-inline');
            wp_add_inline_style('hc-dev-toolkit-inline', $css);
        }
    }
}

HC_Dev_Toolkit::init();

endif;
