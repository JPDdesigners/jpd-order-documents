<?php
/**
 * Plugin Name: JPD Order Documents Email
 * Description: Manual WooCommerce order document emails for showroom staff, including verified User Switching sessions. No scheduler or permanent document archive.
 * Version: 1.0.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: JPD
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
define( 'JPD_OD_FILE', __FILE__ );
define( 'JPD_OD_DIR', __DIR__ . '/' );
require_once JPD_OD_DIR . 'includes/class-collector.php';
require_once JPD_OD_DIR . 'includes/class-documents.php';
require_once JPD_OD_DIR . 'includes/class-jobs.php';
require_once JPD_OD_DIR . 'includes/class-plugin.php';
// Register before another plugin can initialize WooCommerce's mailer during plugins_loaded.
add_filter( 'woocommerce_email_classes', array( 'JPD_OD_Plugin', 'emails' ) );
add_action( 'before_woocommerce_init', static function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', JPD_OD_FILE, true );
    }
} );
add_action( 'plugins_loaded', array( 'JPD_OD_Plugin', 'init' ), 30 );
