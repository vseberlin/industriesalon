(function () {
  var EMPTY = '';
  var ROLE_LABELS = {
    '': 'Alle Orte',
    E: 'Industrieorte',
    M: 'Heutige Nutzungen',
    P: 'Projekte',
    'E+P': 'Bestand + Projekt'
  };
  var STATUS_LABELS = {
    aktiv: 'Aktiv',
    entwicklung: 'In Entwicklung',
    geplant: 'Geplant',
    unklar: 'Unklar',
    abzug: 'Im Abzug',
    sucht: 'Standortsuche'
  };
  var ATLAS_AREAS = {
    'Oberschöneweide': true,
    'Nalepastraße': true,
    Schöneweide: true
  };
  var ERAS = [
    { id: EMPTY, label: 'Alle Zeiten' },
    { id: '1890-1910', label: '1890-1910' },
    { id: '1910-1930', label: '1910-1930' },
    { id: '1930-1945', label: '1930-1945' },
    { id: '1945-1960', label: '1945-1960' },
    { id: '1960-1990', label: '1960-1990' },
    { id: 'heute', label: '1990-heute' }
  ];
  var MAP_BOUNDS = {
    minLat: 52.4448,
    maxLat: 52.4724,
    minLng: 13.4988,
    maxLng: 13.5405
  };
  var BASE_TILE_URL = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
  var BASE_TILE_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, ' +
    '&copy; <a href="https://carto.com/attributions">CARTO</a>';

  function text(value) {
    return typeof value === 'string' ? value.trim() : EMPTY;
  }

  function number(value) {
    var parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function compact(value, maxLength) {
    var normalized = text(value).replace(/\s+/g, ' ');

    if (!normalized || normalized.length <= maxLength) {
      return normalized;
    }

    return normalized.slice(0, maxLength - 1).trim() + '…';
  }

  function relativeUrl(value) {
    var normalized = text(value);

    if (!normalized) {
      return EMPTY;
    }

    try {
      var url = new URL(normalized, window.location.origin);
      return url.pathname + url.search + url.hash;
    } catch (error) {
      return normalized;
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function createElement(tagName, className, textValue) {
    var element = document.createElement(tagName);

    if (className) {
      element.className = className;
    }

    if (typeof textValue === 'string') {
      element.textContent = textValue;
    }

    return element;
  }

  function score(place) {
    if (Number.isFinite(Number(place.story_score))) {
      return Number(place.story_score);
    }

    var total = 0;

    total += Math.min(text(place.summary || place.excerpt).length, 260);
    total += Math.min(text(place.current_summary || place.current).length, 220);
    total += text(place.featured_image_url) ? 160 : 0;
    total += text(place.role).toUpperCase() === 'E+P' ? 40 : 0;

    return total;
  }

  function normalizePlace(place) {
    return {
      post_id: Number.parseInt(place.post_id, 10) || 0,
      slug: text(place.slug),
      name: text(place.name),
      lat: number(place.lat),
      lng: number(place.lng),
      role: text(place.role).toUpperCase(),
      era_id: text(place.era_id),
      era_label: text(place.era_label),
      area: text(place.area),
      address: text(place.address),
      status: text(place.status),
      permalink: relativeUrl(place.permalink),
      featured_image_url: relativeUrl(place.featured_image_url),
      color: text(place.color),
      summary: compact(place.summary || place.excerpt || place.current_summary || place.current, 220),
      secondary: compact(place.secondary || place.archive_summary || place.note_text, 160),
      storyScore: score(place)
    };
  }

  function isAtlasPlace(place) {
    if (place.post_id <= 0 || place.lat === null || place.lng === null || !place.permalink) {
      return false;
    }

    return ATLAS_AREAS[place.area] === true;
  }

  function statusLabel(status) {
    var normalized = text(status).toLowerCase();
    return STATUS_LABELS[normalized] || 'Ort';
  }

  function roleLabel(role) {
    var normalized = text(role).toUpperCase();
    return ROLE_LABELS[normalized] || 'Ort';
  }

  function sortPlaces(left, right) {
    if (right.storyScore !== left.storyScore) {
      return right.storyScore - left.storyScore;
    }

    return text(left.name).localeCompare(text(right.name), 'de');
  }

  function filterPlaces(places, state) {
    var search = text(state.search).toLowerCase();

    return places.filter(function (place) {
      if (state.role && place.role !== state.role) {
        return false;
      }

      if (state.era && place.era_id !== state.era) {
        return false;
      }

      if (!search) {
        return true;
      }

      var haystack = [
        place.name,
        place.address,
        place.area,
        place.summary,
        place.secondary
      ].join(' ').toLowerCase();

      return haystack.indexOf(search) !== -1;
    });
  }

  function getSelectedPlace(state, filteredPlaces) {
    if (!filteredPlaces.length || state.selectedPostId === -1) {
      return null;
    }

    var selected = filteredPlaces.find(function (place) {
      return place.post_id === state.selectedPostId;
    });

    return selected || filteredPlaces[0];
  }

  function buildFilterButton(options) {
    var button = createElement(
      'button',
      'iss-schoneweide-atlas__filter-button' + (options.active ? ' is-active' : EMPTY)
    );

    button.type = 'button';
    button.setAttribute('aria-pressed', options.active ? 'true' : 'false');
    button.appendChild(createElement('span', 'iss-schoneweide-atlas__filter-text', options.label));

    if (typeof options.count === 'number') {
      button.appendChild(
        createElement('span', 'iss-schoneweide-atlas__filter-count', String(options.count))
      );
    }

    button.addEventListener('click', options.onClick);

    return button;
  }

  function renderRoleFilters(container, state) {
    var counts = { '': state.places.length };
    var roles = [EMPTY, 'E', 'M', 'P', 'E+P'];

    state.places.forEach(function (place) {
      counts[place.role] = (counts[place.role] || 0) + 1;
    });

    container.innerHTML = EMPTY;

    roles.forEach(function (role) {
      container.appendChild(buildFilterButton({
        label: ROLE_LABELS[role] || role,
        count: counts[role] || 0,
        active: state.role === role,
        onClick: function () {
          state.role = role;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderEraFilters(container, state) {
    var counts = { '': state.places.length };

    state.places.forEach(function (place) {
      counts[place.era_id] = (counts[place.era_id] || 0) + 1;
    });

    container.innerHTML = EMPTY;

    ERAS.forEach(function (era) {
      container.appendChild(buildFilterButton({
        label: era.label,
        count: counts[era.id] || 0,
        active: state.era === era.id,
        onClick: function () {
          state.era = era.id;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderCount(container, filteredPlaces) {
    if (!filteredPlaces.length) {
      container.textContent = 'Keine Orte im aktuellen Ausschnitt.';
      return;
    }

    container.textContent = String(filteredPlaces.length) + ' Orte im aktuellen Ausschnitt';
  }

  function renderSummary(container, filteredPlaces, selectedPlace) {
    container.innerHTML = EMPTY;

    if (!filteredPlaces.length) {
      return;
    }

    container.appendChild(
      createElement(
        'strong',
        EMPTY,
        selectedPlace && text(selectedPlace.area) ? selectedPlace.area : 'Kernraum Oberschöneweide'
      )
    );
    container.appendChild(
      createElement('span', EMPTY, String(filteredPlaces.length) + ' Orte in der aktuellen Auswahl')
    );
  }

  function setMapStatus(container, message) {
    container.innerHTML = EMPTY;

    if (!message) {
      container.classList.add('is-hidden');
      return;
    }

    container.classList.remove('is-hidden');
    container.appendChild(createElement('p', 'iss-schoneweide-atlas__loading', message));
  }

  function createMarkerIcon(place, active) {
    return window.L.divIcon({
      className: 'iss-schoneweide-atlas__leaflet-marker' + (active ? ' is-active' : EMPTY),
      html:
        '<span class="iss-schoneweide-atlas__marker-dot"></span>' +
        '<span class="iss-schoneweide-atlas__marker-label">' +
        escapeHtml(compact(place.name, 42)) +
        '</span>',
      iconSize: [16, 16],
      iconAnchor: [8, 8]
    });
  }

  function createLeafletState(container) {
    if (!window.L) {
      return null;
    }

    var atlasBounds = window.L.latLngBounds(
      [MAP_BOUNDS.minLat, MAP_BOUNDS.minLng],
      [MAP_BOUNDS.maxLat, MAP_BOUNDS.maxLng]
    );
    var map = window.L.map(container, {
      attributionControl: true,
      maxBounds: atlasBounds.pad(0.16),
      maxBoundsViscosity: 1,
      scrollWheelZoom: false,
      tap: false,
      zoomControl: false,
      zoomSnap: 0.25,
      zoomDelta: 0.5
    });

    window.L.control.zoom({ position: 'bottomright' }).addTo(map);
    window.L.tileLayer(BASE_TILE_URL, {
      attribution: BASE_TILE_ATTRIBUTION,
      maxZoom: 20,
      subdomains: 'abcd'
    }).addTo(map);

    map.fitBounds(atlasBounds, { padding: [24, 24] });
    map.setMinZoom(map.getZoom() - 0.25);

    return {
      map: map,
      markerLayer: window.L.layerGroup().addTo(map)
    };
  }

  function renderMap(state, filteredPlaces, selectedPlace) {
    state.leaflet.markerLayer.clearLayers();

    filteredPlaces
      .slice()
      .sort(function (left, right) {
        if (selectedPlace && left.post_id === selectedPlace.post_id) {
          return 1;
        }

        if (selectedPlace && right.post_id === selectedPlace.post_id) {
          return -1;
        }

        return left.storyScore - right.storyScore;
      })
      .forEach(function (place) {
        var active = selectedPlace && selectedPlace.post_id === place.post_id;
        var marker = window.L.marker([place.lat, place.lng], {
          icon: createMarkerIcon(place, active),
          keyboard: true,
          riseOnHover: true,
          zIndexOffset: active ? 1000 : 0
        });

        marker.on('click', function () {
          state.selectedPostId = place.post_id;
          state.shouldPan = true;
          state.render();
        });

        state.leaflet.markerLayer.addLayer(marker);
      });

    if (state.shouldPan && selectedPlace) {
      state.leaflet.map.panTo([selectedPlace.lat, selectedPlace.lng], { animate: true });
    }

    state.shouldPan = false;
  }

  function buildMediaFigure(place, className, fallbackLabel) {
    var figure = createElement(
      'figure',
      className + (text(place.featured_image_url) ? EMPTY : ' is-fallback')
    );

    if (text(place.featured_image_url)) {
      var image = document.createElement('img');
      image.src = place.featured_image_url;
      image.alt = place.name || EMPTY;
      image.decoding = 'async';
      figure.appendChild(image);
      return figure;
    }

    figure.style.background = text(place.color) || 'linear-gradient(145deg, rgba(56, 52, 48, 0.94), rgba(139, 127, 118, 0.82))';
    figure.appendChild(
      createElement('span', 'iss-schoneweide-atlas__popup-fallback-label', fallbackLabel)
    );
    figure.appendChild(
      createElement('strong', 'iss-schoneweide-atlas__popup-fallback-title', compact(place.name, 54))
    );

    return figure;
  }

  function renderPopup(container, place) {
    container.innerHTML = EMPTY;
    container.classList.toggle('is-empty', !place);

    if (!place) {
      return;
    }

    var card = createElement('article', 'iss-card iss-card--flat iss-schoneweide-atlas__popup-card');
    var close = createElement('button', 'iss-schoneweide-atlas__popup-close', '×');
    var body = createElement('div', 'iss-card__body iss-schoneweide-atlas__popup-body');
    var footer = createElement('div', 'iss-card__footer');
    var facts = createElement('div', 'iss-schoneweide-atlas__popup-facts');

    close.type = 'button';
    close.setAttribute('aria-label', 'Ort schließen');
    close.addEventListener('click', function () {
      container.dispatchEvent(new CustomEvent('iss-close-selection', { bubbles: true }));
    });

    card.appendChild(close);
    card.appendChild(
      buildMediaFigure(place, 'iss-card__media iss-schoneweide-atlas__popup-media', 'Industrieort')
    );

    body.appendChild(
      createElement('p', 'iss-card__kicker iss-schoneweide-atlas__popup-kicker', roleLabel(place.role))
    );
    body.appendChild(createElement('h3', 'iss-card__title', place.name || 'Ort'));

    if (place.address || place.era_label) {
      body.appendChild(
        createElement(
          'p',
          'iss-card__meta',
          [place.era_label, place.address].filter(Boolean).join(' · ')
        )
      );
    }

    if (place.summary) {
      body.appendChild(createElement('p', 'iss-card__text', place.summary));
    }

    if (place.area) {
      var area = createElement('p', 'iss-schoneweide-atlas__popup-fact');
      area.appendChild(createElement('strong', EMPTY, 'Bereich: '));
      area.appendChild(document.createTextNode(place.area));
      facts.appendChild(area);
    }

    if (place.status) {
      var status = createElement('p', 'iss-schoneweide-atlas__popup-fact');
      status.appendChild(createElement('strong', EMPTY, 'Status: '));
      status.appendChild(document.createTextNode(statusLabel(place.status)));
      facts.appendChild(status);
    }

    if (facts.children.length) {
      body.appendChild(facts);
    }

    if (place.permalink) {
      var link = createElement('a', 'iss-action-link', 'Ort entdecken');
      link.href = place.permalink;
      footer.appendChild(link);
    }

    if (footer.children.length) {
      body.appendChild(footer);
    }

    card.appendChild(body);
    container.appendChild(card);
  }

  function pickStoryPlaces(source) {
    var chosen = [];
    var seen = {};

    ERAS.filter(function (era) {
      return era.id;
    }).forEach(function (era) {
      var match = source
        .filter(function (place) {
          return place.era_id === era.id && !seen[place.post_id];
        })
        .sort(sortPlaces)[0];

      if (match) {
        chosen.push(match);
        seen[match.post_id] = true;
      }
    });

    source
      .slice()
      .sort(sortPlaces)
      .forEach(function (place) {
        if (chosen.length >= 6 || seen[place.post_id]) {
          return;
        }

        chosen.push(place);
        seen[place.post_id] = true;
      });

    return chosen.slice(0, 6);
  }

  function renderStories(container, places) {
    container.innerHTML = EMPTY;

    if (!places.length) {
      container.appendChild(
        createElement('p', 'iss-schoneweide-atlas__loading', 'Keine Geschichten für diese Auswahl.')
      );
      return;
    }

    pickStoryPlaces(places).forEach(function (place) {
      var card = createElement('article', 'iss-card iss-card--flat iss-schoneweide-atlas__story-card');
      var body = createElement('div', 'iss-card__body');
      var footer = createElement('div', 'iss-card__footer');

      card.appendChild(buildMediaFigure(place, 'iss-card__media', text(place.era_label) || 'Schöneweide'));
      body.appendChild(createElement('p', 'iss-card__kicker', text(place.era_label) || 'Schöneweide'));
      body.appendChild(createElement('h3', 'iss-card__title', place.name || 'Ort'));

      if (place.summary) {
        body.appendChild(createElement('p', 'iss-card__text', place.summary));
      }

      if (place.permalink) {
        var link = createElement('a', 'iss-action-link', 'Dossier lesen');
        link.href = place.permalink;
        footer.appendChild(link);
      }

      body.appendChild(footer);
      card.appendChild(body);
      container.appendChild(card);
    });
  }

  function render(elements, state) {
    var filteredPlaces = filterPlaces(state.places, state).sort(sortPlaces);
    var selectedPlace = getSelectedPlace(state, filteredPlaces);

    if (selectedPlace) {
      state.selectedPostId = selectedPlace.post_id;
    }

    renderRoleFilters(elements.roleFilters, state);
    renderEraFilters(elements.eraFilters, state);
    renderCount(elements.count, filteredPlaces);
    renderSummary(elements.summary, filteredPlaces, selectedPlace);
    renderMap(state, filteredPlaces, selectedPlace);
    renderPopup(elements.popup, selectedPlace);
    renderStories(elements.stories, filteredPlaces);
    setMapStatus(elements.mapStatus, EMPTY);
  }

  function renderError(elements, message) {
    elements.roleFilters.innerHTML = EMPTY;
    elements.eraFilters.innerHTML = EMPTY;
    elements.summary.innerHTML = EMPTY;
    elements.popup.innerHTML = EMPTY;
    elements.popup.classList.add('is-empty');
    elements.stories.innerHTML = EMPTY;
    elements.count.textContent = message;
    elements.stories.appendChild(createElement('p', 'iss-schoneweide-atlas__loading', message));
    setMapStatus(elements.mapStatus, message);
  }

  function collectElements(root) {
    return {
      roleFilters: root.querySelector('[data-iss-schoneweide-role-filters]'),
      eraFilters: root.querySelector('[data-iss-schoneweide-era-filters]'),
      mapCanvas: root.querySelector('[data-iss-schoneweide-map]'),
      mapStatus: root.querySelector('[data-iss-schoneweide-map-status]'),
      summary: root.querySelector('[data-iss-schoneweide-summary]'),
      popup: root.querySelector('[data-iss-schoneweide-popup]'),
      stories: root.querySelector('[data-iss-schoneweide-stories]'),
      count: root.querySelector('[data-iss-schoneweide-count]'),
      search: root.querySelector('[data-iss-schoneweide-search]'),
      reset: root.querySelector('[data-iss-schoneweide-reset]')
    };
  }

  function init() {
    var root = document.querySelector('[data-iss-schoneweide-atlas]');

    if (!root) {
      return;
    }

    var config = window.industriesalonSchoneweide || {};
    var elements = collectElements(root);
    var keys = Object.keys(elements);
    var hasMissingElement = keys.some(function (key) {
      return !elements[key];
    });

    if (hasMissingElement || !text(config.placesUrl)) {
      return;
    }

    if (!window.L) {
      renderError(elements, 'Die Kartenbibliothek konnte nicht geladen werden.');
      return;
    }

    fetch(config.placesUrl, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Atlas payload unavailable');
        }

        return response.json();
      })
      .then(function (payload) {
        var places = (Array.isArray(payload) ? payload : [])
          .map(normalizePlace)
          .filter(isAtlasPlace)
          .sort(sortPlaces);

        if (!places.length) {
          renderError(elements, 'Keine Atlas-Orte verfügbar.');
          return;
        }

        var leafletState = createLeafletState(elements.mapCanvas);

        if (!leafletState) {
          renderError(elements, 'Die Karte konnte nicht initialisiert werden.');
          return;
        }

        var state = {
          leaflet: leafletState,
          places: places,
          role: EMPTY,
          era: EMPTY,
          search: EMPTY,
          selectedPostId: places[0].post_id,
          shouldPan: false,
          render: function () {
            render(elements, state);
          }
        };

        elements.search.addEventListener('input', function (event) {
          state.search = event.target.value || EMPTY;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        });

        elements.reset.addEventListener('click', function () {
          state.role = EMPTY;
          state.era = EMPTY;
          state.search = EMPTY;
          state.selectedPostId = 0;
          state.shouldPan = false;
          elements.search.value = EMPTY;
          state.render();
        });

        elements.popup.addEventListener('iss-close-selection', function () {
          state.selectedPostId = -1;
          state.shouldPan = false;
          state.render();
        });

        window.addEventListener('resize', function () {
          state.leaflet.map.invalidateSize({ pan: false });
        });

        window.requestAnimationFrame(function () {
          state.leaflet.map.invalidateSize({ pan: false });
        });

        state.render();
      })
      .catch(function () {
        renderError(elements, 'Der Atlas konnte nicht geladen werden.');
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
