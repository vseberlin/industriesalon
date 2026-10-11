import { store, getContext, getElement, getConfig, withSyncEvent } from '@wordpress/interactivity';

const reads = new Map();
const cached = new Map();
const seeded = new Set();
let generation = 0;
let opener = null;
const scope = (source) => JSON.stringify([Number(source.id), source.type, source.tag || '']);
const dialog = () => document.getElementById('iss-booking-dialog');
const dayOf = (slot) => String(slot.start || '').slice(0, 10);
const bookable = (slot) => !!slot && !['cancelled', 'unavailable', 'sold_out'].includes(slot.availability_state) && (slot.available == null || Number(slot.available) > 0);
const dateLabel = (slot) => slot.date_label || new Intl.DateTimeFormat('de-DE', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${dayOf(slot)}T12:00:00Z`));
const timeLabel = (slot) => slot.time_label || `${String(slot.start || '').slice(11, 16)} Uhr`;
const unavailableLabel = (slot) => slot.availability_state === 'cancelled' ? 'abgesagt' : slot.available != null && Number(slot.available) <= 0 || slot.availability_state === 'sold_out' ? 'ausgebucht' : 'nicht verfügbar';

async function readSlots(source, force = false) {
  const key = scope(source);
  if (!seeded.has(key) && state.catalog[key]) {
    seeded.add(key);
    cached.set(key, { payload: state.catalog[key], time: Date.now() });
  }
  const previous = cached.get(key);
  if (!force && previous && Date.now() - previous.time < 60000) return previous.payload;
  if (reads.has(key)) return reads.get(key);
  const config = getConfig('iss/booking');
  const url = new URL(config.slotsUrl, location.href);
  url.searchParams.set('source_post_id', source.id);
  url.searchParams.set('source_post_type', source.type);
  if (source.tag) url.searchParams.set('tag', source.tag);
  const request = (async () => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('Termine konnten nicht geladen werden. Bitte erneut versuchen.');
    const payload = await response.json();
    if (!Array.isArray(payload.slots) || typeof payload.inquiry?.allowed !== 'boolean') throw new Error('Die Terminantwort ist unvollständig. Bitte erneut versuchen.');
    cached.set(key, { payload, time: Date.now() });
    return payload;
  })();
  reads.set(key, request);
  try { return await request; } finally { reads.delete(key); }
}

const { state, actions } = store('iss/booking', {
  state: {
    catalog: {}, source: null, slots: [], day: '', slotId: '', intent: 'booking',
    open: false, loading: false, failed: false, submitting: false, submitted: false,
    inquiryAllowed: false, status: '', requestKey: '', loadedAt: 0,
    customerName: '', email: '', preferredDate: '', preferredTime: '', quantity: 1,
    get title() { return state.source?.title || 'Anfrage'; },
    get selectedSlot() { return state.slots.find((slot) => String(slot.id) === state.slotId && dayOf(slot) === state.day); },
    get isInquiry() { return state.intent === 'inquiry'; },
    get needsQuantity() { return !state.isInquiry; },
    get hidePreferred() { return !state.isInquiry; },
    get hideSlots() { return state.intent !== 'booking'; },
    get noSlots() { return state.slots.length === 0; },
    get noDay() { return state.day === ''; },
    get hideRetry() { return !state.failed; },
    get hideInquirySwitch() { return !state.inquiryAllowed; },
    get fieldsDisabled() { return state.loading || state.submitting || state.submitted; },
    get submitDisabled() { return state.fieldsDisabled || (state.intent === 'booking' && !bookable(state.selectedSlot)) || (state.isInquiry && !state.inquiryAllowed); },
    get quantityLabel() { return state.intent === 'order' ? 'Exemplare' : 'Tickets'; },
    get maxQuantity() { return state.intent === 'order' ? 99 : Math.max(1, Math.min(8, state.selectedSlot?.available ?? 8)); },
    get priceLabel() {
      const cents = Number(state.selectedSlot?.amount ?? state.source?.amount ?? 0) * Number(state.quantity || 1);
      return cents > 0 ? `${new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100)} · Zahlung vor Ort` : 'Keine Online-Zahlung';
    },
    get dates() {
      const groups = new Map();
      for (const slot of state.slots) {
        const day = dayOf(slot);
        if (!groups.has(day)) groups.set(day, { value: day, label: dateLabel(slot), disabled: true });
        if (bookable(slot)) groups.get(day).disabled = false;
      }
      return [...groups.values()].map((date) => ({ ...date, label: date.label + (date.disabled ? ' · nicht verfügbar' : '') }));
    },
    get times() {
      return state.slots.filter((slot) => dayOf(slot) === state.day).map((slot) => ({
        ...slot, disabled: !bookable(slot),
        displayLabel: `${timeLabel(slot)}${bookable(slot) ? '' : ` · ${unavailableLabel(slot)}`}`,
      }));
    },
  },
  actions: {
    open: withSyncEvent(function* (event) {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
      const source = getContext().source;
      const host = dialog();
      if (!source?.id || !host || typeof host.showModal !== 'function') return;
      event.preventDefault();
      generation += 1;
      opener = getElement().ref;
      Object.assign(state, {
        source: { ...source }, slots: [], day: '', slotId: '', intent: source.intent,
        open: true, loading: false, failed: false, submitting: false, submitted: false,
        inquiryAllowed: false, status: '',
        requestKey: Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join(''),
        loadedAt: Math.floor(Date.now() / 1000), customerName: '', email: '', preferredDate: '', preferredTime: '', quantity: 1,
      });
      if (!host.open) host.showModal();
      if (state.intent !== 'order') yield actions.loadSlots();
    }),
    *loadSlots(force = false) {
      const version = generation;
      const source = { ...state.source };
      state.loading = true; state.failed = false; state.status = 'Termine werden geladen …';
      try {
        const payload = yield readSlots(source, force);
        if (version !== generation || !state.open) return;
        state.slots = payload.slots;
        state.inquiryAllowed = payload.inquiry.allowed;
        state.intent = payload.inquiry.allowed && (source.intent === 'inquiry' || payload.mode === 'inquiry') ? 'inquiry' : 'booking';
        const selected = state.slots.find((slot) => String(slot.id) === source.slotId && bookable(slot));
        state.day = selected ? dayOf(selected) : '';
        state.slotId = selected ? String(selected.id) : '';
        state.status = payload.slots.length ? 'Bitte Datum und Uhrzeit wählen.' : payload.inquiry.allowed ? 'Aktuell sind keine festen Termine verfügbar. Sie können einen Wunschtermin anfragen.' : 'Aktuell sind keine buchbaren Termine verfügbar. Nutzen Sie bitte den Kontakt auf der Seite.';
      } catch (error) {
        if (version === generation && state.open) { state.failed = true; state.status = error.message || 'Termine konnten nicht geladen werden.'; }
      } finally { if (version === generation) state.loading = false; }
    },
    *retrySlots() { yield actions.loadSlots(true); },
    selectDay() { state.day = getElement().ref.value; state.slotId = ''; state.quantity = 1; },
    selectSlot() { state.slotId = getElement().ref.value; state.quantity = 1; },
    chooseInquiry() { if (state.inquiryAllowed) { state.intent = 'inquiry'; state.status = ''; } },
    chooseBooking() { state.intent = 'booking'; state.status = ''; },
    input() {
      const { ref } = getElement();
      const property = { name: 'customerName', email: 'email', preferred_date: 'preferredDate', preferred_time: 'preferredTime', quantity: 'quantity' }[ref.name];
      if (property) state[property] = ref.value;
    },
    close() { dialog()?.close(); },
    closed() { generation += 1; state.open = false; opener?.focus(); opener = null; },
    submit: withSyncEvent(function* (event) {
      event.preventDefault();
      const form = getElement().ref;
      if (state.submitDisabled || !form.reportValidity()) return;
      const version = generation;
      const values = Object.fromEntries(new FormData(form));
      const source = state.source;
      const slot = state.selectedSlot;
      const config = getConfig('iss/booking');
      const payload = {
        intent: state.intent, source_post_id: source.id, source_post_type: source.type,
        tag: source.tag, title: source.title, name: values.name, email: values.email,
        tickets: Number(values.quantity || 1), quantity: Number(values.quantity || 1), payment: 'onsite',
        slot_id: slot?.id || '', start: state.isInquiry ? `${values.preferred_date}${values.preferred_time ? `T${values.preferred_time}:00` : ''}` : slot?.start || '',
        website: values.website || '', loaded_at: state.loadedAt, request_key: state.requestKey,
      };
      state.submitting = true; state.status = 'Anfrage wird gesendet …';
      try {
        const response = yield fetch(config.requestUrl, {
          method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce }, body: JSON.stringify(payload),
        });
        const result = yield response.json();
        if (!response.ok || !result.ok) throw new Error(response.status === 403 ? 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden und erneut versuchen.' : result.error || 'Die Anfrage wurde abgelehnt. Bitte prüfen Sie Ihre Angaben.');
        if (version === generation) { state.submitted = true; state.status = 'Vielen Dank. Ihre Anfrage wurde gespeichert. Wir melden uns zur Bestätigung bei Ihnen.'; }
      } catch (error) {
        if (version === generation) state.status = error instanceof TypeError ? 'Netzwerkfehler. Bitte erneut versuchen; Ihre Angaben bleiben erhalten.' : error.message;
      } finally { if (version === generation) state.submitting = false; }
    }),
  },
});
