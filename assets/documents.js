/* Manual, sequential document requests. No polling loop, background job, or automatic email retry. */
(() => {
  'use strict';
  const config = JSON.parse(document.getElementById('jpd-od-config').textContent);
  const el = id => document.getElementById(id);
  let job = null, busy = false;
  const labels = {compact: 'Συνοπτικό PDF', csv: 'Αρχείο CSV'};
  const size = bytes => bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(2)} MB`;
  function storage(action, value) { try { return action === 'get' ? sessionStorage.getItem(config.key) : action === 'set' ? sessionStorage.setItem(config.key, value) : sessionStorage.removeItem(config.key); } catch (_) { return null; } }
  function notice(text, type = '') { el('notice').hidden = !text; el('notice').className = `notice ${type}`; el('notice').textContent = text; }
  function setBusy(value) { busy = value; for (const id of ['create', 'send', 'discard', 'check']) el(id).disabled = value; }
  function focus(id, scroll = false) {
    const target = el(id);
    if (target.hidden || target.disabled) return;
    target.focus({preventScroll: true});
    if (scroll) target.scrollIntoView({block: 'nearest', behavior: 'auto'});
  }
  function announce(text) { el('progress-announcement').textContent = text; }
  function finish() {
    setBusy(false);
    if (!job) focus('create');
    else if (!el('check').hidden) focus('check', true);
    else if (job.canSend && !el('send').hidden) focus('send', true);
    else focus('progress-heading', true);
  }
  function showRecovery() {
    el('progress').hidden = false; el('create').hidden = true; el('choices').disabled = true;
    el('selection-details').hidden = true; el('selection-summary').hidden = false;
    el('selection-summary').textContent = 'Ανάκτηση προηγούμενης δημιουργίας / αποστολής.';
    el('generation-text').textContent = 'Ελέγξτε την πρόοδο για να ανακτηθεί η προηγούμενη συνεδρία.';
    el('sending-text').textContent = 'Δεν ξεκινά νέα αποστολή πριν ελεγχθεί η προηγούμενη.';
    el('check').hidden = false; el('send').hidden = true; el('discard').hidden = true;
  }
  async function request(command, extra = {}) {
    const body = new URLSearchParams({action: 'jpd_order_documents', nonce: config.nonce, order: config.order, command, ...(job ? {token: job.token} : {}), ...extra});
    let response;
    try { response = await fetch(config.ajax, {method: 'POST', credentials: 'same-origin', body}); }
    catch (_) { throw new Error('Η σύνδεση διακόπηκε. Πατήστε «Έλεγχος προόδου» πριν ξεκινήσετε νέα αποστολή.'); }
    let data;
    try { data = await response.json(); }
    catch (_) { throw new Error('Ο server δεν επέστρεψε αποτέλεσμα. Πατήστε «Έλεγχος προόδου». Αν η δημιουργία απέτυχε, ελέγξτε τα όρια χρόνου ή μνήμης του server.'); }
    if (data?.success !== true) { const error = new Error(data?.data?.message || 'Το αίτημα δεν ολοκληρώθηκε.'); error.code = data?.data?.code; throw error; }
    const next = data.data;
    if (!next || typeof next.token !== 'string' || !next.token || (job && next.token !== job.token) ||
        !Array.isArray(next.formats) || !next.formats.length || next.formats.some(format => !Object.prototype.hasOwnProperty.call(labels, format)) ||
        !Array.isArray(next.files) || next.files.some(file => !file || typeof file.name !== 'string' || !Number.isFinite(file.bytes)) ||
        !Number.isFinite(next.bytes) || !Number.isFinite(next.limit) || typeof next.canSend !== 'boolean' ||
        !['generating', 'ready', 'sent', 'sending', 'uncertain', 'failed', 'changed', 'cancelled'].includes(next.status)) {
      throw new Error('Μη έγκυρη απάντηση προόδου. Πατήστε «Έλεγχος προόδου» πριν ξεκινήσετε νέα αποστολή.');
    }
    job = next; storage('set', job.token); render(); return job;
  }
  function render() {
    if (!job) return;
    const first = el('progress').hidden;
    el('progress').hidden = false; el('choices').disabled = true; el('create').hidden = true;
    el('selection-details').hidden = true; el('selection-summary').hidden = false;
    el('selection-summary').textContent = `Επιλεγμένα: ${job.formats.map(format => labels[format]).join(' · ')}`;
    document.querySelectorAll('input[name="format"]').forEach(input => { input.checked = job.formats.includes(input.value); });
    const files = job.files || [], total = job.formats.length;
    const receipt = ['sent', 'failed', 'changed', 'cancelled', 'uncertain'].includes(job.status);
    el('file-receipt').hidden = !receipt || !files.length;
    el('file-receipt').textContent = job.status === 'sent'
      ? 'Καταγραφή εγγράφων που δημιουργήθηκαν και παραδόθηκαν στο σύστημα email. Τα προσωρινά αρχεία έχουν διαγραφεί· δεν είναι διαθέσιμα για λήψη.'
      : 'Καταγραφή εγγράφων που είχαν δημιουργηθεί. Τα προσωρινά αρχεία έχουν διαγραφεί· δεν είναι διαθέσιμα για λήψη. Η καταγραφή δεν επιβεβαιώνει αποστολή.';
    el('generation-count').textContent = `${files.length} / ${total}`;
    el('generation-bar').max = total; el('generation-bar').value = files.length;
    el('generation-text').textContent = files.length === total ? `Δημιουργήθηκαν τα έγγραφα · ${size(job.bytes)} συνολικά.` : 'Η δημιουργία βρίσκεται σε εξέλιξη.';
    el('file-list').replaceChildren(...files.map(file => { const li = document.createElement('li'), name = document.createElement('span'), bytes = document.createElement('span'); name.textContent = file.name; bytes.textContent = size(file.bytes); li.append(name, bytes); return li; }));
    el('send').hidden = !job.canSend;
    el('check').hidden = !['generating', 'sending', 'uncertain'].includes(job.status);
    el('discard').hidden = job.status === 'sending';
    el('discard').textContent = job.status === 'uncertain' ? 'Έλεγξα την αποστολή · νέα δημιουργία' : 'Νέα επιλογή εγγράφων';
    el('sending-bar').value = 0; el('sending-label').textContent = '';
    el('sending-text').textContent = 'Θα ξεκινήσει μόλις πατήσετε «Αποστολή email».';
    if (job.status === 'ready') {
      el('sending-bar').value = 0; el('sending-label').textContent = '';
      el('sending-text').textContent = 'Πατήστε «Αποστολή email» για να σταλούν όλα τα επιλεγμένα έγγραφα.';
      if (!job.canSend) notice(`Τα συνημμένα υπερβαίνουν το όριο ${size(job.limit)}. Κάντε νέα επιλογή εγγράφων.`, 'error');
    } else if (job.status === 'sent') {
      el('sending-bar').value = 1; el('sending-label').textContent = 'Ολοκληρώθηκε';
      el('sending-text').textContent = 'Τα έγγραφα παραδόθηκαν στο σύστημα email και τα προσωρινά αρχεία διαγράφηκαν.';
      el('discard').textContent = 'Δημιουργία για νέα αποστολή';
      notice('Η αποστολή ολοκληρώθηκε. Η τελική παράδοση στο γραμματοκιβώτιο εξαρτάται από την υπηρεσία email.', 'success');
    } else if (['sending', 'uncertain'].includes(job.status)) {
      el('sending-bar').removeAttribute('value');
      el('sending-label').textContent = 'Χρειάζεται έλεγχος';
      el('sending-text').textContent = 'Η αποστολή ξεκίνησε, αλλά δεν υπάρχει επιβεβαιωμένο αποτέλεσμα. Δεν επαναλαμβάνεται αυτόματα.';
      notice('Ελέγξτε την πρόοδο και την καταγραφή SMTP. Μην ξεκινήσετε νέα αποστολή πριν επιβεβαιώσετε αν το προηγούμενο email στάλθηκε.', 'error');
    } else if (['failed', 'changed', 'cancelled'].includes(job.status)) {
      el('generation-text').textContent = 'Απαιτείται νέα δημιουργία εγγράφων.';
      el('sending-text').textContent = 'Η αποστολή δεν ολοκληρώθηκε.';
      notice(job.status === 'changed' ? 'Η παραγγελία άλλαξε. Δημιουργήστε ξανά τα έγγραφα.' : 'Το βήμα δεν ολοκληρώθηκε. Μπορείτε να κάνετε νέα επιλογή εγγράφων.', 'error');
    }
    announce(`${el('generation-text').textContent} ${el('sending-text').textContent}`);
    if (first) focus('progress-heading', true);
  }
  async function generateRemaining() {
    for (const format of job.formats) {
      if (job.files.some(file => file.name.endsWith(`-${format}.${format === 'csv' ? 'csv' : 'pdf'}`))) continue;
      el('generation-text').textContent = `Δημιουργείται: ${labels[format]}…`;
      announce(el('generation-text').textContent);
      el('generation-bar').removeAttribute('value');
      await request('generate', {format});
    }
  }
  function reset() {
    storage('remove'); job = null; el('choices').disabled = false; el('create').hidden = false; el('progress').hidden = true;
    el('selection-details').hidden = false; el('selection-summary').hidden = true; el('selection-summary').textContent = '';
    el('discard').textContent = 'Νέα επιλογή εγγράφων';
    for (const id of ['send', 'check', 'discard', 'file-receipt']) el(id).hidden = true;
    el('generation-count').textContent = ''; el('generation-text').textContent = 'Σε αναμονή';
    el('generation-bar').max = 1; el('generation-bar').value = 0;
    el('sending-label').textContent = ''; el('sending-bar').value = 0;
    el('sending-text').textContent = 'Θα ξεκινήσει μόλις πατήσετε «Αποστολή email».';
    el('file-list').replaceChildren(); el('file-receipt').textContent = ''; announce('');
  }
  function failed(error) {
    if (Number(error.code) === 410) reset();
    else if (job && !job.formats) showRecovery();
    notice(error.message, 'error'); if (job) el('check').hidden = false;
  }
  el('create').addEventListener('click', async () => {
    const formats = [...document.querySelectorAll('input[name="format"]:checked')].map(input => input.value);
    if (!formats.length) { notice('Επιλέξτε τουλάχιστον ένα έγγραφο.', 'error'); return; }
    if (busy) return;
    setBusy(true); notice('');
    try { await request('start', {formats: JSON.stringify(formats)}); await generateRemaining(); }
    catch (error) { failed(error); } finally { finish(); }
  });
  el('send').addEventListener('click', async () => {
    if (busy || !job?.canSend) return;
    setBusy(true); notice(''); el('sending-bar').removeAttribute('value'); el('sending-text').textContent = 'Αποστέλλεται το email με τα επιλεγμένα συνημμένα…';
    // Hide immediately. A lost response must be checked, never blindly retried.
    el('send').hidden = true;
    focus('progress-heading', true); announce(el('sending-text').textContent);
    try { await request('send'); }
    catch (error) { failed(error); } finally { finish(); }
  });
  el('check').addEventListener('click', async () => {
    if (busy) return;
    setBusy(true); notice('');
    focus('progress-heading', true);
    try { await request('status'); if (job.status === 'generating') await generateRemaining(); }
    catch (error) { failed(error); } finally { finish(); }
  });
  el('discard').addEventListener('click', async () => {
    if (busy) return;
    setBusy(true);
    try {
      if (job && !['sent', 'failed', 'changed', 'cancelled'].includes(job.status)) await request('cancel');
      reset(); notice('');
    } catch (error) { failed(error); } finally { finish(); }
  });
  window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
  const saved = storage('get');
  if (saved) {
    job = {token: saved}; setBusy(true);
    showRecovery();
    request('status').catch(failed).finally(finish);
  }
})();
