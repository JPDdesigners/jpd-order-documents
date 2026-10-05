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
        try {
            foreach ( $chunks as $index => $chunk ) {
                $slice = $data; $slice['rows'] = array_merge( ...$chunk ); $slice['last'] = $index === count( $chunks ) - 1; $slice['part'] = $index + 1;
                $options = new \JPD_Order_Documents_Vendor\Dompdf\Options();
                $options->set( 'isRemoteEnabled', false ); $options->set( 'isPhpEnabled', false ); $options->set( 'isJavascriptEnabled', false );
                $options->set( 'defaultFont', 'DejaVu Sans' ); $options->set( 'tempDir', $dir ); $options->set( 'fontCache', $dir );
                $options->set( 'chroot', array( dirname( __DIR__ ) . '/lib/vendor/dompdf/dompdf', $dir ) );
                $pdf = new \JPD_Order_Documents_Vendor\Dompdf\Dompdf( $options );
                $pdf->loadHtml( self::html( $order, $slice ), 'UTF-8' ); $pdf->setPaper( 'A4', 'portrait' ); $pdf->render();
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
    private static function money( $value, $order ) { return number_format( $value, wc_get_price_decimals(), ',', '.' ) . ' ' . $order->get_currency(); }
    public static function html( $order, $data ) {
        $groups = array();
        foreach ( $data['rows'] as $r ) {
            $key = $r['root'] . "\0" . $r['sub'] . "\0" . $r['group'];
            if ( ! isset( $groups[ $key ] ) ) { $groups[ $key ] = $r; $groups[ $key ]['sizes'] = array(); $groups[ $key ]['qty'] = 0; $groups[ $key ]['total'] = 0; }
            $g =& $groups[ $key ];
            if ( ! isset( $g['sizes'][ $r['size'] ] ) ) { $g['sizes'][ $r['size'] ] = 0; }
            $g['sizes'][ $r['size'] ] += $r['qty']; $g['qty'] += $r['qty']; $g['total'] += $r['total'];
            $g['notes'] = array_unique( array_merge( $g['notes'], $r['notes'] ) ); unset( $g );
        }
        $date = $order->get_date_created(); $cols = 3;
        ob_start(); ?>
        <!doctype html><html lang="el"><head><meta charset="UTF-8"><style>
        @page{margin:14mm 12mm 17mm}body{font-family:"DejaVu Sans",sans-serif;font-size:9px;color:#222}h1{font-size:20px;margin:0 0 8px}h2{font-size:13px;margin:0 0 6px}.meta{line-height:1.65;margin-bottom:12px}.muted{color:#666}.warning{background:#fff3db;padding:8px;margin-bottom:10px}table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}th{background:#eee;text-align:left;padding:8px}td{border-bottom:1px solid #ddd;padding:8px;vertical-align:middle;word-wrap:break-word}tr.product{page-break-inside:avoid}.root td{background:#222;color:white;font-weight:bold;padding:8px}.sub td{background:#f0f0f0;font-weight:bold}.num{text-align:right}.photo{width:55px;text-align:center}.photo img{max-width:46px;max-height:69px}.badge{display:inline-block;padding:3px 6px;margin:2px;background:#f1f1f1;border:1px solid #ddd}.summary{font-size:12px;font-weight:bold;margin-top:12px}.note{font-size:8px;color:#666;margin-top:5px}footer{margin-top:15px;font-size:8px;line-height:1.6}
        </style></head><body>
        <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1><h2>Παραγγελία #<?php echo esc_html( $order->get_order_number() ); ?> · Συνοπτικό PDF<?php if ( isset( $data['part'] ) && $data['part'] > 1 ) { echo ' · Συνέχεια'; } ?></h2>
        <div class="meta"><?php echo esc_html( $order->get_billing_last_name() ); ?> · ΑΦΜ: <?php echo esc_html( $order->get_billing_first_name() ); ?><br>
        <?php echo esc_html( $order->get_billing_email() ); ?> · <?php echo esc_html( $date ? $date->date_i18n( 'd/m/Y' ) : '' ); ?> · Σεζόν: <?php echo esc_html( $order->get_meta( '_jpd_season' ) ); ?><br>
        <?php echo esc_html( $order->get_customer_note() ); ?></div>
        <?php if ( $data['warnings'] ) { ?><div class="warning">Γραμμές με παρατηρήσεις: <?php echo (int) $data['warnings']; ?>. Ελέγξτε τις σημειώσεις.</div><?php } ?>
        <?php if ( abs( $data['total'] - (float) $order->get_total() ) >= pow( 10, -wc_get_price_decimals() ) / 2 ) { ?><div class="warning">Η τρέχουσα αξία διαφέρει από την αποθηκευμένη αξία της παραγγελίας.</div><?php } ?>
        <table><thead><tr><th style="width:70%">Προϊόν / Χρώμα / Μεγέθη</th><th class="num" style="width:10%">Τεμάχια</th><th class="num" style="width:20%">Συνολική αξία</th></tr></thead><tbody>
        <?php $root = null; $sub = null; foreach ( $groups as $g ) {
            if ( $root !== $g['root'] ) { $root = $g['root']; $sub = null; ?><tr class="root"><td colspan="<?php echo $cols; ?>"><?php echo esc_html( $root ); ?></td></tr><?php }
            if ( $sub !== $g['sub'] ) { $sub = $g['sub']; ?><tr class="sub"><td colspan="<?php echo $cols; ?>"><?php echo esc_html( $sub ); ?></td></tr><?php } ?>
            <tr class="product">
            <td><strong><?php echo esc_html( $g['parent_sku'] . ' · ' . $g['name'] ); ?></strong><br><?php echo esc_html( $g['color'] ); ?><br>
            <?php foreach ( $g['sizes'] as $size => $qty ) { ?><span class="badge"><?php echo esc_html( $size . ': ' . self::number( $qty ) ); ?></span> <?php } ?>
            <?php if ( $g['notes'] ) { ?><div class="note"><?php echo esc_html( implode( ' · ', $g['notes'] ) ); ?></div><?php } ?></td>
            <td class="num"><?php echo esc_html( self::number( $g['qty'] ) ); ?></td><td class="num"><?php echo esc_html( self::money( $g['total'], $order ) ); ?></td></tr>
        <?php } ?></tbody></table>
        <?php if ( ! isset( $data['last'] ) || $data['last'] ) { ?><div class="summary">Σύνολο: <?php echo esc_html( self::number( $data['qty'] ) ); ?> τεμάχια · <?php echo esc_html( self::money( $data['total'], $order ) ); ?></div><?php } else { ?><div class="note">Η παραγγελία συνεχίζεται στις επόμενες σελίδες. Το συνολικό άθροισμα εμφανίζεται στο τέλος.</div><?php } ?>
        <footer>Οι ποσότητες προέρχονται από την παραγγελία. Οι τιμές και τα στοιχεία προϊόντων υπολογίστηκαν κατά τη δημιουργία των εγγράφων. Όπου δεν υπάρχει διαθέσιμη τρέχουσα τιμή, χρησιμοποιείται η αποθηκευμένη αξία γραμμής. Δεν περιλαμβάνονται φόροι, μεταφορικά ή κουπόνια.</footer>
        </body></html><?php return ob_get_clean();
    }
}
