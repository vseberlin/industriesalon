document.addEventListener('DOMContentLoaded', function () {
  var rootNodes = document.querySelectorAll('[data-timeline-query]');
  if (!rootNodes.length) return;

  var restUrl = window.ISS_TIMELINE && window.ISS_TIMELINE.restUrl ? String(window.ISS_TIMELINE.restUrl) : '';
  if (!restUrl) return;

  rootNodes.forEach(function (root) {
    var configRaw = root.getAttribute('data-config') || '';
    var config = {};

    try {
      config = JSON.parse(configRaw);
    } catch (err) {
      config = {};
    }
    var initialFilters = config && config.filters ? JSON.parse(JSON.stringify(config.filters)) : {};

    var form = root.querySelector('[data-timeline-query-form]');
    var meta = root.querySelector('[data-timeline-query-meta]');
    var countNode = root.querySelector('[data-timeline-query-count]');
    var emptyNoteNode = root.querySelector('[data-timeline-query-empty-note]');
    var results = root.querySelector('[data-timeline-query-results]');
    var loadMoreWrap = root.querySelector('[data-timeline-query-load-more-wrap]');
    var loadMoreButton = root.querySelector('[data-timeline-query-load-more]');
    var calendarMonthInput = root.querySelector('[data-calendar-bridge-month]');
    var calendarDayInput = root.querySelector('[data-calendar-bridge-day]');
    var calendarResetButton = root.querySelector('[data-calendar-bridge-reset]');
    if (!form || !results) return;

    var requestInFlight = null;
    var nextOffset = 0;
    var calendarBridgeMode = '';

    function syncMonthVisibility() {
      var timeMode = form.querySelector('[data-filter-key="time_mode"]');
      var monthSelect = form.querySelector('[data-filter-key="month"]');
      if (!timeMode || !monthSelect) return;
      monthSelect.hidden = timeMode.value !== 'month';
    }

    function getTimeModeInput() {
      return form.querySelector('[data-filter-key="time_mode"]');
    }

    function getMonthSelect() {
      return form.querySelector('[data-filter-key="month"]');
    }

    function syncCalendarBridge() {
      var timeMode = getTimeModeInput();
      var monthSelect = getMonthSelect();

      if (calendarMonthInput && monthSelect && calendarMonthInput.value !== monthSelect.value) {
        calendarMonthInput.value = monthSelect.value || '';
      }

      if (!calendarDayInput) return;

      if (!timeMode || timeMode.value !== 'range') {
        calendarDayInput.value = '';
        return;
      }

      var dateStart = config && config.filters && config.filters.date_start ? String(config.filters.date_start) : '';
      if (/^\d{4}-\d{2}-\d{2}/.test(dateStart)) {
        calendarDayInput.value = dateStart.slice(0, 10);
      }
    }

    function buildPayload() {
      var payload = JSON.parse(JSON.stringify(config || {}));
      if (!payload.filters) payload.filters = {};

      form.querySelectorAll('[data-filter-key]').forEach(function (input) {
        var key = input.getAttribute('data-filter-key');
        if (!key) return;
        if (key === 'post_type') {
          payload.filters.post_types = input.value ? [input.value] : [];
          return;
        }
        payload.filters[key] = input.value;
      });

      if (calendarMonthInput && calendarMonthInput.value) {
        payload.filters.month = calendarMonthInput.value;
      }

      if (calendarDayInput && calendarDayInput.value) {
        payload.filters.time_mode = 'range';
        payload.filters.date_start = calendarDayInput.value + ' 00:00:00';
        payload.filters.date_end = calendarDayInput.value + ' 23:59:59';
      } else if (calendarBridgeMode === 'month' && calendarMonthInput && calendarMonthInput.value) {
        payload.filters.time_mode = 'month';
        delete payload.filters.date_start;
        delete payload.filters.date_end;
      } else {
        delete payload.filters.date_start;
        delete payload.filters.date_end;
      }

      var visibleTaxonomyFilters = [];
      form.querySelectorAll('[data-filter-taxonomy]').forEach(function (input) {
        var taxonomy = input.getAttribute('data-filter-taxonomy');
        var value = input.value;
        if (!taxonomy || !value) return;
        visibleTaxonomyFilters.push({
          taxonomy: taxonomy,
          field: 'slug',
          terms: [value],
          operator: 'IN'
        });
      });

      var presetTaxonomyFilters = Array.isArray(payload.filters.taxonomy_filters)
        ? payload.filters.taxonomy_filters.slice()
        : [];
      payload.filters.taxonomy_filters = presetTaxonomyFilters.concat(visibleTaxonomyFilters);

      if (!payload.render) payload.render = {};
      payload.render.yearGrouping = !!(payload.render && payload.render.yearGrouping);

      return payload;
    }

    function setBusy(isBusy) {
      root.classList.toggle('is-loading', !!isBusy);
      form.querySelectorAll('select').forEach(function (select) {
        select.disabled = !!isBusy;
      });
      if (calendarMonthInput) calendarMonthInput.disabled = !!isBusy;
      if (calendarDayInput) calendarDayInput.disabled = !!isBusy;
      if (calendarResetButton) calendarResetButton.disabled = !!isBusy;
    }

    function updateMeta(data) {
      if (countNode && data && typeof data.count === 'number') {
        countNode.textContent = data.count + ' ' + (data.count === 1 ? 'Eintrag' : 'Einträge');
      }
      if (emptyNoteNode) {
        emptyNoteNode.textContent = data && data.isEmpty ? 'Keine Einträge für die aktuelle Auswahl.' : '';
      }
      if (meta) {
        meta.classList.toggle('is-empty', !!(data && data.isEmpty));
      }
      nextOffset = data && typeof data.nextOffset === 'number' ? data.nextOffset : 0;
      if (loadMoreWrap) {
        loadMoreWrap.hidden = !(data && data.hasMore);
      }
    }

    function refresh(options) {
      options = options || {};
      var append = !!options.append;
      var payload = buildPayload();
      payload.offset = append ? nextOffset : 0;
      setBusy(true);

      requestInFlight = fetch(restUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data && typeof data.html === 'string') {
            if (append) {
              results.insertAdjacentHTML('beforeend', data.html);
            } else {
              results.innerHTML = data.html;
            }
          }
          if (data && data.query && data.query.filters) {
            config.filters = data.query.filters;
          }
          updateMeta(data);
          syncCalendarBridge();
        })
        .catch(function () {
          return null;
        })
        .finally(function () {
          requestInFlight = null;
          setBusy(false);
        });
    }

    form.addEventListener('change', function () {
      syncMonthVisibility();
      if (requestInFlight) return;
      refresh({ append: false });
    });

      if (calendarMonthInput) {
        calendarMonthInput.addEventListener('change', function () {
        var timeMode = getTimeModeInput();
        var monthSelect = getMonthSelect();
        if (calendarDayInput) {
          calendarDayInput.value = '';
        }
        calendarBridgeMode = 'month';
        if (monthSelect) {
          monthSelect.value = calendarMonthInput.value || monthSelect.value;
        }
        if (timeMode) {
          timeMode.value = 'month';
        }
        syncMonthVisibility();
        if (requestInFlight) return;
        refresh({ append: false });
      });
    }

    if (calendarDayInput) {
      calendarDayInput.addEventListener('change', function () {
        var timeMode = getTimeModeInput();
        calendarBridgeMode = calendarDayInput.value ? 'range' : '';
        if (timeMode && calendarDayInput.value) {
          timeMode.value = 'range';
        }
        syncMonthVisibility();
        if (requestInFlight) return;
        refresh({ append: false });
      });
    }

    if (calendarResetButton) {
      calendarResetButton.addEventListener('click', function () {
        var timeMode = getTimeModeInput();
        var monthSelect = getMonthSelect();
        var defaultMonth = initialFilters && initialFilters.month ? String(initialFilters.month) : '';

        calendarBridgeMode = '';
        if (calendarDayInput) {
          calendarDayInput.value = '';
        }
        if (calendarMonthInput) {
          calendarMonthInput.value = defaultMonth;
        }
        if (monthSelect) {
          monthSelect.value = defaultMonth;
        }
        if (timeMode) {
          timeMode.value = initialFilters && initialFilters.time_mode ? String(initialFilters.time_mode) : 'upcoming';
        }
        config.filters = JSON.parse(JSON.stringify(initialFilters || {}));
        syncMonthVisibility();
        if (requestInFlight) return;
        refresh({ append: false });
      });
    }

    if (loadMoreButton) {
      loadMoreButton.addEventListener('click', function () {
        if (requestInFlight) return;
        refresh({ append: true });
      });
    }

    syncMonthVisibility();
    calendarBridgeMode = config && config.filters && config.filters.time_mode === 'range'
      ? 'range'
      : (config && config.filters && config.filters.time_mode === 'month' ? 'month' : '');
    syncCalendarBridge();
  });
});
