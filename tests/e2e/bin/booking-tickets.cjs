// Exercise the shipped store with a small Interactivity API adapter; no browser or network.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');
const { webcrypto } = require('node:crypto');

function fixture() {
  let definition, context = {}, element = {};
  const requests = [];
  let fail = false;
  const dialog = { open: false, showModal() { this.open = true; }, querySelector() { return null; } };
  const source = readFileSync(resolve(__dirname, '../../../plugins/iss-frontend/modules/programme/assets/booking.js'), 'utf8').replace(/^import[^\n]+\n/, '');
  runInNewContext(source, {
    store(namespace, value) { assert.equal(namespace, 'iss/booking'); definition = value; return value; },
    getContext: () => context, getElement: () => ({ ref: element }),
    getConfig: () => ({ today: '2026-10-20', requestUrl: '/fixture/request', nonce: 'fixture' }),
    withSyncEvent: fn => fn,
    document: { getElementById: () => dialog, activeElement: null, body: {} },
    requestAnimationFrame: fn => fn(), crypto: webcrypto, TypeError, URL,
    FormData: class { *[Symbol.iterator]() { yield* Object.entries(element.values || {}); } },
    async fetch(url, options) {
      requests.push(JSON.parse(options.body));
      if (fail) throw new TypeError('offline');
      return { ok: true, json: async () => ({ ok: true }) };
    },
  });
  const { state, actions } = definition;
  Object.assign(state, {
    source: { id: 12, type: 'fuehrung', intent: 'booking', title: 'Fixture' },
    slots: [{ id: 'slot', start: '2026-12-03T10:00:00', available: 5, amount: 1400, reduced_amount: 1000 }],
    slotId: 'slot', day: '2026-12-03', open: true, requestKey: 'fixture-request-0001',
  });
  return {
    state, actions, requests,
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
