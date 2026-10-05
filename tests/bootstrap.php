<?php
// Isolated WordPress/WooCommerce boundary doubles. Never sends network requests or real email.
define( 'ABSPATH', dirname( __DIR__ ) . '/artifacts/wordpress-fixture/' );
if ( ! is_dir( ABSPATH ) && ! mkdir( ABSPATH, 0700, true ) ) { throw new RuntimeException( 'Cannot create mock website directory.' ); }
define( 'JPD_OD_DIR', dirname( __DIR__ ) . '/' );
define( 'JPD_OD_FILE', JPD_OD_DIR . 'jpd-order-documents.php' );
$GLOBALS['uid'] = 12; $GLOBALS['staff'] = false; $GLOBALS['session'] = 'tablet-session';
$GLOBALS['blog'] = 1;
$GLOBALS['limit'] = 20 * 1024 * 1024; $GLOBALS['orders'] = array(); $GLOBALS['products'] = array(); $GLOBALS['calls'] = array();
function get_current_user_id() { return $GLOBALS['uid']; }
function get_current_blog_id() { return $GLOBALS['blog']; }
function current_user_can( $cap ) { return $GLOBALS['staff'] && 'manage_woocommerce' === $cap; }
function user_can( $user, $cap ) { return $user->staff && 'manage_woocommerce' === $cap; }
function wp_salt( $scheme ) { return 'isolated-test-salt'; }
function wp_get_session_token() { return $GLOBALS['session']; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function is_email( $v ) { return filter_var( $v, FILTER_VALIDATE_EMAIL ) !== false; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_url( $v ) { return esc_html( $v ); }
function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
function wp_kses_post( $v ) { return $v; }
function wpautop( $v ) { return '<p>' . $v . '</p>'; }
function get_bloginfo( $v ) { return 'JPD · Δειγματισμός'; }
function wc_get_price_decimals() { return 2; }
function wc_format_datetime( $date ) { return '02/10/2026'; }
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? false; }
function get_the_terms( $id, $tax ) { return false; }
function taxonomy_exists( $tax ) { return false; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function home_url( $path ) { return 'http://127.0.0.1:8093' . $path; }
function admin_url( $path ) { return home_url( '/' . $path ); }
function wc_get_page_permalink( $page ) { return home_url( '/my-account/' ); }
function wc_get_endpoint_url( $endpoint, $value, $url ) { return $url . $endpoint . '/'; }
function plugins_url( $path, $file ) { return home_url( '/plugin/' . $path ); }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function wp_verify_nonce( $value, $action ) { return $value === wp_create_nonce( $action ); }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=' . wp_create_nonce( $action ); }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
function nocache_headers() {}
function add_action( ...$args ) { $GLOBALS['calls'][] = array( 'action', $args ); }
function add_filter( ...$args ) { $GLOBALS['calls'][] = array( 'filter', $args ); }
function do_action( ...$args ) { $GLOBALS['calls'][] = array( 'do', $args ); }
function apply_filters( $name, $value, ...$args ) {
    if ( 'jpd_od_max_attachment_bytes' === $name ) { return $GLOBALS['limit']; }
    if ( 'jpd_b2b_export_include_item' === $name && isset( $GLOBALS['exclude'] ) ) { return $args[0]->id !== $GLOBALS['exclude']; }
    return $value;
}
function wp_send_json_error( $data, $status = 200 ) { if ( defined('JPD_OD_FIXTURE_HTTP') ) { http_response_code($status); header('Content-Type: application/json'); echo json_encode(array('success'=>false,'data'=>$data)); exit; } throw new JsonReply( false, $data, $status ); }
function wp_send_json_success( $data ) { if ( defined('JPD_OD_FIXTURE_HTTP') ) { header('Content-Type: application/json'); echo json_encode(array('success'=>true,'data'=>$data)); exit; } throw new JsonReply( true, $data, 200 ); }
class MockLogger {function error(...$args){}}
function wc_get_logger(){return new MockLogger();}
class JsonReply extends Exception { public $success, $data, $status; public function __construct( $success, $data, $status ) { $this->success = $success; $this->data = $data; $this->status = $status; } }
class WP_User { public $ID, $staff; function __construct( $id, $staff = true ) { $this->ID = $id; $this->staff = $staff; } }
class user_switching { public static $old, $auth = false; static function get_old_user() { return self::$old; } static function authenticate_old_user( $u ) { return self::$auth; } }
class WooCommerce {}
class WC_Product {
    public $id, $price, $parent, $sku, $name, $attrs;
    function __construct( $id, $price = '12.50', $parent = 100, $sku = '' ) { $this->id=$id; $this->price=$price; $this->parent=$parent; $this->sku=$sku ?: (string)$id; $this->name='Πουκάμισο καλοκαιρινό'; $this->attrs=array('pa_color'=>'Μπλε','pa_size'=>'M'); }
    function get_id(){return $this->id;} function get_price(){return $this->price;} function get_parent_id(){return $this->parent;} function get_sku($context='view'){return $this->sku;} function get_name(){return $this->name;} function get_image_id(){return 0;} function get_attributes(){return array();} function get_attribute($name){return $this->attrs[$name]??'';}
}
class WC_Order_Item_Product {
    public $id, $pid, $vid, $qty, $total;
    function __construct($id=1,$pid=100,$vid=101,$qty=3,$total=30){$this->id=$id;$this->pid=$pid;$this->vid=$vid;$this->qty=$qty;$this->total=$total;}
    function get_product(){return wc_get_product($this->vid ?: $this->pid);} function get_product_id(){return $this->pid;} function get_variation_id(){return $this->vid;} function get_quantity(){return $this->qty;} function get_total(){return $this->total;} function get_name(){return 'Προϊόν παραγγελίας';} function get_meta($k,$s=true){return $k==='pa_size'?'M':($k==='pa_color'?'Μπλε':'');}
}
class MockDate {function date_i18n($v){return '02/10/2026';} function getTimestamp(){return 1790935200;}}
class WC_Order {
    public $id=300,$customer=12,$email='customer@example.test',$status='processing',$items,$notes=array();
    function __construct(){ $this->items=array(1=>new WC_Order_Item_Product()); }
    function get_id(){return $this->id;} function get_customer_id(){return $this->customer;} function get_billing_email(){return $this->email;} function get_status(){return $this->status;} function get_items($type='line_item'){return $this->items;} function get_order_number(){return '300';} function get_meta($key){return $key==='_jpd_season'?'SS27':'';} function get_currency(){return 'EUR';} function get_date_created(){return new MockDate();} function get_date_modified(){return new MockDate();} function get_billing_last_name(){return 'Επωνυμία Εταιρείας';} function get_billing_first_name(){return '123456789';} function get_customer_note(){return 'Παράδοση κατόπιν συνεννόησης.';} function get_total(){return 30;} function add_order_note($note){$this->notes[]=$note;}
}
class WC_Email {
    public $id,$title,$description,$customer_email,$manual,$placeholders,$email_type,$object,$recipient,$form_fields,$enabled='yes';
    public static $count=0,$result=true,$captured=array(),$throw=false;
    function __construct(){$this->init_form_fields();} function is_enabled(){return $this->enabled==='yes';} function setup_locale(){} function restore_locale(){} function get_recipient(){return $this->recipient;} function get_subject(){return $this->get_default_subject();} function get_heading(){return $this->get_default_heading();} function get_additional_content(){return '';} function get_content(){return $this->get_content_html();} function get_headers(){return 'Content-Type: text/html';}
    function send($to,$subject,$body,$headers,$attachments){self::$count++;self::$captured=compact('to','subject','body','headers','attachments');foreach($attachments as $path){if(!is_file($path)){throw new RuntimeException('Missing attachment');}} if(self::$throw){throw new Exception('SMTP uncertain');}return self::$result;}
}
class MockMailer {public $emails;function get_emails(){return $this->emails;}}
class MockWC {public $mail;function mailer(){return $this->mail;}}
$GLOBALS['wc']=new MockWC();$GLOBALS['wc']->mail=new MockMailer();
function WC(){return $GLOBALS['wc'];}
require JPD_OD_DIR.'includes/class-collector.php';
require JPD_OD_DIR.'includes/class-documents.php';
require JPD_OD_DIR.'includes/class-jobs.php';
require JPD_OD_DIR.'includes/class-plugin.php';
require JPD_OD_DIR.'includes/class-email.php';
$GLOBALS['wc']->mail->emails=array('JPD_OD_Email'=>new JPD_OD_Email());
$GLOBALS['products'][100]=new WC_Product(100,'',0,'2413851');
$GLOBALS['products'][101]=new WC_Product(101,'12.50',100,'2413851-M');
$GLOBALS['orders'][300]=new WC_Order();
