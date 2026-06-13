(function () {
  var archive = window.issArchiveSetsPicker || null;

  if (!archive) {
    return;
  }

  function intValue(value) {
    var parsed = parseInt(value, 10);
    return isNaN(parsed) ? 0 : parsed;
  }

  function uniqueIds(items) {
    var seen = {};
    return (items || []).map(function (item) {
      return intValue(item && item.id ? item.id : item);
    }).filter(function (id) {
      if (id <= 0 || seen[id]) {
        return false;
      }
      seen[id] = true;
      return true;
    });
  }

  function createObjectPicker(root, options) {
    options = options || {};

    var mode = options.mode === 'single' ? 'single' : 'multiple';
    var perPage = intValue(options.perPage || 24);
    var selectedIds = uniqueIds(options.initialSelection || []);
    var selectedItems = {};
    var lastPayload = null;

    root.innerHTML =
      '<div class="iss-archive-object-picker" data-mode="' + mode + '">' +
        '<div class="iss-archive-object-picker__filters">' +
          '<input type="search" class="regular-text iss-archive-object-picker__search" placeholder="Archivobjekte suchen">' +
          '<select class="iss-archive-object-picker__source" data-empty-label="Alle Quellen"></select>' +
          '<select class="iss-archive-object-picker__field" data-empty-label="Alle Themenfelder"></select>' +
          '<select class="iss-archive-object-picker__family" data-empty-label="Alle Objektfamilien"></select>' +
          '<select class="iss-archive-object-picker__context" data-empty-label="Alle Kontexte"></select>' +
          '<select class="iss-archive-object-picker__decade" data-empty-label="Alle Dekaden"></select>' +
          '<button type="button" class="button iss-archive-object-picker__run">Suchen</button>' +
        '</div>' +
        '<div class="iss-archive-object-picker__status description"></div>' +
        '<div class="iss-archive-object-picker__results"></div>' +
        '<div class="iss-archive-object-picker__pager">' +
          '<button type="button" class="button iss-archive-object-picker__more" hidden>Mehr laden</button>' +
        '</div>' +
        '<div class="iss-archive-object-picker__tray" hidden>' +
          '<strong class="iss-archive-object-picker__tray-count"></strong>' +
          '<div class="iss-archive-object-picker__tray-items"></div>' +
          '<button type="button" class="button button-primary iss-archive-object-picker__confirm">Auswahl uebernehmen</button>' +
        '</div>' +
      '</div>';

    var pickerRoot = root.querySelector('.iss-archive-object-picker');
    var status = root.querySelector('.iss-archive-object-picker__status');
    var results = root.querySelector('.iss-archive-object-picker__results');
    var moreButton = root.querySelector('.iss-archive-object-picker__more');
    var tray = root.querySelector('.iss-archive-object-picker__tray');
    var trayCount = root.querySelector('.iss-archive-object-picker__tray-count');
    var trayItems = root.querySelector('.iss-archive-object-picker__tray-items');
    var confirmButton = root.querySelector('.iss-archive-object-picker__confirm');
    var currentPage = 1;
    var currentItems = [];

    function selectors() {
      return {
        search: '.iss-archive-object-picker__search',
        source: '.iss-archive-object-picker__source',
        field: '.iss-archive-object-picker__field',
        family: '.iss-archive-object-picker__family',
        context: '.iss-archive-object-picker__context',
        decade: '.iss-archive-object-picker__decade'
      };
    }

    function setStatus(message) {
      status.textContent = message || '';
      status.hidden = !message;
    }

    function selectedObjects() {
      return selectedIds.map(function (id) {
        return selectedItems[id] || { id: id };
      });
    }

    function renderTray() {
      archive.clear(trayItems);
      tray.hidden = selectedIds.length === 0;
      trayCount.textContent = selectedIds.length === 1
        ? '1 Objekt ausgewaehlt'
        : String(selectedIds.length) + ' Objekte ausgewaehlt';

      selectedObjects().forEach(function (item) {
        var pill = archive.createElement('button', 'button iss-archive-object-picker__tray-item', item.title || 'Objekt #' + String(item.id));
        pill.type = 'button';
        pill.addEventListener('click', function () {
          toggleSelection(item);
        });
        trayItems.appendChild(pill);
      });
    }

    function syncResultSelection() {
      Array.prototype.forEach.call(results.querySelectorAll('.iss-archive-object-picker__item'), function (card) {
        var id = intValue(card.getAttribute('data-object-id'));
        var selected = selectedIds.indexOf(id) !== -1;
        var button = card.querySelector('.iss-archive-object-picker__select');
        card.classList.toggle('is-selected', selected);
        card.setAttribute('aria-selected', selected ? 'true' : 'false');
        if (button) {
          button.setAttribute('aria-pressed', selected ? 'true' : 'false');
          button.textContent = selected ? 'Ausgewaehlt' : 'Auswaehlen';
        }
      });
      renderTray();
    }

    function toggleSelection(item) {
      var id = intValue(item && item.id);
      var index;
      if (id <= 0) {
        return;
      }

      selectedItems[id] = item;
      index = selectedIds.indexOf(id);
      if (index !== -1) {
        selectedIds.splice(index, 1);
      } else if (mode === 'single') {
        selectedIds = [id];
      } else {
        selectedIds.push(id);
      }

      syncResultSelection();
      if (mode === 'single' && options.confirmOnSelect === true) {
        confirmSelection();
      }
    }

    function renderItem(item) {
      var card = archive.createElement('article', 'iss-archive-object-picker__item');
      var thumb = archive.createElement('div', 'iss-archive-object-picker__thumb');
      var body = archive.createElement('div', 'iss-archive-object-picker__body');
      var title = archive.createElement('strong', 'iss-archive-object-picker__title', item.title || 'Archivobjekt #' + String(item.id));
      var meta = [item.objectTypeLabel, item.inventoryNumber, item.yearLabel].filter(Boolean).join(' - ');
      var button = archive.createElement('button', 'button iss-archive-object-picker__select', 'Auswaehlen');

      card.setAttribute('data-object-id', String(item.id));
      if (item.thumbnail) {
        var image = document.createElement('img');
        image.src = item.thumbnail;
        image.alt = '';
        image.loading = 'lazy';
        thumb.appendChild(image);
      }
      card.appendChild(thumb);
      body.appendChild(title);
      if (meta) {
        body.appendChild(archive.createElement('p', 'description', meta));
      }
      if (item.excerpt) {
        body.appendChild(archive.createElement('p', 'description', item.excerpt));
      }
      card.appendChild(body);
      button.type = 'button';
      button.addEventListener('click', function () {
        toggleSelection(item);
      });
      card.appendChild(button);
      card.addEventListener('dblclick', function () {
        toggleSelection(item);
      });
      results.appendChild(card);
      selectedItems[intValue(item.id)] = item;
    }

    function renderItems(items, append) {
      if (!append) {
        archive.clear(results);
      }
      if (!items.length && !append) {
        results.appendChild(archive.createElement('p', 'description', 'Keine Archivobjekte gefunden.'));
        return;
      }
      items.forEach(renderItem);
      syncResultSelection();
    }

    function buildParams(page) {
      var params = archive.buildObjectPickerParams(pickerRoot, selectors(), {
        contextPostId: options.contextPostId || 0,
        perPage: perPage
      });
      params.set('page', String(page || 1));
      return params;
    }

    function load(page, append) {
      var params = buildParams(page);
      currentPage = page || 1;
      setStatus('Laedt...');

      return archive.searchObjects(params).then(function (payload) {
        var facets = payload.facets || {};
        var items = payload.items || [];
        var totalPages = intValue(payload.totalPages || payload.total_pages || 1);
        lastPayload = payload;
        currentItems = append ? currentItems.concat(items) : items;
        archive.fillObjectFacets(pickerRoot, selectors(), facets, params);
        renderItems(items, append);
        moreButton.hidden = currentPage >= totalPages || !items.length;
        setStatus('');
        return payload;
      }).catch(function (error) {
        setStatus(error.message || 'Archivsuche fehlgeschlagen.');
      });
    }

    function confirmSelection() {
      var result;
      if (!selectedIds.length) {
        setStatus('Waehle zuerst mindestens ein Archivobjekt aus.');
        return Promise.resolve();
      }

      if (typeof options.onConfirm === 'function') {
        confirmButton.disabled = true;
        setStatus('Auswahl wird uebernommen...');
        result = options.onConfirm(selectedObjects(), {
          selectedIds: selectedIds.slice(),
          lastPayload: lastPayload
        });

        return Promise.resolve(result).then(function (payload) {
          setStatus('');
          confirmButton.disabled = false;
          return payload;
        }).catch(function (error) {
          setStatus(error.message || 'Auswahl konnte nicht uebernommen werden.');
          confirmButton.disabled = false;
        });
      }

      return Promise.resolve();
    }

    root.querySelector('.iss-archive-object-picker__run').addEventListener('click', function () {
      load(1, false);
    });
    root.querySelector('.iss-archive-object-picker__search').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        load(1, false);
      }
    });
    moreButton.addEventListener('click', function () {
      load(currentPage + 1, true);
    });
    confirmButton.addEventListener('click', confirmSelection);

    if (options.autoLoad !== false) {
      load(1, false);
    }

    if (options.autoFocus === true) {
      root.querySelector('.iss-archive-object-picker__search').focus();
    }

    return {
      root: pickerRoot,
      refresh: function () {
        return load(1, false);
      },
      getSelectedObjects: selectedObjects,
      getSelectedIds: function () {
        return selectedIds.slice();
      },
      select: toggleSelection,
      clearSelection: function () {
        selectedIds = [];
        renderTray();
        syncResultSelection();
      },
      currentItems: function () {
        return currentItems.slice();
      }
    };
  }

  archive.createObjectPicker = createObjectPicker;
  window.issArchiveObjectPicker = {
    create: createObjectPicker
  };
}());
