(function () {
  function getConfig() {
    return window.issRelationsAdmin || {};
  }

  function getStrings() {
    var config = getConfig();
    return config.strings || {};
  }

  function getString(key, fallback) {
    var strings = getStrings();
    return strings[key] || fallback;
  }

  function renumberRows(container) {
    var rows = container.querySelectorAll('[data-iss-relations-row]');
    rows.forEach(function (row, index) {
      row.querySelectorAll('[name]').forEach(function (field) {
        field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
      });
    });
  }

  function getRowsContainer(box) {
    return box.querySelector('[data-iss-relations-rows]');
  }

  function getTemplate(box) {
    return box.querySelector('[data-iss-relations-template]');
  }

  function getPlaceField(row) {
    return row.querySelector('select[name*="[place_id]"]');
  }

  function getStationObjectField(row) {
    return row.querySelector('[data-iss-relations-station-object]');
  }

  function getStationStoryField(row) {
    return row.querySelector('[data-iss-relations-station-story]');
  }

  function setSelectOptions(select, items, selectedId, emptyLabel) {
    if (!select) {
      return;
    }

    var fragment = document.createDocumentFragment();
    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = emptyLabel;
    fragment.appendChild(placeholder);

    items.forEach(function (item) {
      var option = document.createElement('option');
      option.value = String(item.id || '');
      option.textContent = item.title || '';
      if (selectedId && String(item.id) === String(selectedId)) {
        option.selected = true;
      }
      fragment.appendChild(option);
    });

    select.innerHTML = '';
    select.appendChild(fragment);
  }

  function fetchStationObjects(placeId, selectedId) {
    var config = getConfig();
    if (!config.ajaxUrl || !config.nonce || !placeId) {
      return Promise.resolve([]);
    }

    var url = new URL(config.ajaxUrl, window.location.origin);
    url.searchParams.set('action', 'iss_relations_station_objects');
    url.searchParams.set('nonce', config.nonce);
    url.searchParams.set('place_id', String(placeId));
    if (selectedId) {
      url.searchParams.set('selected_id', String(selectedId));
    }

    return window.fetch(url.toString(), {
      credentials: 'same-origin'
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('request_failed');
      }
      return response.json();
    }).then(function (payload) {
      if (!payload || !payload.success || !payload.data) {
        throw new Error('invalid_payload');
      }
      return Array.isArray(payload.data.objects) ? payload.data.objects : [];
    });
  }

  function fetchStationStories(placeId, selectedId) {
    var config = getConfig();
    if (!config.ajaxUrl || !config.nonce || !placeId) {
      return Promise.resolve([]);
    }

    var url = new URL(config.ajaxUrl, window.location.origin);
    url.searchParams.set('action', 'iss_relations_station_stories');
    url.searchParams.set('nonce', config.nonce);
    url.searchParams.set('place_id', String(placeId));
    if (selectedId) {
      url.searchParams.set('selected_id', String(selectedId));
    }

    return window.fetch(url.toString(), {
      credentials: 'same-origin'
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('request_failed');
      }
      return response.json();
    }).then(function (payload) {
      if (!payload || !payload.success || !payload.data) {
        throw new Error('invalid_payload');
      }
      return Array.isArray(payload.data.stories) ? payload.data.stories : [];
    });
  }

  function refreshStationObjectChoices(row, selectedId) {
    var placeField = getPlaceField(row);
    var objectField = getStationObjectField(row);

    if (!placeField || !objectField) {
      return;
    }

    var placeId = placeField.value;
    var activeSelectedId = selectedId || objectField.value || objectField.getAttribute('data-selected-object-id') || '';

    if (!placeId) {
      setSelectOptions(objectField, [], '', getString('objectPlaceholder', 'Objekt wählen'));
      objectField.setAttribute('data-selected-object-id', '');
      return;
    }

    setSelectOptions(objectField, [], '', getString('objectLoading', 'Objekte werden geladen …'));

    fetchStationObjects(placeId, activeSelectedId)
      .then(function (items) {
        var placeholder = items.length
          ? getString('objectPlaceholder', 'Objekt wählen')
          : getString('objectNone', 'Keine verknüpften Objekte für diesen Ort');
        setSelectOptions(objectField, items, activeSelectedId, placeholder);
        objectField.setAttribute('data-selected-object-id', objectField.value || '');
      })
      .catch(function () {
        setSelectOptions(objectField, [], '', getString('objectError', 'Objekte konnten nicht geladen werden'));
        objectField.setAttribute('data-selected-object-id', '');
      });
  }

  function refreshStationStoryChoices(row, selectedId) {
    var placeField = getPlaceField(row);
    var storyField = getStationStoryField(row);

    if (!placeField || !storyField) {
      return;
    }

    var placeId = placeField.value;
    var activeSelectedId = selectedId || storyField.value || storyField.getAttribute('data-selected-story-id') || '';

    if (!placeId) {
      setSelectOptions(storyField, [], '', getString('storyPlaceholder', 'Beitrag wählen'));
      storyField.setAttribute('data-selected-story-id', '');
      return;
    }

    setSelectOptions(storyField, [], '', getString('storyLoading', 'Beiträge werden geladen …'));

    fetchStationStories(placeId, activeSelectedId)
      .then(function (items) {
        var placeholder = items.length
          ? getString('storyPlaceholder', 'Beitrag wählen')
          : getString('storyNone', 'Keine verknüpften Beiträge für diesen Ort');
        setSelectOptions(storyField, items, activeSelectedId, placeholder);
        storyField.setAttribute('data-selected-story-id', storyField.value || '');
      })
      .catch(function () {
        setSelectOptions(storyField, [], '', getString('storyError', 'Beiträge konnten nicht geladen werden'));
        storyField.setAttribute('data-selected-story-id', '');
      });
  }

  function initRow(row) {
    if (!row) {
      return;
    }

    var objectField = getStationObjectField(row);
    var storyField = getStationStoryField(row);
    var placeField = getPlaceField(row);

    if (objectField) {
      refreshStationObjectChoices(row);
      objectField.addEventListener('change', function () {
        objectField.setAttribute('data-selected-object-id', objectField.value || '');
      });
    }

    if (storyField) {
      refreshStationStoryChoices(row);
      storyField.addEventListener('change', function () {
        storyField.setAttribute('data-selected-story-id', storyField.value || '');
      });
    }

    if (placeField) {
      placeField.addEventListener('change', function () {
        if (objectField) {
          objectField.setAttribute('data-selected-object-id', '');
        }
        if (storyField) {
          storyField.setAttribute('data-selected-story-id', '');
        }
        refreshStationObjectChoices(row, '');
        refreshStationStoryChoices(row, '');
      });
    }
  }

  function initBox(box) {
    if (!box) {
      return;
    }

    var rows = getRowsContainer(box);
    if (!rows) {
      return;
    }

    rows.querySelectorAll('[data-iss-relations-row]').forEach(initRow);
  }

  function addRow(box) {
    var rows = getRowsContainer(box);
    var template = getTemplate(box);

    if (!rows || !template) {
      return;
    }

    var wrapper = document.createElement('tbody');
    wrapper.innerHTML = template.innerHTML.trim();

    if (!wrapper.firstElementChild) {
      return;
    }

    rows.appendChild(wrapper.firstElementChild);
    initRow(rows.lastElementChild);
    renumberRows(rows);
  }

  function moveRow(row, direction) {
    if (!row || !row.parentNode) {
      return;
    }

    var sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) {
      return;
    }

    if (direction === 'up') {
      row.parentNode.insertBefore(row, sibling);
      return;
    }

    row.parentNode.insertBefore(sibling, row);
  }

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest('[data-iss-relations-add]');
    if (addButton) {
      var addBox = addButton.closest('[data-iss-relations-box]');
      if (addBox) {
        addRow(addBox);
      }
      event.preventDefault();
      return;
    }

    var removeButton = event.target.closest('[data-iss-relations-remove]');
    if (removeButton) {
      var removeRow = removeButton.closest('[data-iss-relations-row]');
      var removeBox = removeButton.closest('[data-iss-relations-box]');
      if (removeRow) {
        removeRow.remove();
      }
      if (removeBox) {
        renumberRows(getRowsContainer(removeBox));
      }
      event.preventDefault();
      return;
    }

    var moveButton = event.target.closest('[data-iss-relations-move]');
    if (moveButton) {
      var row = moveButton.closest('[data-iss-relations-row]');
      var box = moveButton.closest('[data-iss-relations-box]');
      moveRow(row, moveButton.getAttribute('data-iss-relations-move'));
      if (box) {
        renumberRows(getRowsContainer(box));
      }
      event.preventDefault();
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-iss-relations-box]').forEach(initBox);
  });
})();
