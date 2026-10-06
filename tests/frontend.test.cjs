const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync, existsSync} = require('node:fs');
const {join} = require('node:path');
const {runInNewContext} = require('node:vm');
const scriptPath = [join(__dirname, '../jpd-order-documents/assets/documents.js'), join(__dirname, '../assets/documents.js')].find(existsSync);
const script = readFileSync(scriptPath, 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));
const pending = () => { let resolve; const promise = new Promise(r => {resolve = r;}); return {promise, resolve}; };
const ready = (status = 'ready') => ({token: 'test-token', status, formats: ['compact'], files: [{name: 'order-compact.pdf', bytes: 1024}], bytes: 1024, limit: 2048, canSend: status === 'ready'});

function fixture(responses, saved = null) {
  const nodes = new Map(), calls = [], values = new Map(saved ? [['fixture', saved]] : []);
  let active = null;
  function element(id) {
    if (!nodes.has(id)) nodes.set(id, {
      id, hidden: ['progress', 'send', 'discard', 'check', 'selection-summary', 'file-receipt'].includes(id), disabled: false,
      textContent: '', value: 0, max: 1, children: [], handlers: {},
      addEventListener(event, handler) {this.handlers[event] = handler;},
      focus() {active = id;}, scrollIntoView() {this.scrolled = true;},
      removeAttribute(name) {delete this[name];},
      replaceChildren(...children) {this.children = children;}, append(...children) {this.children.push(...children);},
    });
    return nodes.get(id);
  }
  element('jpd-od-config').textContent = JSON.stringify({key: 'fixture', nonce: 'nonce', order: 300, ajax: '/test'});
  const inputs = [{value: 'compact', checked: true}, {value: 'csv', checked: false}];
  const context = {
    document: {getElementById: element, querySelectorAll: selector => selector.endsWith(':checked') ? inputs.filter(x => x.checked) : inputs, createElement: tag => element(`${tag}-${nodes.size}`)},
    window: {addEventListener() {}}, URLSearchParams,
    sessionStorage: {getItem: key => values.get(key), setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key)},
    fetch: async (_, {body}) => {
      calls.push(Object.fromEntries(body));
      assert.ok(responses.length, 'Unexpected automatic request');
      const response = responses.shift();
      if (response instanceof Error) throw response;
      const data = await response;
      return {json: async () => data?.success === false ? data : ({success: true, data})};
    },
  };
  runInNewContext(script, context);
  return {el: element, values, calls, active: () => active, click: id => element(id).handlers.click()};
}

test('create moves focus to progress while generating, then enabled Send; selection is compact and progress announced', async () => {
  const generation = pending();
  const f = fixture([{...ready('generating'), files: [], bytes: 0}, generation.promise]);
  const creating = f.click('create'); await tick();
  assert.equal(f.active(), 'progress-heading');
  assert.equal(f.el('selection-details').hidden, true);
  assert.match(f.el('progress-announcement').textContent, /Δημιουργείται/);
  generation.resolve(ready()); await creating;
  assert.equal(f.active(), 'send'); assert.equal(f.el('send').disabled, false);
  assert.equal(f.el('send').hidden, false); assert.equal(f.el('send').scrolled, true);
});

test('sent receipt has no download promise and reset clears every progress state before the next creation', async () => {
  const f = fixture([{...ready('generating'), files: []}, ready(), ready('sent')]);
  await f.click('create'); await f.click('send');
  assert.equal(f.active(), 'progress-heading');
  assert.match(f.el('file-receipt').textContent, /δεν είναι διαθέσιμα για λήψη/);
  assert.equal(f.el('file-receipt').hidden, false);
  await f.click('discard');
  assert.equal(f.active(), 'create'); assert.equal(f.el('progress').hidden, true);
  assert.equal(f.el('generation-text').textContent, 'Σε αναμονή');
  assert.equal(f.el('generation-count').textContent, '');
  assert.equal(f.el('sending-label').textContent, ''); assert.equal(f.el('sending-bar').value, 0);
  assert.equal(f.el('file-list').children.length, 0); assert.equal(f.el('file-receipt').textContent, '');
  assert.equal(f.el('progress-announcement').textContent, ''); assert.equal(f.el('selection-details').hidden, false);
  assert.equal(f.values.size, 0);
});

test('lost send response exposes Check and never sends again automatically', async () => {
  const f = fixture([{...ready('generating'), files: []}, ready(), new Error('offline'), ready('sent')]);
  await f.click('create'); await f.click('send');
  assert.equal(f.el('send').hidden, true); assert.equal(f.active(), 'check');
  assert.equal(f.values.get('fixture'), 'test-token');
  await f.click('check');
  assert.equal(f.calls.filter(x => x.command === 'send').length, 1);
  assert.equal(f.calls.at(-1).command, 'status');
});

test('recovery network failure preserves token and blocks new creation until status can be checked', async () => {
  const f = fixture([new Error('offline'), ready('uncertain')], 'test-token'); await tick();
  assert.equal(f.values.get('fixture'), 'test-token');
  assert.equal(f.el('create').hidden, true); assert.equal(f.el('discard').hidden, true);
  assert.equal(f.el('check').hidden, false); assert.equal(f.active(), 'check');
  await f.click('check');
  assert.equal(f.calls.every(x => x.command === 'status'), true);
  assert.match(f.el('file-receipt').textContent, /δεν επιβεβαιώνει αποστολή/);
});

test('explicit expired recovery clears the token and restores usable selection', async () => {
  const f = fixture([{success: false, data: {code: 410, message: 'expired'}}], 'test-token'); await tick();
  assert.equal(f.values.size, 0); assert.equal(f.el('create').hidden, false);
  assert.equal(f.el('choices').disabled, false); assert.equal(f.active(), 'create');
});

test('malformed successful status response cannot overwrite a recovery token or unlock new creation', async () => {
  const f = fixture([{status: 'ready', formats: ['compact']}], 'test-token'); await tick();
  assert.equal(f.values.get('fixture'), 'test-token');
  assert.equal(f.el('create').hidden, true); assert.equal(f.active(), 'check');
  assert.match(f.el('notice').textContent, /Μη έγκυρη απάντηση/);
});

test('oversized attachments never focus a hidden Send button', async () => {
  const f = fixture([{...ready('generating'), files: []}, {...ready(), canSend: false, limit: 512}]);
  await f.click('create');
  assert.equal(f.el('send').hidden, true); assert.equal(f.active(), 'progress-heading');
  assert.match(f.el('notice').textContent, /υπερβαίνουν/);
});

test('failed send receipt describes generation without claiming successful email', async () => {
  const f = fixture([{...ready('generating'), files: []}, ready(), ready('failed')]);
  await f.click('create'); await f.click('send');
  assert.match(f.el('file-receipt').textContent, /δεν επιβεβαιώνει αποστολή/);
  assert.equal(f.el('send').hidden, true);
});
