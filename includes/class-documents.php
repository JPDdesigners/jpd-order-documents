<?php
defined( 'ABSPATH' ) || exit;
class JPD_OD_Documents {
    public static function collect( $order ) {
        return class_exists( 'JPD_B2B_Order_Export_V2' ) && is_callable( array( 'JPD_B2B_Order_Export_V2', 'collect' ) )
            ? JPD_B2B_Order_Export_V2::collect( $order ) : JPD_OD_Collector::collect( $order );
    }
    public static function generate( $format, $order, $data, $dir ) {
        if ( ! in_array( $format, array( 'compact', 'csv' ), true ) ) { throw new RuntimeException( 'Επιτρέπονται μόνο συνοπτικό PDF και CSV.' ); }
        $path = $dir . '/jpd-order-' . $order->get_id() . '-' . $format . ( 'csv' === $format ? '.csv' : '.pdf' );
        if ( 'csv' === $format ) { self::csv( $path, $order, $data ); }
        else { self::pdf( $path, $order, $data, $dir ); }
        @chmod( $path, 0600 );
        if ( ! is_file( $path ) || ! filesize( $path ) ) { throw new RuntimeException( 'Το έγγραφο δεν δημιουργήθηκε.' ); }
        return $path;
    }
    public static function pdf( $path, $order, $data, $dir ) {
        if ( ! extension_loaded( 'dom' ) || ! extension_loaded( 'mbstring' ) || ! extension_loaded( 'zlib' ) ) { throw new RuntimeException( 'Η δημιουργία PDF απαιτεί τις επεκτάσεις PHP DOM, mbstring και zlib. Ενεργοποιήστε τις από τις ρυθμίσεις PHP του hosting.' ); }
        require_once __DIR__ . '/vendor-loader.php';
        // Bound the HTML layout engine's memory. Keep all sizes of each product/color together.
        $groups = array();
        foreach ( $data['rows'] as $row ) { $key = $row['root'] . "\0" . $row['sub'] . "\0" . $row['group']; $groups[ $key ][] = $row; }
        $chunks = array_chunk( array_values( $groups ), 60 ); $parts = array();
        $logo = self::logo_data_uri();
        try {
            foreach ( $chunks as $index => $chunk ) {
                $slice = $data; $slice['rows'] = array_merge( ...$chunk ); $slice['last'] = $index === count( $chunks ) - 1; $slice['part'] = $index + 1;
                $options = new \JPD_Order_Documents_Vendor\Dompdf\Options();
                $options->set( 'isRemoteEnabled', false ); $options->set( 'isPhpEnabled', false ); $options->set( 'isJavascriptEnabled', false );
                $options->set( 'defaultFont', 'DejaVu Sans' ); $options->set( 'tempDir', $dir ); $options->set( 'fontCache', $dir );
                $options->set( 'chroot', array( dirname( __DIR__ ) . '/lib/vendor/dompdf/dompdf', $dir ) );
                $pdf = new \JPD_Order_Documents_Vendor\Dompdf\Dompdf( $options );
                $pdf->loadHtml( self::html( $order, $slice, $logo ), 'UTF-8' ); $pdf->setPaper( 'A4', 'portrait' ); $pdf->render();
                $part = $dir . '/part-' . $index . '.pdf'; $parts[] = $part;
                if ( false === file_put_contents( $part, $pdf->output(), LOCK_EX ) ) { throw new RuntimeException( 'Δεν ήταν δυνατή η εγγραφή PDF.' ); }
                @chmod( $part, 0600 ); unset( $pdf, $slice, $options ); gc_collect_cycles();
            }
            $merged = new \JPD_Order_Documents_Vendor\setasign\Fpdi\Fpdi(); $merged->SetAutoPageBreak( false );
            $pages = 0;
            foreach ( $parts as $part ) { $pages += $merged->setSourceFile( $part ); }
            $pageNumber = 0;
            foreach ( $parts as $part ) {
                $count = $merged->setSourceFile( $part );
                for ( $page = 1; $page <= $count; $page++ ) {
                    $template = $merged->importPage( $page ); $size = $merged->getTemplateSize( $template );
                    $merged->AddPage( $size['orientation'], array( $size['width'], $size['height'] ) ); $merged->useTemplate( $template );
                    $merged->SetFont( 'Helvetica', '', 8 ); $merged->SetTextColor( 110, 110, 110 );
                    $merged->Text( $size['width'] - 45, $size['height'] - 8, 'Page ' . ++$pageNumber . ' / ' . $pages );
                }
            }
            $merged->Output( 'F', $path ); unset( $merged );
        } finally { foreach ( $parts as $part ) { @unlink( $part ); } gc_collect_cycles(); }
    }
    public static function csv( $path, $order, $data ) {
        $out = fopen( $path, 'wb' );
        if ( ! $out ) { throw new RuntimeException( 'Δεν ήταν δυνατή η δημιουργία CSV.' ); }
        try {
            fwrite( $out, "\xEF\xBB\xBF" );
            fputcsv( $out, array( 'Παραγγελία', 'Σεζόν', 'ID γραμμής', 'Product ID', 'Variation ID', 'Κατηγορία', 'Υποκατηγορία', 'SKU προϊόντος', 'SKU παραλλαγής', 'Περιγραφή', 'Χρώμα', 'Μέγεθος', 'Ποσότητα', 'Τιμή μονάδας', 'Αξία γραμμής', 'Νόμισμα', 'Πηγή τιμής', 'Παρατηρήσεις' ), ';', '"', '' );
            foreach ( $data['rows'] as $r ) {
                $values = array_map( array( 'JPD_OD_Collector', 'csv_text' ), array(
                    $order->get_order_number(), $order->get_meta( '_jpd_season' ), $r['item_id'], $r['product_id'], $r['variation_id'],
                    $r['root'], $r['sub'], $r['parent_sku'], $r['sku'], $r['name'], $r['color'], $r['size'],
                ) );
                $values[] = self::number( $r['qty'] );
                $values[] = number_format( $r['unit'], max( 4, wc_get_price_decimals() ), ',', '' );
                $values[] = number_format( $r['total'], wc_get_price_decimals(), ',', '' );
                $values[] = JPD_OD_Collector::csv_text( $order->get_currency() );
                $values[] = JPD_OD_Collector::csv_text( $r['source'] );
                $values[] = JPD_OD_Collector::csv_text( implode( ' | ', $r['notes'] ) );
                if ( false === fputcsv( $out, $values, ';', '"', '' ) ) { throw new RuntimeException( 'Η εγγραφή CSV απέτυχε.' ); }
            }
        } finally { fclose( $out ); }
    }
    public static function number( $value ) { return rtrim( rtrim( number_format( $value, 4, ',', '' ), '0' ), ',' ); }
    private static function money( $value, $order ) {
        // Keep WooCommerce currency formatting without its browser-only markup.
        return html_entity_decode( wp_strip_all_tags( wc_price( $value, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
    }
    private static function logo_data_uri() {
        // Resolve the snippet's logo inside uploads. Never enable remote PDF resources.
        $url = apply_filters( 'jpd_b2b_export_logo_url', home_url( '/wp-content/uploads/2025/12/logo-jpd.svg' ) );
        if ( ! is_string( $url ) || ! function_exists( 'wp_upload_dir' ) ) { return ''; }
        $uploads = wp_upload_dir( null, false );
        if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) { return ''; }
        $source = parse_url( $url ); $base = parse_url( $uploads['baseurl'] );
        if ( ! is_array( $source ) || ! is_array( $base ) || ! in_array( strtolower( $source['scheme'] ?? '' ), array( 'http', 'https' ), true )
            || 0 !== strcasecmp( $source['host'] ?? '', $base['host'] ?? '' ) || ( $source['port'] ?? null ) !== ( $base['port'] ?? null )
            || isset( $source['user'] ) || isset( $source['pass'] ) ) { return ''; }
        $prefix = rtrim( rawurldecode( $base['path'] ?? '' ), '/' ) . '/';
        $path = rawurldecode( $source['path'] ?? '' );
        if ( 0 !== strpos( $path, $prefix ) || false !== strpos( $path, "\0" ) || false !== strpos( $path, '\\' ) ) { return ''; }
        $root = realpath( $uploads['basedir'] );
        $file = $root ? realpath( $root . '/' . substr( $path, strlen( $prefix ) ) ) : false;
        if ( ! $file || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || ! is_file( $file ) || ! is_readable( $file ) ) { return ''; }
        $bytes = filesize( $file );
        if ( ! $bytes || $bytes > 1024 * 1024 ) { return ''; }
        $contents = file_get_contents( $file, false, null, 0, 1024 * 1024 + 1 );
        if ( false === $contents || strlen( $contents ) > 1024 * 1024 ) { return ''; }
        if ( 'svg' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
            // Illustrator exports may declare the standard SVG DTD. Embed no DTD.
            $contents = preg_replace( '/<!DOCTYPE\s+svg\s+(?:PUBLIC\s+"[^"]*"\s+"[^"]*"|SYSTEM\s+"[^"]*")\s*>/i', '', $contents );
            if ( ! self::safe_svg( $contents ) ) { return ''; }
            $mime = 'image/svg+xml';
        } else {
            $image = @getimagesizefromstring( $contents ); $mime = $image['mime'] ?? '';
            if ( ! in_array( $mime, array( 'image/png', 'image/jpeg' ), true ) || $image[0] > 4096 || $image[1] > 4096 ) { return ''; }
        }
        return 'data:' . $mime . ';base64,' . base64_encode( $contents );
    }
    private static function safe_svg( $contents ) {
        if ( ! class_exists( 'DOMDocument' ) || preg_match( '/<!DOCTYPE|<!ENTITY|<\?xml-stylesheet/i', $contents ) ) { return false; }
        $previous = libxml_use_internal_errors( true );
        try {
            $xml = new DOMDocument();
            if ( ! $xml->loadXML( $contents, LIBXML_NONET ) || ! $xml->documentElement || 'svg' !== $xml->documentElement->localName
                || 'http://www.w3.org/2000/svg' !== $xml->documentElement->namespaceURI ) { return false; }
            foreach ( $xml->getElementsByTagName( '*' ) as $element ) {
                if ( in_array( strtolower( $element->localName ), array( 'script', 'foreignobject' ), true ) ) { return false; }
                foreach ( $element->attributes as $attribute ) {
                    $value = trim( $attribute->value );
                    if ( 'href' === strtolower( $attribute->localName ) && '' !== $value && '#' !== $value[0] ) { return false; }
                }
            }
            if ( preg_match( '/@import/i', $contents ) ) { return false; }
            preg_match_all( '/url\s*\(\s*([^)]+)\)/i', $xml->saveXML(), $matches );
            foreach ( $matches[1] as $value ) {
                $value = trim( $value, " \t\r\n\"'" );
                if ( '' === $value || '#' !== $value[0] ) { return false; }
            }
            return true;
        } finally { libxml_clear_errors(); libxml_use_internal_errors( $previous ); }
    }
    public static function html( $order, $data, $logo = null ) {
        $groups = array();
        foreach ( $data['rows'] as $r ) {
            $key = $r['root'] . "\0" . $r['sub'] . "\0" . $r['group'];
            if ( ! isset( $groups[ $key ] ) ) { $groups[ $key ] = $r; $groups[ $key ]['sizes'] = array(); $groups[ $key ]['qty'] = 0; $groups[ $key ]['total'] = 0; }
            $g =& $groups[ $key ];
            if ( ! isset( $g['sizes'][ $r['size'] ] ) ) { $g['sizes'][ $r['size'] ] = 0; }
            $g['sizes'][ $r['size'] ] += $r['qty']; $g['qty'] += $r['qty']; $g['total'] += $r['total'];
            $g['notes'] = array_unique( array_merge( $g['notes'], $r['notes'] ) ); unset( $g );
        }
        $date = $order->get_date_created(); $season = trim( (string) $order->get_meta( '_jpd_season' ) );
        if ( null === $logo ) { $logo = self::logo_data_uri(); }
        ob_start();
        try { require __DIR__ . '/pdf-template.php'; return ob_get_contents(); }
        finally { ob_end_clean(); }
    }
}
