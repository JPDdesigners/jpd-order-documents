<?php
defined( 'ABSPATH' ) || exit;
class JPD_OD_Jobs {
    const TTL = 1800;
    public static function root() {
        $parent = realpath( sys_get_temp_dir() );
        if ( ! $parent || ! is_writable( $parent ) ) { throw new RuntimeException( 'Δεν υπάρχει διαθέσιμος ιδιωτικός προσωρινός φάκελος στον server.' ); }
        foreach ( array( ABSPATH, isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : ABSPATH ) as $public ) {
            $public = realpath( $public );
            if ( $public && ( $parent === $public || 0 === strpos( $parent, $public . DIRECTORY_SEPARATOR ) ) ) {
                throw new RuntimeException( 'Ο προσωρινός φάκελος βρίσκεται μέσα στον δημόσιο φάκελο του site. Η δημιουργία εγγράφων δεν μπορεί να ξεκινήσει.' );
            }
        }
        $root = $parent . '/jpd-order-documents-' . substr( hash( 'sha256', ABSPATH . '|' . get_current_blog_id() . '|' . wp_salt( 'auth' ) ), 0, 24 );
        if ( is_link( $root ) || ( ! is_dir( $root ) && ! mkdir( $root, 0700 ) && ! is_dir( $root ) ) ) { throw new RuntimeException( 'Δεν ήταν δυνατή η δημιουργία ιδιωτικού προσωρινού φακέλου.' ); }
        @chmod( $root, 0700 );
        return $root;
    }
    public static function session() {
        return hash_hmac( 'sha256', get_current_blog_id() . '|' . get_current_user_id() . '|' . wp_get_session_token(), wp_salt( 'auth' ) );
    }
    public static function fingerprint( $order ) {
        $lines = array();
        foreach ( $order->get_items( 'line_item' ) as $id => $item ) {
            $lines[] = array( $id, $item->get_product_id(), $item->get_variation_id(), $item->get_quantity(), $item->get_total() );
        }
        $date = $order->get_date_modified();
        return hash( 'sha256', wp_json_encode( array( $lines, $order->get_currency(), $order->get_billing_email(), $order->get_customer_id(), $order->get_status(), $order->get_meta( '_jpd_season' ), $date ? $date->getTimestamp() : 0 ) ) );
    }
    public static function write( $dir, $state ) {
        $json = wp_json_encode( $state );
        if ( false === $json || false === file_put_contents( $dir . '/state.tmp', $json, LOCK_EX ) || ! rename( $dir . '/state.tmp', $dir . '/state.json' ) ) { throw new RuntimeException( 'Δεν ήταν δυνατή η αποθήκευση της προόδου.' ); }
        @chmod( $dir . '/state.json', 0600 );
    }
    public static function start( $order, $actor, $formats ) {
        self::cleanup();
        if ( ! is_array( $formats ) || ! $formats || count( array_filter( $formats, 'is_string' ) ) !== count( $formats ) || count( array_unique( $formats ) ) !== count( $formats ) || array_diff( $formats, array( 'compact', 'csv' ) ) ) { throw new RuntimeException( 'Επιλέξτε συνοπτικό PDF ή/και CSV.' ); }
        if ( ! is_email( $order->get_billing_email() ) ) { throw new RuntimeException( 'Δεν υπάρχει έγκυρο email χρέωσης στην παραγγελία.' ); }
        $data = JPD_OD_Documents::collect( $order );
        if ( empty( $data['rows'] ) ) { throw new RuntimeException( 'Δεν υπάρχουν γραμμές για εξαγωγή.' ); }
        $token = bin2hex( random_bytes( 24 ) ); $dir = self::root() . '/' . $token;
        if ( ! mkdir( $dir, 0700 ) ) { throw new RuntimeException( 'Δεν ήταν δυνατή η έναρξη της δημιουργίας.' ); }
        $state = array( 'token' => $token, 'order' => $order->get_id(), 'actor' => $actor, 'session' => self::session(), 'created' => time(), 'status' => 'generating', 'formats' => array_values( $formats ), 'files' => array(), 'fingerprint' => self::fingerprint( $order ), 'email' => $order->get_billing_email(), 'data' => $data );
        try { self::write( $dir, $state ); } catch ( Throwable $e ) { self::remove( $dir ); throw $e; }
        return self::summary( $state );
    }
    public static function run( $token, $order, $actor, $command, $format = '' ) {
        if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{48}$/D', $token ) ) { throw new RuntimeException( 'Μη έγκυρη συνεδρία εγγράφων.' ); }
        $dir = self::root() . '/' . $token;
        if ( is_link( $dir ) || ! is_dir( $dir ) ) { throw new RuntimeException( 'Η συνεδρία έληξε. Δημιουργήστε ξανά τα έγγραφα.', 410 ); }
        $lock = fopen( $dir . '/operation.lock', 'c' );
        if ( ! $lock ) { throw new RuntimeException( 'Δεν ήταν δυνατή η πρόσβαση στη συνεδρία.' ); }
        if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) { fclose( $lock ); throw new RuntimeException( 'Ένα βήμα εκτελείται ήδη. Περιμένετε και ελέγξτε την πρόοδο.' ); }
        try {
            $raw = @file_get_contents( $dir . '/state.json' );
            if ( false === $raw ) { throw new RuntimeException( 'Η συνεδρία έληξε. Δημιουργήστε ξανά τα έγγραφα.', 410 ); }
            $state = json_decode( $raw, true );
            if ( ! is_array( $state ) || (int) $state['order'] !== (int) $order->get_id() || (int) $state['actor'] !== (int) $actor || ! hash_equals( $state['session'], self::session() ) ) { throw new RuntimeException( 'Η συνεδρία δεν ανήκει στον τρέχοντα λογαριασμό και πωλητή.' ); }
            if ( time() - $state['created'] >= self::TTL ) { self::discard_files( $dir ); throw new RuntimeException( 'Η συνεδρία έληξε. Δημιουργήστε ξανά τα έγγραφα.', 410 ); }
            if ( 'sending' === $state['status'] ) {
                // We own the lock, so no dispatch is still executing. The previous worker ended without a receipt.
                $state['status'] = 'uncertain'; self::discard_files( $dir ); self::write( $dir, $state );
            }
            if ( 'status' === $command ) { return self::summary( $state ); }
            if ( 'cancel' === $command ) {
                if ( in_array( $state['status'], array( 'sending', 'sent', 'uncertain' ), true ) ) { return self::summary( $state ); }
                self::discard_files( $dir ); unset( $state['data'] ); $state['files'] = array(); $state['status'] = 'cancelled'; self::write( $dir, $state ); return self::summary( $state );
            }
            if ( 'sent' === $state['status'] ) { return self::summary( $state ); }
            if ( self::fingerprint( $order ) !== $state['fingerprint'] ) { self::discard_files( $dir ); unset( $state['data'] ); $state['status'] = 'changed'; self::write( $dir, $state ); throw new RuntimeException( 'Η παραγγελία άλλαξε. Δημιουργήστε ξανά τα έγγραφα με τα νέα στοιχεία.' ); }
            if ( 'generate' === $command ) {
                if ( ! in_array( $state['status'], array( 'generating', 'ready' ), true ) || ! in_array( $format, $state['formats'], true ) ) { throw new RuntimeException( 'Το βήμα δημιουργίας δεν είναι διαθέσιμο.' ); }
                if ( ! isset( $state['files'][ $format ] ) ) {
                    try {
                        $path = JPD_OD_Documents::generate( $format, $order, $state['data'], $dir );
                        $state['files'][ $format ] = array( 'name' => basename( $path ), 'bytes' => filesize( $path ), 'sha256' => hash_file( 'sha256', $path ) );
                        if ( count( $state['files'] ) === count( $state['formats'] ) ) { $state['status'] = 'ready'; unset( $state['data'] ); }
                        self::write( $dir, $state );
                    } catch ( Throwable $e ) { self::discard_files( $dir ); unset( $state['data'] ); $state['status'] = 'failed'; $state['files'] = array(); self::write( $dir, $state ); throw $e; }
                }
                return self::summary( $state );
            }
            if ( 'send' !== $command || 'ready' !== $state['status'] ) {
                throw new RuntimeException( in_array( $state['status'], array( 'sending', 'uncertain' ), true ) ? 'Η αποστολή έχει ήδη ξεκινήσει. Δεν θα επαναληφθεί αυτόματα. Ελέγξτε το email/SMTP πριν από νέα αποστολή.' : 'Τα έγγραφα δεν είναι έτοιμα για αποστολή.' );
            }
            $paths = array(); $bytes = 0;
            foreach ( $state['formats'] as $selected ) {
                if ( empty( $state['files'][ $selected ] ) ) { throw new RuntimeException( 'Δεν έχουν δημιουργηθεί όλα τα επιλεγμένα έγγραφα.' ); }
                $f = $state['files'][ $selected ]; $path = $dir . '/' . basename( $f['name'] );
                if ( is_link( $path ) || ! is_file( $path ) || ! hash_equals( $f['sha256'], hash_file( 'sha256', $path ) ) ) { throw new RuntimeException( 'Ένα έγγραφο λείπει ή έχει αλλάξει. Δημιουργήστε ξανά τα έγγραφα.' ); }
                $paths[] = $path; $bytes += filesize( $path );
            }
            if ( $bytes > self::limit() ) { throw new RuntimeException( 'Τα συνημμένα υπερβαίνουν το επιτρεπόμενο μέγεθος. Επιλέξτε λιγότερα έγγραφα.' ); }
            $emails = WC()->mailer()->get_emails();
            if ( empty( $emails['JPD_OD_Email'] ) || ! $emails['JPD_OD_Email']->is_enabled() ) { throw new RuntimeException( 'Ενεργοποιήστε το email «JPD · Έγγραφα παραγγελίας» στις ρυθμίσεις WooCommerce.' ); }
            // Persist before dispatch. A killed request must never resend the same operation blindly.
            $state['status'] = 'sending'; self::write( $dir, $state );
            try {
                $accepted = $emails['JPD_OD_Email']->send_documents( $order, $paths );
                $state['status'] = $accepted ? 'sent' : 'failed';
            } catch ( Throwable $e ) { $state['status'] = 'uncertain'; }
            self::discard_files( $dir ); self::write( $dir, $state );
            if ( 'sent' === $state['status'] ) {
                $order->add_order_note( 'JPD: Τα έγγραφα (' . implode( ', ', $state['formats'] ) . ') παραδόθηκαν στο σύστημα email προς ' . $state['email'] . '. Πωλητής #' . $actor . '. Συνεδρία ' . substr( $token, 0, 8 ) . '.' );
            }
            return self::summary( $state );
        } finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
    }
    public static function limit() { return max( 1024, (int) apply_filters( 'jpd_od_max_attachment_bytes', 20 * 1024 * 1024 ) ); }
    public static function summary( $state ) {
        $bytes = array_sum( array_column( $state['files'], 'bytes' ) );
        return array( 'token' => $state['token'], 'status' => $state['status'], 'formats' => $state['formats'], 'files' => array_values( $state['files'] ), 'bytes' => $bytes, 'limit' => self::limit(), 'canSend' => 'ready' === $state['status'] && $bytes <= self::limit() );
    }
    public static function discard_files( $dir ) {
        foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
            if ( in_array( basename( $file ), array( 'state.json', 'operation.lock' ), true ) ) { continue; }
            if ( is_file( $file ) || is_link( $file ) ) { @unlink( $file ); }
        }
    }
    public static function remove( $dir ) {
        if ( is_link( $dir ) ) { return; }
        foreach ( glob( $dir . '/*' ) ?: array() as $file ) { if ( is_file( $file ) || is_link( $file ) ) { @unlink( $file ); } }
        @rmdir( $dir );
    }
    public static function cleanup( $all = false ) {
        $root = self::root();
        foreach ( glob( $root . '/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
            if ( is_link( $dir ) || ! preg_match( '/^[a-f0-9]{48}$/D', basename( $dir ) ) ) { continue; }
            $state = json_decode( (string) @file_get_contents( $dir . '/state.json' ), true );
            $created = isset( $state['created'] ) ? (int) $state['created'] : filemtime( $dir );
            if ( ! $all && time() - $created < self::TTL ) { continue; }
            $lock = fopen( $dir . '/operation.lock', 'c' );
            if ( ! $lock ) { continue; }
            if ( flock( $lock, LOCK_EX | LOCK_NB ) ) {
                // Keep the locked inode in place until its handle closes (also works on Windows).
                foreach ( glob( $dir . '/*' ) ?: array() as $file ) { if ( 'operation.lock' !== basename( $file ) && ( is_file( $file ) || is_link( $file ) ) ) { @unlink( $file ); } }
                flock( $lock, LOCK_UN ); fclose( $lock ); @unlink( $dir . '/operation.lock' ); @rmdir( $dir );
            } else { fclose( $lock ); }
        }
    }
}
