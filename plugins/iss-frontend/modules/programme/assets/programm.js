document.addEventListener('DOMContentLoaded', () => {
  initExternalBookingTriggers();
  initOccurrenceCalendarTriggers();

  document.querySelectorAll('.is-tour-calendar:not([data-booking-host="1"])').forEach((widget) => {
    initCalendarWidget(widget);
  });
});

async function initCalendarWidget(widget) {
  if (!widget || !widget.dataset || widget.dataset.calendarInitialized === '1') {
    return;
  }

  widget.dataset.calendarInitialized = '1';

    let tag = widget.dataset.tag;
    const sourcePostId = (widget.dataset && (widget.dataset.sourcePostId || widget.dataset.postId)) ? String(widget.dataset.sourcePostId || widget.dataset.postId) : '';
    const sourcePostType = widget.dataset && widget.dataset.sourcePostType ? String(widget.dataset.sourcePostType) : '';
    const itemType = widget.dataset && widget.dataset.itemType ? String(widget.dataset.itemType) : '';

    const status = widget.querySelector('.is-tour-calendar__status');
    const dateInput = widget.querySelector('.is-tour-calendar__date-input');
    const selectedDateLabel = widget.querySelector('.is-tour-calendar__selected-date');
    const slotSelect = widget.querySelector('.is-tour-calendar__slot-select');
    const appointmentsTitleDate = widget.querySelector('.is-tour-calendar__appointments-title-date');
    const appointmentsList = widget.querySelector('.is-tour-calendar__appointments-list');
    const bookingPanel = widget.querySelector('.is-tour-calendar__booking');
    const fallbackLink = widget.querySelector('.is-tour-calendar__fallback-link');

    const calendarConfig = getOccurrenceCalendarConfig();
    const restUrl = calendarConfig.slotsUrl || '';

    tag = widget.dataset.tag;
    if (!tag && !sourcePostId) {
      widget.classList.add('is-tour-calendar--no-slots');

      let statusEl = status;
      if (!statusEl) {
        statusEl = document.createElement('p');
        statusEl.className = 'is-tour-calendar__status has-small-font-size';
        widget.prepend(statusEl);
      }

      renderStatus(statusEl, 'Keine Zuordnung vorhanden.');
      return;
    }

    if (!restUrl) {
      widget.classList.add('is-tour-calendar--no-slots');
      renderStatus(status, 'Kalender momentan nicht verfügbar.');
      return;
    }

    try {
      const params = new URLSearchParams();
      if (tag) {
        params.set('tag', tag);
      }
      if (sourcePostId) {
        params.set('source_post_id', sourcePostId);
      }
      if (sourcePostType) {
        params.set('source_post_type', sourcePostType);
      }
      if (itemType) {
        params.set('item_type', itemType);
      }
      const query = params.toString();

      const res = await fetch(`${restUrl}?${query}`, {
        credentials: 'same-origin'
      });

      const data = await res.json();

      const payload = (data && typeof data === 'object' && !Array.isArray(data))
        ? data
        : { source: '', slots: Array.isArray(data) ? data : [] };

      const source = payload && typeof payload.source === 'string' ? payload.source : '';
      const slotRows = Array.isArray(payload.slots) ? payload.slots : [];

      if (res.ok && source === 'nomap') {
        widget.classList.add('is-tour-calendar--no-slots');
        renderStatus(status, 'Keine Zuordnung vorhanden.');
        return;
      }

      if (!res.ok) {
        widget.classList.add('is-tour-calendar--no-slots');
        renderStatus(status, 'Kalender momentan nicht verfügbar.');
        return;
      }

      if (slotRows.length === 0) {
        widget.classList.add('is-tour-calendar--no-slots');
        if (payload && payload.inquiry && payload.inquiry.allowed) {
          renderStatus(status, 'Aktuell sind keine festen Termine verfügbar.');
          renderInquiryFallback(widget, payload);
        } else {
          renderStatus(status, 'Aktuell sind keine Termine verfügbar.');
        }
        return;
      }

      widget.classList.remove('is-tour-calendar--no-slots');

      const slots = slotRows
        .map(slot => {
          const d = parseDate(slot.start);
          if (!d) return null;
          return applyWidgetSlotDefaults(widget, { ...slot, _date: d });
        })
        .filter(Boolean)
        .sort((a, b) => a._date - b._date);

      const slotsByDay = groupSlotsByDay(slots);

      const dayKeys = Object.keys(slotsByDay).sort();
      let selectedDayKey = dayKeys.find((key) => (slotsByDay[key] || []).some(isSlotBookable)) || dayKeys[0] || null;

      widget.classList.remove('is-details-open');

		      function renderSlotSelect() {
		        if (!selectedDayKey) {
		          selectedDateLabel.textContent = 'Bitte wählen Sie einen Tag.';
		          if (appointmentsTitleDate) appointmentsTitleDate.textContent = '';
		          if (slotSelect) {
		            slotSelect.disabled = true;
		            slotSelect.innerHTML = '<option value="">Bitte zuerst ein Datum wählen</option>';
		          }
		          if (appointmentsList) appointmentsList.innerHTML = '';
		          if (bookingPanel) bookingPanel.innerHTML = '';
		          widget.classList.remove('is-details-open');
		          return;
		        }

		        if (!selectedDayKey || !slotsByDay[selectedDayKey]) {
		          selectedDateLabel.textContent = 'Für diesen Monat ist aktuell kein Termin verfügbar.';
		          if (slotSelect) {
		            slotSelect.disabled = true;
	            slotSelect.innerHTML = '<option value="">Bitte einen anderen Monat wählen</option>';
	          }
	          if (appointmentsList) {
	            appointmentsList.innerHTML = '<p class="is-tour-calendar__empty">Bitte wählen Sie einen anderen Monat.</p>';
	          }
	          return;
	        }

		        const daySlots = slotsByDay[selectedDayKey];
		        const bookableDaySlots = daySlots.filter(isSlotBookable);
		        const date = daySlots[0]._date;
		        const isSingleSlotDay = bookableDaySlots.length === 1;

		        const dateStr = date.toLocaleDateString('de-DE', {
		          weekday: 'long',
		          day: 'numeric',
		          month: 'long',
	          year: 'numeric'
	        });

        selectedDateLabel.textContent = formatSelectedDateLabel(widget, date, dateStr);
        if (appointmentsTitleDate) {
          appointmentsTitleDate.textContent = dateStr;
        }

		        const selectedSlotId = widget.dataset.selectedSlotId || '';
		        const selectedSlotExists = selectedSlotId && bookableDaySlots.some((s) => String(s.id ?? '') === selectedSlotId);

		        widget.classList.toggle('is-tour-calendar--single-slot', isSingleSlotDay);
		        widget.classList.add('is-details-open');

		        if (bookableDaySlots.length === 0) {
		          widget.dataset.selectedSlotId = '';
		          if (slotSelect) {
		            slotSelect.disabled = true;
		            slotSelect.innerHTML = '<option value="">Keine Termine verfügbar</option>';
		          }
		          if (appointmentsList) {
		            appointmentsList.innerHTML = '<p class="is-tour-calendar__empty">Keine Termine verfügbar</p>';
		          }
		          if (bookingPanel) bookingPanel.innerHTML = '';
		          return;
		        }

		        if (slotSelect) {
		          slotSelect.innerHTML = '';
		          slotSelect.disabled = false;

	          const placeholder = document.createElement('option');
	          placeholder.value = '';
	          placeholder.textContent = 'Uhrzeit wählen';
		          slotSelect.appendChild(placeholder);

		          bookableDaySlots.forEach((slot) => {
		            const opt = document.createElement('option');
		            opt.value = String(slot.id ?? '');

	            const time = formatSlotTimeRange(slot);
	            const meta = buildSlotMeta(slot);

	            opt.textContent = meta ? `${time} – ${meta}` : time;

	            slotSelect.appendChild(opt);
		          });

		          slotSelect.value = selectedSlotExists ? selectedSlotId : '';
		        }

		        if (appointmentsList) {
		          appointmentsList.innerHTML = '';

		          if (isSingleSlotDay) {
		            const slot = bookableDaySlots[0];
		            const btn = document.createElement('button');
		            btn.type = 'button';
		            btn.className = 'is-tour-calendar__time-btn';
		            btn.dataset.slotId = String(slot.id ?? '');

		            if (selectedSlotExists && String(slot.id ?? '') === selectedSlotId) {
		              btn.classList.add('is-selected');
		            }

		            btn.textContent = formatSlotTimeRange(slot) || '';

		            btn.addEventListener('click', () => {
		              widget.dispatchEvent(new CustomEvent('is:slotSelected', { detail: slot }));
		              openBookingForm(widget, slot, btn);
		            });

		            appointmentsList.appendChild(btn);
		          } else {
		            bookableDaySlots.forEach((slot) => {
		              const btn = document.createElement('button');
		              btn.type = 'button';
		              btn.className = 'is-tour-calendar__time-btn';
		              btn.dataset.slotId = String(slot.id ?? '');

		              if (selectedSlotExists && String(slot.id ?? '') === selectedSlotId) {
		                btn.classList.add('is-selected');
		              }

		              btn.textContent = formatSlotTimeRange(slot) || '';

		              btn.addEventListener('click', () => {
		                widget.dispatchEvent(new CustomEvent('is:slotSelected', { detail: slot }));
		                openBookingForm(widget, slot, btn);
		              });

		              appointmentsList.appendChild(btn);
		            });
		          }
		        }

		        if (!selectedSlotExists) {
		          widget.dataset.selectedSlotId = '';
		          if (bookingPanel) bookingPanel.innerHTML = '';
		        }
		      }

	      if (slotSelect) {
	        slotSelect.addEventListener('change', () => {
	          const slotId = slotSelect.value;
	          if (!slotId) {
	            widget.dataset.selectedSlotId = '';
	            if (bookingPanel) bookingPanel.innerHTML = '';
	            return;
	          }

	          const daySlots = (slotsByDay[selectedDayKey] || []).filter(isSlotBookable);
	          const slot = daySlots.find((s) => String(s.id ?? '') === slotId);
	          if (!slot) {
	            widget.dataset.selectedSlotId = '';
	            if (bookingPanel) bookingPanel.innerHTML = '';
	            return;
	          }

		          widget.dispatchEvent(new CustomEvent('is:slotSelected', { detail: slot }));
		          const safeId = (window.CSS && typeof window.CSS.escape === 'function')
		            ? window.CSS.escape(String(slotId))
		            : String(slotId).replace(/"/g, '\\"');
		          const clickedEl = appointmentsList
		            ? appointmentsList.querySelector(`[data-slot-id="${safeId}"]`)
		            : null;
			          openBookingForm(widget, slot, clickedEl);
			        });
			      }

      if (dateInput && window.flatpickr && dayKeys.length) {
        if (window.flatpickr.l10ns && window.flatpickr.l10ns.de) {
          window.flatpickr.localize(window.flatpickr.l10ns.de);
        }

	        const datePicker = window.flatpickr(dateInput, {
          inline: true,
          disableMobile: true,
          minDate: dayKeys[0],
          maxDate: dayKeys[dayKeys.length - 1],
          enable: [
            (date) => !!slotsByDay[dateKey(date)]
          ],
		          onChange: (dates) => {
		            if (!dates || !dates[0]) return;
		            selectedDayKey = dateKey(dates[0]);
		            renderSlotSelect();
		          }
	        });

	        if (selectedDayKey) {
	          datePicker.setDate(selectedDayKey, false, 'Y-m-d');
	        }

	        // Toggle: click the already-selected day to close details.
	        try {
	          const fp = dateInput._flatpickr;
	          if (fp && fp.calendarContainer) {
	            fp.calendarContainer.addEventListener('click', (e) => {
	              const day = e.target && e.target.closest ? e.target.closest('.flatpickr-day') : null;
	              if (!day || !day.classList || !day.classList.contains('selected')) return;
	              selectedDayKey = null;
	              widget.dataset.selectedSlotId = '';
	              if (bookingPanel) bookingPanel.innerHTML = '';
	              try { fp.clear(); } catch {}
	              renderSlotSelect();
	            });
	          }
	        } catch {}

	        widget.classList.add('is-has-flatpickr');
	      }

	      status.textContent = '';
	      if (fallbackLink) {
	        const wrap = fallbackLink.closest('.is-tour-calendar__fallback');
	        if (wrap) wrap.classList.add('is-hidden');
	      }
	      renderSlotSelect();

    } catch (e) {
      widget.classList.add('is-tour-calendar--no-slots');
      renderStatus(status, 'Kalender momentan nicht verfügbar.');
    }
}

function getOccurrenceCalendarConfig() {
  return window.ISS_OCCURRENCE_CALENDAR || {};
}

function renderStatus(el, msg) {
  if (!el) return;
  el.textContent = msg;
}

function renderInquiryFallback(widget) {
  const bookingPanel = widget && widget.querySelector
    ? widget.querySelector('.is-tour-calendar__booking')
    : null;
  if (!bookingPanel) return;

  bookingPanel.innerHTML = '';
  bookingPanel.appendChild(createInquiryForm(widget));
}

function parseDate(value) {
  if (!value) return null;
  const d = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
  return isNaN(d.getTime()) ? null : d;
}

function dateKey(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function groupSlotsByDay(slots) {
  const out = {};
  (slots || []).forEach((slot) => {
    if (!slot || !slot._date) return;
    const key = dateKey(slot._date);
    if (!out[key]) out[key] = [];
    out[key].push(slot);
  });
  return out;
}

function isSlotBookable(slot) {
  if (!slot) return false;

  if (slot.available !== null && Number(slot.available) <= 0) {
    return false;
  }

  const startDate = slot._date || parseDate(String(slot.start || ''));
  if (!startDate || Number.isNaN(startDate.getTime())) {
    return false;
  }

  return startDate.getTime() > Date.now();
}

function formatSlotTimeRange(slot) {
  if (!slot || !slot._date) return '';
  const start = slot._date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });

  const endVal = slot.end ?? null;
  const endDate = endVal ? parseDate(String(endVal)) : null;
  if (!endDate || Number.isNaN(endDate.getTime())) {
    return start;
  }

  const end = endDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
  return `${start} – ${end}`;
}

function formatSelectedDateLabel(widget, date, longDateLabel) {
  if (widget && widget.classList && widget.classList.contains('is-tour-calendar--modal')) {
    return date.toLocaleDateString('de-DE', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  }

  return 'Verfügbare Termine am ' + longDateLabel;
}

function buildSlotMeta(slot) {
  if (!slot) return '';

  if (slot.available !== null && slot.capacity !== null) {
    return slot.available <= 0
      ? 'Ausgebucht'
      : `${slot.available} von ${slot.capacity} frei`;
  }

  if (slot.available !== null) {
    return slot.available <= 0
      ? 'Ausgebucht'
      : `${slot.available} Plätze frei`;
  }

  return '';
}

function applyWidgetSlotDefaults(widget, slot) {
  if (!widget || !widget.dataset || !slot) {
    return slot;
  }

  const out = { ...slot };
  if (!out.amount && widget.dataset.amount) {
    out.amount = Number(widget.dataset.amount || 0);
  }
  if (!out.label && widget.dataset.label) {
    out.label = String(widget.dataset.label || '').trim();
  }
  if (!out.description && widget.dataset.description) {
    out.description = String(widget.dataset.description || '').trim();
  }
  return out;
}

function openBookingForm(widget, slot, clickedEl) {
  const bookingPanel = widget && widget.querySelector
    ? widget.querySelector('.is-tour-calendar__booking')
    : null;

  if (widget && widget.dataset) {
    widget.dataset.selectedSlotId = String(slot.id ?? '');
  }

  const slotSelect = widget && widget.querySelector
    ? widget.querySelector('.is-tour-calendar__slot-select')
    : null;
  if (slotSelect) {
    slotSelect.value = String(slot.id ?? '');
  }

  if (widget && widget.querySelectorAll) {
    widget.querySelectorAll('.is-tour-calendar__slot-card.is-selected, .is-tour-calendar__appointment.is-selected, .is-tour-calendar__time-btn.is-selected').forEach((el) => {
      el.classList.remove('is-selected');
    });
  }

  if (clickedEl) {
    clickedEl.classList.add('is-selected');
  }

  const formWrap = createBookingForm(widget, slot);

  const modal = getTourCalendarModal();
  if (modal && modal.open(formWrap, slot)) {
    if (bookingPanel) {
      bookingPanel.innerHTML = '';
    }
    return;
  }

  if (!bookingPanel) return;

  bookingPanel.innerHTML = '';
  bookingPanel.appendChild(formWrap);
}

function escapeHtml(str){ const d=document.createElement('div'); d.innerText=String(str); return d.innerHTML; }

let __isTourCalendarModal = null;

function getTourCalendarModal() {
  if (__isTourCalendarModal) return __isTourCalendarModal;

  const root = document.querySelector('[data-shared-tour-calendar-modal="1"]');
  if (!root) {
    return null;
  }

  const content = root.querySelector('.is-tour-calendar-modal__content');
  const closeBtns = root.querySelectorAll('[data-close="1"]');
  let lastActive = null;

  function close() {
    if (root.hidden) return;
    root.hidden = true;
    root.classList.remove('is-open');
    document.documentElement.classList.remove('is-tour-calendar-modal-open');
    if (content) content.innerHTML = '';
    if (lastActive && typeof lastActive.focus === 'function') lastActive.focus();
    lastActive = null;
  }

  closeBtns.forEach((btn) => {
    btn.addEventListener('click', close);
  });

  document.addEventListener('keydown', (e) => {
    if (!root.hidden && e.key === 'Escape') close();
  });

  function open(node) {
    try {
      lastActive = document.activeElement;
      if (content) {
        content.innerHTML = '';
        content.appendChild(node);
      }
      root.hidden = false;
      root.classList.add('is-open');
      document.documentElement.classList.add('is-tour-calendar-modal-open');

      const first = root.querySelector('input, select, textarea, button');
      if (first && typeof first.focus === 'function') first.focus();
      return true;
    } catch {
      close();
      return false;
    }
  }

  __isTourCalendarModal = { open, close };
  return __isTourCalendarModal;
}

function formatBookingDateLabel(slot) {
  if (!slot) return '';

  const startDate = slot._date || parseDate(String(slot.start || ''));
  if (!startDate) return '';

  const dateLabel = startDate.toLocaleDateString('de-DE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
  const timeLabel = formatSlotTimeRange({ ...slot, _date: startDate });

  return timeLabel ? `${dateLabel} · ${timeLabel} Uhr` : dateLabel;
}

function buildTicketOptions(max = 8, selected = 1) {
  let out = '';
  for (let i = 1; i <= max; i += 1) {
    out += `<option value="${i}" ${i === selected ? 'selected' : ''}>${i}</option>`;
  }
  return out;
}

function formatCents(amount) {
  const cents = Number(amount || 0);
  if (!Number.isFinite(cents) || cents <= 0) return '';
  return `${(cents / 100).toFixed(2).replace('.', ',')} €`;
}

function createBookingForm(widget, slot) {
  const startISO = slot._date ? slot._date.toISOString() : (slot.start || '');
  const calendarConfig = getOccurrenceCalendarConfig();
  const postUrl = calendarConfig.requestUrl || '';
  const hasBookUrl = !!postUrl;
  const sourcePostId = (widget && widget.dataset && widget.dataset.sourcePostId) ? widget.dataset.sourcePostId : String(slot.source_post_id || '');
  const sourcePostType = (widget && widget.dataset && widget.dataset.sourcePostType) ? widget.dataset.sourcePostType : String(slot.source_post_type || '');
  const bookingTitle = escapeHtml((slot.title || 'Termin').trim() || 'Termin');
  const bookingDate = escapeHtml(formatBookingDateLabel(slot));
  const ticketOptions = buildTicketOptions(8, 1);
  const amountCents = Number(slot.amount || 0);
  const priceLabel = formatCents(amountCents);
  const summaryPrice = priceLabel
    ? `<p class="is-tour-calendar__booking-summary-price">${escapeHtml(priceLabel)} pro Ticket</p>`
    : '';
  const bookingDescription = String(slot.description || '').trim();
  const paymentCopy = bookingDescription || (priceLabel ? 'Zahlung derzeit vor Ort. Mollie ist vorbereitet und folgt nach der Provider-Anbindung.' : 'Zahlung derzeit vor Ort. Online-Zahlung folgt später.');
  const paymentField = priceLabel
    ? `<fieldset class="is-tour-calendar__field is-tour-calendar__field--full">
        <legend class="is-tour-calendar__field-label">Zahlung</legend>
        <label><input type="radio" name="payment" value="onsite" checked> Vor Ort</label>
        <label><input type="radio" name="payment" value="mollie" disabled aria-disabled="true"> Mollie (bald)</label>
      </fieldset>`
    : '<input type="hidden" name="payment" value="onsite">';
  const submitLabel = escapeHtml(String(slot.label || 'Buchung anfragen').trim() || 'Buchung anfragen');

  const wrap = document.createElement('div');
  wrap.innerHTML = `
    <div class="is-tour-calendar__booking-sheet">
      <div class="is-tour-calendar__booking-topbar">
        <p class="is-tour-calendar__booking-kicker">Buchung anfragen</p>
      </div>
      <div class="is-tour-calendar__booking-body">
        <p class="is-tour-calendar__booking-intro">Bitte bestätigen Sie Ihre Anfrage für folgenden Termin:</p>
        <div class="is-tour-calendar__booking-summary">
          <p class="is-tour-calendar__booking-summary-title">${bookingTitle}</p>
          <p class="is-tour-calendar__booking-summary-meta">
            <span class="iss-icon iss-icon--calendar iss-icon--sm" aria-hidden="true"></span>
            <span>${bookingDate}</span>
          </p>
          ${summaryPrice}
        </div>
        <form class="is-tour-calendar__form" novalidate>
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" hidden>
          <input type="hidden" name="intent" value="booking">
          <input type="hidden" name="selection_mode" value="slot">
          <input type="hidden" name="loaded_at" value="${Math.floor(Date.now() / 1000)}">
          <input type="hidden" name="slot_id" value="${escapeHtml(slot.id ?? '')}">
          <input type="hidden" name="start" value="${escapeHtml(startISO)}">
          <input type="hidden" name="end" value="${escapeHtml(slot.end ?? '')}">
          <input type="hidden" name="title" value="${escapeHtml(slot.title || '')}">
          <input type="hidden" name="source_post_id" value="${escapeHtml(sourcePostId)}">
          <input type="hidden" name="source_post_type" value="${escapeHtml(sourcePostType)}">

          <div class="is-tour-calendar__form-section">
            <div class="is-tour-calendar__form-heading">
              <h3 class="is-tour-calendar__form-title">Ihre Angaben <span class="is-tour-calendar__field-required" aria-hidden="true">*</span></h3>
              <p class="is-tour-calendar__form-copy">Wir verwenden Ihre Angaben nur für diese Buchungsanfrage.</p>
            </div>
            <div class="is-tour-calendar__form-grid">
              <label class="is-tour-calendar__field">
                <span class="is-tour-calendar__field-label">Vorname</span>
                <input required name="first_name" type="text" autocomplete="given-name" placeholder="Vorname">
              </label>
              <label class="is-tour-calendar__field">
                <span class="is-tour-calendar__field-label">Nachname</span>
                <input required name="last_name" type="text" autocomplete="family-name" placeholder="Nachname">
              </label>
              <label class="is-tour-calendar__field is-tour-calendar__field--full">
                <span class="is-tour-calendar__field-label">E-Mail</span>
                <input required name="email" type="email" autocomplete="email" placeholder="name@beispiel.de">
              </label>
            </div>
          </div>

          <div class="is-tour-calendar__form-section">
            <div class="is-tour-calendar__form-heading">
              <h3 class="is-tour-calendar__form-title">Tickets</h3>
              <p class="is-tour-calendar__form-copy">${escapeHtml(paymentCopy)}</p>
            </div>
            <div class="is-tour-calendar__form-grid is-tour-calendar__form-grid--compact">
              <label class="is-tour-calendar__field">
                <span class="is-tour-calendar__field-label">Anzahl Tickets</span>
                <select name="tickets" autocomplete="off">
                  ${ticketOptions}
                </select>
              </label>
              ${paymentField}
            </div>
          </div>

          <div class="is-tour-calendar__form-actions">
            <button type="submit" class="is-tour-calendar__form-submit" ${hasBookUrl ? '' : 'disabled aria-disabled="true"'}>${submitLabel}</button>
            <button type="button" class="is-tour-calendar__form-cancel">Abbrechen</button>
          </div>
          <p class="is-tour-calendar__form-status" aria-live="polite"></p>
        </form>
      </div>
    </div>
  `;

  const form = wrap.querySelector('form');
  const status = wrap.querySelector('.is-tour-calendar__form-status');
  const cancelBtn = wrap.querySelector('.is-tour-calendar__form-cancel');
  if (!form || !status) return wrap;

  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      const modal = getTourCalendarModal();
      if (modal) modal.close();
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
      return;
    }
    if (!postUrl) { status.textContent = 'Buchung momentan nicht verfügbar.'; return; }
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    payload.name = [payload.first_name, payload.last_name]
      .map((value) => String(value || '').trim())
      .filter(Boolean)
      .join(' ')
      .trim();
    payload.tickets = Number(payload.tickets || 1);
    delete payload.first_name;
    delete payload.last_name;

    if (!payload.name) {
      status.textContent = 'Bitte Vor- und Nachname eingeben.';
      return;
    }

    try {
      status.textContent = 'Sende ...';
      const res = await fetch(postUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(calendarConfig.nonce ? { 'X-WP-Nonce': calendarConfig.nonce } : {})
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data || data.ok !== true) {
        status.textContent = (data && data.error) ? data.error : 'Fehler bei der Buchung.';
        return;
      }
      status.textContent = 'Danke! Ihre Anfrage wurde gesendet.';
      form.reset();

      const modal = getTourCalendarModal();
      if (modal) modal.close();
    } catch {
      status.textContent = 'Netzwerkfehler. Bitte später erneut versuchen.';
    }
  });

  return wrap;
}

function createInquiryForm(widget) {
  const calendarConfig = getOccurrenceCalendarConfig();
  const postUrl = calendarConfig.requestUrl || '';
  const sourcePostId = (widget && widget.dataset && widget.dataset.sourcePostId) ? widget.dataset.sourcePostId : '';
  const sourcePostType = (widget && widget.dataset && widget.dataset.sourcePostType) ? widget.dataset.sourcePostType : '';
  const title = (widget && widget.dataset && widget.dataset.title) ? widget.dataset.title : 'Terminanfrage';
  const hasPostUrl = !!postUrl;

  const wrap = document.createElement('div');
  wrap.innerHTML = `
    <div class="is-tour-calendar__booking-sheet">
      <div class="is-tour-calendar__booking-body">
        <p class="is-tour-calendar__booking-intro">Senden Sie uns eine Anfrage mit Ihrem Wunschtermin.</p>
        <form class="is-tour-calendar__form" novalidate>
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" hidden>
          <input type="hidden" name="intent" value="inquiry">
          <input type="hidden" name="selection_mode" value="preferred_date">
          <input type="hidden" name="loaded_at" value="${Math.floor(Date.now() / 1000)}">
          <input type="hidden" name="title" value="${escapeHtml(title)}">
          <input type="hidden" name="source_post_id" value="${escapeHtml(sourcePostId)}">
          <input type="hidden" name="source_post_type" value="${escapeHtml(sourcePostType)}">

          <div class="is-tour-calendar__form-section">
            <div class="is-tour-calendar__form-heading">
              <h3 class="is-tour-calendar__form-title">Wunschtermin</h3>
              <p class="is-tour-calendar__form-copy">Wir prüfen den Termin und melden uns zurück.</p>
            </div>
            <div class="is-tour-calendar__form-grid">
              <label class="is-tour-calendar__field">
                <span class="is-tour-calendar__field-label">Datum</span>
                <input required name="preferred_date" type="date">
              </label>
              <label class="is-tour-calendar__field">
                <span class="is-tour-calendar__field-label">Uhrzeit</span>
                <input name="preferred_time" type="time">
              </label>
              <label class="is-tour-calendar__field">
                <span class="is-tour-calendar__field-label">Vorname</span>
                <input required name="first_name" type="text" autocomplete="given-name" placeholder="Vorname">
              </label>
              <label class="is-tour-calendar__field">
                <span class="is-tour-calendar__field-label">Nachname</span>
                <input required name="last_name" type="text" autocomplete="family-name" placeholder="Nachname">
              </label>
              <label class="is-tour-calendar__field is-tour-calendar__field--full">
                <span class="is-tour-calendar__field-label">E-Mail</span>
                <input required name="email" type="email" autocomplete="email" placeholder="name@beispiel.de">
              </label>
            </div>
          </div>

          <div class="is-tour-calendar__form-actions">
            <button type="submit" class="is-tour-calendar__form-submit" ${hasPostUrl ? '' : 'disabled aria-disabled="true"'}>Anfrage senden</button>
            <button type="button" class="is-tour-calendar__form-cancel">Abbrechen</button>
          </div>
          <p class="is-tour-calendar__form-status" aria-live="polite"></p>
        </form>
      </div>
    </div>
  `;

  const form = wrap.querySelector('form');
  const status = wrap.querySelector('.is-tour-calendar__form-status');
  const cancelBtn = wrap.querySelector('.is-tour-calendar__form-cancel');
  if (!form || !status) return wrap;

  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      const modal = getTourCalendarModal();
      if (modal) modal.close();
    });
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
      return;
    }
    if (!postUrl) { status.textContent = 'Anfrage momentan nicht verfügbar.'; return; }

    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    payload.name = [payload.first_name, payload.last_name]
      .map((value) => String(value || '').trim())
      .filter(Boolean)
      .join(' ')
      .trim();
    payload.start = payload.preferred_time
      ? `${payload.preferred_date}T${payload.preferred_time}:00`
      : payload.preferred_date;
    delete payload.first_name;
    delete payload.last_name;

    if (!payload.name) {
      status.textContent = 'Bitte Vor- und Nachname eingeben.';
      return;
    }

    try {
      status.textContent = 'Sende ...';
      const res = await fetch(postUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(calendarConfig.nonce ? { 'X-WP-Nonce': calendarConfig.nonce } : {})
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data || data.ok !== true) {
        status.textContent = (data && data.error) ? data.error : 'Fehler bei der Anfrage.';
        return;
      }
      status.textContent = 'Danke! Ihre Anfrage wurde gesendet.';
      form.reset();

      const modal = getTourCalendarModal();
      if (modal) modal.close();
    } catch {
      status.textContent = 'Netzwerkfehler. Bitte später erneut versuchen.';
    }
  });

  return wrap;
}

function initOccurrenceCalendarTriggers() {
  const root = document.documentElement;
  if (root.dataset.issOccurrenceCalendarTriggersBound === '1') {
    return;
  }
  root.dataset.issOccurrenceCalendarTriggersBound = '1';

  document.addEventListener('click', (event) => {
    const trigger = event.target && event.target.closest
      ? event.target.closest('.js-iss-occurrence-calendar-trigger, .js-iss-tour-inquiry-trigger')
      : null;

    if (!trigger) {
      return;
    }

    event.preventDefault();

    if (String(trigger.dataset.calendarMode || '') === 'inquiry') {
      const context = document.createElement('div');
      context.dataset.title = String(trigger.dataset.title || 'Terminanfrage').trim() || 'Terminanfrage';
      if (trigger.dataset.sourcePostId) context.dataset.sourcePostId = String(trigger.dataset.sourcePostId);
      if (trigger.dataset.sourcePostType) context.dataset.sourcePostType = String(trigger.dataset.sourcePostType);

      const form = createInquiryForm(context);
      const modal = getTourCalendarModal();
      if (modal && modal.open(form)) {
        return;
      }

      document.body.appendChild(form);
      return;
    }

    const widget = createOccurrenceCalendarWidget(trigger.dataset || {});
    const modal = getTourCalendarModal();
    if (modal && modal.open(widget)) {
      initCalendarWidget(widget);
      return;
    }

    document.body.appendChild(widget);
    initCalendarWidget(widget);
  });
}

function createOccurrenceCalendarWidget(data) {
  const title = String(data.title || 'Termine wählen').trim() || 'Termine wählen';
  const widget = document.createElement('div');
  widget.className = 'is-tour-calendar is-tour-calendar--modal';
  widget.dataset.title = title;
  if (data.tag) widget.dataset.tag = String(data.tag);
  if (data.sourcePostId) widget.dataset.sourcePostId = String(data.sourcePostId);
  if (data.sourcePostType) widget.dataset.sourcePostType = String(data.sourcePostType);
  if (data.itemType) widget.dataset.itemType = String(data.itemType);
  if (data.amount) widget.dataset.amount = String(data.amount);
  if (data.label) widget.dataset.label = String(data.label);
  if (data.description) widget.dataset.description = String(data.description);

  widget.innerHTML = `
    <div class="is-tour-calendar__inner wp-block-group is-layout-constrained">
      <div class="is-tour-calendar__header wp-block-group is-layout-constrained">
        <p class="is-tour-calendar__eyebrow has-small-font-size">Termin wählen</p>
        <h3 class="is-tour-calendar__heading wp-block-heading">${escapeHtml(title)}</h3>
        <p class="is-tour-calendar__status has-small-font-size">Termine werden geladen ...</p>
      </div>
      <div class="is-tour-calendar__layout">
        <div class="is-tour-calendar__calendar">
          <input type="text" class="is-tour-calendar__date-input" aria-label="Datum auswählen" />
          <div class="is-tour-calendar__slots-panel">
            <p class="is-tour-calendar__selected-date has-small-font-size">Bitte wählen Sie einen Tag.</p>
            <div class="is-tour-calendar__appointments">
              <p class="is-tour-calendar__appointments-title">
                <span class="is-tour-calendar__appointments-title-label">Verfügbare Termine am</span>
                <span class="is-tour-calendar__appointments-title-date"></span>
              </p>
              <div class="is-tour-calendar__appointments-divider" aria-hidden="true"></div>
              <div class="is-tour-calendar__appointments-list"></div>
            </div>
            <div class="is-tour-calendar__slot-select-wrap">
              <label class="is-tour-calendar__slot-label">Uhrzeit</label>
              <select class="is-tour-calendar__slot-select" disabled>
                <option value="">Bitte zuerst ein Datum wählen</option>
              </select>
            </div>
            <div class="is-tour-calendar__booking"></div>
          </div>
        </div>
      </div>
    </div>
  `;

  return widget;
}

function initExternalBookingTriggers() {
  const root = document.documentElement;
  if (root.dataset.isTourBookingTriggersBound === '1') {
    return;
  }
  root.dataset.isTourBookingTriggersBound = '1';

  document.addEventListener('click', (event) => {
    const trigger = event.target && event.target.closest
      ? event.target.closest('.js-is-tour-slot-trigger')
      : null;

    if (!trigger) {
      return;
    }

    const slotId = String(trigger.dataset.slotId || '').trim();
    const start = String(trigger.dataset.start || '').trim();
    const end = String(trigger.dataset.end || '').trim();
    if (!slotId || !start) {
      return;
    }

    const widget = resolveBookingWidget(trigger);
    if (!widget) {
      return;
    }

    event.preventDefault();

    const sourcePostId = String(trigger.dataset.sourcePostId || '').trim();
    const sourcePostType = String(trigger.dataset.sourcePostType || '').trim();
    if (sourcePostId) {
      widget.dataset.sourcePostId = sourcePostId;
    }
    if (sourcePostType) {
      widget.dataset.sourcePostType = sourcePostType;
    }

    const slot = {
      id: slotId,
      start,
      end,
      title: String(trigger.dataset.title || ''),
      requestKind: String(trigger.dataset.requestKind || '').trim(),
      amount: Number(trigger.dataset.amount || 0),
      label: String(trigger.dataset.label || '').trim(),
      description: String(trigger.dataset.description || '').trim(),
      available: null,
      capacity: null
    };

    const parsedStart = parseDate(start);
    if (parsedStart) {
      slot._date = parsedStart;
    }

    openBookingForm(widget, slot, trigger);
  });
}

function resolveBookingWidget(trigger) {
  const targetSelector = String(trigger.dataset.calendarTarget || '').trim();
  if (targetSelector) {
    try {
      const target = document.querySelector(targetSelector);
      if (target) {
        return target;
      }
    } catch {}
  }

  const scope = trigger.closest('.iss-tour-page, main, article');
  if (scope && scope.querySelector) {
    const inScope = scope.querySelector('.is-tour-calendar');
    if (inScope) {
      return inScope;
    }
  }

  const sharedModal = document.querySelector('[data-shared-tour-calendar-modal="1"]');
  if (sharedModal) {
    return sharedModal;
  }

  return document.querySelector('.is-tour-calendar');
}
