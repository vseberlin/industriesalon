(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var store = Atlas.store || {};
  var EMPTY = core.EMPTY || '';
  var MAP_BOUNDS = core.MAP_BOUNDS || {
    minLat: 52.4448,
    maxLat: 52.4724,
    minLng: 13.4988,
    maxLng: 13.5405
  };

  function text(value) {
    return typeof core.text === 'function' ? core.text(value) : (typeof value === 'string' ? value.trim() : EMPTY);
  }

  function number(value) {
    if (typeof core.number === 'function') {
      return core.number(value);
    }

    var parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function createElement(tagName, className, textValue) {
    return typeof core.createElement === 'function'
      ? core.createElement(tagName, className, textValue)
      : document.createElement(tagName);
  }

  function renderRelationRail(container, state, title, description, rows, emptyText, selectedPlaceId) {
    function buildMiniMap(place) {
      var map = createElement('div', 'iss-atlas-relations__mini-map');
      var dot = createElement('span', 'iss-atlas-relations__mini-map-dot');
      var hasMap = text(state.relationMapUrl);
      var x;
      var y;

      if (!hasMap) {
        return null;
      }

      x = ((number(place.lng) - MAP_BOUNDS.minLng) / (MAP_BOUNDS.maxLng - MAP_BOUNDS.minLng)) * 100;
      y = ((MAP_BOUNDS.maxLat - number(place.lat)) / (MAP_BOUNDS.maxLat - MAP_BOUNDS.minLat)) * 100;

      if (!Number.isFinite(x) || !Number.isFinite(y)) {
        return null;
      }

      x = Math.max(0, Math.min(100, x));
      y = Math.max(0, Math.min(100, y));

      map.style.backgroundImage = 'url("' + state.relationMapUrl + '")';
      map.style.backgroundPosition = x.toFixed(2) + '% ' + y.toFixed(2) + '%';
      map.appendChild(dot);

      return map;
    }

    var rail = createElement('article', 'iss-atlas-relations__rail');
    var head = createElement('header', 'iss-atlas-relations__rail-head');
    var list = createElement('div', 'iss-atlas-relations__rail-list');

    head.appendChild(createElement('p', 'iss-atlas-relations__rail-kicker', title));
    head.appendChild(createElement('p', 'iss-atlas-relations__rail-description', description));
    rail.appendChild(head);

    if (!rows.length) {
      list.appendChild(createElement('p', 'iss-atlas-relations__empty', emptyText));
      rail.appendChild(list);
      container.appendChild(rail);
      return;
    }

    rows.forEach(function (row) {
      var item = createElement('article', 'iss-atlas-relations__item' + (row.place.post_id === selectedPlaceId ? ' is-active' : EMPTY));
      var selectButton = createElement('button', 'iss-atlas-relations__select', row.place.name || 'Ort');
      var meta = createElement('p', 'iss-atlas-relations__meta', row.meta || 'Bezug im Korridor');
      var footer = createElement('div', 'iss-atlas-relations__footer');
      var miniMap = buildMiniMap(row.place);

      if (miniMap) {
        item.appendChild(miniMap);
      }

      selectButton.type = 'button';
      selectButton.addEventListener('click', function () {
        state.selectedPostId = row.place.post_id;
        state.shouldPan = true;
        state.render();
      });

      item.appendChild(selectButton);
      item.appendChild(meta);

      if (row.place.permalink) {
        var link = createElement('a', 'iss-action-link iss-atlas-relations__link', 'Dossier');
        link.href = row.place.permalink;
        footer.appendChild(link);
      }

      if (footer.children.length) {
        item.appendChild(footer);
      }

      list.appendChild(item);
    });

    rail.appendChild(list);
    container.appendChild(rail);
  }

  function renderRelations(container, state, selectedPlace, relationPool) {
    if (!container) {
      return;
    }

    container.innerHTML = EMPTY;

    if (!selectedPlace) {
      container.appendChild(
        createElement('p', 'iss-atlas-relations__empty', 'Ort auswählen, um räumliche, zeitliche und soziale Beziehungen zu sehen.')
      );
      return;
    }

    var pool = relationPool.filter(function (place) {
      return place.post_id !== selectedPlace.post_id;
    });
    var spatial = store.buildSpatialRelations(selectedPlace, pool, state);
    var temporal = store.buildTemporalRelations(selectedPlace, pool, state);
    var social = store.buildSocialRelations(selectedPlace, pool, state);

    renderRelationRail(
      container,
      state,
      'Räumliche Beziehungen',
      'Nähe im Korridor, ergänzt um Akteursbezüge.',
      spatial,
      'Keine belastbaren räumlichen Nachbarschaften in der aktuellen Auswahl.',
      selectedPlace.post_id
    );
    renderRelationRail(
      container,
      state,
      'Zeitliche Beziehungen',
      'Gemeinsame Epochen, Phasen und Funktionslogiken.',
      temporal,
      'Keine zeitlichen Überschneidungen in der aktuellen Auswahl.',
      selectedPlace.post_id
    );
    renderRelationRail(
      container,
      state,
      'Soziale Beziehungen',
      'Publikationen, Nutzungsmuster und institutionelle Verbindungen.',
      social,
      'Keine sozialen bzw. funktionalen Verknüpfungen in der aktuellen Auswahl.',
      selectedPlace.post_id
    );
  }

  Atlas.relations = {
    renderRelations: renderRelations
  };

  window.issSchoneweideAtlas = Atlas;
})();
