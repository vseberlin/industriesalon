document.addEventListener('DOMContentLoaded', function () {
  var rootNodes = document.querySelectorAll('[data-timeline-query]');
  if (!rootNodes.length) return;

  var restUrl = window.ISS_TIMELINE && window.ISS_TIMELINE.restUrl ? String(window.ISS_TIMELINE.restUrl) : '';
  if (!restUrl) return;

  function cloneData(value) {
    return JSON.parse(JSON.stringify(value || {}));
  }

  function normalizeTaxonomyFilters(filters) {
    if (!Array.isArray(filters)) return [];

    return filters
      .filter(function (rule) {
        return rule && rule.taxonomy;
      })
      .map(function (rule) {
        var terms = Array.isArray(rule.terms) ? rule.terms : [rule.terms];
        return {
          taxonomy: String(rule.taxonomy || ''),
          field: String(rule.field || 'slug'),
          terms: terms
            .map(function (term) {
              return String(term || '').trim();
            })
            .filter(Boolean),
          operator: String(rule.operator || 'IN'),
        };
      })
      .filter(function (rule) {
        return rule.taxonomy && rule.terms.length;
      });
  }

  function taxonomyRuleEquals(left, right) {
    if (!left || !right) return false;
    if (String(left.taxonomy || '') !== String(right.taxonomy || '')) return false;
    if (String(left.field || 'slug') !== String(right.field || 'slug')) return false;
    if (String(left.operator || 'IN') !== String(right.operator || 'IN')) return false;

    var leftTerms = Array.isArray(left.terms) ? left.terms.slice().sort() : [];
    var rightTerms = Array.isArray(right.terms) ? right.terms.slice().sort() : [];
    if (leftTerms.length !== rightTerms.length) return false;

    for (var index = 0; index < leftTerms.length; index += 1) {
      if (String(leftTerms[index]) !== String(rightTerms[index])) {
        return false;
      }
    }

    return true;
  }

  function padDatePart(value) {
    return String(value).padStart(2, '0');
  }

  function formatLocalDate(date) {
    return [
      date.getFullYear(),
      padDatePart(date.getMonth() + 1),
      padDatePart(date.getDate()),
    ].join('-');
  }

  function getRangePresetFilters(rangePreset, fallbackMonth) {
    var today = new Date();
    today.setHours(0, 0, 0, 0);

    if (rangePreset === 'today') {
      var todayValue = formatLocalDate(today);
      return {
        time_mode: 'range',
        date_start: todayValue + ' 00:00:00',
        date_end: todayValue + ' 23:59:59',
      };
    }

    if (rangePreset === 'week') {
      var start = new Date(today.getTime());
      var day = start.getDay() || 7;
      start.setDate(start.getDate() - day + 1);
      var end = new Date(start.getTime());
      end.setDate(start.getDate() + 6);

      return {
        time_mode: 'range',
        date_start: formatLocalDate(start) + ' 00:00:00',
        date_end: formatLocalDate(end) + ' 23:59:59',
      };
    }

    if (rangePreset === 'month') {
      return {
        time_mode: 'month',
        month: fallbackMonth || formatLocalDate(today).slice(0, 7),
      };
    }

    if (rangePreset === 'upcoming') {
      return {
        time_mode: 'upcoming',
      };
    }

    if (rangePreset === 'past') {
      return {
        time_mode: 'past',
      };
    }

    return {};
  }

  rootNodes.forEach(function (root) {
    var configRaw = root.getAttribute('data-config') || '';
    var config = {};

    try {
      config = JSON.parse(configRaw);
    } catch (err) {
      config = {};
    }

    var initialFilters = cloneData(config && config.filters ? config.filters : {});
    var baseFilters = cloneData(config && config.baseFilters ? config.baseFilters : initialFilters);

    var form = root.querySelector('[data-timeline-query-form]');
    var meta = root.querySelector('[data-timeline-query-meta]');
    var countNode = root.querySelector('[data-timeline-query-count]');
    var emptyNoteNode = root.querySelector('[data-timeline-query-empty-note]');
    var results = root.querySelector('[data-timeline-query-results]');
    var loadMoreWrap = root.querySelector('[data-timeline-query-load-more-wrap]');
    var loadMoreButton = root.querySelector('[data-timeline-query-load-more]');
    var presetButtons = root.querySelectorAll('[data-timeline-preset]');
    var defaultPresetButton = root.querySelector('[data-timeline-preset-default="true"]');
    var calendarMonthInput = root.querySelector('[data-calendar-bridge-month]');
    var calendarDayInput = root.querySelector('[data-calendar-bridge-day]');
    var calendarResetButton = root.querySelector('[data-calendar-bridge-reset]');
    var queryResetButton = root.querySelector('[data-timeline-query-reset]');
    if (!results) return;

    function formQuery(selector) {
      return form ? form.querySelector(selector) : null;
    }

    function formQueryAll(selector) {
      return form ? form.querySelectorAll(selector) : [];
    }

    var requestInFlight = null;
    var nextOffset = config && typeof config.initialNextOffset === 'number' ? config.initialNextOffset : 0;
    var calendarBridgeMode = '';
    var activePreset = null;

    function getCalendarDayFlatpickr() {
      return calendarDayInput && calendarDayInput._flatpickr ? calendarDayInput._flatpickr : null;
    }

    function getCalendarDayValue() {
      var picker = getCalendarDayFlatpickr();
      if (picker && calendarDayInput) {
        return calendarDayInput.value || '';
      }
      return calendarDayInput ? String(calendarDayInput.value || '') : '';
    }

    function setCalendarDayValue(value) {
      if (!calendarDayInput) return;
      var nextValue = String(value || '');
      var picker = getCalendarDayFlatpickr();
      if (picker) {
        if (!nextValue) {
          picker.clear(false);
          calendarDayInput.value = '';
          return;
        }
        picker.setDate(nextValue, false, 'Y-m-d');
        calendarDayInput.value = nextValue;
        return;
      }
      calendarDayInput.value = nextValue;
    }

    function setCalendarDayPickerDisabled(isDisabled) {
      var picker = getCalendarDayFlatpickr();
      if (picker && picker.altInput) {
        picker.altInput.disabled = !!isDisabled;
      }
    }

    function refreshFromCalendarDay() {
      var timeMode = getTimeModeInput();
      var dayValue = getCalendarDayValue();
      if (dayValue) {
        calendarBridgeMode = 'range';
        if (timeMode) {
          timeMode.value = 'range';
        }
        if (calendarMonthInput && /^\d{4}-\d{2}-\d{2}$/.test(dayValue)) {
          calendarMonthInput.value = dayValue.slice(0, 7);
        }
      } else {
        calendarBridgeMode = calendarMonthInput && calendarMonthInput.value ? 'month' : '';
        if (timeMode && calendarBridgeMode === 'month') {
          timeMode.value = 'month';
        }
      }
      syncMonthVisibility();
      if (!requestInFlight) {
        refresh({ append: false });
      }
    }

    function initCalendarDayPicker() {
      if (!calendarDayInput || !window.flatpickr) {
        return;
      }

      var locale = window.flatpickr.l10ns && window.flatpickr.l10ns.de
        ? window.flatpickr.l10ns.de
        : 'default';

      window.flatpickr(calendarDayInput, {
        locale: locale,
        altInput: true,
        altFormat: 'j. F Y',
        dateFormat: 'Y-m-d',
        allowInput: false,
        disableMobile: true,
        clickOpens: true,
        onChange: function () {
          refreshFromCalendarDay();
        },
      });
    }

    function getActiveTimeMode() {
      var timeModeInput = getTimeModeInput();
      if (timeModeInput) {
        return String(getControlValue(timeModeInput) || '');
      }

      if (calendarBridgeMode === 'range' || calendarBridgeMode === 'month') {
        return calendarBridgeMode;
      }

      return config && config.filters && config.filters.time_mode
        ? String(config.filters.time_mode)
        : '';
    }

    function getControlValue(input) {
      if (!input) return '';
      if (input.type === 'radio') {
        var checked = formQuery('[data-filter-key="' + input.getAttribute('data-filter-key') + '"]:checked');
        return checked ? checked.value : '';
      }
      return input.value;
    }

    function setControlValue(key, value) {
      if (!key) return;
      var inputs = formQueryAll('[data-filter-key="' + key + '"]');
      if (!inputs.length) return;

      var first = inputs[0];
      if (first.type === 'radio') {
        inputs.forEach(function (node) {
          node.checked = String(node.value) === String(value);
        });
        return;
      }

      first.value = value;
    }

    function setTaxonomyControlValue(taxonomy, values) {
      if (!taxonomy) return;

      var selectedValues = Array.isArray(values) ? values.map(String) : [];
      formQueryAll('[data-filter-taxonomy="' + taxonomy + '"]').forEach(function (input) {
        Array.prototype.forEach.call(input.options || [], function (option) {
          option.selected = selectedValues.indexOf(String(option.value || '')) !== -1;
        });
      });
    }

    function clearTaxonomyControlValue(taxonomy) {
      if (!taxonomy) return;
      setTaxonomyControlValue(taxonomy, []);
    }

    function syncMonthVisibility() {
      var monthSelect = formQuery('[data-filter-key="month"]');
      var monthFilter = formQuery('[data-timeline-month-filter]');
      if (!monthSelect) return;
      var showMonth = getActiveTimeMode() === 'month';
      monthSelect.hidden = !showMonth;
      if (monthFilter) {
        monthFilter.hidden = !showMonth;
      }
    }

    function getTimeModeInput() {
      return formQuery('[data-filter-key="time_mode"]');
    }

    function getMonthSelect() {
      return formQuery('[data-filter-key="month"]');
    }

    function syncCalendarBridge() {
      var monthSelect = getMonthSelect();
      var activeTimeMode = getActiveTimeMode();

      if (calendarMonthInput && monthSelect && calendarMonthInput.value !== monthSelect.value) {
        calendarMonthInput.value = monthSelect.value || '';
      }

      if (!calendarDayInput) return;

      if (activeTimeMode !== 'range') {
        setCalendarDayValue('');
        return;
      }

      var dateStart = config && config.filters && config.filters.date_start ? String(config.filters.date_start) : '';
      if (/^\d{4}-\d{2}-\d{2}/.test(dateStart)) {
        setCalendarDayValue(dateStart.slice(0, 10));
      }
    }

    function getPresetStateFromButton(button) {
      if (!button) return null;

      return {
        timeMode: button.getAttribute('data-preset-time-mode') || '',
        rangePreset: button.getAttribute('data-preset-range') || '',
        taxonomy: button.getAttribute('data-preset-taxonomy') || '',
        terms: (button.getAttribute('data-preset-terms') || '')
          .split(',')
          .map(function (term) {
            return term.trim();
          })
          .filter(Boolean),
      };
    }

    function mergePresetIntoFilters(filters, preset) {
      var nextFilters = cloneData(filters || {});
      if (!preset) return nextFilters;

      if (preset.timeMode) {
        nextFilters.time_mode = preset.timeMode;
      }

      if (preset.rangePreset) {
        var rangeFilters = getRangePresetFilters(preset.rangePreset, baseFilters && baseFilters.month ? String(baseFilters.month) : '');
        Object.keys(rangeFilters).forEach(function (key) {
          nextFilters[key] = rangeFilters[key];
        });
        if (rangeFilters.time_mode !== 'range') {
          delete nextFilters.date_start;
          delete nextFilters.date_end;
        }
        if (rangeFilters.time_mode !== 'month') {
          delete nextFilters.month;
        }
      }

      var taxonomyFilters = normalizeTaxonomyFilters(nextFilters.taxonomy_filters || []);
      if (preset.taxonomy) {
        taxonomyFilters = taxonomyFilters.filter(function (rule) {
          return rule.taxonomy !== preset.taxonomy;
        });

        if (preset.terms.length) {
          taxonomyFilters.push({
            taxonomy: preset.taxonomy,
            field: 'slug',
            terms: preset.terms.slice(),
            operator: 'IN',
          });
        }
      }

      nextFilters.taxonomy_filters = taxonomyFilters;
      return nextFilters;
    }

    function applyActivePresetToControls(previousPreset) {
      var nextMonth = initialFilters && initialFilters.month
        ? String(initialFilters.month)
        : (baseFilters && baseFilters.month ? String(baseFilters.month) : '');
      var presetRangeFilters = activePreset && activePreset.rangePreset
        ? getRangePresetFilters(activePreset.rangePreset, nextMonth)
        : {};
      var nextTimeMode = presetRangeFilters.time_mode
        ? presetRangeFilters.time_mode
        : (activePreset && activePreset.timeMode
          ? activePreset.timeMode
          : (initialFilters && initialFilters.time_mode ? String(initialFilters.time_mode) : 'upcoming'));
      var nextPresetMonth = presetRangeFilters.month ? String(presetRangeFilters.month) : nextMonth;

      setCalendarDayValue('');

      setControlValue('time_mode', nextTimeMode);

      if (nextPresetMonth !== '') {
        if (calendarMonthInput) {
          calendarMonthInput.value = nextPresetMonth;
        }
        setControlValue('month', nextPresetMonth);
      }

      if (previousPreset && previousPreset.taxonomy && (!activePreset || activePreset.taxonomy !== previousPreset.taxonomy)) {
        clearTaxonomyControlValue(previousPreset.taxonomy);
      }

      if (activePreset && activePreset.taxonomy) {
        setTaxonomyControlValue(activePreset.taxonomy, activePreset.terms);
      }

      calendarBridgeMode = nextTimeMode === 'range' || nextTimeMode === 'month' ? nextTimeMode : '';
      syncMonthVisibility();
      syncCalendarBridge();
    }

    function resetToInitialFilters() {
      var defaultMonth = initialFilters && initialFilters.month ? String(initialFilters.month) : '';
      var defaultTimeMode = initialFilters && initialFilters.time_mode ? String(initialFilters.time_mode) : 'upcoming';

      calendarBridgeMode = '';

      formQueryAll('[data-filter-key]').forEach(function (input) {
        var key = input.getAttribute('data-filter-key');
        if (!key) return;
        if (input.dataset.resetHandled === 'true') return;
        input.dataset.resetHandled = 'true';

        if (key === 'post_type') {
          var selectedPostType = initialFilters && Array.isArray(initialFilters.post_types) && initialFilters.post_types.length
            ? String(initialFilters.post_types[0])
            : '';
          setControlValue(key, selectedPostType);
          return;
        }

        if (key === 'month') {
          setControlValue(key, defaultMonth);
          return;
        }

        if (key === 'time_mode') {
          setControlValue(key, defaultTimeMode);
          return;
        }

        setControlValue(
          key,
          initialFilters && Object.prototype.hasOwnProperty.call(initialFilters, key)
            ? String(initialFilters[key] || '')
            : ''
        );
      });

      formQueryAll('[data-filter-key]').forEach(function (input) {
        delete input.dataset.resetHandled;
      });

      formQueryAll('[data-filter-taxonomy]').forEach(function (input) {
        Array.prototype.forEach.call(input.options || [], function (option) {
          option.selected = false;
        });
      });

      setCalendarDayValue('');
      if (calendarMonthInput) {
        calendarMonthInput.value = defaultMonth;
      }

      if (presetButtons.length) {
        var previousPreset = activePreset;
        setActivePreset(defaultPresetButton || null);
        applyActivePresetToControls(previousPreset);
      }

      config.filters = cloneData(initialFilters || {});
      syncMonthVisibility();
      syncCalendarBridge();
    }

    function buildPayload() {
      var payload = cloneData(config || {});
      payload.filters = cloneData(baseFilters || {});

      if (activePreset) {
        payload.filters = mergePresetIntoFilters(payload.filters, activePreset);
      }

      formQueryAll('[data-filter-key]').forEach(function (input) {
        var key = input.getAttribute('data-filter-key');
        if (!key) return;
        if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) return;
        if (key === 'post_type') {
          payload.filters.post_types = input.value ? [input.value] : [];
          return;
        }
        payload.filters[key] = input.value;
      });

      var calendarDayValue = getCalendarDayValue();
      if (calendarDayValue) {
        payload.filters.time_mode = 'range';
        payload.filters.date_start = calendarDayValue + ' 00:00:00';
        payload.filters.date_end = calendarDayValue + ' 23:59:59';
      } else if (payload.filters.time_mode === 'range' && payload.filters.date_start && payload.filters.date_end) {
        delete payload.filters.month;
      } else {
        delete payload.filters.date_start;
        delete payload.filters.date_end;

        if (calendarBridgeMode === 'month' && calendarMonthInput && calendarMonthInput.value) {
          payload.filters.time_mode = 'month';
          payload.filters.month = calendarMonthInput.value;
        } else if (payload.filters.time_mode !== 'month') {
          delete payload.filters.month;
        }
      }

      var visibleTaxonomyFilters = [];
      formQueryAll('[data-filter-taxonomy]').forEach(function (input) {
        var taxonomy = input.getAttribute('data-filter-taxonomy');
        if (!taxonomy) return;
        var values = input.multiple
          ? Array.prototype.slice.call(input.selectedOptions || [])
              .map(function (option) {
                return option.value;
              })
              .filter(Boolean)
          : [input.value].filter(Boolean);
        if (!values.length) return;
        visibleTaxonomyFilters.push({
          taxonomy: taxonomy,
          field: 'slug',
          terms: values,
          operator: 'IN',
        });
      });

      var baseTaxonomyFilters = normalizeTaxonomyFilters(baseFilters && baseFilters.taxonomy_filters ? baseFilters.taxonomy_filters : []);
      var effectiveTaxonomyFilters = normalizeTaxonomyFilters(payload.filters.taxonomy_filters || []);
      if (visibleTaxonomyFilters.length) {
        var visibleTaxonomies = visibleTaxonomyFilters.map(function (rule) {
          return rule.taxonomy;
        });

        effectiveTaxonomyFilters = effectiveTaxonomyFilters.filter(function (rule) {
          if (visibleTaxonomies.indexOf(rule.taxonomy) === -1) {
            return true;
          }

          return baseTaxonomyFilters.some(function (baseRule) {
            return taxonomyRuleEquals(baseRule, rule);
          });
        });
      }

      payload.filters.taxonomy_filters = effectiveTaxonomyFilters.concat(visibleTaxonomyFilters);

      if (!payload.render) payload.render = {};
      payload.render.yearGrouping = !!(payload.render && payload.render.yearGrouping);

      return payload;
    }

    function setBusy(isBusy) {
      root.classList.toggle('is-loading', !!isBusy);
      formQueryAll('select, input, button').forEach(function (control) {
        control.disabled = !!isBusy;
      });
      presetButtons.forEach(function (button) {
        button.disabled = !!isBusy;
      });
      if (calendarMonthInput) calendarMonthInput.disabled = !!isBusy;
      if (calendarDayInput) calendarDayInput.disabled = !!isBusy;
      setCalendarDayPickerDisabled(isBusy);
      if (calendarResetButton) calendarResetButton.disabled = !!isBusy;
    }

    function setActivePreset(button) {
      var previousPreset = activePreset;
      activePreset = null;

      presetButtons.forEach(function (node) {
        var isActive = !!button && node === button;
        node.classList.toggle('is-active', isActive);
        node.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        if (!isActive) return;
        activePreset = getPresetStateFromButton(node);
      });

      return previousPreset;
    }

    function updateMeta(data, options) {
      options = options || {};
      var appendEmpty = !!options.append && !!(data && data.isEmpty);

      if (countNode && data && typeof data.count === 'number') {
        countNode.textContent = data.count + ' ' + (data.count === 1 ? 'Eintrag' : 'Einträge');
      }
      if (emptyNoteNode) {
        emptyNoteNode.textContent = data && data.isEmpty && !appendEmpty ? 'Keine Einträge für die aktuelle Auswahl.' : '';
      }
      if (meta) {
        meta.classList.toggle('is-empty', !!(data && data.isEmpty && !appendEmpty));
      }
      nextOffset = data && typeof data.nextOffset === 'number' ? data.nextOffset : 0;
      if (loadMoreWrap) {
        loadMoreWrap.hidden = !(data && data.hasMore);
      }
    }

    function getYearLabel(group) {
      var label = group ? group.querySelector('.iss-timeline__year-label') : null;
      return label ? String(label.textContent || '').trim() : '';
    }

    function appendYearGroupContents(existingGroup, incomingGroup) {
      Array.prototype.slice.call(incomingGroup.childNodes).forEach(function (node) {
        if (node.nodeType === 1 && node.classList.contains('iss-timeline__year-label')) {
          return;
        }
        existingGroup.appendChild(node);
      });
    }

    function appendTimelineHtml(html) {
      var template = document.createElement('template');
      template.innerHTML = html;

      Array.prototype.slice.call(template.content.childNodes).forEach(function (node) {
        if (node.nodeType !== 1 || !node.classList.contains('iss-timeline__year')) {
          results.appendChild(node);
          return;
        }

        var incomingLabel = getYearLabel(node);
        var lastGroup = results.lastElementChild && results.lastElementChild.classList.contains('iss-timeline__year')
          ? results.lastElementChild
          : null;

        if (lastGroup && incomingLabel !== '' && getYearLabel(lastGroup) === incomingLabel) {
          appendYearGroupContents(lastGroup, node);
          return;
        }

        results.appendChild(node);
      });
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
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data && typeof data.html === 'string') {
            if (append && !data.isEmpty) {
              appendTimelineHtml(data.html);
            } else if (!append) {
              results.innerHTML = data.html;
            }
          }
          if (data && data.query && data.query.filters) {
            config.filters = data.query.filters;
            if (config.filters.time_mode === 'range') {
              calendarBridgeMode = 'range';
            } else if (config.filters.time_mode === 'month') {
              calendarBridgeMode = 'month';
            } else {
              calendarBridgeMode = '';
            }
          }
          updateMeta(data, { append: append });
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

    if (form) {
      form.addEventListener('change', function () {
        syncMonthVisibility();
        if (requestInFlight) return;
        refresh({ append: false });
      });
    }

    initCalendarDayPicker();

    if (calendarMonthInput) {
      calendarMonthInput.addEventListener('change', function () {
        var timeMode = getTimeModeInput();
        var monthSelect = getMonthSelect();
        setCalendarDayValue('');
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

    if (calendarDayInput && !getCalendarDayFlatpickr()) {
      calendarDayInput.addEventListener('change', function () {
        refreshFromCalendarDay();
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
        resetToInitialFilters();
        if (requestInFlight) return;
        refresh({ append: false });
      });
    }

    if (queryResetButton) {
      queryResetButton.addEventListener('click', function () {
        resetToInitialFilters();
        if (requestInFlight) return;
        refresh({ append: false });
      });
    }

    if (loadMoreButton) {
      loadMoreButton.addEventListener('click', function () {
        if (requestInFlight) return;
        if (loadMoreWrap && loadMoreWrap.hidden) return;
        refresh({ append: true });
      });
    }

    if (presetButtons.length) {
      if (defaultPresetButton) {
        setActivePreset(defaultPresetButton);
      } else {
        presetButtons.forEach(function (button) {
          if (button.getAttribute('aria-pressed') === 'true') {
            setActivePreset(button);
          }
        });
      }

      if (activePreset) {
        applyActivePresetToControls(null);
      }

      presetButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          if (requestInFlight) return;
          var previousPreset = setActivePreset(button);
          applyActivePresetToControls(previousPreset);
          refresh({ append: false });
        });
      });
    }

    syncMonthVisibility();
    calendarBridgeMode = config && config.filters && config.filters.time_mode === 'range'
      ? 'range'
      : (config && config.filters && config.filters.time_mode === 'month' ? 'month' : '');
    syncCalendarBridge();
  });
});
