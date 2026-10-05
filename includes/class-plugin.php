<?php
defined( 'ABSPATH' ) || exit;
class JPD_OD_Plugin {
    public static function init() {
        // Hide the floating return link site-wide; User Switching's My Account entry remains available.
        add_filter( 'user_switching_in_footer', '__return_false' );
        if ( ! class_exists( 'WooCommerce' ) ) { return; }
        add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'account_action' ), 110, 2 );
        add_action( 'woocommerce_admin_order_actions_end', array( __CLASS__, 'admin_action' ) );
        add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'admin_detail_action' ) );
        add_action( 'admin_head', array( __CLASS__, 'admin_css' ) );
        add_action( 'template_redirect', array( __CLASS__, 'page' ), 0 );
        add_action( 'wp_ajax_jpd_order_documents', array( __CLASS__, 'ajax' ) );
    }
    public static function emails( $emails ) {
        require_once __DIR__ . '/class-email.php';
        $emails['JPD_OD_Email'] = new JPD_OD_Email();
        return $emails;
    }
    public static function actor() {
        if ( ! get_current_user_id() ) { return 0; }
        if ( current_user_can( 'manage_woocommerce' ) ) { return get_current_user_id(); }
        if ( is_callable( array( 'user_switching', 'get_old_user' ) ) && is_callable( array( 'user_switching', 'authenticate_old_user' ) ) ) {
            $old = user_switching::get_old_user();
            if ( $old instanceof WP_User && user_switching::authenticate_old_user( $old ) && user_can( $old, 'manage_woocommerce' ) ) { return (int) $old->ID; }
        }
        return 0;
    }
    public static function allowed( $order ) {
        return $order instanceof WC_Order && self::actor() && ( current_user_can( 'manage_woocommerce' ) || (int) $order->get_customer_id() === get_current_user_id() );
    }
    public static function url( $order ) {
        return wp_nonce_url( add_query_arg( 'jpd_send_documents', $order->get_id(), home_url( '/' ) ), 'jpd_od_page_' . $order->get_id() );
    }
    public static function account_action( $actions, $order ) {
        if ( self::allowed( $order ) ) { $actions['jpd_send_documents'] = array( 'url' => self::url( $order ), 'name' => 'Αποστολή εγγράφων' ); }
        return $actions;
    }
    public static function admin_action( $order ) {
        if ( self::allowed( $order ) ) { echo '<a class="button wc-action-button wc-action-button-jpd-documents" href="' . esc_url( self::url( $order ) ) . '" target="_blank" rel="noopener" title="Αποστολή εγγράφων" aria-label="Αποστολή εγγράφων">Αποστολή εγγράφων</a>'; }
    }
    public static function admin_detail_action( $order ) {
        if ( self::allowed( $order ) ) { echo '<a class="button" href="' . esc_url( self::url( $order ) ) . '" target="_blank" rel="noopener">Αποστολή εγγράφων</a>'; }
    }
    public static function admin_css() {
        echo '<style>.wc-action-button-jpd-documents::after{content:"\\2709"!important;font-family:Arial,sans-serif!important;font-size:17px!important}</style>';
    }
    public static function scalar( $source, $key ) {
        return isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? (string) wp_unslash( $source[ $key ] ) : '';
    }
    public static function page() {
        if ( ! isset( $_GET['jpd_send_documents'] ) ) { return; }
        nocache_headers(); do_action( 'litespeed_control_set_nocache' );
        header( 'X-Robots-Tag: noindex, nofollow', true ); header( 'Referrer-Policy: same-origin', true );
        $id = absint( self::scalar( $_GET, 'jpd_send_documents' ) );
        $order = $id ? wc_get_order( $id ) : false;
        if ( ! self::allowed( $order ) || ! wp_verify_nonce( self::scalar( $_GET, '_wpnonce' ), 'jpd_od_page_' . $id ) ) {
            wp_die( 'Δεν έχετε δικαίωμα αποστολής εγγράφων ή ο σύνδεσμος έχει λήξει. Ανοίξτε ξανά τη σελίδα από τις παραγγελίες.', 'Αποστολή εγγράφων', array( 'response' => 403 ) );
        }
        try { JPD_OD_Jobs::cleanup(); } catch ( Throwable $e ) { /* Show a usable page; creation reports the error. */ }
        $config = array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'jpd_od_' . $id ), 'order' => $id, 'key' => 'jpd-od-' . get_current_blog_id() . '-' . get_current_user_id() . '-' . self::actor() . '-' . $id );
        require __DIR__ . '/page.php'; exit;
    }
    public static function ajax() {
        nocache_headers(); do_action( 'litespeed_control_set_nocache' );
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_send_json_error( array( 'message' => 'Μη έγκυρο αίτημα.' ), 405 ); }
        $id = absint( self::scalar( $_POST, 'order' ) );
        $order = $id ? wc_get_order( $id ) : false;
        if ( ! self::allowed( $order ) || ! wp_verify_nonce( self::scalar( $_POST, 'nonce' ), 'jpd_od_' . $id ) ) {
            wp_send_json_error( array( 'message' => 'Η πρόσβαση έληξε ή ο λογαριασμός άλλαξε. Ανοίξτε ξανά τη σελίδα από τις παραγγελίες.' ), 403 );
        }
        $command = self::scalar( $_POST, 'command' );
        if ( ! in_array( $command, array( 'start', 'status', 'generate', 'send', 'cancel' ), true ) ) { wp_send_json_error( array( 'message' => 'Μη έγκυρο βήμα.' ), 400 ); }
        // The current request can finish if a tablet disconnects; subsequent steps still need the page.
        ignore_user_abort( true );
        try {
            if ( 'start' === $command ) {
                $raw = self::scalar( $_POST, 'formats' ); $formats = json_decode( $raw, true );
                $result = JPD_OD_Jobs::start( $order, self::actor(), $formats );
            } else { $result = JPD_OD_Jobs::run( self::scalar( $_POST, 'token' ), $order, self::actor(), $command, self::scalar( $_POST, 'format' ) ); }
            wp_send_json_success( $result );
        } catch ( Throwable $e ) {
            if ( ! $e instanceof RuntimeException ) { wc_get_logger()->error( 'Document operation failed: ' . get_class( $e ), array( 'source' => 'jpd-order-documents' ) ); }
            wp_send_json_error( array( 'code' => $e->getCode(), 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Η δημιουργία εγγράφων απέτυχε. Ελέγξτε την καταγραφή JPD Order Documents στο WooCommerce.' ), 400 );
        }
    }
}
