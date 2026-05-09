(function () {
  var EMPTY = '';
  var DEFAULT_ERA_LABEL = 'Alle Zeiten';
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
    'Niederschöneweide': true,
    'Oberschöneweide': true,
    'Nalepastraße': true,
    Schöneweide: true
  };
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
      area: text(place.area),
      address: text(place.address),
      status: text(place.status),
      permalink: relativeUrl(place.permalink),
      featured_image_url: relativeUrl(place.featured_image_url),
      color: text(place.color),
      summary: compact(place.summary || place.excerpt || place.current_summary || place.current, 220),
      secondary: compact(place.secondary || place.archive_summary || place.note_text, 160),
      era_id: text(place.era_id),
      era_label: text(place.era_label),
      era_slug: text(place.era_slug),
      era_name: text(place.era_name),
      era_source: text(place.era_source),
      explicit_era_slugs: Array.isArray(place.explicit_era_slugs)
        ? place.explicit_era_slugs.map(text).filter(Boolean)
        : [],
      storyScore: score(place)
    };
  }

  function normalizeStory(story) {
    return {
      id: Number.parseInt(story.id, 10) || 0,
      slug: text(story.slug),
      title: text(story.title),
      excerpt: compact(story.excerpt, 260),
      permalink: relativeUrl(story.permalink),
      featured_image_url: relativeUrl(story.featured_image_url),
      eraSlugs: Array.isArray(story.era_slugs) ? story.era_slugs.map(text).filter(Boolean) : [],
      eraLegacyIds: Array.isArray(story.era_legacy_ids) ? story.era_legacy_ids.map(text).filter(Boolean) : [],
      placeIds: Array.isArray(story.place_ids)
        ? story.place_ids.map(function (value) {
            return Number.parseInt(value, 10) || 0;
          }).filter(Boolean)
        : []
    };
  }

  function normalizeEra(era) {
    return {
      slug: text(era.slug),
      name: text(era.name),
      caption: text(era.caption),
      legacyId: text(era.legacy_id),
      legacyLabel: text(era.legacy_label),
      legacyShortLabel: text(era.legacy_short_label),
      legacyCaption: text(era.legacy_caption),
      placeCount: Number.parseInt(era.place_count, 10) || 0,
      storyCount: Number.parseInt(era.story_count, 10) || 0
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

  function buildEraMap(eras) {
    var map = {};

    eras.forEach(function (era) {
      if (era.slug) {
        map[era.slug] = era;
      }
    });

    return map;
  }

  function deriveFallbackEras(places) {
    var seen = {};
    var eras = [];

    places.forEach(function (place) {
      var slug = text(place.era_slug);
      if (!slug || seen[slug]) {
        return;
      }

      seen[slug] = true;
      eras.push({
        slug: slug,
        name: text(place.era_name) || text(place.era_label) || slug,
        caption: EMPTY,
        legacyId: text(place.era_id),
        legacyLabel: text(place.era_label),
        legacyShortLabel: EMPTY,
        legacyCaption: EMPTY,
        placeCount: 0,
        storyCount: 0
      });
    });

    return eras;
  }

  function getEraScopedPlaces(places, eraSlug) {
    if (!eraSlug) {
      return places.slice();
    }

    return places.filter(function (place) {
      if (text(place.era_slug) === eraSlug) {
        return true;
      }

      return place.explicit_era_slugs.indexOf(eraSlug) !== -1;
    });
  }

  function filterPlaces(places, state) {
    var search = text(state.search).toLowerCase();

    return places.filter(function (place) {
      if (state.role && place.role !== state.role) {
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
      'iss-atlas-app__filter-button' + (options.active ? ' is-active' : EMPTY)
    );

    button.type = 'button';
    button.setAttribute('aria-pressed', options.active ? 'true' : 'false');
    button.appendChild(createElement('span', 'iss-atlas-app__filter-text', options.label));

    if (typeof options.count === 'number') {
      button.appendChild(
        createElement('span', 'iss-atlas-app__filter-count', String(options.count))
      );
    }

    button.addEventListener('click', options.onClick);

    return button;
  }

  function renderEraFilters(container, state) {
    var eraCounts = {};
    var eras = state.eras.length ? state.eras : deriveFallbackEras(state.places);

    state.places.forEach(function (place) {
      if (!place.era_slug) {
        return;
      }

      eraCounts[place.era_slug] = (eraCounts[place.era_slug] || 0) + 1;
    });

    container.innerHTML = EMPTY;
    container.appendChild(buildFilterButton({
      label: DEFAULT_ERA_LABEL,
      count: state.places.length,
      active: state.era === EMPTY,
      onClick: function () {
        state.era = EMPTY;
        state.selectedPostId = 0;
        state.shouldPan = false;
        state.render();
      }
    }));

    eras.forEach(function (era) {
      var label = era.name || era.legacyLabel || era.slug;

      container.appendChild(buildFilterButton({
        label: label,
        count: eraCounts[era.slug] || 0,
        active: state.era === era.slug,
        onClick: function () {
          state.era = era.slug;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderRoleFilters(container, state, eraScopedPlaces) {
    var counts = { '': eraScopedPlaces.length };
    var roles = [EMPTY, 'E', 'M', 'P', 'E+P'];

    eraScopedPlaces.forEach(function (place) {
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

  function renderCount(container, filteredPlaces, state) {
    if (!filteredPlaces.length) {
      container.textContent = 'Keine Orte in der aktuellen Auswahl.';
      return;
    }

    var label = state.activeEra ? state.activeEra.name || state.activeEra.legacyLabel : DEFAULT_ERA_LABEL;
    container.textContent = String(filteredPlaces.length) + ' Orte für ' + label;
  }

  function renderSummary(container, filteredPlaces, selectedPlace, state) {
    container.innerHTML = EMPTY;

    if (!filteredPlaces.length) {
      return;
    }

    var headline = DEFAULT_ERA_LABEL;
    if (selectedPlace && text(selectedPlace.area)) {
      headline = selectedPlace.area;
    } else if (state.activeEra) {
      headline = state.activeEra.name || state.activeEra.legacyLabel || DEFAULT_ERA_LABEL;
    }

    container.appendChild(createElement('strong', EMPTY, headline));
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
    container.appendChild(createElement('p', 'iss-atlas-loading', message));
  }

  function createMarkerIcon(place, active) {
    return window.L.divIcon({
      className: 'iss-atlas-marker' + (active ? ' is-active' : EMPTY),
      html:
        '<span class="iss-atlas-marker__dot"></span>' +
        '<span class="iss-atlas-marker__label">' +
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
          zIndexOffset: active ? 2400 : 0
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

  function buildPlaceMediaFigure(place, className, fallbackLabel) {
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

    figure.style.background =
      text(place.color) ||
      'var(--iss-atlas-fallback-gradient, linear-gradient(145deg, rgba(56, 52, 48, 0.94), rgba(139, 127, 118, 0.82)))';
    figure.appendChild(createElement('span', 'iss-atlas-fallback__label', fallbackLabel));
    figure.appendChild(createElement('strong', 'iss-atlas-fallback__title', compact(place.name, 54)));

    return figure;
  }

  function buildStoryMediaFigure(story, className, fallbackLabel) {
    var figure = createElement(
      'figure',
      className + (text(story.featured_image_url) ? EMPTY : ' is-fallback')
    );

    if (text(story.featured_image_url)) {
      var image = document.createElement('img');
      image.src = story.featured_image_url;
      image.alt = story.title || EMPTY;
      image.decoding = 'async';
      figure.appendChild(image);
      return figure;
    }

    figure.style.background =
      'var(--iss-atlas-fallback-gradient, linear-gradient(145deg, rgba(56, 52, 48, 0.94), rgba(139, 127, 118, 0.82)))';
    figure.appendChild(createElement('span', 'iss-atlas-fallback__label', fallbackLabel));
    figure.appendChild(createElement('strong', 'iss-atlas-fallback__title', compact(story.title, 54)));

    return figure;
  }

  function renderPopup(container, place) {
    container.innerHTML = EMPTY;
    container.classList.toggle('is-empty', !place);

    if (!place) {
      return;
    }

    var card = createElement('article', 'iss-card iss-card--flat iss-atlas-popup-card');
    var close = createElement('button', 'iss-atlas-popup-card__close', '×');
    var body = createElement('div', 'iss-card__body');
    var footer = createElement('div', 'iss-card__footer');
    var facts = createElement('div', 'iss-atlas-popup-card__facts');

    close.type = 'button';
    close.setAttribute('aria-label', 'Ort schließen');
    close.addEventListener('click', function () {
      container.dispatchEvent(new CustomEvent('iss-close-selection', { bubbles: true }));
    });

    card.appendChild(close);
    card.appendChild(
      buildPlaceMediaFigure(place, 'iss-card__media iss-atlas-popup-card__media', 'Industrieort')
    );

    body.appendChild(
      createElement('p', 'iss-card__kicker iss-kicker iss-kicker--compact', roleLabel(place.role))
    );
    body.appendChild(createElement('h3', 'iss-card__title', place.name || 'Ort'));

    if (place.address || place.era_name || place.era_label) {
      body.appendChild(
        createElement(
          'p',
          'iss-card__meta',
          [place.era_name || place.era_label, place.address].filter(Boolean).join(' · ')
        )
      );
    }

    if (place.summary) {
      body.appendChild(createElement('p', 'iss-card__text', place.summary));
    }

    if (place.area) {
      var area = createElement('p', 'iss-atlas-popup-card__fact');
      area.appendChild(createElement('strong', EMPTY, 'Bereich: '));
      area.appendChild(document.createTextNode(place.area));
      facts.appendChild(area);
    }

    if (place.status) {
      var status = createElement('p', 'iss-atlas-popup-card__fact');
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

  function getSelectedStories(state) {
    if (!state.stories.length) {
      return [];
    }

    if (!state.era) {
      return state.stories.slice();
    }

    return state.stories.filter(function (story) {
      return story.eraSlugs.indexOf(state.era) !== -1;
    });
  }

  function pickStoryPlaces(source) {
    var chosen = [];
    var seen = {};
    var eraBuckets = {};

    source.forEach(function (place) {
      if (!place.era_slug) {
        return;
      }

      if (!eraBuckets[place.era_slug]) {
        eraBuckets[place.era_slug] = [];
      }

      eraBuckets[place.era_slug].push(place);
    });

    Object.keys(eraBuckets).forEach(function (slug) {
      var match = eraBuckets[slug].sort(sortPlaces)[0];

      if (match && !seen[match.post_id]) {
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

  function renderStoryIntro(container, state, stories, filteredPlaces) {
    container.innerHTML = EMPTY;

    if (!state.activeEra && !stories.length) {
      return;
    }

    var title = state.activeEra
      ? state.activeEra.name || state.activeEra.legacyLabel
      : 'Atlas';
    var body = EMPTY;

    if (stories.length && stories[0].excerpt) {
      body = stories[0].excerpt;
    } else if (state.activeEra && state.activeEra.caption) {
      body = state.activeEra.caption;
    } else if (filteredPlaces.length) {
      body = 'Die aktuelle Auswahl zeigt Orte, die zur gewählten Zeitschicht und Suche passen.';
    }

    var panel = createElement('div', 'iss-atlas-app__story-note');
    panel.appendChild(createElement('p', 'iss-atlas-app__story-note-kicker', state.activeEra ? 'Epoche' : 'Atlas'));
    panel.appendChild(createElement('h3', 'iss-atlas-app__story-note-title', title));

    if (body) {
      panel.appendChild(createElement('p', 'iss-atlas-app__story-note-text', body));
    }

    container.appendChild(panel);
  }

  function renderStoryCards(container, stories, state) {
    stories.forEach(function (story) {
      var card = createElement('article', 'iss-card iss-card--flat iss-atlas-story-card');
      var body = createElement('div', 'iss-card__body');
      var footer = createElement('div', 'iss-card__footer');
      var kicker = state.activeEra
        ? state.activeEra.name || state.activeEra.legacyLabel
        : (story.eraSlugs[0] && state.eraMap[story.eraSlugs[0]]
            ? state.eraMap[story.eraSlugs[0]].name
            : 'Atlas');

      card.appendChild(buildStoryMediaFigure(story, 'iss-card__media', kicker));
      body.appendChild(createElement('p', 'iss-card__kicker iss-kicker iss-kicker--compact', kicker));
      body.appendChild(createElement('h3', 'iss-card__title', story.title || 'Geschichte'));

      if (story.excerpt) {
        body.appendChild(createElement('p', 'iss-card__text', story.excerpt));
      }

      if (story.permalink) {
        var link = createElement('a', 'iss-action-link', 'Geschichte lesen');
        link.href = story.permalink;
        footer.appendChild(link);
      }

      body.appendChild(footer);
      card.appendChild(body);
      container.appendChild(card);
    });
  }

  function renderFallbackPlaceCards(container, places) {
    pickStoryPlaces(places).forEach(function (place) {
      var card = createElement('article', 'iss-card iss-card--flat iss-atlas-story-card');
      var body = createElement('div', 'iss-card__body');
      var footer = createElement('div', 'iss-card__footer');
      var kicker = text(place.era_name) || text(place.era_label) || 'Schöneweide';

      card.appendChild(buildPlaceMediaFigure(place, 'iss-card__media', kicker));
      body.appendChild(createElement('p', 'iss-card__kicker iss-kicker iss-kicker--compact', kicker));
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

  function renderStories(container, state, filteredPlaces) {
    var stories = getSelectedStories(state);

    container.innerHTML = EMPTY;

    if (stories.length) {
      renderStoryCards(container, stories, state);
      return;
    }

    if (!filteredPlaces.length) {
      container.appendChild(
        createElement('p', 'iss-atlas-loading', 'Keine Geschichten für diese Auswahl.')
      );
      return;
    }

    renderFallbackPlaceCards(container, filteredPlaces);
  }

  function render(elements, state) {
    var eraScopedPlaces = getEraScopedPlaces(state.places, state.era).sort(sortPlaces);
    var filteredPlaces = filterPlaces(eraScopedPlaces, state).sort(sortPlaces);
    var selectedPlace = getSelectedPlace(state, filteredPlaces);
    var selectedStories = getSelectedStories(state);

    state.activeEra = state.era ? (state.eraMap[state.era] || null) : null;

    if (selectedPlace) {
      state.selectedPostId = selectedPlace.post_id;
    }

    renderEraFilters(elements.eraFilters, state);
    renderRoleFilters(elements.roleFilters, state, eraScopedPlaces);
    renderStoryIntro(elements.storyIntro, state, selectedStories, filteredPlaces);
    renderCount(elements.count, filteredPlaces, state);
    renderSummary(elements.summary, filteredPlaces, selectedPlace, state);
    renderMap(state, filteredPlaces, selectedPlace);
    renderPopup(elements.popup, selectedPlace);
    renderStories(elements.stories, state, filteredPlaces);
    setMapStatus(elements.mapStatus, EMPTY);
  }

  function renderError(elements, message) {
    elements.eraFilters.innerHTML = EMPTY;
    elements.roleFilters.innerHTML = EMPTY;
    elements.summary.innerHTML = EMPTY;
    elements.storyIntro.innerHTML = EMPTY;
    elements.popup.innerHTML = EMPTY;
    elements.popup.classList.add('is-empty');
    elements.stories.innerHTML = EMPTY;
    elements.count.textContent = message;
    elements.stories.appendChild(createElement('p', 'iss-atlas-loading', message));
    setMapStatus(elements.mapStatus, message);
  }

  function collectElements(root) {
    return {
      eraFilters: root.querySelector('[data-iss-schoneweide-era-filters]'),
      roleFilters: root.querySelector('[data-iss-schoneweide-role-filters]'),
      mapSurface: root.querySelector('.iss-atlas-app__map-surface'),
      mapCanvas: root.querySelector('[data-iss-schoneweide-map]'),
      mapStatus: root.querySelector('[data-iss-schoneweide-map-status]'),
      summary: root.querySelector('[data-iss-schoneweide-summary]'),
      storyIntro: root.querySelector('[data-iss-schoneweide-story-intro]'),
      popup: root.querySelector('[data-iss-schoneweide-popup]'),
      stories: root.querySelector('[data-iss-schoneweide-stories]'),
      count: root.querySelector('[data-iss-schoneweide-count]'),
      search: root.querySelector('[data-iss-schoneweide-search]'),
      reset: root.querySelector('[data-iss-schoneweide-reset]')
    };
  }

  function bindMapSizeSync(map, surface) {
    var frameId = 0;

    function sync() {
      if (frameId) {
        return;
      }

      frameId = window.requestAnimationFrame(function () {
        frameId = 0;
        map.invalidateSize({ pan: false });
      });
    }

    window.addEventListener('resize', sync);

    if (window.ResizeObserver && surface) {
      var observer = new window.ResizeObserver(sync);
      observer.observe(surface);
    }

    sync();
  }

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) {
        throw new Error('Request failed');
      }

      return response.json();
    });
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

    var placesRequest = fetchJson(config.placesUrl);
    var contextRequest = text(config.contextUrl)
      ? fetchJson(config.contextUrl).catch(function () {
          return { eras: [], stories: [] };
        })
      : Promise.resolve({ eras: [], stories: [] });

    Promise.all([placesRequest, contextRequest])
      .then(function (results) {
        var placePayload = Array.isArray(results[0]) ? results[0] : [];
        var contextPayload = results[1] && typeof results[1] === 'object' ? results[1] : {};
        var places = placePayload
          .map(normalizePlace)
          .filter(isAtlasPlace)
          .sort(sortPlaces);
        var eras = Array.isArray(contextPayload.eras)
          ? contextPayload.eras.map(normalizeEra).filter(function (era) { return era.slug; })
          : [];
        var stories = Array.isArray(contextPayload.stories)
          ? contextPayload.stories.map(normalizeStory).filter(function (story) { return story.id > 0; })
          : [];

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
          eras: eras,
          eraMap: buildEraMap(eras.length ? eras : deriveFallbackEras(places)),
          stories: stories,
          era: EMPTY,
          activeEra: null,
          role: EMPTY,
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
          state.era = EMPTY;
          state.role = EMPTY;
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

        bindMapSizeSync(state.leaflet.map, elements.mapSurface);
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
