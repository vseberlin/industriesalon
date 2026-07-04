(function () {
  var initialConfig = window.issEditorialSetMediaPicker || {};
  var apiFetch = window.wp && window.wp.apiFetch ? window.wp.apiFetch : null;
  var REST_NAMESPACE = '/' + String(initialConfig.namespace || 'iss-content/v1').replace(/^\/+|\/+$/g, '');

  if (!apiFetch) {
    return;
  }

  if (apiFetch.createNonceMiddleware && initialConfig.nonce) {
    apiFetch.use(apiFetch.createNonceMiddleware(initialConfig.nonce));
  }

  function intValue(value) {
    var parsed = parseInt(value, 10);
    return isNaN(parsed) ? 0 : parsed;
  }

  function createElement(tag, className, text) {
    var element = document.createElement(tag);
    if (className) {
      element.className = className;
    }
    if (typeof text === 'string') {
      element.textContent = text;
    }

    return element;
  }

  function clear(element) {
    if (!element) {
      return;
    }

    while (element.firstChild) {
      element.removeChild(element.firstChild);
    }
  }

  function request(path, options) {
    options = options || {};

    return apiFetch({
      path: REST_NAMESPACE + (path.indexOf('/') === 0 ? path : '/' + path),
      method: options.method || 'GET',
      data: options.data || undefined
    });
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

  function cleanLabel(value, fallback) {
    var text = String(value || '').replace(/\s+/g, ' ').trim();
    return text || fallback || '';
  }

  function setMetaLine(set) {
    var parts = [];
    if (set.status) {
      parts.push(set.status);
    }
    parts.push(String(intValue(set.itemCount || 0)) + ' Elemente');
    if (set.linkCount) {
      parts.push(String(intValue(set.linkCount)) + ' Verknüpfungen');
    }

    return parts.join(' - ');
  }

  function itemTitle(item) {
    item = item || {};
    return cleanLabel(item.label, cleanLabel(item.preview && item.preview.title, 'Medium #' + String(item.sourceId || item.id || '')));
  }

  function itemMetaLine(item) {
    var parts = [];
    if (item.setTitle) {
      parts.push(item.setTitle);
    }
    if (item.status) {
      parts.push(item.status);
    }
    if (item.preview && item.preview.mime) {
      parts.push(item.preview.mime);
    }

    return parts.join(' - ');
  }

  function selectableAttachmentId(item) {
    if (item && item.kind === 'wp_media' && item.source === 'wp-media') {
      return intValue(item.sourceId);
    }
    if (item && item.kind === 'external_upload' && item.source === 'event-drop' && item.provenance) {
      return intValue(item.provenance.imported_attachment_id);
    }

    return 0;
  }

  function isSelectableMediaItem(item, mediaType) {
    var mime = item && item.preview ? String(item.preview.mime || '') : '';
    return selectableAttachmentId(item) > 0
      && (mediaType !== 'image' || mime.indexOf('image/') === 0)
      && (mediaType !== 'file' || mime.indexOf('image/') !== 0);
  }

  function referenceFromSetItem(item) {
    var preview = item.preview || {};
    return {
      kind: 'media',
      source: 'wp-media',
      id: String(selectableAttachmentId(item) || ''),
      label: item.label || preview.title || '',
      thumbnail: preview.thumbnail || '',
      width: preview.width ? String(preview.width) : '',
      height: preview.height ? String(preview.height) : '',
      mime: preview.mime || ''
    };
  }

  function createPicker(root, options) {
    options = options || {};

    var mode = options.mode === 'single' ? 'single' : 'multiple';
    var mediaType = options.mediaType || '';
    var initialSelection = (options.initialSelection || []).filter(function (item) {
      var mime = String(item && item.mime ? item.mime : '');
      return mediaType !== 'file' || mime.indexOf('image/') !== 0;
    });
    var selectedIds = uniqueIds(initialSelection);
    var selectedItems = {};
    var currentSet = null;
    var attachedSetItems = [];
    var allSetItems = [];
    var modal = null;
    var scope = root;
    var contextType = options.contextType || initialConfig.contextType || '';
    var contextId = intValue(options.contextId || initialConfig.postId || 0);
    var pickerMarkup =
      '<div class="iss-archive-object-picker iss-editorial-set-media-picker is-bucket-first" data-mode="' + mode + '">' +
        '<aside class="iss-archive-object-picker__sets">' +
          '<div class="iss-archive-object-picker__set-search">' +
            '<input type="search" class="regular-text iss-archive-object-picker__set-query" placeholder="Sets suchen">' +
            '<button type="button" class="button iss-archive-object-picker__set-run">Suchen</button>' +
          '</div>' +
          '<div class="iss-archive-object-picker__attached"></div>' +
          '<div class="iss-archive-object-picker__set-results"></div>' +
        '</aside>' +
        '<main class="iss-archive-object-picker__main">' +
          '<div class="iss-archive-object-picker__main-head">' +
            '<div><h3 class="iss-archive-object-picker__current-title">Set</h3><p class="description iss-archive-object-picker__current-meta"></p></div>' +
            '<button type="button" class="button iss-archive-object-picker__advanced-toggle">Medien suchen</button>' +
          '</div>' +
          '<div class="iss-archive-object-picker__advanced" hidden></div>' +
          '<div class="iss-archive-object-picker__status description"></div>' +
          '<div class="iss-archive-object-picker__results"></div>' +
          '<div class="iss-archive-object-picker__pager"></div>' +
        '</main>' +
      '</div>' +
      '<div class="iss-archive-object-picker__tray" hidden>' +
        '<strong class="iss-archive-object-picker__tray-count"></strong>' +
        '<div class="iss-archive-object-picker__tray-items"></div>' +
        '<button type="button" class="button button-primary iss-archive-object-picker__confirm">Auswahl übernehmen</button>' +
      '</div>';

    root.innerHTML = '';
    if (options.modal === true) {
      modal = createElement('div', 'iss-archive-object-picker-modal iss-editorial-set-media-picker-modal');
      modal.innerHTML =
        '<div class="iss-archive-object-picker-modal__dialog" role="dialog" aria-modal="true" aria-label="Medien aus Sets auswählen">' +
          '<div class="iss-archive-object-picker-modal__head">' +
            '<div><h2>Medien aus Sets auswählen</h2><p class="description">Zuerst ein Set wählen. Die Mediathek bleibt als zweite Option verfügbar.</p></div>' +
            '<button type="button" class="button iss-archive-object-picker__close">Schließen</button>' +
          '</div>' +
          pickerMarkup +
        '</div>';
      document.body.appendChild(modal);
      scope = modal;
    } else {
      root.innerHTML = pickerMarkup;
    }

    var attachedSets = scope.querySelector('.iss-archive-object-picker__attached');
    var setResults = scope.querySelector('.iss-archive-object-picker__set-results');
    var setQuery = scope.querySelector('.iss-archive-object-picker__set-query');
    var status = scope.querySelector('.iss-archive-object-picker__status');
    var results = scope.querySelector('.iss-archive-object-picker__results');
    var tray = scope.querySelector('.iss-archive-object-picker__tray');
    var trayCount = scope.querySelector('.iss-archive-object-picker__tray-count');
    var trayItems = scope.querySelector('.iss-archive-object-picker__tray-items');
    var confirmButton = scope.querySelector('.iss-archive-object-picker__confirm');
    var currentTitle = scope.querySelector('.iss-archive-object-picker__current-title');
    var currentMeta = scope.querySelector('.iss-archive-object-picker__current-meta');

    selectedIds.forEach(function (id) {
      initialSelection.forEach(function (item) {
        if (intValue(item && item.id ? item.id : item) === id && item && typeof item === 'object') {
          selectedItems[id] = item;
        }
      });
    });

    function stopAction(event) {
      if (event && event.preventDefault) {
        event.preventDefault();
      }
      if (event && event.stopPropagation) {
        event.stopPropagation();
      }
    }

    function setStatus(message) {
      status.textContent = message || '';
      status.hidden = !message;
    }

    function closePicker(event) {
      stopAction(event);
      if (modal) {
        modal.remove();
      }
      root.innerHTML = '';
    }

    function selectedMedia() {
      return selectedIds.map(function (id) {
        return selectedItems[id] || { id: String(id) };
      });
    }

    function renderTray() {
      clear(trayItems);
      tray.hidden = selectedIds.length === 0;
      trayCount.textContent = selectedIds.length === 1
        ? '1 Medium ausgewählt'
        : String(selectedIds.length) + ' Medien ausgewählt';

      selectedMedia().forEach(function (item) {
        var pill = createElement('button', 'button iss-archive-object-picker__tray-item', item.label || 'Medium #' + String(item.id));
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
        var id = intValue(card.getAttribute('data-media-id'));
        var selected = selectedIds.indexOf(id) !== -1;
        var button = card.querySelector('.iss-archive-object-picker__select');
        card.classList.toggle('is-selected', selected);
        card.setAttribute('aria-selected', selected ? 'true' : 'false');
        if (button && !button.disabled) {
          button.setAttribute('aria-pressed', selected ? 'true' : 'false');
          button.textContent = selected ? 'Ausgewählt' : 'Auswählen';
        }
      });
      renderTray();
    }

    function toggleSelection(reference) {
      var id = intValue(reference && reference.id);
      var index;
      if (id <= 0) {
        return;
      }

      selectedItems[id] = reference;
      index = selectedIds.indexOf(id);
      if (index !== -1) {
        selectedIds.splice(index, 1);
      } else if (mode === 'single') {
        selectedIds = [id];
      } else {
        selectedIds.push(id);
      }
      syncResultSelection();
    }

    function renderItem(item) {
      var selectable = isSelectableMediaItem(item, mediaType);
      var reference = selectable ? referenceFromSetItem(item) : null;
      var id = intValue(reference && reference.id);
      var card = createElement('article', 'iss-archive-object-picker__item');
      var thumb = createElement('div', 'iss-archive-object-picker__thumb');
      var body = createElement('div', 'iss-archive-object-picker__body');
      var button = createElement('button', 'button iss-archive-object-picker__select', selectable ? 'Auswählen' : 'Nicht auswählbar');
      var preview = item.preview || {};
      var meta = selectable ? itemMetaLine(item) : [item.setTitle, item.status, item.kind].filter(Boolean).join(' - ');

      card.setAttribute('data-media-id', String(id));
      if (preview.thumbnail) {
        var image = document.createElement('img');
        image.src = preview.thumbnail;
        image.alt = '';
        image.loading = 'lazy';
        thumb.appendChild(image);
      } else {
        thumb.appendChild(createElement('span', '', item.kind || 'Datei'));
      }
      card.appendChild(thumb);
      body.appendChild(createElement('strong', 'iss-archive-object-picker__title', itemTitle(item)));
      if (meta) {
        body.appendChild(createElement('p', 'description', meta));
      }
      if (!selectable && item.kind === 'external_upload') {
        body.appendChild(createElement('p', 'description', 'Roh-Uploads erst im Set prüfen und in die Mediathek übernehmen.'));
      } else if (!selectable && mediaType === 'image') {
        body.appendChild(createElement('p', 'description', 'Dieser Abschnitt erwartet ein Bild.'));
      } else if (!selectable && mediaType === 'file') {
        body.appendChild(createElement('p', 'description', 'Material erwartet Dateien. Bilder bitte über Galerie oder Bilderwand einsetzen.'));
      }
      card.appendChild(body);
      button.type = 'button';
      button.disabled = !selectable;
      button.addEventListener('click', function (event) {
        stopAction(event);
        if (reference) {
          toggleSelection(reference);
        }
      });
      card.appendChild(button);
      if (selectable) {
        selectedItems[id] = selectedItems[id] || reference;
        card.addEventListener('dblclick', function () {
          toggleSelection(reference);
        });
      }
      results.appendChild(card);
    }

    function renderItems(items) {
      clear(results);
      if (!items.length) {
        results.appendChild(createElement('p', 'description', 'Keine Medien in diesem Set gefunden.'));
        syncResultSelection();
        return;
      }
      items.forEach(renderItem);
      syncResultSelection();
    }

    function renderSetButton(set, container, active) {
      var button = createElement('button', 'iss-archive-object-picker__set' + (active ? ' is-active' : ''));
      button.type = 'button';
      button.appendChild(createElement('strong', '', set.title || 'Set #' + String(set.id)));
      button.appendChild(createElement('span', '', setMetaLine(set)));
      button.addEventListener('click', function (event) {
        stopAction(event);
        loadSetItems(set);
      });
      container.appendChild(button);
    }

    function renderSetList(container, sets, heading, emptyText) {
      clear(container);
      if (heading) {
        container.appendChild(createElement('h4', '', heading));
      }
      if (!sets.length) {
        container.appendChild(createElement('p', 'description', emptyText));
        return;
      }
      sets.forEach(function (set) {
        renderSetButton(set, container, currentSet && currentSet.id === set.id);
      });
    }

    function searchSets(query, attachedOnly) {
      var params = new URLSearchParams();
      params.set('search', query || '');
      params.set('perPage', '30');
      if (attachedOnly && contextType && contextId) {
        params.set('contextType', contextType);
        params.set('contextId', String(contextId));
      }

      return request('/editorial-sets?' + params.toString()).then(function (payload) {
        return payload.items || [];
      });
    }

    function loadAttachedSets() {
      if (!contextType || !contextId) {
        renderSetList(attachedSets, [], 'Verknüpfte Sets', 'Kein Beitrag gespeichert oder keine verknüpften Sets.');
        return Promise.resolve([]);
      }

      return searchSets('', true).then(function (sets) {
        attachedSetItems = sets;
        renderSetList(attachedSets, sets, 'Verknüpfte Sets', 'Keine verknüpften Sets.');
        return sets;
      }).catch(function (error) {
        renderSetList(attachedSets, [], 'Verknüpfte Sets', error.message || 'Sets konnten nicht geladen werden.');
        return [];
      });
    }

    function searchAllSets() {
      var query = setQuery.value || '';
      setStatus('Sets werden geladen...');
      return searchSets(query, false).then(function (sets) {
        allSetItems = sets;
        renderSetList(setResults, sets, query ? 'Suchergebnisse' : 'Alle Sets', 'Kein Set gefunden.');
        setStatus('');
        return sets;
      }).catch(function (error) {
        setStatus(error.message || 'Sets konnten nicht geladen werden.');
        return [];
      });
    }

    function loadSetItems(set) {
      currentSet = set;
      currentTitle.textContent = set.title || 'Set #' + String(set.id);
      currentMeta.textContent = setMetaLine(set);
      renderSetList(attachedSets, attachedSetItems, 'Verknüpfte Sets', 'Keine verknüpften Sets.');
      renderSetList(setResults, allSetItems, (setQuery.value || '') ? 'Suchergebnisse' : 'Alle Sets', 'Kein Set gefunden.');
      setStatus('Medien werden geladen...');

      return request('/editorial-set-items?setId=' + encodeURIComponent(String(set.id)) + '&perPage=120').then(function (payload) {
        renderItems(payload.items || []);
        setStatus('');
        return payload;
      }).catch(function (error) {
        setStatus(error.message || 'Medien konnten nicht geladen werden.');
      });
    }

    function confirmSelection(event) {
      var result;
      stopAction(event);
      if (!selectedIds.length) {
        setStatus('Wähle zuerst mindestens ein Medium aus.');
        return Promise.resolve();
      }

      if (typeof options.onConfirm === 'function') {
        confirmButton.disabled = true;
        setStatus('Auswahl wird übernommen...');
        result = options.onConfirm(selectedMedia(), {
          currentSet: currentSet
        });
        return Promise.resolve(result).then(function () {
          confirmButton.disabled = false;
          setStatus('');
          closePicker();
        }).catch(function (error) {
          confirmButton.disabled = false;
          setStatus(error.message || 'Auswahl konnte nicht übernommen werden.');
        });
      }

      closePicker();
      return Promise.resolve();
    }

    function openMediaLibrary(event) {
      stopAction(event);
      if (typeof options.onMediaSearch === 'function') {
        options.onMediaSearch({
          close: closePicker,
          currentSet: currentSet
        });
      }
    }

    if (modal) {
      scope.querySelector('.iss-archive-object-picker__close').addEventListener('click', closePicker);
    }
    scope.querySelector('.iss-archive-object-picker__set-run').addEventListener('click', function (event) {
      stopAction(event);
      searchAllSets();
    });
    setQuery.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchAllSets();
      }
    });
    scope.querySelector('.iss-archive-object-picker__advanced-toggle').addEventListener('click', openMediaLibrary);
    confirmButton.addEventListener('click', confirmSelection);

    loadAttachedSets().then(function (attached) {
      return searchAllSets().then(function (sets) {
        var first = attached[0] || sets[0] || null;
        if (first) {
          return loadSetItems(first);
        }
        currentTitle.textContent = 'Sets';
        currentMeta.textContent = 'Keine Sets gefunden.';
        renderItems([]);
        return null;
      });
    });

    return {
      close: closePicker,
      getSelectedMedia: selectedMedia
    };
  }

  window.issEditorialSetMediaPicker = {
    config: initialConfig,
    create: createPicker
  };
}());
