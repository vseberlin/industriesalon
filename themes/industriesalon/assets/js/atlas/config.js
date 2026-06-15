(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var text = core.text || function (value) {
    return typeof value === 'string' ? value.trim() : '';
  };
  var payloadCache = {};

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) {
        throw new Error('Request failed');
      }

      return response.json();
    });
  }

  function getAtlasPayload(config) {
    var contextUrl = text(config.contextUrl);
    var overlaysUrl = text(config.overlaysUrl);
    var cacheKey = [
      text(config.placesUrl),
      contextUrl,
      overlaysUrl
    ].join('|');

    if (!payloadCache[cacheKey]) {
      payloadCache[cacheKey] = Promise.all([
        fetchJson(config.placesUrl),
        contextUrl
          ? fetchJson(contextUrl).catch(function () {
              return { eras: [], stories: [] };
            })
          : Promise.resolve({ eras: [], stories: [] }),
        overlaysUrl
          ? fetchJson(overlaysUrl).catch(function () {
              return { type: 'FeatureCollection', features: [] };
            })
          : Promise.resolve({ type: 'FeatureCollection', features: [] })
      ]);
    }

    return payloadCache[cacheKey];
  }

  function reportInitError(root, message, detail) {
    if (window.console && typeof window.console.error === 'function') {
      window.console.error('Schoneweide Atlas:', message, detail || root);
    }
  }

  function readAtlasConfig(root) {
    var node = root ? root.querySelector('[data-iss-schoneweide-atlas-config]') : null;

    if (!node) {
      return {};
    }

    try {
      var parsed = JSON.parse(node.textContent || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      reportInitError(root, 'Atlas config JSON could not be parsed.', error);
      return {};
    }
  }

  function collectRoots() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-iss-schoneweide-atlas]'));
  }

  Atlas.config = {
    collectRoots: collectRoots,
    getAtlasPayload: getAtlasPayload,
    readAtlasConfig: readAtlasConfig,
    reportInitError: reportInitError
  };

  window.issSchoneweideAtlas = Atlas;
})();
