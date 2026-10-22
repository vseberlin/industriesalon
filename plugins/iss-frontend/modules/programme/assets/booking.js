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
const timeLabel = (slot) => slot.time_label || `${String(slot.start || '').slice(11, 16)}${slot.end ? ` – ${String(slot.end).slice(11, 16)}` : ''} Uhr`;
const unavailableLabel = (slot) => slot.availability_state === 'cancelled' ? 'abgesagt' : slot.availability_state === 'unavailable' ? 'Buchung noch nicht geöffnet' : slot.available != null && Number(slot.available) <= 0 || slot.availability_state === 'sold_out' ? 'ausgebucht' : 'nicht verfügbar';
const price = cents => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100);

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
    voucherCode: '', paymentMethod: 'onsite', paymentReturn: false, paymentId: '', paymentToken: '', paymentSummary: '', checkoutUrl: '', paymentSourceUrl: '',
    customerName: '', email: '', preferredDate: '', preferredTime: '', quantity: 1, reducedQuantity: 0,
    get title() { return state.selectedSlot?.title || state.source?.title || 'Anfrage'; },
    get selectedSlot() { return state.slots.find((slot) => String(slot.id) === state.slotId && dayOf(slot) === state.day); },
    get isInquiry() { return state.intent === 'inquiry'; },
    get needsQuantity() { return !state.isInquiry; },
    get hidePreferred() { return !state.isInquiry; },
    get hideSlots() { return state.intent !== 'booking'; },
    get isBooking() { return state.intent === 'booking'; },
    get choosingDate() { return !state.paymentReturn && state.intent === 'booking' && !bookable(state.selectedSlot); },
    get step() { return state.choosingDate ? 'dates' : 'request'; },
    get headingLabel() { return state.paymentReturn ? 'Zahlungsstatus' : state.choosingDate ? 'Termin wählen' : state.intent === 'order' ? 'Publikation anfragen' : state.isInquiry ? 'Termin anfragen' : 'Plätze buchen'; },
    get canPayOnline() { return !!getConfig('iss/booking').mollieEnabled && state.intent === 'booking' && Number(state.selectedSlot?.amount) > 0; },
    get onlinePayment() { return state.canPayOnline && state.paymentMethod === 'mollie'; },
    get submitLabel() { return state.onlinePayment ? 'Weiter zur Testzahlung' : state.isBooking ? 'Verbindlich buchen' : 'Anfrage senden'; },
    get submitNotice() { return state.isBooking && state.selectedSlot?.test_mode && !state.onlinePayment ? 'Testbuchung: keine echte Reservierung und keine Zahlung. Dieser Termin dient der Funktionsprüfung.' : state.onlinePayment ? 'Testzahlung ohne echtes Geld. Ihre Plätze werden für 15 Minuten reserviert. Die Bestätigung erfolgt nach erfolgreicher Zahlung.' : state.isBooking ? 'Mit dem Absenden buchen Sie die ausgewählten Plätze. Den Restbetrag bezahlen Sie vor Ort.' : 'Mit dem Absenden stellen Sie eine Anfrage. Eine Buchung oder Zahlung ist damit noch nicht bestätigt.'; },
    get selectedTimeLabel() { return state.selectedSlot ? timeLabel(state.selectedSlot) : ''; },
    get noSlots() { return state.slots.length === 0; },
    get noDay() { return state.day === ''; },
    get hideRetry() { return !state.failed; },
    get hideInquirySwitch() { return !state.inquiryAllowed; },
    get fieldsDisabled() { return state.loading || state.submitting || state.submitted; },
    get submitDisabled() { return state.fieldsDisabled || !!state.quantityError || (state.intent === 'booking' && !bookable(state.selectedSlot)) || (state.isInquiry && !state.inquiryAllowed); },
    get hasReducedTickets() { return state.intent === 'booking' && Number(state.selectedSlot?.reduced_amount) > 0 && Number(state.selectedSlot.reduced_amount) < Number(state.selectedSlot.amount); },
    get hideReducedTickets() { return !state.hasReducedTickets; },
    get reducedDisabled() { return state.fieldsDisabled || !state.hasReducedTickets; },
    get quantityLabel() { return state.hasReducedTickets ? `Regulär (${price(state.selectedSlot.amount)})` : state.intent === 'order' ? 'Exemplare' : 'Tickets'; },
    get reducedQuantityLabel() { return state.hasReducedTickets ? `Ermäßigt (${price(state.selectedSlot.reduced_amount)})` : 'Ermäßigt'; },
    get minQuantity() { return state.hasReducedTickets ? 0 : 1; },
    get maxQuantity() { return state.intent === 'order' ? 99 : Math.max(1, Math.min(8, state.selectedSlot?.available ?? 8)); },
    get totalQuantity() { return Number(state.quantity) + (state.hasReducedTickets ? Number(state.reducedQuantity) : 0); },
    get quantityError() {
      if (state.isInquiry || state.choosingDate) return '';
      if (String(state.quantity).trim() === '' || !Number.isInteger(Number(state.quantity)) || Number(state.quantity) < state.minQuantity
        || (state.hasReducedTickets && (String(state.reducedQuantity).trim() === '' || !Number.isInteger(Number(state.reducedQuantity)) || Number(state.reducedQuantity) < 0))) return 'Bitte eine ganze Anzahl angeben.';
      if (state.totalQuantity < 1) return 'Bitte mindestens 1 Ticket wählen.';
      if (state.totalQuantity > state.maxQuantity) return `Bitte insgesamt höchstens ${state.maxQuantity} ${state.intent === 'order' ? 'Exemplare' : 'Tickets'} wählen.`;
      return '';
    },
    get priceLabel() {
      if (state.quantityError) return '';
      const unit = Number(state.selectedSlot?.amount ?? state.source?.amount ?? 0);
      const cents = unit * Number(state.quantity) + (state.hasReducedTickets ? Number(state.selectedSlot.reduced_amount) * Number(state.reducedQuantity) : 0);
      return cents > 0 ? `${price(cents)} · ${state.onlinePayment ? 'Testzahlung online' : 'Zahlung vor Ort'}` : 'Keine Online-Zahlung';
    },
    picker: { month: '', focusDay: '' },
    get selectedDateLabel() { return state.day ? new Intl.DateTimeFormat('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${state.day}T12:00:00Z`)) : 'Bitte einen verfügbaren Tag wählen.'; },
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
    get hideNextAvailable() { return state.calendarHasDates || !calendarSlots().some(slot => bookable(slot) && dayOf(slot).slice(0, 7) > state.calendarMonth); },
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
      const days = Array.from({ length: 42 }, (_, index) => {
        const number = index - offset + 1;
        if (number < 1 || number > count) return { key: `${month}-empty-${index}`, empty: true, number: new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth(), number)).getUTCDate() };
        const value = `${month}-${String(number).padStart(2, '0')}`;
        const slots = groups.get(value) || [];
        const free = value >= today() ? slots.filter(bookable).length : 0;
        const availability = free ? 'available' : slots.length ? 'unavailable' : 'empty';
        return { key: value, value, number, empty: false, disabled: !free, availability, selected: chosen === value,
          current: value === today() ? 'date' : undefined, tabIndex: focus === value ? 0 : -1,
          label: `${dateLabel({ start: value })} · ${free ? `${free} verfügbare ${free === 1 ? 'Uhrzeit' : 'Uhrzeiten'}` : slots.length ? 'ausgebucht oder nicht verfügbar' : 'keine Termine'}` };
      });
      return Array.from({ length: days.length / 7 }, (_, week) => ({ key: `${month}-${week}`, days: days.slice(week * 7, week * 7 + 7) }));
    },
    get times() {
      return state.slots.filter((slot) => dayOf(slot) === state.day).map((slot) => ({
        ...slot, disabled: !bookable(slot),
        displayLabel: `${timeLabel(slot)}${slot.title && slot.title !== state.source?.title ? ` · ${slot.title}` : ''}${bookable(slot) ? '' : ` · ${unavailableLabel(slot)}`}`,
      }));
    },
  },
  callbacks: {
    *paymentReturn() {
      const match = location.hash.match(/^#iss-payment=([1-9][0-9]*)\.([a-f0-9]{64})$/);
      if (!match || state.paymentReturn) return;
      generation += 1;
      Object.assign(state, { paymentReturn: true, paymentId: match[1], paymentToken: match[2], open: true, failed: false, source: { title: 'Ihre Testzahlung' } });
      if (!dialog().open) dialog().showModal();
      yield actions.refreshPayment();
    },
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
        voucherCode: '', paymentMethod: 'onsite', paymentReturn: false, checkoutUrl: '', paymentSummary: '', paymentSourceUrl: '',
        requestKey: Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join(''),
        loadedAt: Math.floor(Date.now() / 1000), customerName: '', email: '', preferredDate: '', preferredTime: '', quantity: 1, reducedQuantity: 0,
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
        state.day = selected ? dayOf(selected) : state.slots.some(slot => dayOf(slot) === source.day && bookable(slot)) ? source.day : state.slots.some(bookable) ? initialDay(state.slots) : '';
        const firstDay = state.day || initialDay(state.slots);
        state.picker = { month: firstDay.slice(0, 7), focusDay: firstDay };
        state.slotId = selected ? String(selected.id) : '';
        state.status = selected ? '' : payload.slots.length ? 'Bitte Datum und Uhrzeit wählen.' : payload.inquiry.allowed ? 'Aktuell sind keine festen Termine verfügbar. Sie können einen Wunschtermin anfragen.' : 'Aktuell sind keine buchbaren Termine verfügbar. Nutzen Sie bitte den Kontakt auf der Seite.';
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
        state.day = day.value; state.slotId = ''; state.quantity = 1; state.reducedQuantity = 0;
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
    *selectSlot() {
      const slot = getContext().slot;
      if (!bookable(slot)) return;
      const trigger = getElement().ref;
      state.slotId = String(slot.id); state.quantity = 1; state.reducedQuantity = 0; state.status = '';
      yield new Promise(resolve => requestAnimationFrame(resolve));
      if (document.activeElement === trigger || document.activeElement === document.body) dialog().querySelector('[name="name"]')?.focus();
    },
    *chooseInquiry() {
      if (!state.inquiryAllowed) return;
      const trigger = getElement().ref;
      state.intent = 'inquiry'; state.status = '';
      yield new Promise(resolve => requestAnimationFrame(resolve));
      if (document.activeElement === trigger || document.activeElement === document.body) dialog().querySelector('[name="preferred_date"]')?.focus();
    },
    *chooseBooking() {
      const trigger = getElement().ref;
      state.intent = 'booking'; state.slotId = ''; state.status = 'Bitte Datum und Uhrzeit wählen.';
      if (state.day) state.picker = { month: state.day.slice(0, 7), focusDay: state.day };
      yield new Promise(resolve => requestAnimationFrame(resolve));
      if (document.activeElement === trigger || document.activeElement === document.body) dialog().querySelector(`[data-calendar-day="${state.day}"]`)?.focus();
    },
    input() {
      const { ref } = getElement();
      const property = { name: 'customerName', email: 'email', preferred_date: 'preferredDate', preferred_time: 'preferredTime', quantity: 'quantity', reduced_quantity: 'reducedQuantity', payment: 'paymentMethod', voucher_code: 'voucherCode' }[ref.name];
      if (property) state[property] = ref.value;
    },
    *refreshPayment() {
      if (state.loading) return;
      const version = generation;
      state.loading = true; state.status = 'Zahlung wird geprüft …'; state.checkoutUrl = '';
      try {
        const response = yield fetch(getConfig('iss/booking').paymentStatusUrl, { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ booking_id: state.paymentId, token: state.paymentToken }) });
        const result = yield response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Zahlungsstatus derzeit nicht prüfbar.');
        if (version !== generation) return;
        const messages = { paid: 'Testzahlung erfolgreich.', open: 'Die Testzahlung ist noch offen.', pending: 'Die Testzahlung wird noch verarbeitet.', authorized: 'Die Testzahlung wurde autorisiert, aber noch nicht abgeschlossen.', failed: 'Die Testzahlung ist fehlgeschlagen. Es wurde keine Zahlung bestätigt.', canceled: 'Die Testzahlung wurde abgebrochen.', expired: 'Die Testzahlung ist abgelaufen.', creating: 'Die Zahlungsanfrage wird noch geprüft. Bitte kontaktieren Sie uns, falls dieser Status bestehen bleibt.', refunded: 'Die Testzahlung wurde erstattet.', partially_refunded: 'Die Testzahlung wurde teilweise erstattet.', charged_back: 'Für diese Testzahlung liegt eine Rückbuchung vor.' };
        state.status = messages[result.payment_status] || 'Die Zahlung ist noch nicht bestätigt.';
        if (result.booking_state === 'confirmed') state.status += ' Ihre Buchung ist bestätigt.';
        if (result.booking_state === 'payment_exception') state.status += ' Es konnten keine Plätze bestätigt werden. Bitte kontaktieren Sie uns zur Klärung oder Erstattung.';
        if (result.booking_state === 'expired') state.status += ' Die Platzreservierung ist abgelaufen.';
        if (result.booking_state === 'cancelled') state.status += ' Die Buchung wurde storniert.';
        if (result.booking_state === 'pending' && result.hold_expires_at) state.status += ` Plätze reserviert bis ${new Date(result.hold_expires_at.replace(' ', 'T') + 'Z').toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Berlin' })} Uhr.`;
        state.paymentSummary = `Buchung #${result.booking_id} · ${result.title} · ${price(result.amount_cents)}`;
        state.checkoutUrl = result.checkout_url || ''; state.paymentSourceUrl = result.source_url || '';
      } catch (error) { if (version === generation) state.status = error.message || 'Zahlungsstatus derzeit nicht prüfbar.'; }
      finally { if (version === generation) state.loading = false; }
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
        tickets: state.totalQuantity, quantity: state.totalQuantity,
        reduced_tickets: state.hasReducedTickets ? Number(state.reducedQuantity) : 0, payment: state.onlinePayment ? 'mollie' : 'onsite',
        slot_id: slot?.id || '', start: state.isInquiry ? `${values.preferred_date}${values.preferred_time ? `T${values.preferred_time}:00` : ''}` : slot?.start || '',
        voucher_code: state.intent === 'booking' ? (values.voucher_code || '') : '', website: values.website || '', loaded_at: state.loadedAt, request_key: state.requestKey,
      };
      state.submitting = true; state.status = state.intent === 'booking' ? 'Buchung wird gesendet …' : 'Anfrage wird gesendet …';
      try {
        const response = yield fetch(config.requestUrl, {
          method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce }, body: JSON.stringify(payload),
        });
        const result = yield response.json();
        if (!response.ok || !result.ok) throw new Error(response.status === 403 ? 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden und erneut versuchen.' : result.error || 'Die Anfrage wurde abgelehnt. Bitte prüfen Sie Ihre Angaben.');
        if (version === generation) {
          state.submitted = true;
          if (payload.payment === 'mollie' && result.booking_state !== 'confirmed') {
            state.status = 'Sie werden zur Testzahlung weitergeleitet …';
            location.assign(result.checkout_url || result.return_url);
          } else { state.status = result.booking_state === 'confirmed' ? `Ihre Buchung ${result.booking_reference || ''} ist bestätigt.${result.voucher_cents ? ' Gutschein angerechnet.' : ''}` : 'Vielen Dank. Ihre Anfrage wurde gespeichert. Wir melden uns zur Bestätigung bei Ihnen.'; }
        }
      } catch (error) {
        if (version === generation) state.status = error instanceof TypeError ? 'Netzwerkfehler. Bitte erneut versuchen; Ihre Angaben bleiben erhalten.' : error.message;
      } finally { if (version === generation) state.submitting = false; }
    }),
  },
});
