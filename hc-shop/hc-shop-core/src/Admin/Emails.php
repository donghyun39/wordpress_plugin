<?php
namespace HC\Shop\Admin;

use HC\Shop\Notifications\Mailer;

final class Emails
{
    private string $option = 'hc_shop_settings';

    public function register(): void
    {
        // 메뉴
        add_submenu_page(
            'hc-settings',
            'Emails',
            'Emails',
            'manage_options',
            'hc-settings-emails',
            [$this, 'render']
        );

        // 저장
        add_action('admin_post_hc_emails_save', [$this, 'handleSave']);

        // AJAX: 미리보기/테스트 발송
        add_action('wp_ajax_hc_emails_preview', [$this, 'ajaxPreview']);
        add_action('wp_ajax_hc_emails_test',    [$this, 'ajaxTest']);

        // 스크립트/스타일
        add_action('admin_enqueue_scripts', function($hook){
            if (strpos($hook, 'hc-settings-emails') === false) return;
            wp_enqueue_style('hc-emails-admin', plugin_dir_url(\HC\Shop\Plugin::$file).'assets/css/hc-emails-admin.css', [], \HC\Shop\Plugin::VERSION);
            wp_enqueue_script('hc-emails-admin', plugin_dir_url(\HC\Shop\Plugin::$file).'assets/js/hc-emails-admin.js', ['jquery'], \HC\Shop\Plugin::VERSION, true);
            wp_localize_script('hc-emails-admin', 'HCEmails', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hc_emails'),
            ]);
        });
    }

    // ===== helpers =====
    private function get(): array { return get_option($this->option, []) ?: []; }
    private function savePatch(array $patch): void {
        $curr = $this->get();
        update_option($this->option, array_merge($curr, $patch), false);
    }
    private function defaults(): array
    {
        return [
            'brand' => [
                'from_name'    => get_bloginfo('name'),
                'from_email'   => get_option('admin_email'),
                'logo_id'      => 0,
                'brand_color'  => '#111827', // slate-900
                'title_prefix' => '주문 알림 | ',
                'footer_text'  => "감사합니다.\n" . home_url('/'),
            ],
            'templates' => [
                // 고객/관리자 각각
                'order_created_customer' => [
                    'subject' => '{{store.name}} 주문 접수: {{order.number}}',
                    'body'    => "<p>{{customer.name}} 님, 주문이 접수되었습니다.</p>\n{{items.table}}\n<p><strong>합계:</strong> {{totals.grand}} {{currency}}</p>\n<p><a href=\"{{order.url}}\">주문 상세 확인</a></p>"
                ],
                'order_created_admin' => [
                    'subject' => '[관리자] 새 주문: {{order.number}}',
                    'body'    => "<p>새 주문이 생성되었습니다.</p>\n{{items.table}}\n<p><strong>합계:</strong> {{totals.grand}} {{currency}}</p>\n<p>고객: {{customer.name}} / {{customer.email}} / {{customer.phone}}</p>"
                ],
                'payment_paid_customer' => [
                    'subject' => '{{store.name}} 결제 완료: {{order.number}}',
                    'body'    => "<p>{{customer.name}} 님, 결제가 완료되었습니다.</p>\n{{items.table}}\n<p><strong>합계:</strong> {{totals.grand}} {{currency}}</p>\n<p><a href=\"{{order.url}}\">주문 상세 확인</a></p>"
                ],
                'payment_paid_admin' => [
                    'subject' => '[관리자] 결제 완료: {{order.number}}',
                    'body'    => "<p>아래 주문의 결제가 완료되었습니다.</p>\n{{items.table}}\n<p><strong>합계:</strong> {{totals.grand}} {{currency}}</p>\n<p>고객: {{customer.name}} / {{customer.email}} / {{customer.phone}}</p>"
                ],
                'shipping_started_customer' => [
                    'subject' => '{{store.name}} 배송 시작: {{order.number}}',
                    'body'    => "<p>{{customer.name}} 님, 상품이 출고되었습니다.</p>\n<p>송장: {{shipping.tracking}} ({{shipping.carrier}})</p>\n{{items.table}}\n<p><a href=\"{{order.url}}\">배송 상태 확인</a></p>"
                ],
                'shipping_started_admin' => [
                    'subject' => '[관리자] 배송 시작: {{order.number}}',
                    'body'    => "<p>주문이 배송 단계로 전환되었습니다.</p>\n<p>송장: {{shipping.tracking}} ({{shipping.carrier}})</p>\n{{items.table}}"
                ],
                'order_cancelled_customer' => [
                    'subject' => '{{store.name}} 주문 취소 안내: {{order.number}}',
                    'body'    => "<p>{{customer.name}} 님, 요청하신 주문이 취소되었습니다.</p>\n<p>환불/취소 내역을 확인해주세요.</p>\n<p><a href=\"{{order.url}}\">주문 상세</a></p>"
                ],
                'order_cancelled_admin' => [
                    'subject' => '[관리자] 주문 취소: {{order.number}}',
                    'body'    => "<p>아래 주문이 취소되었습니다.</p>\n{{items.table}}\n<p>고객: {{customer.name}} / {{customer.email}}</p>"
                ],
                'order_refunded_customer' => [
                    'subject' => '{{store.name}} 환불 처리: {{order.number}}',
                    'body'    => "<p>{{customer.name}} 님, 환불이 진행되었습니다.</p>\n<p>환불 금액: {{totals.refunded}} / {{totals.grand}}</p>\n<p><a href=\"{{order.url}}\">주문 상세</a></p>"
                ],
                'order_refunded_admin' => [
                    'subject' => '[관리자] 환불 처리: {{order.number}}',
                    'body'    => "<p>주문 환불이 기록되었습니다.</p>\n<p>환불 금액: {{totals.refunded}} / {{totals.grand}}</p>\n{{items.table}}"
                ],
            ],
            'events' => [
                'order_created_customer' => 1,
                'order_created_admin'    => 1,
                'payment_paid_customer'  => 1,
                'payment_paid_admin'     => 1,
                'shipping_started_customer' => 1,
                'shipping_started_admin'    => 1,
                'order_cancelled_customer'  => 1,
                'order_cancelled_admin'     => 1,
                'order_refunded_customer'   => 1,
                'order_refunded_admin'      => 1,
            ],
        ];
    }
    private function mergeDefaults(array $s): array
    {
        $d = $this->defaults();
        $s['email_brand']     = array_merge($d['brand'], (array)($s['email_brand'] ?? []));
        $s['email_templates'] = array_merge($d['templates'], (array)($s['email_templates'] ?? []));
        $s['email_events']    = array_merge($d['events'], (array)($s['email_events'] ?? []));
        return $s;
    }

    // ===== UI =====
    public function render(): void
    {
        if (!current_user_can('manage_options')) wp_die('No permission');

        $s = $this->mergeDefaults($this->get());
        $brand = $s['email_brand'];
        $tpl   = $s['email_templates'];

        $logo_id = (int)$brand['logo_id'];
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

        ?>
        <div class="wrap">
          <h1 class="wp-heading-inline">HC Emails</h1>
          <hr class="wp-header-end">

          <h2 class="nav-tab-wrapper" id="hc-email-tabs">
            <a href="#brand" class="nav-tab nav-tab-active">Branding</a>
            <a href="#templates" class="nav-tab">Templates</a>
            <a href="#preview" class="nav-tab">Preview / Test</a>
          </h2>

          <!-- Branding -->
          <div class="hc-tab-panel" id="brand" style="display:block">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <?php wp_nonce_field('hc_emails_save', 'hc_emails_nonce'); ?>
              <input type="hidden" name="action" value="hc_emails_save">
              <input type="hidden" name="section" value="brand">

              <table class="form-table">
                <tr>
                  <th scope="row">From 이름</th>
                  <td><input type="text" class="regular-text" name="from_name" value="<?php echo esc_attr($brand['from_name']); ?>"></td>
                </tr>
                <tr>
                  <th scope="row">From 이메일</th>
                  <td><input type="email" class="regular-text" name="from_email" value="<?php echo esc_attr($brand['from_email']); ?>"></td>
                </tr>
                <tr>
                  <th scope="row">로고</th>
                  <td>
                    <input type="hidden" id="hc-email-logo-id" name="logo_id" value="<?php echo (int)$logo_id; ?>">
                    <button type="button" class="button" id="hc-email-logo-select">로고 선택</button>
                    <button type="button" class="button" id="hc-email-logo-remove" <?php if(!$logo_id) echo 'style="display:none"'; ?>>제거</button>
                    <div id="hc-email-logo-preview" style="margin-top:8px;"><?php if ($logo_url) echo '<img style="max-width:200px;height:auto" src="'.esc_url($logo_url).'">'; ?></div>
                  </td>
                </tr>
                <tr>
                  <th scope="row">브랜드 컬러</th>
                  <td><input type="text" name="brand_color" value="<?php echo esc_attr($brand['brand_color']); ?>" class="regular-text" placeholder="#111827"></td>
                </tr>
                <tr>
                  <th scope="row">제목 접두사</th>
                  <td><input type="text" name="title_prefix" value="<?php echo esc_attr($brand['title_prefix']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                  <th scope="row">푸터</th>
                  <td><textarea name="footer_text" rows="4" class="large-text"><?php echo esc_textarea($brand['footer_text']); ?></textarea></td>
                </tr>
              </table>
              <p><button class="button button-primary" type="submit">저장</button></p>
            </form>
          </div>

          <!-- Templates -->
          <div class="hc-tab-panel" id="templates" style="display:none">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <?php wp_nonce_field('hc_emails_save', 'hc_emails_nonce'); ?>
              <input type="hidden" name="action" value="hc_emails_save">
              <input type="hidden" name="section" value="templates">

              <?php
              $defs = [
                'order_created_customer' => '주문 생성 – 고객',
                'order_created_admin'    => '주문 생성 – 관리자',
                'payment_paid_customer'  => '결제 완료 – 고객',
                'payment_paid_admin'     => '결제 완료 – 관리자',
                'shipping_started_customer' => '배송 시작 – 고객',
                'shipping_started_admin'    => '배송 시작 – 관리자',
                'order_cancelled_customer'  => '주문 취소 – 고객',
                'order_cancelled_admin'     => '주문 취소 – 관리자',
                'order_refunded_customer'   => '환불 처리 – 고객',
                'order_refunded_admin'      => '환불 처리 – 관리자',
              ];
              ?>
              <p class="description">사용 가능 토큰: <code>{{store.name}}</code>, <code>{{order.number}}</code>, <code>{{items.table}}</code>, <code>{{totals.grand}}</code>, <code>{{currency}}</code>, <code>{{customer.name}}</code>, <code>{{order.url}}</code> 등</p>
              <hr>

              <?php foreach ($defs as $slug => $label): ?>
                <h3><?php echo esc_html($label); ?></h3>
                <table class="form-table">
                  <tr>
                    <th scope="row">사용</th>
                    <td><label><input type="checkbox" name="enabled[<?php echo esc_attr($slug); ?>]" value="1" <?php checked((int)($events[$slug] ?? 1), 1); ?>> 활성화</label></td>
                  </tr>
                  <tr>
                    <th scope="row">Subject</th>
                    <td><input type="text" name="tpl[<?php echo esc_attr($slug); ?>][subject]" class="regular-text" value="<?php echo esc_attr($tpl[$slug]['subject'] ?? ''); ?>"></td>
                  </tr>
                  <tr>
                    <th scope="row">Body (HTML)</th>
                    <td><textarea name="tpl[<?php echo esc_attr($slug); ?>][body]" rows="8" class="large-text code"><?php echo esc_textarea($tpl[$slug]['body'] ?? ''); ?></textarea></td>
                  </tr>
                </table>
                <hr>
              <?php endforeach; ?>

              <p><button class="button button-primary" type="submit">저장</button></p>
            </form>
          </div>

          <!-- Preview / Test -->
          <div class="hc-tab-panel" id="preview" style="display:none">
            <p>아래에서 주문번호 기준으로 템플릿을 미리보기/테스트 발송할 수 있습니다.</p>
            <table class="form-table">
              <tr>
                <th scope="row">주문 ID</th>
                <td><input type="number" id="hc-email-order-id" min="1" class="small-text"> <span class="description">예: 123</span></td>
              </tr>
              <tr>
                <th scope="row">템플릿</th>
                <td>
                  <select id="hc-email-template">
                    <option value="order_created_customer">주문 생성 – 고객</option>
                    <option value="order_created_admin">주문 생성 – 관리자</option>
                    <option value="payment_paid_customer">결제 완료 – 고객</option>
                    <option value="payment_paid_admin">결제 완료 – 관리자</option>
                    <option value="shipping_started_customer">배송 시작 – 고객</option>
                    <option value="shipping_started_admin">배송 시작 – 관리자</option>
                    <option value="order_cancelled_customer">주문 취소 – 고객</option>
                    <option value="order_cancelled_admin">주문 취소 – 관리자</option>
                    <option value="order_refunded_customer">환불 처리 – 고객</option>
                    <option value="order_refunded_admin">환불 처리 – 관리자</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row">테스트 수신 이메일</th>
                <td><input type="email" id="hc-email-test-to" class="regular-text" placeholder="you@example.com"></td>
              </tr>
            </table>
            <p>
              <button class="button" id="hc-email-preview-btn">미리보기</button>
              <button class="button button-primary" id="hc-email-test-btn">테스트 발송</button>
              <span id="hc-email-msg" style="margin-left:8px;color:#555"></span>
            </p>
            <div id="hc-email-preview" style="display:none;border:1px solid #ddd;background:#fff;margin-top:12px;padding:0;max-width:900px">
              <iframe style="width:100%;height:520px;border:0"></iframe>
            </div>
          </div>
        </div>
        <?php
    }

    // ===== 저장 =====
    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) wp_die('No permission');
        if (!isset($_POST['hc_emails_nonce']) || !wp_verify_nonce($_POST['hc_emails_nonce'], 'hc_emails_save')) wp_die('Invalid nonce');

        $section = sanitize_key($_POST['section'] ?? '');
        $s = $this->get();
        $s = $this->mergeDefaults($s); // ensure keys exist

        if ($section === 'brand') {
            $s['email_brand'] = [
                'from_name'    => sanitize_text_field($_POST['from_name'] ?? $s['email_brand']['from_name']),
                'from_email'   => sanitize_email($_POST['from_email'] ?? $s['email_brand']['from_email']),
                'logo_id'      => (int)($_POST['logo_id'] ?? 0),
                'brand_color'  => sanitize_hex_color($_POST['brand_color'] ?? $s['email_brand']['brand_color']) ?: '#111827',
                'title_prefix' => sanitize_text_field($_POST['title_prefix'] ?? $s['email_brand']['title_prefix']),
                'footer_text'  => wp_kses_post($_POST['footer_text'] ?? $s['email_brand']['footer_text']),
            ];
            $this->savePatch($s);
            wp_redirect(admin_url('admin.php?page=hc-settings-emails#brand&updated=1'));
            exit;
        }

        if ($section === 'templates') {
            $tplIn = (array)($_POST['tpl'] ?? []);
            foreach ($tplIn as $slug => $pair) {
                $slug = sanitize_key($slug);
                $subject = sanitize_text_field($pair['subject'] ?? '');
                $body    = (string)($pair['body'] ?? '');
                $s['email_templates'][$slug] = ['subject' => $subject, 'body' => $body];
            }
            $enabled = (array)($_POST['enabled'] ?? []);
            foreach ($s['email_events'] as $slug => $flag) {
                $s['email_events'][$slug] = isset($enabled[$slug]) ? 1 : 0;
            }
            $this->savePatch($s);
            wp_redirect(admin_url('admin.php?page=hc-settings-emails#templates&updated=1'));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=hc-settings-emails'));
        exit;
    }

    // ===== AJAX: 미리보기 =====
    public function ajaxPreview(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'no permission'], 403);
        check_ajax_referer('hc_emails', 'nonce');

        $orderId = (int)($_POST['order_id'] ?? 0);
        $slug    = sanitize_key($_POST['template'] ?? '');
        if ($orderId <= 0 || !$slug) wp_send_json_error(['message'=>'invalid params'], 400);

        $m = new Mailer();
        [$subject, $html] = $m->render($slug, $orderId);
        if (!$html) wp_send_json_error(['message'=>'render failed'], 500);

        wp_send_json_success(['subject'=>$subject, 'html'=>$html]);
    }

    // ===== AJAX: 테스트 발송 =====
    public function ajaxTest(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'no permission'], 403);
        check_ajax_referer('hc_emails', 'nonce');

        $orderId = (int)($_POST['order_id'] ?? 0);
        $slug    = sanitize_key($_POST['template'] ?? '');
        $to      = sanitize_email($_POST['to'] ?? '');
        if ($orderId <= 0 || !$slug || !$to) wp_send_json_error(['message'=>'invalid params'], 400);

        $m = new Mailer();
        [$subject, $html] = $m->render($slug, $orderId);
        if (!$html) wp_send_json_error(['message'=>'render failed'], 500);

        $ok = $m->send($to, $subject, $html, $slug);
        if ($ok) wp_send_json_success(['message'=>'sent']);
        else wp_send_json_error(['message'=>'send failed'], 500);
    }
}
