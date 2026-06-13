(function () {
  var REST_NAMESPACE = '/iss-archive/v1';

  function intValue(value) {
    var parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function getConfig() {
    return window.issArchiveSetsAdmin || {};
  }

  function getRestRoot() {
    return String(getConfig().restRoot || '').replace(/\/$/, '');
  }

  function getNonce() {
    return getConfig().nonce || '';
  }

  function normalizeApiFetchPath(path) {
    path = String(path || '');
    if (path.indexOf(REST_NAMESPACE) === 0) {
      return path;
    }

    return REST_NAMESPACE + (path.indexOf('/') === 0 ? path : '/' + path);
  }

  function normalizeRestRootPath(path) {
    path = String(path || '');
    if (path.indexOf(REST_NAMESPACE) === 0) {
      path = path.slice(REST_NAMESPACE.length);
    }

    return path.indexOf('/') === 0 ? path : '/' + path;
  }

  function request(path, options) {
    options = options || {};

    if (!getRestRoot() && window.wp && window.wp.apiFetch) {
      return window.wp.apiFetch({
        path: normalizeApiFetchPath(path),
        method: options.method || 'GET',
        data: options.body || undefined,
      });
    }

    if (!getRestRoot()) {
      return Promise.reject(new Error('Die Anfrage konnte nicht abgeschlossen werden.'));
    }

    return window.fetch(getRestRoot() + normalizeRestRootPath(path), {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': getNonce(),
      },
      body: options.body ? JSON.stringify(options.body) : undefined,
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (payload) {
        if (!response.ok) {
          throw new Error(payload && payload.message ? payload.message : 'Die Anfrage konnte nicht abgeschlossen werden.');
        }

        return payload;
      });
    });
  }

  function attachedSetsPath(postId) {
    return '/iss-archive/v1/content/' + encodeURIComponent(String(postId)) + '/sets';
  }

  function setPath(setId, contextPostId) {
    var params = new URLSearchParams();
    if (contextPostId) {
      params.set('contextPostId', String(contextPostId));
    }

    return '/iss-archive/v1/sets/' + encodeURIComponent(String(setId)) + (params.toString() ? '?' + params.toString() : '');
  }

  function searchSetsPath(args) {
    var params = new URLSearchParams();
    args = args || {};
    params.set('search', args.search || '');
    params.set('perPage', String(args.perPage || 12));
    if (args.contextPostId) {
      params.set('contextPostId', String(args.contextPostId));
    }

    return '/iss-archive/v1/sets?' + params.toString();
  }

  function cleanLabel(value, fallback) {
    var text = String(value || '').replace(/\s+/g, ' ').trim();
    return text || fallback || '';
  }

  function setOptionLabel(set) {
    if (!set || !set.id) {
      return '';
    }

    var count = intValue(set.memberCount || 0);
    var title = cleanLabel(set.title, 'Archivset #' + String(set.id));
    return count > 0 ? title + ' (' + String(count) + ')' : title;
  }

  function setMetaLine(set) {
    var parts = [];
    if (set && set.statusLabel) {
      parts.push(set.statusLabel);
    }
    parts.push(String(set && set.memberCount ? set.memberCount : 0) + ' Objekte');
    if (set && set.legacyCollectionPostId) {
      parts.push('Legacy #' + String(set.legacyCollectionPostId));
    }

    return parts.join(' - ');
  }

  function notifyAttachedSetsChanged(postId, sets) {
    if (!window.CustomEvent) {
      return;
    }

    var detail = {
      postId: intValue(postId),
      sets: Array.isArray(sets) ? sets : [],
    };
    var event = new CustomEvent('iss-archive-sets:attached-changed', {
      detail: detail,
    });

    window.dispatchEvent(event);
    if (window.parent && window.parent !== window) {
      window.parent.dispatchEvent(new CustomEvent('iss-archive-sets:attached-changed', {
        detail: detail,
      }));
    }
  }

  function getAttachedSets(postId) {
    return request(attachedSetsPath(postId));
  }

  function loadSet(setId, args) {
    args = args || {};
    return request(setPath(setId, args.contextPostId || 0));
  }

  function searchSets(args) {
    return request(searchSetsPath(args));
  }

  function attachSet(postId, setId) {
    return request(attachedSetsPath(postId), {
      method: 'POST',
      body: {
        setId: setId,
      },
    });
  }

  function detachSet(postId, setId) {
    return request(attachedSetsPath(postId) + '/' + encodeURIComponent(String(setId)), {
      method: 'DELETE',
    });
  }

  window.issArchiveSetSelector = {
    attachedSetsPath: attachedSetsPath,
    attachSet: attachSet,
    cleanLabel: cleanLabel,
    detachSet: detachSet,
    getAttachedSets: getAttachedSets,
    intValue: intValue,
    loadSet: loadSet,
    notifyAttachedSetsChanged: notifyAttachedSetsChanged,
    request: request,
    searchSets: searchSets,
    searchSetsPath: searchSetsPath,
    setMetaLine: setMetaLine,
    setOptionLabel: setOptionLabel,
    setPath: setPath,
  };
}());
