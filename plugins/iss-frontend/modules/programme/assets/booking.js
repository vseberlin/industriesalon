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

// Day-only arithmetic deliberately stays in UTC; the occurrence owner supplies
// Berlin dates and the server supplies "today", independently of browser zone.
const today = () => getConfig('iss/booking').today;
const monthLabel = month => new Intl.DateTimeFormat('de-DE', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month}-01T12:00:00Z`));
const shiftMonth = (month, offset) => { const date = new Date(`${month}-01T12:00:00Z`); date.setUTCMonth(date.getUTCMonth() + offset); return date.toISOString().slice(0, 7); };
const initialDay = slots => slots.filter(slot => bookable(slot) && dayOf(slot) >= today()).map(dayOf).sort()[0] || slots.map(dayOf).sort()[0] || today();
const picker = () => getContext().calendar.inline ? getContext().calendar : state.picker;
const calendarSlots = () => getContext().calendar.inline ? state.catalog[scope(getContext().source)]?.slots || [] : state.slots;

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
    state.catalog[key] = payload;
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
    picker: { month: '', focusDay: '' },
    get selectedDateLabel() { return state.day ? dateLabel({ start: state.day }) : 'Bitte einen verfügbaren Tag wählen.'; },
    get calendarMonth() { return picker().month || initialDay(calendarSlots()).slice(0, 7); },
    get calendarLabel() { return monthLabel(state.calendarMonth); },
    get calendarMonths() {
      const months = calendarSlots().map(dayOf).filter(Boolean).map(day => day.slice(0, 7));
      const first = [today().slice(0, 7), ...months].sort()[0];
      const last = [shiftMonth(today().slice(0, 7), 12), ...months].sort().at(-1);
      const options = [];
      for (let month = first; month <= last; month = shiftMonth(month, 1)) options.push({ value: month, label: monthLabel(month), selected: month === state.calendarMonth });
      return options;
    },
    get calendarAtStart() { return state.calendarMonth <= state.calendarMonths[0].value; },
    get calendarAtEnd() { return state.calendarMonth >= state.calendarMonths.at(-1).value; },
    get calendarHasDates() { return calendarSlots().some(slot => bookable(slot) && dayOf(slot) >= today() && dayOf(slot).startsWith(state.calendarMonth)); },
    get hideNextAvailable() { return !calendarSlots().some(slot => bookable(slot) && dayOf(slot).slice(0, 7) > state.calendarMonth); },
    get calendarWeeks() {
      const month = state.calendarMonth;
      const first = new Date(`${month}-01T12:00:00Z`);
      const offset = (first.getUTCDay() + 6) % 7;
      const count = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
      const groups = new Map();
      for (const slot of calendarSlots()) {
        const day = dayOf(slot);
        if (!groups.has(day)) groups.set(day, []);
        groups.get(day).push(slot);
      }
      const chosen = getContext().calendar.inline ? picker().day : state.day;
      const focus = picker().focusDay.startsWith(month) ? picker().focusDay : chosen.startsWith(month) ? chosen : [...groups.keys()].sort().find(day => day.startsWith(month) && groups.get(day).some(bookable)) || `${month}-01`;
      const days = Array.from({ length: Math.ceil((offset + count) / 7) * 7 }, (_, index) => {
        const number = index - offset + 1;
        if (number < 1 || number > count) return { key: `${month}-empty-${index}`, empty: true };
        const value = `${month}-${String(number).padStart(2, '0')}`;
        const slots = groups.get(value) || [];
        const free = value >= today() ? slots.filter(bookable).length : 0;
        const availability = free ? 'available' : slots.length ? 'unavailable' : 'empty';
        return { key: value, value, number, empty: false, disabled: !free, availability, selected: chosen === value,
          current: value === today() ? 'date' : undefined, tabIndex: focus === value ? 0 : -1,
          mark: free ? '●' : slots.length ? '×' : '',
          label: `${dateLabel({ start: value })} · ${free ? `${free} verfügbare ${free === 1 ? 'Uhrzeit' : 'Uhrzeiten'}` : slots.length ? 'ausgebucht oder nicht verfügbar' : 'keine Termine'}` };
      });
      return Array.from({ length: days.length / 7 }, (_, week) => ({ key: `${month}-${week}`, days: days.slice(week * 7, week * 7 + 7) }));
    },
    get times() {
      return state.slots.filter((slot) => dayOf(slot) === state.day).map((slot) => ({
        ...slot, disabled: !bookable(slot),
        displayLabel: `${timeLabel(slot)}${bookable(slot) ? '' : ` · ${unavailableLabel(slot)}`}`,
      }));
    },
  },
  callbacks: {
    initCalendar() {
      const context = getContext();
      context.calendar.ready = true;
      if (context.calendar.inline) {
        const day = initialDay(calendarSlots());
        context.calendar.month = day.slice(0, 7); context.calendar.focusDay = day;
      }
    },
  },
  actions: {
    open: withSyncEvent(function* (event) {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
      const source = getContext().source;
      const host = dialog();
      if (!source?.id || !host || typeof host.showModal !== 'function') return;
      event.preventDefault();
      yield actions.openSource(source, getElement().ref);
    }),
    *openSource(source, trigger) {
      const host = dialog();
      generation += 1;
      opener = trigger;
      Object.assign(state, {
        source: { ...source }, slots: [], day: '', slotId: '', intent: source.intent, picker: { month: '', focusDay: '' },
        open: true, loading: false, failed: false, submitting: false, submitted: false,
        inquiryAllowed: false, status: '',
        requestKey: Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join(''),
        loadedAt: Math.floor(Date.now() / 1000), customerName: '', email: '', preferredDate: '', preferredTime: '', quantity: 1,
      });
      if (!host.open) host.showModal();
      if (state.intent !== 'order') yield actions.loadSlots();
    },
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
        state.day = selected ? dayOf(selected) : state.slots.some(slot => dayOf(slot) === source.day && bookable(slot)) ? source.day : '';
        const firstDay = state.day || initialDay(state.slots);
        state.picker = { month: firstDay.slice(0, 7), focusDay: firstDay };
        state.slotId = selected ? String(selected.id) : '';
        state.status = payload.slots.length ? 'Bitte Datum und Uhrzeit wählen.' : payload.inquiry.allowed ? 'Aktuell sind keine festen Termine verfügbar. Sie können einen Wunschtermin anfragen.' : 'Aktuell sind keine buchbaren Termine verfügbar. Nutzen Sie bitte den Kontakt auf der Seite.';
      } catch (error) {
        if (version === generation && state.open) { state.failed = true; state.status = error.message || 'Termine konnten nicht geladen werden.'; }
      } finally { if (version === generation) state.loading = false; }
    },
    *retrySlots() { yield actions.loadSlots(true); },
    *pickCalendarDay() {
      const context = getContext();
      const day = context.cell;
      if (day.disabled) return;
      picker().focusDay = day.value;
      if (context.calendar.inline) {
        context.calendar.day = day.value;
        yield actions.openSource({ ...context.source, day: day.value, slotId: '' }, getElement().ref);
      } else {
        state.day = day.value; state.slotId = ''; state.quantity = 1;
      }
    },
    focusCalendarDay() { picker().focusDay = getContext().cell.value; },
    moveCalendar() { picker().month = shiftMonth(state.calendarMonth, Number(getElement().ref.dataset.calendarStep)); picker().focusDay = `${picker().month}-01`; },
    changeCalendarMonth() { picker().month = getElement().ref.value; picker().focusDay = `${picker().month}-01`; },
    nextAvailableMonth() {
      const next = calendarSlots().filter(slot => bookable(slot) && dayOf(slot).slice(0, 7) > state.calendarMonth).map(dayOf).sort()[0];
      if (next) { picker().month = next.slice(0, 7); picker().focusDay = next; }
    },
    calendarKey: withSyncEvent(function* (event) {
      const { ref } = getElement();
      const current = getContext().cell.value;
      const date = new Date(`${current}T12:00:00Z`);
      const weekday = (date.getUTCDay() + 6) % 7;
      const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7, Home: -weekday, End: 6 - weekday }[event.key];
      let target;
      if (step !== undefined) { date.setUTCDate(date.getUTCDate() + step); target = date.toISOString().slice(0, 10); }
      else if (event.key === 'PageUp' || event.key === 'PageDown') {
        const month = shiftMonth(current.slice(0, 7), (event.key === 'PageUp' ? -1 : 1) * (event.shiftKey ? 12 : 1));
        const last = new Date(`${shiftMonth(month, 1)}-01T12:00:00Z`); last.setUTCDate(0);
        target = `${month}-${String(Math.min(date.getUTCDate(), last.getUTCDate())).padStart(2, '0')}`;
      } else return;
      event.preventDefault();
      if (target.slice(0, 7) < state.calendarMonths[0].value || target.slice(0, 7) > state.calendarMonths.at(-1).value) return;
      const calendar = ref.closest('.iss-availability-calendar');
      picker().month = target.slice(0, 7); picker().focusDay = target;
      yield new Promise(resolve => requestAnimationFrame(resolve));
      calendar.querySelector(`[data-calendar-day="${target}"]`)?.focus();
    }),
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
