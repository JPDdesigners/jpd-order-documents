<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
require_once __DIR__ . '/includes/class-jobs.php';
try { JPD_OD_Jobs::cleanup( true ); @rmdir( JPD_OD_Jobs::root() ); } catch ( Throwable $e ) { /* Never delete outside the verified private temp directory. */ }
delete_option( 'woocommerce_jpd_order_documents_settings' );
