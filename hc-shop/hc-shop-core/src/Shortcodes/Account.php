<?php
namespace HC\Shop\Shortcodes;

final class Account
{
    public function register(): void
    {
        add_shortcode('hc_account', [$this, 'render']);
    }

    public function render(): string
    {
        if (!function_exists('hc_shop_is_page') || !hc_shop_is_page()) return '';

        // 로그인 상태: 간단한 마이페이지 헤더
        if (is_user_logged_in()) {
            $u = wp_get_current_user();
            ob_start(); ?>
              <div class="hc-shop">
                <h2>마이페이지</h2>
                <p><strong><?php echo esc_html($u->display_name ?: $u->user_login); ?></strong>님 환영합니다.</p>
                <p>
                  <a class="hc-btn" href="<?php echo esc_url(wp_lostpassword_url()); ?>">비밀번호 변경</a>
                  <a class="hc-btn" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">로그아웃</a>
                </p>
              </div>
            <?php return (string)ob_get_clean();
        }

        // 비로그인 상태: 로그인 + (선택)소셜 로그인 + 회원가입
        $s = get_option('hc_shop_settings', []);
        $social_on = (int)($s['social_enabled'] ?? 0);

        // 현재 페이지로 돌아올 리디렉션 URL
        $current_url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
        $redirect    = esc_url_raw($current_url);

        ob_start(); ?>
        <div class="hc-shop">
          <h2>로그인</h2>
          <?php wp_login_form(['redirect' => $redirect]); ?>

          <?php if (!empty($_GET['login']) && $_GET['login'] === 'failed'): ?>
            <p style="color:#b00;">로그인에 실패했습니다. 아이디/비밀번호를 확인해 주세요.</p>
          <?php endif; ?>

          <?php if ($social_on): ?>
            <hr>
            <h3>소셜 로그인</h3>
            <p>
              <?php if (!empty($s['kakao_enabled'])): ?>
                <a class="hc-btn" href="<?php echo esc_url( rest_url('hc/v1/auth/kakao/start') . '?redirect=' . rawurlencode($redirect) ); ?>">카카오로 로그인</a>
              <?php endif; ?>
              <?php if (!empty($s['naver_enabled'])): ?>
                <a class="hc-btn" href="<?php echo esc_url( rest_url('hc/v1/auth/naver/start') . '?redirect=' . rawurlencode($redirect) ); ?>">네이버로 로그인</a>
              <?php endif; ?>
              <?php if (!empty($s['google_enabled'])): ?>
                <a class="hc-btn" href="<?php echo esc_url( rest_url('hc/v1/auth/google/start') . '?redirect=' . rawurlencode($redirect) ); ?>">Google로 로그인</a>
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <?php if (!empty($_GET['hc_oauth_error'])): ?>
            <p style="color:#b00;"><?php echo esc_html( wp_unslash($_GET['hc_oauth_error']) ); ?></p>
          <?php endif; ?>

          <hr>
          <h3>회원가입</h3>
          <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="hc-form">
            <?php wp_nonce_field('hc_register','hc_reg_nonce'); ?>
            <input type="hidden" name="action" value="hc_register">

            <p>
              <label>이름<br>
                <input type="text" name="name" required>
              </label>
            </p>
            <p>
              <label>이메일(아이디)<br>
                <input type="email" name="email" required>
              </label>
            </p>
            <p>
              <label>비밀번호<br>
                <input type="password" name="pass" required minlength="6">
              </label>
            </p>

            <p>
              <button class="hc-btn hc-solid" type="submit">가입하기</button>
            </p>
          </form>

          <?php if (!empty($_GET['reg'])): ?>
            <p style="color:#118000">가입이 완료되었습니다.</p>
          <?php endif; ?>
          <?php if (!empty($_GET['err'])): ?>
            <p style="color:#b00;"><?php echo esc_html( wp_unslash($_GET['err']) ); ?></p>
          <?php endif; ?>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
