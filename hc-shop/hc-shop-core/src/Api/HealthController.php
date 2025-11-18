<?php
namespace HC\Shop\Api;

use WP_REST_Request; use WP_REST_Response;

final class HealthController
{
    public function register_routes(): void
    {
        register_rest_route('hc/v1', '/health', [
            'methods' => 'GET', 'permission_callback' => '__return_true',
            'callback' => [$this, 'get']
        ]);
    }

    public function get(WP_REST_Request $req): WP_REST_Response
    {
        $env = get_option('hc_shop_env', 'sandbox');
        return new WP_REST_Response([
            'ok' => true,
            'version' => \HC\Shop\Plugin::VERSION,
            'env' => $env,
            'ts' => time()
        ]);
    }
}
