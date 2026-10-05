<?php
// Local browser fixture. All WordPress/WooCommerce services are doubles; emails never leave this process.
define('JPD_OD_FIXTURE_HTTP', true);
require __DIR__.'/bootstrap.php';
user_switching::$old=new WP_User(7);user_switching::$auth=true;
$route=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($route==='/plugin/assets/documents.css'||$route==='/plugin/assets/documents.js'){
    header('Content-Type: '.(substr($route,-3)==='.js'?'text/javascript':'text/css'));
    readfile(JPD_OD_DIR.'assets/'.basename($route));exit;
}
if($route==='/admin-ajax.php'){JPD_OD_Plugin::ajax();exit;}
if(isset($_GET['jpd_send_documents'])){JPD_OD_Plugin::page();exit;}
echo '<a href="'.esc_url(JPD_OD_Plugin::url($GLOBALS['orders'][300])).'">Αποστολή εγγράφων</a>';
