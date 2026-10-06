<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html><html lang="el"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Αποστολή εγγράφων · #<?php echo esc_html( $order->get_order_number() ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'assets/documents.css', JPD_OD_FILE ) ); ?>?ver=1.1.0"></head><body style="<?php echo esc_attr( JPD_OD_Plugin::appearance() ); ?>">
<main class="shell">
    <a class="back" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) ); ?>">← Επιστροφή στις παραγγελίες</a>
    <header><p class="eyebrow">ΕΓΓΡΑΦΑ ΠΑΡΑΓΓΕΛΙΑΣ</p><h1>Αποστολή με email</h1><p>Παραγγελία <strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong> · <?php echo esc_html( $order->get_billing_last_name() ); ?></p></header>
    <section class="card recipient"><span class="label">ΠΑΡΑΛΗΠΤΗΣ</span><strong><?php echo esc_html( $order->get_billing_email() ); ?></strong><span class="subtle">Το email χρέωσης της παραγγελίας.</span></section>
    <section class="card" id="selection"><h2>Επιλογή εγγράφων</h2><p id="selection-summary" class="subtle" hidden></p><div id="selection-details"><fieldset id="choices"><legend class="sr-only">Έγγραφα για επισύναψη</legend>
        <label class="choice"><input type="checkbox" name="format" value="compact" checked><span><strong>Συνοπτικό PDF</strong><small>Προϊόντα, χρώματα, μεγέθη, ποσότητες και αξίες, χωρίς εικόνες.</small></span><span class="tag">PDF</span></label>
        <label class="choice"><input type="checkbox" name="format" value="csv"><span><strong>Αρχείο για Excel</strong><small>Αναλυτικές γραμμές παραγγελίας σε CSV.</small></span><span class="tag">CSV</span></label>
    </fieldset><p class="subtle detail">Οι τιμές υπολογίζονται κατά τη δημιουργία. Τα έγγραφα δημιουργούνται προσωρινά για αυτή την αποστολή.</p></div>
    <button id="create" class="primary" type="button">Δημιουργία εγγράφων</button></section>
    <section class="card" id="progress" aria-labelledby="progress-heading" hidden><h2 id="progress-heading" tabindex="-1">Πρόοδος</h2>
        <div class="step"><div class="step-title"><strong>1. Δημιουργία εγγράφων</strong><span id="generation-count"></span></div><p id="generation-text" class="subtle">Σε αναμονή</p><progress id="generation-bar" value="0" max="1" aria-label="Πρόοδος δημιουργίας"></progress><ul id="file-list"></ul></div>
        <div class="step"><div class="step-title"><strong>2. Αποστολή email</strong><span id="sending-label"></span></div><p id="sending-text" class="subtle">Θα ξεκινήσει μόλις πατήσετε «Αποστολή email».</p><progress id="sending-bar" value="0" max="1" aria-label="Πρόοδος αποστολής"></progress></div>
        <p id="file-receipt" class="subtle" hidden></p>
        <div class="actions"><button id="send" class="primary" type="button" hidden>Αποστολή email</button><button id="discard" class="secondary" type="button" hidden>Νέα επιλογή εγγράφων</button><button id="check" class="secondary" type="button" hidden>Έλεγχος προόδου</button></div>
    </section>
    <p id="progress-announcement" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
    <p id="notice" class="notice" role="status" aria-live="polite" hidden></p>
    <footer>Κρατήστε τη σελίδα ανοιχτή μέχρι να ολοκληρωθεί η αποστολή.</footer>
</main><script id="jpd-od-config" type="application/json"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
<script src="<?php echo esc_url( plugins_url( 'assets/documents.js', JPD_OD_FILE ) ); ?>?ver=1.1.0" defer></script></body></html>
