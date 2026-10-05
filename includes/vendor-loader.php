<?php
defined( 'ABSPATH' ) || exit;
spl_autoload_register( static function( $class ) {
    $prefix = 'JPD_Order_Documents_Vendor\\';
    if ( 0 !== strpos( $class, $prefix ) ) { return; }
    $name = substr( $class, strlen( $prefix ) );
    $roots = array(
        'Dompdf' => 'dompdf/dompdf/src', 'FontLib' => 'dompdf/php-font-lib/src/FontLib',
        'Svg' => 'dompdf/php-svg-lib/src/Svg', 'Masterminds' => 'masterminds/html5/src',
        'Sabberworm\\CSS' => 'sabberworm/php-css-parser/src',
        'setasign\\Fpdi' => '../fpdi/src',
    );
    $base = dirname( __DIR__ ) . '/lib/vendor/';
    if ( 'FPDF' === $name ) { require_once $base . '../fpdf/fpdf.php'; return; }
    if ( 'Dompdf\\Cpdf' === $name ) { require_once $base . 'dompdf/dompdf/lib/Cpdf.php'; return; }
    foreach ( $roots as $root => $dir ) {
        if ( 0 !== strpos( $name, $root . '\\' ) ) { continue; }
        $file = $base . $dir . '/' . str_replace( '\\', '/', substr( $name, strlen( $root ) + 1 ) ) . '.php';
        if ( is_file( $file ) ) { require_once $file; }
        return;
    }
} );
