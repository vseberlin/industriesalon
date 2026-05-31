(function () {
  function parseJson(raw, fallback) {
    if (!raw) {
      return fallback;
    }

    try {
      var decoded = JSON.parse(raw);
      return Array.isArray(fallback) ? (Array.isArray(decoded) ? decoded : fallback) : decoded;
    } catch (error) {
      return fallback;
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function createOptions(options, currentValue) {
    return Object.keys(options).map(function (key) {
      var selected = key === currentValue ? ' selected' : '';
      return '<option value="' + escapeHtml(key) + '"' + selected + '>' + escapeHtml(options[key]) + '</option>';
    }).join('');
  }

  function createEntityOptions(choices, currentValue) {
    var options = '<option value="">Profil waehlen</option>';

    choices.forEach(function (choice) {
      var value = String(choice.id || '');
      if (!value) {
        return;
      }

      var label = choice.name || ('Profil ' + value);
      var selected = value === String(currentValue || '') ? ' selected' : '';
      options += '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
    });

    return options;
  }

  function createActions() {
    return '' +
      '<div class="iss-graph-editor__actions">' +
        '<button type="button" class="button-link iss-graph-editor__up">Nach oben</button>' +
        '<button type="button" class="button-link iss-graph-editor__down">Nach unten</button>' +
        '<button type="button" class="button-link-delete iss-graph-editor__remove">Entfernen</button>' +
      '</div>';
  }

  function createNameRow(config, item) {
    var row = document.createElement('div');
    row.className = 'iss-graph-editor__row';
    row.innerHTML =
      '<div class="iss-graph-editor__row-top">' +
        '<strong>Name</strong>' +
        createActions() +
      '</div>' +
      '<div class="iss-graph-editor__grid iss-graph-editor__grid--name">' +
        '<label>Name<input type="text" class="widefat iss-graph-editor__name" value="' + escapeHtml(item.name || '') + '"></label>' +
        '<label>Typ<select class="iss-graph-editor__name-type">' + createOptions(config.nameTypes, item.name_type || 'historical') + '</select></label>' +
        '<label>Startjahr<input type="number" class="small-text iss-graph-editor__from-year" value="' + escapeHtml(item.valid_from_year || '') + '"></label>' +
        '<label>Endjahr<input type="number" class="small-text iss-graph-editor__to-year" value="' + escapeHtml(item.valid_to_year || '') + '"></label>' +
      '</div>';

    return row;
  }

  function createRelationRow(config, item) {
    var row = document.createElement('div');
    var hasEntityChoices = Array.isArray(config.entityChoices) && config.entityChoices.length > 0;
    var nameControl = hasEntityChoices
      ? '<label>Profil<select class="widefat iss-graph-editor__entity-id">' + createEntityOptions(config.entityChoices, item.entity_id || '') + '</select></label>'
      : '<label>Name<input type="text" class="widefat iss-graph-editor__name" value="' + escapeHtml(item.name || '') + '"></label>';

    row.className = 'iss-graph-editor__row';
    row.innerHTML =
      '<div class="iss-graph-editor__row-top">' +
        '<strong>' + escapeHtml(config.title) + '</strong>' +
        createActions() +
      '</div>' +
      '<div class="iss-graph-editor__grid iss-graph-editor__grid--relation">' +
        nameControl +
        '<label>Bezug<select class="iss-graph-editor__relation-type">' + createOptions(config.relationTypes, item.relation_type || 'related') + '</select></label>' +
        '<label>Startjahr<input type="number" class="small-text iss-graph-editor__from-year" value="' + escapeHtml(item.valid_from_year || '') + '"></label>' +
        '<label>Endjahr<input type="number" class="small-text iss-graph-editor__to-year" value="' + escapeHtml(item.valid_to_year || '') + '"></label>' +
        '<label class="iss-graph-editor__visibility"><input type="checkbox" class="iss-graph-editor__public"' + (item.is_public ? ' checked' : '') + '> Oeffentlich zeigen</label>' +
      '</div>';

    return row;
  }

  function readRow(group, row, index) {
    if (group.kind === 'names') {
      return {
        name: row.querySelector('.iss-graph-editor__name').value || '',
        name_type: row.querySelector('.iss-graph-editor__name-type').value || 'historical',
        valid_from_year: row.querySelector('.iss-graph-editor__from-year').value || '',
        valid_to_year: row.querySelector('.iss-graph-editor__to-year').value || '',
        position: index
      };
    }

    var entitySelect = row.querySelector('.iss-graph-editor__entity-id');
    var nameInput = row.querySelector('.iss-graph-editor__name');

    return {
      entity_id: entitySelect ? entitySelect.value || '' : '',
      name: nameInput ? nameInput.value || '' : '',
      relation_type: row.querySelector('.iss-graph-editor__relation-type').value || 'related',
      valid_from_year: row.querySelector('.iss-graph-editor__from-year').value || '',
      valid_to_year: row.querySelector('.iss-graph-editor__to-year').value || '',
      is_public: !!row.querySelector('.iss-graph-editor__public').checked,
      position: index
    };
  }

  function syncState(group) {
    var rows = Array.prototype.slice.call(group.rows.querySelectorAll('.iss-graph-editor__row'));
    var payload = rows.map(function (row, index) {
      return readRow(group, row, index);
    }).filter(function (item) {
      return String(item.entity_id || item.name || '').trim() !== '';
    });

    group.input.value = JSON.stringify(payload);
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

    row.querySelector('.iss-graph-editor__remove').addEventListener('click', function () {
      row.remove();
      syncState(group);
    });

    row.querySelector('.iss-graph-editor__up').addEventListener('click', function () {
      if (row.previousElementSibling) {
        row.parentNode.insertBefore(row, row.previousElementSibling);
        syncState(group);
      }
    });

    row.querySelector('.iss-graph-editor__down').addEventListener('click', function () {
      if (row.nextElementSibling) {
        row.parentNode.insertBefore(row.nextElementSibling, row);
        syncState(group);
      }
    });
  }

  function addRow(group, item) {
    var row = group.kind === 'names'
      ? createNameRow(group.config, item || {})
      : createRelationRow(group.config, item || {});

    group.rows.appendChild(row);
    bindRow(group, row);
    syncState(group);
  }

  function initGroup(container, section) {
    var kind = section.getAttribute('data-group');
    var rows = section.querySelector('.iss-graph-editor__rows');
    var addButton = section.querySelector('.iss-graph-editor__add');
    var inputId = section.getAttribute('data-input-id') || (kind === 'names'
      ? 'iss-graph-register-name-rows'
      : (kind === 'organizations' ? 'iss-graph-register-organization-rows' : 'iss-graph-register-person-rows'));
    var input = document.getElementById(inputId);

    if (!kind || !rows || !addButton || !input) {
      return;
    }

    var relationTypes = parseJson(section.getAttribute('data-relation-types'), null);
    if (!relationTypes) {
      relationTypes = kind === 'organizations'
        ? parseJson(container.getAttribute('data-organization-types'), {})
        : parseJson(container.getAttribute('data-person-types'), {});
    }

    var config = {
      nameTypes: parseJson(container.getAttribute('data-name-types'), {}),
      relationTypes: relationTypes,
      entityChoices: parseJson(section.getAttribute('data-entity-choices'), []),
      title: section.getAttribute('data-row-title') || (kind === 'organizations' ? 'Organisation' : 'Person')
    };

    var group = {
      kind: kind,
      config: config,
      rows: rows,
      input: input
    };

    parseJson(input.value, []).forEach(function (item) {
      addRow(group, item);
    });

    addButton.addEventListener('click', function () {
      addRow(group, kind === 'names'
        ? { name: '', name_type: 'historical', valid_from_year: '', valid_to_year: '', position: group.rows.querySelectorAll('.iss-graph-editor__row').length }
        : { name: '', relation_type: 'related', valid_from_year: '', valid_to_year: '', is_public: true, position: group.rows.querySelectorAll('.iss-graph-editor__row').length });
    });

    syncState(group);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.iss-graph-editor').forEach(function (container) {
      container.querySelectorAll('.iss-graph-editor__group').forEach(function (section) {
        initGroup(container, section);
      });
    });
  });
})();
