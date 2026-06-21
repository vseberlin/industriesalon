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

  function normalizeSetPayload(payload) {
    if (payload && payload.item) {
      return payload.item;
    }

    return payload || {};
  }

  function mapMemberToItem(member, set) {
    var id = intValue(member.objectPostId || member.object_post_id || 0);
    var title = member.displayTitle || member.objectTitle || member.title || member.memberTitle || '';

    return {
      id: id,
      title: title || 'Archivobjekt #' + String(id),
      excerpt: member.caption || '',
      thumbnail: member.thumbnail || '',
      objectTypeLabel: member.pageLabel || '',
      inventoryNumber: member.sourceObjectId || '',
      yearLabel: '',
      setId: intValue(set && set.id),
      setTitle: set && set.title ? String(set.title) : '',
      memberId: intValue(member.id),
      memberTitle: member.memberTitle || '',
      memberCaption: member.caption || '',
      sourceUrl: member.sourceUrl || ''
    };
  }

  function createObjectPicker(root, options) {
    options = options || {};

    var mode = options.mode === 'single' ? 'single' : 'multiple';
    var perPage = intValue(options.perPage || 24);
    var useModal = options.modal === true;
    var bucketFirst = options.bucketFirst === true;
    var selectedIds = uniqueIds(options.initialSelection || []);
    var selectedItems = {};
    var lastPayload = null;
    var currentPage = 1;
    var currentItems = [];
    var currentSet = null;
    var searchActive = false;

    root.innerHTML = '';

    var modal = null;
    var scope = root;
    var pickerMarkup =
        '<div class="iss-archive-object-picker ' + (bucketFirst ? 'is-bucket-first' : 'is-search-first') + '" data-mode="' + mode + '">' +
          '<aside class="iss-archive-object-picker__sets">' +
            '<div class="iss-archive-object-picker__set-search">' +
              '<input type="search" class="regular-text iss-archive-object-picker__set-query" placeholder="Archivauswahl suchen">' +
              '<button type="button" class="button iss-archive-object-picker__set-run">Suchen</button>' +
            '</div>' +
            '<div class="iss-archive-object-picker__attached"></div>' +
            '<div class="iss-archive-object-picker__set-results"></div>' +
          '</aside>' +
          '<main class="iss-archive-object-picker__main">' +
            '<div class="iss-archive-object-picker__main-head">' +
              '<div><h3 class="iss-archive-object-picker__current-title">Archivauswahl</h3><p class="description iss-archive-object-picker__current-meta"></p></div>' +
              '<button type="button" class="button iss-archive-object-picker__advanced-toggle">Alle Objekte suchen</button>' +
            '</div>' +
            '<div class="iss-archive-object-picker__advanced" hidden>' +
              '<div class="iss-archive-object-picker__filters">' +
                '<input type="search" class="regular-text iss-archive-object-picker__search" placeholder="Archivobjekte suchen">' +
                '<select class="iss-archive-object-picker__source" data-empty-label="Alle Quellen"></select>' +
                '<select class="iss-archive-object-picker__field" data-empty-label="Alle Themenfelder"></select>' +
                '<select class="iss-archive-object-picker__family" data-empty-label="Alle Objektfamilien"></select>' +
                '<select class="iss-archive-object-picker__context" data-empty-label="Alle Kontexte"></select>' +
                '<select class="iss-archive-object-picker__decade" data-empty-label="Alle Dekaden"></select>' +
                '<button type="button" class="button iss-archive-object-picker__run">Suchen</button>' +
              '</div>' +
            '</div>' +
            '<div class="iss-archive-object-picker__status description"></div>' +
            '<div class="iss-archive-object-picker__results"></div>' +
            '<div class="iss-archive-object-picker__pager">' +
              '<button type="button" class="button iss-archive-object-picker__more" hidden>Mehr laden</button>' +
            '</div>' +
          '</main>' +
        '</div>' +
        '<div class="iss-archive-object-picker__tray" hidden>' +
          '<strong class="iss-archive-object-picker__tray-count"></strong>' +
          '<div class="iss-archive-object-picker__tray-items"></div>' +
          '<button type="button" class="button button-primary iss-archive-object-picker__confirm">Auswahl übernehmen</button>' +
        '</div>';

    if (useModal) {
      modal = archive.createElement('div', 'iss-archive-object-picker-modal');
      modal.innerHTML =
        '<div class="iss-archive-object-picker-modal__dialog" role="dialog" aria-modal="true" aria-label="Archivobjekte auswählen">' +
          '<div class="iss-archive-object-picker-modal__head">' +
            '<div><h2>Archivobjekte auswählen</h2><p class="description">Zuerst eine Archivauswahl wählen. Die freie Suche bleibt als zweite Option verfügbar.</p></div>' +
            '<button type="button" class="button iss-archive-object-picker__close">Schließen</button>' +
          '</div>' +
          pickerMarkup +
        '</div>';
      document.body.appendChild(modal);
      scope = modal;
    } else {
      root.innerHTML = pickerMarkup;
    }

    var pickerRoot = scope.querySelector('.iss-archive-object-picker');
    var attachedSets = scope.querySelector('.iss-archive-object-picker__attached');
    var setResults = scope.querySelector('.iss-archive-object-picker__set-results');
    var setQuery = scope.querySelector('.iss-archive-object-picker__set-query');
    var status = scope.querySelector('.iss-archive-object-picker__status');
    var results = scope.querySelector('.iss-archive-object-picker__results');
    var moreButton = scope.querySelector('.iss-archive-object-picker__more');
    var tray = scope.querySelector('.iss-archive-object-picker__tray');
    var trayCount = scope.querySelector('.iss-archive-object-picker__tray-count');
    var trayItems = scope.querySelector('.iss-archive-object-picker__tray-items');
    var confirmButton = scope.querySelector('.iss-archive-object-picker__confirm');
    var currentTitle = scope.querySelector('.iss-archive-object-picker__current-title');
    var currentMeta = scope.querySelector('.iss-archive-object-picker__current-meta');
    var advanced = scope.querySelector('.iss-archive-object-picker__advanced');

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

    function resetSearchFilters() {
      var map = selectors();
      Object.keys(map).forEach(function (key) {
        var control = pickerRoot.querySelector(map[key]);
        if (control) {
          control.value = '';
        }
      });
    }

    function setStatus(message) {
      status.textContent = message || '';
      status.hidden = !message;
    }

    function setSearchActive(active) {
      searchActive = active === true;
      pickerRoot.classList.toggle('is-search-active', searchActive);
      if (!searchActive) {
        moreButton.hidden = true;
      }
    }

    function stopAction(event) {
      if (event && event.preventDefault) {
        event.preventDefault();
      }
      if (event && event.stopPropagation) {
        event.stopPropagation();
      }
    }

    function closePicker(event) {
      stopAction(event);
      if (modal) {
        modal.remove();
      }
      root.innerHTML = '';
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
        ? '1 Objekt ausgewählt'
        : String(selectedIds.length) + ' Objekte ausgewählt';

      selectedObjects().forEach(function (item) {
        var pill = archive.createElement('button', 'button iss-archive-object-picker__tray-item', item.title || 'Objekt #' + String(item.id));
        pill.type = 'button';
        pill.addEventListener('click', function (event) {
          stopAction(event);
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
          button.textContent = selected ? 'Ausgewählt' : 'Auswählen';
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
      item = item || {};
      var card = archive.createElement('article', 'iss-archive-object-picker__item');
      var thumb = archive.createElement('div', 'iss-archive-object-picker__thumb');
      var body = archive.createElement('div', 'iss-archive-object-picker__body');
      var id = intValue(item.id);
      var label = item.title || item.objectTitle || item.displayTitle || item.memberTitle || item.inventoryNumber || item.sourceId || '';
      var title = archive.createElement('strong', 'iss-archive-object-picker__title', label || 'Archivobjekt #' + String(id));
      var meta = [item.setTitle, item.objectTypeLabel, item.inventoryNumber || item.sourceId, item.yearLabel].filter(Boolean).join(' - ');
      var button = archive.createElement('button', 'button iss-archive-object-picker__select', 'Auswählen');

      card.setAttribute('data-object-id', String(id));
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
      button.addEventListener('click', function (event) {
        stopAction(event);
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

    function renderSetButton(set, container, active) {
      var button = archive.createElement('button', 'iss-archive-object-picker__set' + (active ? ' is-active' : ''));
      var title = archive.createElement('strong', '', set.title || 'Archivauswahl #' + String(set.id));
      var meta = archive.createElement('span', '', archive.setMetaLine ? archive.setMetaLine(set) : String(set.memberCount || 0) + ' Objekte');
      button.type = 'button';
      button.appendChild(title);
      button.appendChild(meta);
      button.addEventListener('click', function (event) {
        stopAction(event);
        loadSetObjects(set);
      });
      container.appendChild(button);
    }

    function renderSetList(container, sets, heading, emptyText) {
      archive.clear(container);
      if (heading) {
        container.appendChild(archive.createElement('h4', '', heading));
      }
      if (!sets.length) {
        container.appendChild(archive.createElement('p', 'description', emptyText || 'Keine Archivauswahl gefunden.'));
        return;
      }
      sets.forEach(function (set) {
        renderSetButton(set, container, currentSet && currentSet.id === set.id);
      });
    }

    function loadAttachedSets() {
      if (!options.contextPostId || !archive.getAttachedSets) {
        renderSetList(attachedSets, [], 'Verknüpfte Archivauswahlen', 'Keine verknüpfte Archivauswahl.');
        return Promise.resolve([]);
      }

      return archive.getAttachedSets(options.contextPostId).then(function (payload) {
        var sets = payload.items || [];
        renderSetList(attachedSets, sets, 'Verknüpfte Archivauswahlen', 'Keine verknüpfte Archivauswahl.');
        return sets;
      }).catch(function (error) {
        renderSetList(attachedSets, [], 'Verknüpfte Archivauswahlen', error.message || 'Archivauswahlen konnten nicht geladen werden.');
        return [];
      });
    }

    function searchSets() {
      var query = setQuery.value || '';
      setStatus('Archivauswahlen werden geladen...');
      return archive.searchSets({
        search: query,
        perPage: 20,
        contextPostId: options.contextPostId || 0
      }).then(function (payload) {
        var sets = payload.items || [];
        renderSetList(setResults, sets, query ? 'Suchergebnisse' : 'Alle Archivauswahlen', 'Keine Archivauswahl gefunden.');
        setStatus('');
        return sets;
      }).catch(function (error) {
        setStatus(error.message || 'Archivauswahlen konnten nicht geladen werden.');
        return [];
      });
    }

    function loadSetObjects(set) {
      currentSet = set;
      currentPage = 1;
      setSearchActive(false);
      currentTitle.textContent = set.title || 'Archivauswahl #' + String(set.id);
      currentMeta.textContent = archive.setMetaLine ? archive.setMetaLine(set) : '';
      moreButton.hidden = true;
      setStatus('Objekte werden geladen...');

      return archive.loadSet(set.id, {
        contextPostId: options.contextPostId || 0
      }).then(function (payload) {
        var loadedSet = normalizeSetPayload(payload);
        var members = loadedSet.members || [];
        var items = members.map(function (member) {
          return mapMemberToItem(member, loadedSet);
        }).filter(function (item) {
          return item.id > 0;
        });
        lastPayload = payload;
        currentItems = items;
        renderItems(items, false);
        setStatus('');
        return payload;
      }).catch(function (error) {
        setStatus(error.message || 'Archivobjekte konnten nicht geladen werden.');
      });
    }

    function buildParams(page) {
      var params = archive.buildObjectPickerParams(pickerRoot, selectors(), {
        contextPostId: options.contextPostId || 0,
        perPage: perPage
      });
      params.set('page', String(page || 1));
      return params;
    }

    function loadSearch(page, append) {
      var params = buildParams(page);
      currentPage = page || 1;
      currentSet = null;
      setSearchActive(true);
      currentTitle.textContent = 'Alle Objekte';
      currentMeta.textContent = 'Facettierte Suche';
      setStatus('Lädt...');

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

    function confirmSelection(event) {
      var result;
      stopAction(event);
      if (!selectedIds.length) {
        setStatus('Wähle zuerst mindestens ein Archivobjekt aus.');
        return Promise.resolve();
      }

      if (typeof options.onConfirm === 'function') {
        confirmButton.disabled = true;
        setStatus('Auswahl wird übernommen...');
        result = options.onConfirm(selectedObjects(), {
          selectedIds: selectedIds.slice(),
          lastPayload: lastPayload,
          currentSet: currentSet
        });

        return Promise.resolve(result).then(function (payload) {
          setStatus('');
          confirmButton.disabled = false;
          if (useModal) {
            closePicker();
          }
          return payload;
        }).catch(function (error) {
          setStatus(error.message || 'Auswahl konnte nicht übernommen werden.');
          confirmButton.disabled = false;
        });
      }

      if (useModal) {
        closePicker();
      }
      return Promise.resolve();
    }

    if (useModal) {
      scope.querySelector('.iss-archive-object-picker__close').addEventListener('click', closePicker);
    }
    scope.querySelector('.iss-archive-object-picker__set-run').addEventListener('click', function (event) {
      stopAction(event);
      searchSets();
    });
    setQuery.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchSets();
      }
    });
    scope.querySelector('.iss-archive-object-picker__advanced-toggle').addEventListener('click', function (event) {
      stopAction(event);
      advanced.hidden = !advanced.hidden;
      if (!advanced.hidden && (!searchActive || !currentItems.length)) {
        resetSearchFilters();
        loadSearch(1, false);
      }
    });
    scope.querySelector('.iss-archive-object-picker__run').addEventListener('click', function (event) {
      stopAction(event);
      loadSearch(1, false);
    });
    scope.querySelector('.iss-archive-object-picker__search').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        loadSearch(1, false);
      }
    });
    moreButton.addEventListener('click', function (event) {
      stopAction(event);
      if (!searchActive) {
        moreButton.hidden = true;
        return;
      }
      loadSearch(currentPage + 1, true);
    });
    confirmButton.addEventListener('click', confirmSelection);

    if (bucketFirst) {
      loadAttachedSets().then(function (sets) {
        if (sets.length) {
          return loadSetObjects(sets[0]);
        }
        return searchSets().then(function (allSets) {
          if (allSets.length) {
            return loadSetObjects(allSets[0]);
          }
          resetSearchFilters();
          return loadSearch(1, false);
        });
      });
    } else {
      resetSearchFilters();
      currentTitle.textContent = 'Alle Objekte';
      currentMeta.textContent = 'Facettierte Suche';
      advanced.hidden = false;
      loadSearch(1, false);
    }

    if (options.autoFocus === true) {
      setQuery.focus();
    }

    return {
      root: pickerRoot,
      close: closePicker,
      refresh: function () {
        if (currentSet) {
          return loadSetObjects(currentSet);
        }
        return loadSearch(1, false);
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
