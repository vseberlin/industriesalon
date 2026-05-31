(function () {
  function parseJson(raw, fallback) {
    if (!raw) {
      return fallback;
    }

    try {
      var decoded = JSON.parse(raw);
      return Array.isArray(fallback) ? (Array.isArray(decoded) ? decoded : fallback) : (decoded && typeof decoded === 'object' ? decoded : fallback);
    } catch (error) {
      return fallback;
    }
  }

  function createOptions(items, valueKey, labelKey, currentValue) {
    return items.map(function (item) {
      var value = item[valueKey] || '';
      var label = item[labelKey] || value;
      var selected = value === currentValue ? ' selected' : '';
      return '<option value="' + value + '"' + selected + '>' + label + '</option>';
    }).join('');
  }

  function normalizeList(value) {
    if (Array.isArray(value)) {
      return value.join('\n');
    }

    return typeof value === 'string' ? value : '';
  }

  function createRow(config, item) {
    var row = document.createElement('div');
    row.className = 'iss-register-epoch-row';
    row.innerHTML =
      '<div class="iss-register-epoch-row__top">' +
        '<strong>Zeitschicht</strong>' +
        '<div class="iss-register-epoch-row__actions">' +
          '<button type="button" class="button-link iss-register-epoch__up">Nach oben</button>' +
          '<button type="button" class="button-link iss-register-epoch__down">Nach unten</button>' +
          '<button type="button" class="button-link-delete iss-register-epoch__remove">Entfernen</button>' +
        '</div>' +
      '</div>' +
      '<div class="iss-register-epoch-row__grid">' +
        '<label>Epoche<select class="iss-register-epoch__era"><option value="">Bitte wählen</option>' + createOptions(config.eras, 'slug', 'label', item.era_slug || '') + '</select></label>' +
        '<label>Historischer Name / Identität<input type="text" class="widefat iss-register-epoch__name" value="' + (item.phase_name || '') + '"></label>' +
        '<label>Funktion<select class="iss-register-epoch__function"><option value="">Bitte wählen</option>' + createOptions(config.functions, 'key', 'label', item.function_key || '') + '</select></label>' +
        '<label>Startjahr<input type="number" class="small-text iss-register-epoch__start-year" value="' + (item.start_year || '') + '"></label>' +
        '<label>Endjahr<input type="number" class="small-text iss-register-epoch__end-year" value="' + (item.end_year || '') + '"></label>' +
        '<label>Sortierung<input type="number" class="small-text iss-register-epoch__sort-order" value="' + (item.sort_order || 0) + '"></label>' +
        '<label class="iss-register-epoch__current-wrap"><input type="checkbox" class="iss-register-epoch__current"' + (item.is_current ? ' checked' : '') + '> Aktuelle Phase</label>' +
        '<label>Quellen-Sicherheit<select class="iss-register-epoch__confidence"><option value="">Bitte wählen</option>' + createOptions(config.confidences, 'key', 'label', item.source_confidence || 'unknown') + '</select></label>' +
        '<label class="iss-register-epoch-row__full">Kurztext<textarea class="widefat iss-register-epoch__summary" rows="3">' + (item.summary || '') + '</textarea></label>' +
        '<label class="iss-register-epoch-row__full">Quellen-Zusammenfassung<textarea class="widefat iss-register-epoch__source-summary" rows="2">' + (item.source_summary || '') + '</textarea></label>' +
        '<label>Quellen-Links<textarea class="widefat iss-register-epoch__source-links" rows="3">' + normalizeList(item.source_links) + '</textarea></label>' +
        '<label>Media IDs<textarea class="widefat iss-register-epoch__media-ids" rows="3">' + normalizeList(item.media_ids) + '</textarea></label>' +
      '</div>' +
      '<input type="hidden" class="iss-register-epoch__id" value="' + (item.id || 0) + '">' ;

    return row;
  }

  function readTextareaList(value) {
    return String(value || '')
      .split(/\r\n|\r|\n|,/)
      .map(function (item) { return item.trim(); })
      .filter(Boolean);
  }

  function readRow(row) {
    return {
      id: parseInt(row.querySelector('.iss-register-epoch__id').value || '0', 10),
      era_slug: row.querySelector('.iss-register-epoch__era').value || '',
      phase_name: row.querySelector('.iss-register-epoch__name').value || '',
      function_key: row.querySelector('.iss-register-epoch__function').value || '',
      start_year: row.querySelector('.iss-register-epoch__start-year').value || '',
      end_year: row.querySelector('.iss-register-epoch__end-year').value || '',
      sort_order: parseInt(row.querySelector('.iss-register-epoch__sort-order').value || '0', 10),
      is_current: !!row.querySelector('.iss-register-epoch__current').checked,
      summary: row.querySelector('.iss-register-epoch__summary').value || '',
      source_confidence: row.querySelector('.iss-register-epoch__confidence').value || 'unknown',
      source_summary: row.querySelector('.iss-register-epoch__source-summary').value || '',
      source_links: readTextareaList(row.querySelector('.iss-register-epoch__source-links').value || ''),
      media_ids: readTextareaList(row.querySelector('.iss-register-epoch__media-ids').value || '')
    };
  }

  function syncState(group) {
    var rows = Array.prototype.slice.call(group.rows.querySelectorAll('.iss-register-epoch-row'));
    var payload = rows.map(readRow).filter(function (item) {
      return item.era_slug || item.phase_name || item.summary;
    });
    group.input.value = JSON.stringify(payload);

    var currentRows = rows.filter(function (row) {
      return !!row.querySelector('.iss-register-epoch__current').checked;
    });
    rows.forEach(function (row) {
      var current = row.querySelector('.iss-register-epoch__current');
      current.disabled = currentRows.length > 0 && !current.checked;
    });
  }

  function bindRow(group, row) {
    row.querySelectorAll('input,select,textarea').forEach(function (input) {
      input.addEventListener('input', function () {
        syncState(group);
      });
      input.addEventListener('change', function () {
        syncState(group);
      });
    });

    row.querySelector('.iss-register-epoch__remove').addEventListener('click', function () {
      row.remove();
      syncState(group);
    });

    row.querySelector('.iss-register-epoch__up').addEventListener('click', function () {
      if (row.previousElementSibling) {
        row.parentNode.insertBefore(row, row.previousElementSibling);
        syncState(group);
      }
    });

    row.querySelector('.iss-register-epoch__down').addEventListener('click', function () {
      if (row.nextElementSibling) {
        row.parentNode.insertBefore(row.nextElementSibling, row);
        syncState(group);
      }
    });
  }

  function addRow(group, item) {
    var row = createRow(group.config, item || {});
    group.rows.appendChild(row);
    bindRow(group, row);
    syncState(group);
  }

  function init(container) {
    var input = document.getElementById('iss-register-epoch-rows');
    var rows = container.querySelector('.iss-register-epochs__rows');
    var addButton = container.querySelector('.iss-register-epochs__add');

    if (!input || !rows || !addButton) {
      return;
    }

    var group = {
      config: {
        eras: parseJson(container.getAttribute('data-eras'), []),
        functions: parseJson(container.getAttribute('data-functions'), []),
        confidences: parseJson(container.getAttribute('data-confidences'), [])
      },
      input: input,
      rows: rows
    };

    parseJson(input.value, []).forEach(function (item) {
      addRow(group, item);
    });

    addButton.addEventListener('click', function () {
      addRow(group, {
        id: 0,
        era_slug: '',
        phase_name: '',
        function_key: '',
        start_year: '',
        end_year: '',
        sort_order: group.rows.querySelectorAll('.iss-register-epoch-row').length,
        is_current: false,
        summary: '',
        source_confidence: 'unknown',
        source_summary: '',
        source_links: [],
        media_ids: []
      });
    });

    syncState(group);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.iss-register-epochs').forEach(init);
  });
})();
