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
  async function request(command, extra = {}) {
    const body = new URLSearchParams({action: 'jpd_order_documents', nonce: config.nonce, order: config.order, command, ...(job ? {token: job.token} : {}), ...extra});
    let response;
    try { response = await fetch(config.ajax, {method: 'POST', credentials: 'same-origin', body}); }
    catch (_) { throw new Error('Η σύνδεση διακόπηκε. Πατήστε «Έλεγχος προόδου» πριν ξεκινήσετε νέα αποστολή.'); }
    let data;
    try { data = await response.json(); }
    catch (_) { throw new Error('Ο server δεν επέστρεψε αποτέλεσμα. Πατήστε «Έλεγχος προόδου». Αν η δημιουργία απέτυχε, ελέγξτε τα όρια χρόνου ή μνήμης του server.'); }
    if (!data.success) { const error = new Error(data.data?.message || 'Το αίτημα δεν ολοκληρώθηκε.'); error.code = data.data?.code; throw error; }
    job = data.data; storage('set', job.token); render(); return job;
  }
  function render() {
    if (!job) return;
    el('progress').hidden = false; el('choices').disabled = true; el('create').hidden = true;
    document.querySelectorAll('input[name="format"]').forEach(input => { input.checked = job.formats.includes(input.value); });
    const files = job.files || [], total = job.formats.length;
    el('generation-count').textContent = `${files.length} / ${total}`;
    el('generation-bar').max = total; el('generation-bar').value = files.length;
    el('generation-text').textContent = files.length === total ? `Δημιουργήθηκαν τα έγγραφα · ${size(job.bytes)} συνολικά.` : 'Η δημιουργία βρίσκεται σε εξέλιξη.';
    el('file-list').replaceChildren(...files.map(file => { const li = document.createElement('li'), name = document.createElement('span'), bytes = document.createElement('span'); name.textContent = file.name; bytes.textContent = size(file.bytes); li.append(name, bytes); return li; }));
    el('send').hidden = !job.canSend;
    el('check').hidden = !['generating', 'sending', 'uncertain'].includes(job.status);
    el('discard').hidden = job.status === 'sending';
    el('discard').textContent = job.status === 'uncertain' ? 'Έλεγξα την αποστολή · νέα δημιουργία' : 'Νέα επιλογή εγγράφων';
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
      el('sending-label').textContent = 'Χρειάζεται έλεγχος';
      el('sending-text').textContent = 'Η αποστολή ξεκίνησε, αλλά δεν υπάρχει επιβεβαιωμένο αποτέλεσμα. Δεν επαναλαμβάνεται αυτόματα.';
      notice('Ελέγξτε την πρόοδο και την καταγραφή SMTP. Μην ξεκινήσετε νέα αποστολή πριν επιβεβαιώσετε αν το προηγούμενο email στάλθηκε.', 'error');
    } else if (['failed', 'changed', 'cancelled'].includes(job.status)) {
      el('generation-text').textContent = 'Απαιτείται νέα δημιουργία εγγράφων.';
      el('sending-text').textContent = 'Η αποστολή δεν ολοκληρώθηκε.';
      notice(job.status === 'changed' ? 'Η παραγγελία άλλαξε. Δημιουργήστε ξανά τα έγγραφα.' : 'Το βήμα δεν ολοκληρώθηκε. Μπορείτε να κάνετε νέα επιλογή εγγράφων.', 'error');
    }
  }
  async function generateRemaining() {
    for (const format of job.formats) {
      if (job.files.some(file => file.name.endsWith(`-${format}.${format === 'csv' ? 'csv' : 'pdf'}`))) continue;
      el('generation-text').textContent = `Δημιουργείται: ${labels[format]}…`;
      el('generation-bar').removeAttribute('value');
      await request('generate', {format});
    }
  }
  function reset() { storage('remove'); job = null; el('choices').disabled = false; el('create').hidden = false; el('progress').hidden = true; el('discard').textContent = 'Νέα επιλογή εγγράφων'; el('sending-bar').value = 0; }
  function failed(error) { if (error.code === 410) reset(); notice(error.message, 'error'); if (job) el('check').hidden = false; }
  el('create').addEventListener('click', async () => {
    const formats = [...document.querySelectorAll('input[name="format"]:checked')].map(input => input.value);
    if (!formats.length) { notice('Επιλέξτε τουλάχιστον ένα έγγραφο.', 'error'); return; }
    setBusy(true); notice('');
    try { await request('start', {formats: JSON.stringify(formats)}); await generateRemaining(); }
    catch (error) { failed(error); } finally { setBusy(false); }
  });
  el('send').addEventListener('click', async () => {
    if (busy || !job?.canSend) return;
    setBusy(true); notice(''); el('sending-bar').removeAttribute('value'); el('sending-text').textContent = 'Αποστέλλεται το email με τα επιλεγμένα συνημμένα…';
    // Hide immediately. A lost response must be checked, never blindly retried.
    el('send').hidden = true;
    try { await request('send'); }
    catch (error) { failed(error); } finally { setBusy(false); }
  });
  el('check').addEventListener('click', async () => {
    setBusy(true); notice('');
    try { await request('status'); if (job.status === 'generating') await generateRemaining(); }
    catch (error) { failed(error); } finally { setBusy(false); }
  });
  el('discard').addEventListener('click', async () => {
    setBusy(true);
    try {
      if (job && !['sent', 'failed', 'changed', 'cancelled'].includes(job.status)) await request('cancel');
      reset(); notice('');
    } catch (error) { failed(error); } finally { setBusy(false); }
  });
  window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
  const saved = storage('get');
  if (saved) {
    job = {token: saved}; setBusy(true);
    request('status').catch(error => { storage('remove'); job = null; notice(error.message, 'error'); }).finally(() => setBusy(false));
  }
})();
