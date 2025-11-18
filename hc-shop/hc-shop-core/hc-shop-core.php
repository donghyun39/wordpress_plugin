<?php
/**
 * Plugin Name: HC Shop Core
 * Description: Minimal e-commerce core (DB + Admin Form, scoped CSS)
 * Version: 0.2.0
 * Author: HyCoding
 * Text Domain: hc-shop-core
 * License: GPL2+
 */

if (!defined('ABSPATH')) exit;

// Composer autoload
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}

// Boot
add_action('plugins_loaded', function(){
  \HC\Shop\Plugin::boot(__FILE__);
});

// Activation (pages + DB table create)
register_activation_hook(__FILE__, ['\\HC\\Shop\\Install\\Activator', 'run']);
