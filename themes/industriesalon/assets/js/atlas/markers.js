(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var store = Atlas.store || {};
  var mapAdapter = Atlas.map || {};
  var labels = store.labels || {};
  var EMPTY = core.EMPTY || '';
  var HISTORICAL_NO_DATA_KEY = labels.HISTORICAL_NO_DATA_KEY || 'no_data';

  function text(value) {
    return typeof core.text === 'function' ? core.text(value) : (typeof value === 'string' ? value.trim() : EMPTY);
  }

  function compact(value, maxLength) {
    if (typeof core.compact === 'function') {
      return core.compact(value, maxLength);
    }

    var normalized = text(value).replace(/\s+/g, ' ');

    if (!normalized || normalized.length <= maxLength) {
      return normalized;
    }

    return normalized.slice(0, maxLength - 1).trim() + '…';
  }

  function escapeHtml(value) {
    if (typeof core.escapeHtml === 'function') {
      return core.escapeHtml(value);
    }

    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function placeMatchesActor(place, actorKey, eraSlug) {
    return store.placeMatchesActor(place, actorKey, eraSlug);
  }

  function isUnknownEpochFunction(place, state) {
    return store.isUnknownEpochFunction(place, state);
  }

  function createMarkerIcon(place, active, state) {
    var statusClass = text(place.current_status)
      ? ' is-status-' + text(place.current_status).replace(/[^a-z0-9_-]+/g, '-')
      : EMPTY;
    var actorClass = EMPTY;
    var unknownFunctionClass = EMPTY;
    var highlightUnknowns = state && state.activeEra &&
      (state.currentUseType === HISTORICAL_NO_DATA_KEY || state.currentUseType === EMPTY);

    if (state && state.actorKey && placeMatchesActor(place, state.actorKey, state.era)) {
      actorClass = ' is-actor-focus is-actor-' + state.actorKey.replace(/[^a-z0-9_-]+/g, '-');
    }

    if (highlightUnknowns && isUnknownEpochFunction(place, state)) {
      unknownFunctionClass = ' is-function-unknown';
    }

    return window.L.divIcon({
      className: 'iss-atlas-marker' + statusClass + actorClass + unknownFunctionClass + (active ? ' is-active' : EMPTY),
      html:
        '<span class="iss-atlas-marker__dot"></span>' +
        '<span class="iss-atlas-marker__label">' +
        escapeHtml(compact(place.name, 42)) +
        '</span>',
      iconSize: [16, 16],
      iconAnchor: [8, 8]
    });
  }

  function renderMap(state, filteredPlaces, selectedPlace) {
    mapAdapter.renderMarkers(state.leaflet, filteredPlaces, selectedPlace, {
      createMarkerIcon: function (place, active) {
        return createMarkerIcon(place, active, state);
      },
      onSelect: function (place) {
        state.selectedPostId = place.post_id;
        state.shouldPan = true;
        state.render();
      },
      shouldPan: state.shouldPan
    });
    state.shouldPan = false;
  }

  Atlas.markers = {
    createMarkerIcon: createMarkerIcon,
    renderMap: renderMap
  };

  window.issSchoneweideAtlas = Atlas;
})();
