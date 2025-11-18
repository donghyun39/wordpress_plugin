<?php
namespace HC\Shop\Admin;

final class Settings
{
    private string $option = 'hc_shop_settings';

    public function register(): void
    {
        add_menu_page('HC Settings', 'HC Settings', 'manage_options', 'hc-settings', [$this, 'renderGeneral'], 'dashicons-admin-generic', 28);
        add_submenu_page('hc-settings', 'General', 'General', 'manage_options', 'hc-settings', [$this, 'renderGeneral']);
        add_submenu_page('hc-settings', 'Shipping', 'Shipping', 'manage_options', 'hc-settings-shipping', [$this, 'renderShipping']);
        add_submenu_page('hc-settings', 'Inventory', 'Inventory', 'manage_options', 'hc-settings-inventory', [$this, 'renderInventory']);
        add_submenu_page('hc-settings', 'Notifications', 'Notifications', 'manage_options', 'hc-settings-notifications', [$this, 'renderNotifications']);
        add_submenu_page('hc-settings', 'Payments', 'Payments', 'manage_options', 'hc-settings-payments', [$this, 'renderPayments']);
        add_submenu_page('hc-settings', 'Customers', 'Customers', 'manage_options', 'hc-settings-customers', [$this, 'renderCustomers']);
    }

    // ===== Common =====
    private function get(): array
    {
        return get_option($this->option, []) ?: [];
    }
    private function save(array $patch): void
    {
        $curr = $this->get();
        update_option($this->option, array_merge($curr, $patch), false);
    }
    private function header(string $title, string $sectionSlug): void
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <hr class="wp-header-end">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="hc-settings-form">
                <?php wp_nonce_field('hc_settings_save', 'hc_settings_nonce'); ?>
                <input type="hidden" name="action" value="hc_settings_save">
                <input type="hidden" name="section" value="<?php echo esc_attr($sectionSlug); ?>">
                <?php
    }
    private function footer(): void
    {
        echo '<p><button class="button button-primary" type="submit">저장</button></p></form></div>';
    }

    private function currencyList(): array
    {
        return [
            'KRW' => 'KRW (₩ 원)',
            'USD' => 'USD ($ Dollar)',
            'EUR' => 'EUR (€ Euro)',
            'JPY' => 'JPY (¥ 円)',
            'CNY' => 'CNY (¥ 元)',
            'GBP' => 'GBP (£ Pound)',
            'AUD' => 'AUD ($ Australian)',
            'CAD' => 'CAD ($ Canadian)',
            'HKD' => 'HKD ($ Hong Kong)',
            'TWD' => 'TWD ($ 新台幣)',
        ];
    }

    // ===== General =====
    public function renderGeneral(): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        $s = $this->get();
        $store_name = esc_attr($s['store_name'] ?? get_bloginfo('name'));
        $store_email = esc_attr($s['store_email'] ?? get_option('admin_email'));
        $currency = esc_attr($s['currency'] ?? 'KRW');
        $order_prefix = esc_attr($s['order_prefix'] ?? 'HC');

        $this->header('HC Settings – General', 'general'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>스토어 이름</th>
                        <td><input type="text" name="store_name" class="regular-text" value="<?php echo $store_name; ?>"></td>
                    </tr>
                    <tr>
                        <th>스토어 이메일(From)</th>
                        <td><input type="email" name="store_email" class="regular-text" value="<?php echo $store_email; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>통화</th>
                        <td>
                            <select name="currency">
                                <?php foreach ($this->currencyList() as $code => $label): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>주문번호 접두사</th>
                        <td><input type="text" name="order_prefix" value="<?php echo $order_prefix; ?>" class="regular-text"
                                placeholder="HC"></td>
                    </tr>
                </table>
                <?php $this->footer();
    }

    // ===== Shipping =====
    public function renderShipping(): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        $s = $this->get();
        $shipping_flat_fee = (int) ($s['shipping_flat_fee'] ?? 3000);
        $shipping_free_over = (int) ($s['shipping_free_over'] ?? 30000);

        $this->header('HC Settings – Shipping', 'shipping'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>기본 배송비</th>
                        <td>
                            <input type="text" name="shipping_flat_fee" class="regular-text hc-money"
                                value="<?php echo esc_attr(number_format($shipping_flat_fee)); ?>"> 원
                        </td>
                    </tr>
                    <tr>
                        <th>무료배송 기준</th>
                        <td>
                            <input type="text" name="shipping_free_over" class="regular-text hc-money"
                                value="<?php echo esc_attr(number_format($shipping_free_over)); ?>"> 원
                            <p class="description">0이면 무료배송 미적용(항상 기본 배송비)</p>
                        </td>
                    </tr>
                </table>
                <?php $this->footer();
    }

    // ===== Inventory =====
    public function renderInventory(): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        $s = $this->get();
        $stock_decrement_on_paid = (int) ($s['stock_decrement_on_paid'] ?? 1);
        $stock_restore_on_cancel = (int) ($s['stock_restore_on_cancel'] ?? 1);
        $stock_restore_on_refund = (int) ($s['stock_restore_on_refund'] ?? 1);
        $low_stock_threshold = (int) ($s['low_stock_threshold'] ?? 0);
        $low_stock_notify = (int) ($s['low_stock_notify'] ?? 0);

        $this->header('HC Settings – Inventory', 'inventory'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>결제완료 시 재고 차감</th>
                        <td><label><input type="checkbox" name="stock_decrement_on_paid" value="1" <?php checked($stock_decrement_on_paid, 1); ?>> 사용</label></td>
                    </tr>
                    <tr>
                        <th>취소 시 재고 복원</th>
                        <td><label><input type="checkbox" name="stock_restore_on_cancel" value="1" <?php checked($stock_restore_on_cancel, 1); ?>> 사용</label></td>
                    </tr>
                    <tr>
                        <th>환불 시 재고 복원</th>
                        <td><label><input type="checkbox" name="stock_restore_on_refund" value="1" <?php checked($stock_restore_on_refund, 1); ?>> 사용</label></td>
                    </tr>
                    <tr>
                        <th>낮은 재고 임계값</th>
                        <td><input type="number" name="low_stock_threshold" value="<?php echo $low_stock_threshold; ?>" min="0"
                                step="1"></td>
                    </tr>
                    <tr>
                        <th>낮은 재고 알림(관리자)</th>
                        <td><label><input type="checkbox" name="low_stock_notify" value="1" <?php checked($low_stock_notify, 1); ?>> 이메일 알림</label></td>
                    </tr>
                </table>
                <?php $this->footer();
    }

    // ===== Notifications =====
    public function renderNotifications(): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        $s = $this->get();
        $notify_admin_on_order = (int) ($s['notify_admin_on_order'] ?? 1);
        $notify_customer_on_order = (int) ($s['notify_customer_on_order'] ?? 1);
        $notify_admin_on_paid = (int) ($s['notify_admin_on_paid'] ?? 1);
        $notify_customer_on_paid = (int) ($s['notify_customer_on_paid'] ?? 1);

        $this->header('HC Settings – Notifications', 'notifications'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>주문 생성 시</th>
                        <td>
                            <label><input type="checkbox" name="notify_admin_on_order" value="1" <?php checked($notify_admin_on_order, 1); ?>> 관리자에게</label>
                            &nbsp;&nbsp;
                            <label><input type="checkbox" name="notify_customer_on_order" value="1" <?php checked($notify_customer_on_order, 1); ?>> 고객에게</label>
                        </td>
                    </tr>
                    <tr>
                        <th>결제 완료 시</th>
                        <td>
                            <label><input type="checkbox" name="notify_admin_on_paid" value="1" <?php checked($notify_admin_on_paid, 1); ?>> 관리자에게</label>
                            &nbsp;&nbsp;
                            <label><input type="checkbox" name="notify_customer_on_paid" value="1" <?php checked($notify_customer_on_paid, 1); ?>> 고객에게</label>
                        </td>
                    </tr>
                </table>
                <?php $this->footer();
    }

    // ===== Payments =====
    public function renderPayments(): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        $s = $this->get();

        $payments_enabled = (int) ($s['payments_enabled'] ?? 1); // ✅ 결제 사용 여부
        $inquiry_flow = (string) ($s['inquiry_flow'] ?? 'cart_then_inquiry'); // ✅ inquiry 플로우
        $pg_provider = esc_attr($s['pg_provider'] ?? 'none'); // none|toss|inicis
        $pg_mode = esc_attr($s['pg_mode'] ?? 'test');

        $toss_key = esc_attr($s['pg_toss_secret_key'] ?? '');
        $inicis_mid = esc_attr($s['pg_inicis_mid'] ?? '');
        $inicis_sign = esc_attr($s['pg_inicis_sign_key'] ?? '');

        $this->header('HC Settings – Payments', 'payments'); ?>
                <table class="form-table" role="presentation" id="hc-payments-table">
                    <tr>
                        <th>결제 사용</th>
                        <td>
                            <label><input type="checkbox" name="payments_enabled" value="1" <?php checked($payments_enabled, 1); ?>> 사용</label>
                            <p class="description">체크 해제 시 결제 버튼 대신 “문의하기” 플로우가 노출됩니다.</p>
                        </td>
                    </tr>

                    <tr>
                        <th>문의 플로우</th>
                        <td>
                            <select name="inquiry_flow">
                                <option value="cart_then_inquiry" <?php selected($inquiry_flow, 'cart_then_inquiry'); ?>>장바구니 →
                                    문의하기 페이지</option>
                                <option value="combined" <?php selected($inquiry_flow, 'combined'); ?>>장바구니 + 문의하기(통합) 페이지
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>활성 PG</th>
                        <td>
                            <select name="pg_provider" id="hc-pg-provider">
                                <option value="none" <?php selected($pg_provider, 'none'); ?>>사용 안 함</option>
                                <option value="toss" <?php selected($pg_provider, 'toss'); ?>>토스페이먼츠</option>
                                <option value="inicis" <?php selected($pg_provider, 'inicis'); ?>>KG이니시스</option>
                            </select>
                            &nbsp;&nbsp;
                            <label>모드:
                                <select name="pg_mode" id="hc-pg-mode">
                                    <option value="test" <?php selected($pg_mode, 'test'); ?>>테스트</option>
                                    <option value="live" <?php selected($pg_mode, 'live'); ?>>라이브</option>
                                </select>
                            </label>
                        </td>
                    </tr>

                    <!-- 활성 PG만 입력 가능 -->
                    <tr class="pg-toss-row">
                        <th>토스 Secret Key</th>
                        <td><input type="password" name="pg_toss_secret_key" class="regular-text"
                                value="<?php echo $toss_key; ?>" autocomplete="new-password"></td>
                    </tr>
                    <tr class="pg-toss-row">
                        <th>토스 Client Key</th>
                        <td>
                            <input type="text" name="pg_toss_client_key" class="regular-text"
                                value="<?php echo esc_attr($s['pg_toss_client_key'] ?? ''); ?>">
                            <p class="description">예: test_ck_xxx (위젯 초기화에 필요)</p>
                        </td>
                    </tr>

                    <tr class="pg-inicis-row">
                        <th>이니시스 MID</th>
                        <td><input type="text" name="pg_inicis_mid" class="regular-text" value="<?php echo $inicis_mid; ?>">
                        </td>
                    </tr>
                    <tr class="pg-inicis-row">
                        <th>이니시스 Sign Key</th>
                        <td><input type="password" name="pg_inicis_sign_key" class="regular-text"
                                value="<?php echo $inicis_sign; ?>" autocomplete="new-password"></td>
                    </tr>
                </table>
                <p class="description">※ 결제 미사용 시 PG 키 입력은 무시되며, 프런트엔드는 “문의하기”로 전환됩니다.</p>
                <?php $this->footer();
    }

    // ===== Save =====
    public function handleSave(): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        if (!isset($_POST['hc_settings_nonce']) || !wp_verify_nonce($_POST['hc_settings_nonce'], 'hc_settings_save')) {
            wp_die('Invalid nonce');
        }
        $section = sanitize_key($_POST['section'] ?? 'general');

        // helpers
        $toInt = function ($v) {
            return (int) preg_replace('/[^\d]/', '', (string) $v);
        }; // 쉼표 허용 입력 정규화

        switch ($section) {
            case 'general':
                $this->save([
                    'store_name' => sanitize_text_field($_POST['store_name'] ?? ''),
                    'store_email' => sanitize_email($_POST['store_email'] ?? ''),
                    'currency' => sanitize_text_field($_POST['currency'] ?? 'KRW'),
                    'order_prefix' => sanitize_text_field($_POST['order_prefix'] ?? 'HC'),
                ]);
                $page = 'hc-settings';
                break;

            case 'shipping':
                $this->save([
                    'shipping_flat_fee' => max(0, $toInt($_POST['shipping_flat_fee'] ?? 3000)),
                    'shipping_free_over' => max(0, $toInt($_POST['shipping_free_over'] ?? 30000)),
                ]);
                $page = 'hc-settings-shipping';
                break;

            case 'inventory':
                $this->save([
                    'stock_decrement_on_paid' => isset($_POST['stock_decrement_on_paid']) ? 1 : 0,
                    'stock_restore_on_cancel' => isset($_POST['stock_restore_on_cancel']) ? 1 : 0,
                    'stock_restore_on_refund' => isset($_POST['stock_restore_on_refund']) ? 1 : 0,
                    'low_stock_threshold' => max(0, (int) ($_POST['low_stock_threshold'] ?? 0)),
                    'low_stock_notify' => isset($_POST['low_stock_notify']) ? 1 : 0,
                ]);
                $page = 'hc-settings-inventory';
                break;

            case 'notifications':
                $this->save([
                    'notify_admin_on_order' => isset($_POST['notify_admin_on_order']) ? 1 : 0,
                    'notify_customer_on_order' => isset($_POST['notify_customer_on_order']) ? 1 : 0,
                    'notify_admin_on_paid' => isset($_POST['notify_admin_on_paid']) ? 1 : 0,
                    'notify_customer_on_paid' => isset($_POST['notify_customer_on_paid']) ? 1 : 0,
                ]);
                $page = 'hc-settings-notifications';
                break;

            case 'customers':
                $patch = [
                    'social_enabled' => isset($_POST['social_enabled']) ? 1 : 0,

                    'kakao_enabled' => isset($_POST['kakao_enabled']) ? 1 : 0,
                    'kakao_client_id' => sanitize_text_field($_POST['kakao_client_id'] ?? ''),
                    'kakao_client_secret' => sanitize_text_field($_POST['kakao_client_secret'] ?? ''),

                    'naver_enabled' => isset($_POST['naver_enabled']) ? 1 : 0,
                    'naver_client_id' => sanitize_text_field($_POST['naver_client_id'] ?? ''),
                    'naver_client_secret' => sanitize_text_field($_POST['naver_client_secret'] ?? ''),

                    'google_enabled' => isset($_POST['google_enabled']) ? 1 : 0,
                    'google_client_id' => sanitize_text_field($_POST['google_client_id'] ?? ''),
                    'google_client_secret' => sanitize_text_field($_POST['google_client_secret'] ?? ''),
                ];
                $this->save($patch);
                $page = 'hc-settings-customers';
                break;

            case 'payments':
                $provider = in_array(($_POST['pg_provider'] ?? 'none'), ['none', 'toss', 'inicis'], true) ? $_POST['pg_provider'] : 'none';
                $mode = in_array(($_POST['pg_mode'] ?? 'test'), ['test', 'live'], true) ? $_POST['pg_mode'] : 'test';

                $payments_enabled = isset($_POST['payments_enabled']) ? 1 : 0;
                $inquiry_flow = in_array(($_POST['inquiry_flow'] ?? 'cart_then_inquiry'), ['cart_then_inquiry', 'combined'], true)
                    ? $_POST['inquiry_flow'] : 'cart_then_inquiry';

                $patch = [
                    'payments_enabled' => $payments_enabled,
                    'inquiry_flow' => $inquiry_flow,
                    'pg_provider' => $provider,
                    'pg_mode' => $mode,
                    'pg_toss_secret_key' => '',
                    'pg_toss_client_key' => '',
                    'pg_inicis_mid' => '',
                    'pg_inicis_sign_key' => '',
                ];
                if ($payments_enabled) {
                    if ($provider === 'toss') {
                        $patch['pg_toss_secret_key'] = sanitize_text_field($_POST['pg_toss_secret_key'] ?? '');
                        $patch['pg_toss_client_key'] = sanitize_text_field($_POST['pg_toss_client_key'] ?? '');
                    } elseif ($provider === 'inicis') {
                        $patch['pg_inicis_mid'] = sanitize_text_field($_POST['pg_inicis_mid'] ?? '');
                        $patch['pg_inicis_sign_key'] = sanitize_text_field($_POST['pg_inicis_sign_key'] ?? '');
                    }
                }
                $this->save($patch);
                $page = 'hc-settings-payments';
                wp_redirect(admin_url('admin.php?page=' . $page . '&updated=1'));
                exit;



        }
        wp_redirect(admin_url('admin.php?page=' . $page . '&updated=1'));
        exit;
    }
    public function renderCustomers(): void
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        $s = $this->get();

        $social_enabled = (int) ($s['social_enabled'] ?? 0);

        $kakao_enabled = (int) ($s['kakao_enabled'] ?? 0);
        $kakao_id = esc_attr($s['kakao_client_id'] ?? '');
        $kakao_secret = esc_attr($s['kakao_client_secret'] ?? '');

        $naver_enabled = (int) ($s['naver_enabled'] ?? 0);
        $naver_id = esc_attr($s['naver_client_id'] ?? '');
        $naver_secret = esc_attr($s['naver_client_secret'] ?? '');

        $google_enabled = (int) ($s['google_enabled'] ?? 0);
        $google_id = esc_attr($s['google_client_id'] ?? '');
        $google_secret = esc_attr($s['google_client_secret'] ?? '');

        $cb_kakao = esc_url(rest_url('hc/v1/auth/kakao/callback'));
        $cb_naver = esc_url(rest_url('hc/v1/auth/naver/callback'));
        $cb_google = esc_url(rest_url('hc/v1/auth/google/callback'));

        $this->header('HC Settings – Customers (Social Login)', 'customers'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>소셜 로그인 사용</th>
                        <td><label><input type="checkbox" name="social_enabled" value="1" <?php checked($social_enabled, 1); ?>>
                                사용</label></td>
                    </tr>

                    <tr>
                        <th colspan="2">
                            <h2>카카오</h2>
                        </th>
                    </tr>
                    <tr>
                        <th>사용</th>
                        <td><label><input type="checkbox" name="kakao_enabled" value="1" <?php checked($kakao_enabled, 1); ?>>
                                사용</label></td>
                    </tr>
                    <tr>
                        <th>REST API 키(클라이언트 ID)</th>
                        <td><input type="text" name="kakao_client_id" class="regular-text" value="<?php echo $kakao_id; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>클라이언트 시크릿(선택)</th>
                        <td><input type="password" name="kakao_client_secret" class="regular-text"
                                value="<?php echo $kakao_secret; ?>" autocomplete="new-password"></td>
                    </tr>
                    <tr>
                        <th>Redirect URI</th>
                        <td><input type="text" readonly class="regular-text code" value="<?php echo $cb_kakao; ?>"></td>
                    </tr>

                    <tr>
                        <th colspan="2">
                            <h2>네이버</h2>
                        </th>
                    </tr>
                    <tr>
                        <th>사용</th>
                        <td><label><input type="checkbox" name="naver_enabled" value="1" <?php checked($naver_enabled, 1); ?>>
                                사용</label></td>
                    </tr>
                    <tr>
                        <th>클라이언트 ID</th>
                        <td><input type="text" name="naver_client_id" class="regular-text" value="<?php echo $naver_id; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>클라이언트 시크릿</th>
                        <td><input type="password" name="naver_client_secret" class="regular-text"
                                value="<?php echo $naver_secret; ?>" autocomplete="new-password"></td>
                    </tr>
                    <tr>
                        <th>Redirect URI</th>
                        <td><input type="text" readonly class="regular-text code" value="<?php echo $cb_naver; ?>"></td>
                    </tr>

                    <tr>
                        <th colspan="2">
                            <h2>Google</h2>
                        </th>
                    </tr>
                    <tr>
                        <th>사용</th>
                        <td><label><input type="checkbox" name="google_enabled" value="1" <?php checked($google_enabled, 1); ?>>
                                사용</label></td>
                    </tr>
                    <tr>
                        <th>Client ID</th>
                        <td><input type="text" name="google_client_id" class="regular-text" value="<?php echo $google_id; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>Client Secret</th>
                        <td><input type="password" name="google_client_secret" class="regular-text"
                                value="<?php echo $google_secret; ?>" autocomplete="new-password"></td>
                    </tr>
                    <tr>
                        <th>Redirect URI</th>
                        <td><input type="text" readonly class="regular-text code" value="<?php echo $cb_google; ?>"></td>
                    </tr>
                </table>
                <?php $this->footer();
    }
}
