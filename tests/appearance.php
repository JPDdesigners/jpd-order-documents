<?php
require __DIR__ . '/bootstrap.php';
function check_appearance( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } echo "PASS $message\n"; }
$GLOBALS['options']['woocommerce_jpd_order_documents_settings'] = ['matrix_appearance'=>'yes'];
check_appearance( JPD_OD_Plugin::appearance() === '', 'Documents keeps independent defaults without a compatible Matrix' );
if ( true ) {
    class B2B_Matrix_Settings {
        public static function design_tokens() { return $GLOBALS['tokens']; }
    }
}
$GLOBALS['options'] = [];
$GLOBALS['tokens'] = ['accent'=>'#7c3aed','on_accent'=>'#ffffff','focus'=>'#7c3aed','card_radius'=>'0px','control_radius'=>'0px'];
check_appearance( JPD_OD_Plugin::appearance() === '', 'shared styling is off by default even with Matrix installed' );
$GLOBALS['options']['woocommerce_jpd_order_documents_settings'] = ['matrix_appearance'=>'yes'];
$style=JPD_OD_Plugin::appearance();
check_appearance( strpos($style,'--jpd-accent:#7c3aed;')!==false && strpos($style,'--jpd-card-radius:0px;')!==false, 'explicit opt-in applies only small design tokens' );
$GLOBALS['tokens']['accent']='red; background:url(https://bad.test)';
$GLOBALS['tokens']['control_radius']=['0px'];
$style=JPD_OD_Plugin::appearance();
check_appearance( strpos($style,'url(')===false && strpos($style,'--jpd-accent:')===false && strpos($style,'--jpd-control-radius:')===false, 'invalid CSS and nested token values are rejected' );
$GLOBALS['options']['woocommerce_jpd_order_documents_settings'] = 'malformed';
check_appearance( JPD_OD_Plugin::appearance() === '', 'malformed settings fall back safely' );
