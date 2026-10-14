<?php
require __DIR__ . '/bootstrap.php';
$checks = 0;
function check( $condition, $message ) { global $checks; if ( ! $condition ) { throw new RuntimeException( 'FAILED: ' . $message ); } ++$checks; }
$GLOBALS['uploads'] = array( 'baseurl' => home_url( '/wp-content/uploads' ), 'basedir' => __DIR__ . '/fixtures/uploads', 'error' => false );
$order = $GLOBALS['orders'][300]; $data = JPD_OD_Documents::collect( $order );
$html = JPD_OD_Documents::html( $order, $data );
check( false !== strpos( $html, 'data:image/svg+xml;base64,' ), 'local snippet logo embedded without HTTP' );
check( false !== strpos( $html, 'ΔΕΛΤΙΟ ΠΑΡΑΓΓΕΛΙΑΣ' ) && false !== strpos( $html, 'ΠΕΡΙΓΡΑΦΗ / SKU' ), 'compact export structure' );
check( false !== strpos( $html, 'sb-size' ) && false !== strpos( $html, 'sb-qty' ), 'separate size and quantity badges' );
check( false !== strpos( $html, '37,50 €' ), 'WooCommerce currency formatting' );
foreach ( array( 'Τρέχουσ', 'τρέχουσ', 'Αποθηκευμέν', 'αποθηκευμέν', 'συνεχίζεται', 'Συνέχεια', 'Γραμμές με παρατηρήσεις', 'φόροι', 'κουπόνια' ) as $text ) {
    check( false === strpos( $html, $text ), 'no technical customer text: ' . $text );
}
$data['part'] = 2; $data['last'] = false;
$middle = JPD_OD_Documents::html( $order, $data );
check( false === strpos( $middle, 'ΔΕΛΤΙΟ ΠΑΡΑΓΓΕΛΙΑΣ' ) && false === strpos( $middle, 'data:image' ), 'chunk does not repeat document header or logo' );
check( false === strpos( $middle, 'ΣΥΝΟΛΟ ΕΙΔΩΝ:' ) && false === strpos( $middle, 'ΣΥΝΟΛΟ ΤΕΜΑΧΙΩΝ:' ), 'no intermediate grand totals' );
$data['last'] = true;
check( 1 === substr_count( JPD_OD_Documents::html( $order, $data ), 'ΣΥΝΟΛΟ ΕΙΔΩΝ:' ), 'final chunk grand total once' );
$data['rows'][0]['name'] = '<script>alert(1)</script>'; $data['rows'][0]['size'] = '<b>M</b>';
$escaped = JPD_OD_Documents::html( $order, $data );
check( false !== strpos( $escaped, '&lt;script&gt;' ) && false !== strpos( $escaped, '&lt;b&gt;M&lt;/b&gt;' ) && false === strpos( $escaped, '<script>' ), 'product fields escaped' );
$readLogo = new ReflectionMethod( 'JPD_OD_Documents', 'logo_data_uri' ); $readLogo->setAccessible( true );
foreach ( array( 'https://other.example/wp-content/uploads/2025/12/logo-jpd.svg', home_url( '/wp-content/uploads/../private.svg' ), home_url( '/wp-content/uploads/%2e%2e/private.svg' ), home_url( '/wp-content/uploads/2025/12/missing.svg' ), 'file:///etc/passwd', 'data:image/svg+xml;base64,abc' ) as $url ) {
    $GLOBALS['logo_url'] = $url; check( '' === $readLogo->invoke( null ), 'unsafe or unavailable logo rejected' );
}
unset( $GLOBALS['logo_url'] );
$GLOBALS['logo_url'] = home_url( '/wp-content/uploads/2025/12/logo-doctype.svg' );
$embedded = $readLogo->invoke( null );
check( '' !== $embedded && false === strpos( base64_decode( substr( $embedded, strpos( $embedded, ',' ) + 1 ) ), '<!DOCTYPE' ), 'standard SVG doctype removed before embedding' );
unset( $GLOBALS['logo_url'] );
$safeSvg = new ReflectionMethod( 'JPD_OD_Documents', 'safe_svg' ); $safeSvg->setAccessible( true );
foreach ( array( '<!DOCTYPE svg [<!ENTITY e SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&e;</svg>', '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://other.example/a.png"/></svg>', '<svg xmlns="http://www.w3.org/2000/svg"><style>rect{fill:url(file:///private.svg)}</style></svg>', '<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>', '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject/></svg>', '<not-svg/>' ) as $svg ) {
    check( ! $safeSvg->invoke( null, $svg ), 'external or invalid SVG rejected' );
}
check( $safeSvg->invoke( null, '<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g"/></defs><rect fill="url(#g)"/></svg>' ), 'internal SVG gradient retained' );
echo "$checks PDF layout and logo checks passed\n";
