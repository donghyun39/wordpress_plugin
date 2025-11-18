<?php
namespace HC\Shop\Api;

use WP_REST_Request;

final class AuthController
{
    public function register_routes(): void
    {
        register_rest_route('hc/v1', '/auth/(?P<provider>kakao|naver|google)/start', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'start'],
        ]);
        register_rest_route('hc/v1', '/auth/(?P<provider>kakao|naver|google)/callback', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'callback'],
        ]);
    }

    private function settings(): array
    {
        return get_option('hc_shop_settings', []) ?: [];
    }

    public function start(WP_REST_Request $req)
    {
        $p = $req['provider'];
        $s = $this->settings();
        if (empty($s['social_enabled']) || empty($s["{$p}_enabled"])) {
            return $this->redirect_with_error('소셜 로그인이 비활성화되었습니다.');
        }

        $redirect = wp_validate_redirect($req->get_param('redirect'), home_url('/'));
        $state = bin2hex(random_bytes(12));
        set_transient('hc_oauth_' . $state, ['redirect' => $redirect, 'ts' => time()], 10 * MINUTE_IN_SECONDS);

        $cb = rest_url("hc/v1/auth/$p/callback");
        if ($p === 'kakao') {
            $client_id = $s['kakao_client_id'] ?? '';
            $url = 'https://kauth.kakao.com/oauth/authorize?response_type=code'
                . '&client_id=' . rawurlencode($client_id)
                . '&redirect_uri=' . rawurlencode($cb)
                . '&state=' . rawurlencode($state);
        } elseif ($p === 'naver') {
            $client_id = $s['naver_client_id'] ?? '';
            $url = 'https://nid.naver.com/oauth2.0/authorize?response_type=code'
                . '&client_id=' . rawurlencode($client_id)
                . '&redirect_uri=' . rawurlencode($cb)
                . '&state=' . rawurlencode($state);
        } else { // google
            $client_id = $s['google_client_id'] ?? '';
            $scope = rawurlencode('openid email profile');
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code'
                . '&client_id=' . rawurlencode($client_id)
                . '&redirect_uri=' . rawurlencode($cb)
                . '&scope=' . $scope
                . '&access_type=online&prompt=consent'
                . '&state=' . rawurlencode($state);
        }
        wp_redirect($url);
        exit;
    }

    public function callback(WP_REST_Request $req)
    {
        $p = $req['provider'];
        $code = sanitize_text_field($req->get_param('code') ?? '');
        $state = sanitize_text_field($req->get_param('state') ?? '');
        $saved = get_transient('hc_oauth_' . $state);
        delete_transient('hc_oauth_' . $state);
        $redirect = is_array($saved) ? ($saved['redirect'] ?? home_url('/')) : home_url('/');
        $redirect = wp_validate_redirect($redirect, home_url('/'));

        if (!$code || !$state || empty($saved)) {
            return $this->redirect_with_error('인증요청이 만료되었거나 유효하지 않습니다.', $redirect);
        }

        $s = $this->settings();
        $cb = rest_url("hc/v1/auth/$p/callback");

        // 1) 토큰 교환
        if ($p === 'kakao') {
            $resp = wp_remote_post('https://kauth.kakao.com/oauth/token', [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $s['kakao_client_id'] ?? '',
                    'client_secret' => $s['kakao_client_secret'] ?? '',
                    'redirect_uri' => $cb,
                    'code' => $code,
                ],
                'timeout' => 15,
            ]);
        } elseif ($p === 'naver') {
            $params = [
                'grant_type' => 'authorization_code',
                'client_id' => $s['naver_client_id'] ?? '',
                'client_secret' => $s['naver_client_secret'] ?? '',
                'code' => $code,
                'state' => $state,
            ];
            $resp = wp_remote_get('https://nid.naver.com/oauth2.0/token?' . http_build_query($params), ['timeout' => 15]);
        } else {
            $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => $s['google_client_id'] ?? '',
                    'client_secret' => $s['google_client_secret'] ?? '',
                    'redirect_uri' => $cb,
                ],
                'timeout' => 15,
            ]);
        }

        if (is_wp_error($resp))
            return $this->redirect_with_error('토큰 요청 실패', $redirect);
        $token = json_decode(wp_remote_retrieve_body($resp), true) ?: [];
        $access = $token['access_token'] ?? '';

        if (!$access)
            return $this->redirect_with_error('토큰이 없습니다', $redirect);

        // 2) 사용자 정보
        if ($p === 'kakao') {
            $u = $this->get_json('https://kapi.kakao.com/v2/user/me', $access);
            $sid = $u['id'] ?? null;
            $email = $u['kakao_account']['email'] ?? '';
            $name = $u['kakao_account']['profile']['nickname'] ?? 'Kakao User';
        } elseif ($p === 'naver') {
            $u = $this->get_json('https://openapi.naver.com/v1/nid/me', $access);
            $r = $u['response'] ?? [];
            $sid = $r['id'] ?? null;
            $email = $r['email'] ?? '';
            $name = $r['name'] ?? 'Naver User';
        } else {
            $u = $this->get_json('https://openidconnect.googleapis.com/v1/userinfo', $access);
            $sid = $u['sub'] ?? ($u['id'] ?? null);
            $email = $u['email'] ?? '';
            $name = $u['name'] ?? 'Google User';
        }
        if (!$sid)
            return $this->redirect_with_error('프로필을 가져오지 못했습니다', $redirect);

        // 3) WP 사용자 매핑/생성
        $user_id = $this->resolve_user($p, $sid, $email, $name);

        // 4) 로그인 후 리다이렉트
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        wp_redirect($redirect ?: home_url('/'));
        exit;
    }

    private function get_json(string $url, string $access): array
    {
        $r = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer {$access}"], 'timeout' => 15]);
        if (is_wp_error($r))
            return [];
        return json_decode(wp_remote_retrieve_body($r), true) ?: [];
    }

    private function resolve_user(string $provider, string $sid, string $email, string $name): int
    {
        $meta_key = 'hc_social_' . $provider . '_id';

        // 1) provider id 매칭
        $q = new \WP_User_Query([
            'meta_key' => $meta_key,
            'meta_value' => $sid,
            'number' => 1,
            'fields' => 'ID',
        ]);
        if (!empty($q->results))
            return (int) $q->results[0];

        // 2) 이메일로 매칭
        if ($email) {
            $user = get_user_by('email', $email);
            if ($user) {
                update_user_meta($user->ID, $meta_key, $sid);
                return (int) $user->ID;
            }
        }

        // 3) 신규 생성
        $login_base = $provider . '_' . substr($sid, 0, 12);
        $login = $login_base;
        $i = 1;
        while (username_exists($login)) {
            $login = $login_base . $i;
            $i++;
        }
        $pwd = wp_generate_password(20, true, true);
        $uid = wp_insert_user([
            'user_login' => $login,
            'user_pass' => $pwd,
            'user_email' => $email ?: $login . '@example.com',
            'display_name' => $name ?: $login,
            'role' => 'subscriber',
        ]);
        if (is_wp_error($uid))
            wp_die($uid);
        update_user_meta($uid, $meta_key, $sid);
        return (int) $uid;
    }

    private function redirect_with_error(string $msg, string $to = '')
    {
        $to = $to ?: (get_permalink(get_page_by_path('account')) ?: home_url('/'));
        $to = add_query_arg(['hc_oauth_error' => rawurlencode($msg)], $to);
        wp_redirect($to);
        exit;
    }
    public function handleRegisterPost(): void
    {
        $back = get_permalink(get_page_by_path('account')) ?: home_url('/');
        $fail = function ($m) use ($back) {
            wp_redirect(add_query_arg('err', rawurlencode($m), $back));
            exit; };

        if (!isset($_POST['hc_reg_nonce']) || !wp_verify_nonce($_POST['hc_reg_nonce'], 'hc_register')) {
            $fail('요청이 유효하지 않습니다.');
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $pass = (string) ($_POST['pass'] ?? '');

        if (!$name || !$email || !$pass)
            $fail('필수 항목을 입력하세요.');
        if (!is_email($email))
            $fail('올바른 이메일이 아닙니다.');
        if (email_exists($email))
            $fail('이미 가입된 이메일입니다.');
        if (username_exists($email))
            $fail('이미 사용 중인 아이디입니다.');

        // 아이디는 이메일 그대로 사용(원하면 규칙 변경 가능)
        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_pass' => $pass,
            'user_email' => $email,
            'display_name' => $name,
            'role' => 'subscriber',
        ]);
        if (is_wp_error($user_id))
            $fail($user_id->get_error_message());

        // 자동 로그인
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_redirect(add_query_arg('reg', '1', $back));
        exit;
    }

}
