(function () {
  var config = window.issArchiveSetsAdmin || {};
  var setSelector = window.issArchiveSetSelector || {};
  var strings = config.strings || {};
  var restRoot = String(config.restRoot || '').replace(/\/$/, '');

  function t(key, fallback) {
    return strings[key] || fallback;
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
    if (!restRoot) {
      return Promise.reject(new Error(t('error', 'Die Anfrage konnte nicht abgeschlossen werden.')));
    }

    return window.fetch(restRoot + path, {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || ''
      },
      body: options.body ? JSON.stringify(options.body) : undefined
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (payload) {
        if (!response.ok) {
          var message = payload && payload.message ? payload.message : t('error', 'Die Anfrage konnte nicht abgeschlossen werden.');
          throw new Error(message);
        }

        return payload;
      });
    });
  }

  function makeButton(label, className, onClick) {
    var button = createElement('button', className || 'button', label);
    button.type = 'button';
    if (typeof onClick === 'function') {
      button.addEventListener('click', onClick);
    }

    return button;
  }

  function setMetaLine(set) {
    if (setSelector.setMetaLine) {
      return setSelector.setMetaLine(set);
    }

    var parts = [];
    if (set.statusLabel) {
      parts.push(set.statusLabel);
    }
    parts.push(String(set.memberCount || 0) + ' Objekte');
    if (set.legacyCollectionPostId) {
      parts.push('Legacy #' + String(set.legacyCollectionPostId));
    }

    return parts.join(' - ');
  }

  function notifyAttachedSetsChanged(postId, sets) {
    if (setSelector.notifyAttachedSetsChanged) {
      setSelector.notifyAttachedSetsChanged(postId, sets);
      return;
    }

    var detail = {
      postId: postId,
      sets: Array.isArray(sets) ? sets : []
    };
    var event = new CustomEvent('iss-archive-sets:attached-changed', {
      detail: detail
    });

    window.dispatchEvent(event);
    if (window.parent && window.parent !== window) {
      window.parent.dispatchEvent(new CustomEvent('iss-archive-sets:attached-changed', {
        detail: detail
      }));
    }
  }

  function getAttachedSets(postId) {
    if (setSelector.getAttachedSets) {
      return setSelector.getAttachedSets(postId);
    }

    return request('/content/' + encodeURIComponent(String(postId)) + '/sets');
  }

  function loadSet(setId, args) {
    if (setSelector.loadSet) {
      return setSelector.loadSet(setId, args || {});
    }

    var params = new URLSearchParams();
    args = args || {};
    if (args.contextPostId) {
      params.set('contextPostId', String(args.contextPostId));
    }

    var suffix = params.toString();
    return request('/sets/' + encodeURIComponent(String(setId)) + (suffix ? '?' + suffix : ''));
  }

  function searchSets(args) {
    if (setSelector.searchSets) {
      return setSelector.searchSets(args || {});
    }

    var params = new URLSearchParams();
    args = args || {};
    params.set('search', args.search || '');
    params.set('perPage', String(args.perPage || 12));
    if (args.contextPostId) {
      params.set('contextPostId', String(args.contextPostId));
    }

    return request('/sets?' + params.toString());
  }

  function attachSet(postId, setId) {
    if (setSelector.attachSet) {
      return setSelector.attachSet(postId, setId);
    }

    return request('/content/' + encodeURIComponent(String(postId)) + '/sets', {
      method: 'POST',
      body: { setId: setId }
    });
  }

  function detachSet(postId, setId) {
    if (setSelector.detachSet) {
      return setSelector.detachSet(postId, setId);
    }

    return request('/content/' + encodeURIComponent(String(postId)) + '/sets/' + encodeURIComponent(String(setId)), {
      method: 'DELETE'
    });
  }

  function createSet(args) {
    args = args || {};

    return request('/sets', {
      method: 'POST',
      body: {
        title: args.title || '',
        summary: args.summary || '',
        contextPostId: args.contextPostId || 0,
        attachToContext: !!args.attachToContext
      }
    });
  }

  function addObjectToSet(setId, objectPostId, args) {
    args = args || {};

    return request('/sets/' + encodeURIComponent(String(setId)) + '/members', {
      method: 'POST',
      body: {
        objectPostId: objectPostId,
        contextPostId: args.contextPostId || 0
      }
    });
  }

  function fillFacetSelect(select, items, currentValue) {
    if (!select) {
      return;
    }

    var existing = currentValue || select.value || '';
    clear(select);
    select.appendChild(new Option(select.getAttribute('data-empty-label') || 'Alle', ''));
    (items || []).forEach(function (item) {
      var label = item.name || item.slug || '';
      if (item.count) {
        label += ' (' + String(item.count) + ')';
      }
      select.appendChild(new Option(label, item.slug || ''));
    });
    select.value = existing;
  }

  function fillObjectFacets(root, selectors, facets, params) {
    selectors = selectors || {};
    facets = facets || {};
    params = params || new URLSearchParams();

    fillFacetSelect(root.querySelector(selectors.source), facets.source || [], params.get('source'));
    fillFacetSelect(root.querySelector(selectors.field), facets.field || [], params.get('field'));
    fillFacetSelect(root.querySelector(selectors.family), facets.family || [], params.get('family'));
    fillFacetSelect(root.querySelector(selectors.context), facets.context || [], params.get('context'));
    fillFacetSelect(root.querySelector(selectors.decade), facets.decade || [], params.get('decade'));
  }

  function buildObjectPickerParams(root, selectors, args) {
    selectors = selectors || {};
    args = args || {};

    var params = new URLSearchParams();
    params.set('search', selectors.search && root.querySelector(selectors.search) ? root.querySelector(selectors.search).value || '' : '');
    params.set('source', selectors.source && root.querySelector(selectors.source) ? root.querySelector(selectors.source).value || '' : '');
    params.set('field', selectors.field && root.querySelector(selectors.field) ? root.querySelector(selectors.field).value || '' : '');
    params.set('family', selectors.family && root.querySelector(selectors.family) ? root.querySelector(selectors.family).value || '' : '');
    params.set('context', selectors.context && root.querySelector(selectors.context) ? root.querySelector(selectors.context).value || '' : '');
    params.set('decade', selectors.decade && root.querySelector(selectors.decade) ? root.querySelector(selectors.decade).value || '' : '');
    params.set('perPage', String(args.perPage || 12));

    if (args.contextPostId) {
      params.set('contextPostId', String(args.contextPostId));
    }

    return params;
  }

  function searchObjects(params) {
    return request('/object-picker?' + params.toString());
  }

  function renderObjectCard(container, item, options) {
    options = options || {};

    var card = createElement('article', options.cardClass || 'iss-archivsets-object');
    if (options.showThumbnail) {
      var thumb = createElement('div', options.thumbClass || 'iss-archivsets-object__thumb');
      if (item.thumbnail) {
        var image = document.createElement('img');
        image.src = item.thumbnail;
        image.alt = '';
        thumb.appendChild(image);
      }
      card.appendChild(thumb);
    }

    var body = options.bodyClass ? createElement('div', options.bodyClass) : card;
    body.appendChild(createElement('strong', options.titleClass || '', item.title || 'Archivobjekt #' + String(item.id)));
    var meta = [item.objectTypeLabel, item.inventoryNumber, item.yearLabel].filter(Boolean).join(' - ');
    if (meta) {
      body.appendChild(createElement('p', 'description', meta));
    }
    if (options.showExcerpt && item.excerpt) {
      body.appendChild(createElement('p', 'description', item.excerpt));
    }
    if (body !== card) {
      card.appendChild(body);
    }

    if (options.buttonLabel && typeof options.onSelect === 'function') {
      card.appendChild(makeButton(options.buttonLabel, options.buttonClass || 'button', function (event) {
        options.onSelect(item, event.currentTarget, card);
      }));
    }

    container.appendChild(card);

    return card;
  }

  function mapMemberToArchiveItem(member) {
    return {
      id: parseInt(member.objectPostId || '0', 10),
      title: member.objectTitle || member.title || member.memberTitle || '',
      excerpt: member.caption || '',
      thumbnail: member.thumbnail || '',
      objectTypeLabel: member.pageLabel || '',
      inventoryNumber: member.sourceObjectId || '',
      yearLabel: ''
    };
  }

  window.issArchiveSetsPicker = {
    config: config,
    t: t,
    request: request,
    createElement: createElement,
    clear: clear,
    makeButton: makeButton,
    setMetaLine: setMetaLine,
    notifyAttachedSetsChanged: notifyAttachedSetsChanged,
    getAttachedSets: getAttachedSets,
    loadSet: loadSet,
    searchSets: searchSets,
    attachSet: attachSet,
    detachSet: detachSet,
    createSet: createSet,
    addObjectToSet: addObjectToSet,
    fillFacetSelect: fillFacetSelect,
    fillObjectFacets: fillObjectFacets,
    buildObjectPickerParams: buildObjectPickerParams,
    searchObjects: searchObjects,
    renderObjectCard: renderObjectCard,
    mapMemberToArchiveItem: mapMemberToArchiveItem
  };
}());
