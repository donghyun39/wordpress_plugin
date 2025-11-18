<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// 옵션 제거
delete_option('hc_shop_env');

// 테이블 삭제
$table = $wpdb->prefix . 'hc_products';
// 안전을 위해 존재할 때만 드롭
$wpdb->query("DROP TABLE IF EXISTS {$table}");
