// Exercise the shipped store with a small Interactivity API adapter; no browser or network.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');
const { webcrypto } = require('node:crypto');

function fixture(configOverrides = {}) {
  let definition, context = {}, element = {};
  const requests = [];
  let fail = false, answer = { ok: true };
  const location = { hash: '', assign(url) { this.assigned = url; } };
  const dialog = { open: false, showModal() { this.open = true; }, querySelector() { return null; } };
  const source = readFileSync(resolve(__dirname, '../../../plugins/iss-frontend/modules/programme/assets/booking.js'), 'utf8').replace(/^import[^\n]+\n/, '');
  runInNewContext(source, {
    store(namespace, value) { assert.equal(namespace, 'iss/booking'); definition = value; return value; },
    getContext: () => context, getElement: () => ({ ref: element }),
    getConfig: () => ({ today: '2026-10-20', requestUrl: '/fixture/request', nonce: 'fixture', paymentStatusUrl: '/fixture/payment-status', ...configOverrides }),
    withSyncEvent: fn => fn,
    document: { getElementById: () => dialog, activeElement: null, body: {} },
    requestAnimationFrame: fn => fn(), crypto: webcrypto, TypeError, URL, location,
    FormData: class { *[Symbol.iterator]() { yield* Object.entries(element.values || {}); } },
    async fetch(url, options) {
      requests.push(JSON.parse(options.body));
      if (fail) throw new TypeError('offline');
      return { ok: true, json: async () => answer };
    },
  });
  const { state, actions } = definition;
  Object.assign(state, {
    source: { id: 12, type: 'fuehrung', intent: 'booking', title: 'Fixture' },
    slots: [{ id: 'slot', start: '2026-12-03T10:00:00', available: 5, amount: 1400, reduced_amount: 1000 }],
    slotId: 'slot', day: '2026-12-03', open: true, requestKey: 'fixture-request-0001',
  });
  return {
    state, actions, requests, location,
    answer(value) { answer = value; },
    input(name, value) { element = { name, value }; actions.input(); },
    context(value) { context = value; },
    form() { element = { reportValidity: () => true, values: { name: 'Fixture', email: 'fixture@example.invalid' } }; },
    offline(value) { fail = value; },
  };
}

async function run(iterator) {
  let next = iterator.next();
  while (!next.done) {
    try { next = iterator.next(await next.value); }
    catch (error) { next = iterator.throw(error); }
  }
}

test('regular, reduced-only and mixed totals follow input actions', () => {
  const f = fixture(), s = f.state;
  assert.equal(s.quantityLabel.replace(/\s/g, ' '), 'Regulär (14,00 €)');
  assert.equal(s.reducedQuantityLabel.replace(/\s/g, ' '), 'Ermäßigt (10,00 €)');
  assert.equal(s.minQuantity, 0);
  assert.match(s.priceLabel, /^14,00/);
  f.input('quantity', '0'); f.input('reduced_quantity', '2');
  assert.equal(s.totalQuantity, 2); assert.equal(s.submitDisabled, false); assert.match(s.priceLabel, /^20,00/);
  f.input('quantity', '1');
  assert.equal(s.totalQuantity, 3); assert.match(s.priceLabel, /^34,00/);
});

test('combined capacity, zero, fractions and empty fields prevent submission', () => {
  const f = fixture(), s = f.state;
  f.input('quantity', '3'); f.input('reduced_quantity', '3');
  assert.match(s.quantityError, /höchstens 5/); assert.equal(s.submitDisabled, true); assert.equal(s.priceLabel, '');
  f.input('quantity', '0'); f.input('reduced_quantity', '0');
  assert.match(s.quantityError, /mindestens 1/); assert.equal(s.submitDisabled, true);
  for (const [name, value] of [['quantity', '-1'], ['quantity', '1.5'], ['quantity', ''], ['reduced_quantity', '-1'], ['reduced_quantity', '1.5'], ['reduced_quantity', '']]) {
    f.input('quantity', '1'); f.input('reduced_quantity', '0'); f.input(name, value);
    assert.equal(s.submitDisabled, true, `${name}=${value}`);
  }
  f.input('quantity', '0'); f.input('reduced_quantity', '5');
  assert.equal(s.submitDisabled, false); assert.match(s.priceLabel, /^50,00/);
  s.slots[0].available = null;
  assert.equal(s.maxQuantity, 8);
});

test('unsupported discounts, orders and inquiries retain their separate semantics', () => {
  const { state: s } = fixture();
  s.reducedQuantity = 2;
  for (const reduced of [undefined, 0, 1400, 1500]) {
    s.slots[0].reduced_amount = reduced;
    assert.equal(s.hasReducedTickets, false); assert.equal(s.totalQuantity, 1); assert.equal(s.minQuantity, 1);
  }
  s.intent = 'order'; s.slotId = ''; s.source.amount = 600; s.quantity = 2;
  assert.equal(s.quantityLabel, 'Exemplare'); assert.equal(s.maxQuantity, 99); assert.match(s.priceLabel, /^12,00/);
  s.intent = 'inquiry'; s.inquiryAllowed = true; s.quantity = '';
  assert.equal(s.needsQuantity, false); assert.equal(s.quantityError, ''); assert.equal(s.submitDisabled, false);
  assert.equal(s.reducedDisabled, true);
});

test('mixed request payload and network retry keep counts and identity', async () => {
  const f = fixture();
  f.input('quantity', '1'); f.input('reduced_quantity', '2'); f.form(); f.offline(true);
  await run(f.actions.submit({ preventDefault() {} }));
  assert.equal(f.state.submitted, false); assert.match(f.state.status, /Netzwerkfehler/);
  assert.equal(f.state.quantity, '1'); assert.equal(f.state.reducedQuantity, '2');
  assert.equal(f.requests[0].tickets, 3); assert.equal(f.requests[0].quantity, 3); assert.equal(f.requests[0].reduced_tickets, 2);
  assert.equal(f.requests[0].amount_cents, undefined);
  f.offline(false);
  await run(f.actions.submit({ preventDefault() {} }));
  assert.deepEqual(f.requests[1], f.requests[0]); assert.equal(f.state.submitted, true);
});

test('invalid mixed quantities never invoke request transport', async () => {
  const f = fixture();
  f.input('quantity', '4'); f.input('reduced_quantity', '2'); f.form();
  await run(f.actions.submit({ preventDefault() {} }));
  assert.equal(f.requests.length, 0);
});

test('special appointment keeps its title in time selection and request heading', () => {
  const { state: s } = fixture();
  s.slots[0].title = 'Fixture – Spezial';
  assert.equal(s.title, 'Fixture – Spezial');
  assert.match(s.times[0].displayLabel, /Fixture – Spezial/);
  s.slotId = '';
  assert.equal(s.title, 'Fixture');
});

test('changing slot, calendar day and source resets reduced quantities', async () => {
  const f = fixture();
  f.state.reducedQuantity = 3;
  f.context({ slot: f.state.slots[0] });
  await run(f.actions.selectSlot());
  assert.equal(f.state.reducedQuantity, 0); assert.equal(f.state.quantity, 1);
  f.state.reducedQuantity = 3;
  f.context({ calendar: { inline: false }, cell: { value: '2026-12-04', disabled: false } });
  await run(f.actions.pickCalendarDay());
  assert.equal(f.state.reducedQuantity, 0); assert.equal(f.state.slotId, '');
  f.state.reducedQuantity = 3;
  await run(f.actions.openSource({ id: 13, type: 'publication', intent: 'order' }, null));
  assert.equal(f.state.reducedQuantity, 0); assert.equal(f.state.quantity, 1);
});


test('online booking redirects to provider checkout and retains identity on network retry', async () => {
  const f = fixture({ mollieEnabled: true });
  f.input('payment', 'mollie'); f.form(); f.offline(true);
  assert.equal(f.state.onlinePayment, true);
  assert.equal(f.state.submitLabel, 'Weiter zur Testzahlung');
  await run(f.actions.submit({ preventDefault() {} }));
  assert.equal(f.state.submitted, false);
  f.offline(false); f.answer({ ok: true, checkout_url: 'https://www.mollie.com/checkout/fixture' });
  await run(f.actions.submit({ preventDefault() {} }));
  assert.equal(f.requests[0].payment, 'mollie');
  assert.equal(f.requests[0].request_key, f.requests[1].request_key);
  assert.equal(f.location.assigned, 'https://www.mollie.com/checkout/fixture');
  assert.equal(f.state.submitted, true);
  f.state.intent = 'order'; assert.equal(f.state.onlinePayment, false);
  f.state.intent = 'inquiry'; assert.equal(f.state.onlinePayment, false);
});

test('payment receipt separates verified paid, pending and failure states without implying seats', async () => {
  const f = fixture(); f.state.paymentId = '1'; f.state.paymentToken = 'fixture';
  for (const [status, expected] of [['paid', /Testzahlung erfolgreich/], ['pending', /noch verarbeitet/], ['failed', /fehlgeschlagen/], ['canceled', /abgebrochen/], ['expired', /abgelaufen/], ['refunded', /erstattet/]]) {
    f.answer({ ok: true, payment_status: status, booking_id: 1, title: 'Fixture', amount_cents: 2000 });
    await run(f.actions.refreshPayment());
    assert.match(f.state.status, expected);
    assert.equal(f.requests.at(-1).token, 'fixture');
    assert.match(f.state.paymentSummary, /20,00/);
  }
  f.offline(true); await run(f.actions.refreshPayment());
  assert.doesNotMatch(f.state.status, /erfolgreich/);
  assert.equal(f.state.checkoutUrl, '');
});
