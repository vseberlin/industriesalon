(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var AtlasCore = Atlas.core || {};
  var AtlasConfig = Atlas.config || {};
  var AtlasLayout = Atlas.layout || {};
  var AtlasMap = Atlas.map || {};
  var AtlasProvider = Atlas.provider || {};
  var AtlasStore = Atlas.store || {};
  var AtlasPlaces = Atlas.places || {};
  var AtlasStoreLabels = AtlasStore.labels || {};
  var EMPTY = AtlasCore.EMPTY || '';
  var DEFAULT_STATUS_LABEL = AtlasStoreLabels.DEFAULT_STATUS_LABEL || 'Alle Situationen';
  var DEFAULT_USE_TYPE_LABEL = AtlasStoreLabels.DEFAULT_USE_TYPE_LABEL || 'Alle Nutzungen';
  var CURRENT_STATUS_LABELS = AtlasStoreLabels.CURRENT_STATUS_LABELS || {
    '': DEFAULT_STATUS_LABEL,
    in_use: 'In Nutzung',
    vacant: 'Leerstand',
    temporary_use: 'Zwischennutzung',
    in_transition: 'Im Umbau',
    planned: 'Projekt / Planung',
    for_sale: 'Verkauf / offen',
    unclear: 'Unklar'
  };
  var CURRENT_USE_TYPE_LABELS = AtlasStoreLabels.CURRENT_USE_TYPE_LABELS || {
    '': DEFAULT_USE_TYPE_LABEL,
    culture: 'Kultur',
    education: 'Bildung / Forschung',
    commercial: 'Gewerbe / Bueros',
    industrial: 'Produktion',
    residential: 'Wohnen',
    administration: 'Verwaltung',
    community: 'Gemeinwohl / Soziales',
    mixed: 'Mischnutzung'
  };
  var HISTORICAL_NO_DATA_KEY = AtlasStoreLabels.HISTORICAL_NO_DATA_KEY || 'no_data';
  var UNKNOWN_EPOCH_SUMMARY = AtlasStoreLabels.UNKNOWN_EPOCH_SUMMARY ||
    'Für diesen Ort liegen im gewählten Zeitfenster bisher keine gesicherten historischen Nachweise vor. Wenn Sie historische Dokumente, Fotos oder andere Objekte haben, freuen wir uns über Ihre Kontaktaufnahme.';
  var MAP_BOUNDS = AtlasCore.MAP_BOUNDS;

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

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getStaticRelationMapUrl(config) {
    return AtlasProvider.getStaticRelationMapUrl(config);
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

  function normalizePlace(place) {
    return AtlasStore.normalizePlace(place);
  }

  function normalizeStory(story) {
    return AtlasStore.normalizeStory(story);
  }

  function normalizeEra(era) {
    return AtlasStore.normalizeEra(era);
  }

  function normalizeActor(actor) {
    return AtlasStore.normalizeActor(actor);
  }

  function isAtlasPlace(place) {
    return AtlasStore.isAtlasPlace(place);
  }

  function currentStatusLabel(place) {
    if (text(place.current_status_label)) {
      return place.current_status_label;
    }

    return CURRENT_STATUS_LABELS[text(place.current_status).toLowerCase()] || 'Ort';
  }

  function currentUseTypeLabel(place) {
    if (text(place.current_use_type_label)) {
      return place.current_use_type_label;
    }

    return CURRENT_USE_TYPE_LABELS[text(place.current_use_type).toLowerCase()] || EMPTY;
  }

  function presentLabel(place) {
    if (text(place.present_label)) {
      return place.present_label;
    }

    var pieces = [];
    var status = currentStatusLabel(place);
    var type = currentUseTypeLabel(place);

    if (status && status !== 'Ort') {
      pieces.push(status);
    }

    if (type) {
      pieces.push(type);
    }

    return pieces.join(' · ') || 'Ort';
  }

  function historyLabel(place) {
    if (place.explicit_era_names.length) {
      return place.explicit_era_names.join(' · ');
    }

    return text(place.era_name) || text(place.era_label) || 'Zeitfenster offen';
  }

  function getMatchingEpochSummary(place, state) {
    if (!state.activeEra || !Array.isArray(place.epoch_summaries) || !place.epoch_summaries.length) {
      return null;
    }

    var eraCandidates = place.epoch_summaries.filter(function (epoch) {
      return text(epoch.era_slug) === state.era;
    });

    if (!eraCandidates.length) {
      return null;
    }

    if (state.currentUseType) {
      var exact = eraCandidates.find(function (epoch) {
        return text(epoch.function_key) === state.currentUseType && (text(epoch.summary) || text(epoch.phase_name));
      });
      if (exact) {
        return exact;
      }
    }

    var withSummary = eraCandidates.find(function (epoch) {
      return !!text(epoch.summary);
    });

    return withSummary || eraCandidates[0];
  }

  function epochRangeLabel(epoch) {
    if (!epoch || (!Number.isFinite(epoch.start_year) && !Number.isFinite(epoch.end_year))) {
      return EMPTY;
    }

    var start = Number.isFinite(epoch.start_year) ? String(epoch.start_year) : '?';
    var end = Number.isFinite(epoch.end_year) ? String(epoch.end_year) : 'heute';

    return start + '–' + end;
  }

  function popupKickerLabel(place, state, _epochContext) {
    if (state.activeEra) {
      if (state.currentUseType) {
        return useTypeFilterLabel(state, state.currentUseType);
      }

      return state.activeEra.name || state.activeEra.legacyLabel || historyLabel(place);
    }

    return presentLabel(place);
  }

  function popupTitleLabel(place, state, epochContext) {
    if (state.activeEra && isUnknownEpochFunction(place, state)) {
      return 'Historische Funktion unklar';
    }

    if (state.activeEra && epochContext && text(epochContext.phase_name)) {
      return text(epochContext.phase_name);
    }

    return text(place.name) || 'Ort';
  }

  function activeSummary(place, state) {
    if (state.activeEra) {
      if (isUnknownEpochFunction(place, state)) {
        return UNKNOWN_EPOCH_SUMMARY;
      }

      var epoch = getMatchingEpochSummary(place, state);
      if (epoch && text(epoch.summary)) {
        return text(epoch.summary);
      }

      return text(place.archive_summary) || text(place.summary);
    }

    if (state.currentStatus || state.currentUseType) {
      return text(place.current_summary) || text(place.summary);
    }

    return text(place.summary);
  }

  function buildSpatialRelations(selectedPlace, places, state) {
    return AtlasStore.buildSpatialRelations(selectedPlace, places, state);
  }

  function buildTemporalRelations(selectedPlace, places, state) {
    return AtlasStore.buildTemporalRelations(selectedPlace, places, state);
  }

  function buildSocialRelations(selectedPlace, places, state) {
    return AtlasStore.buildSocialRelations(selectedPlace, places, state);
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
    var spatial = buildSpatialRelations(selectedPlace, pool, state);
    var temporal = buildTemporalRelations(selectedPlace, pool, state);
    var social = buildSocialRelations(selectedPlace, pool, state);

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

  function sortPlaces(left, right) {
    return AtlasStore.sortPlaces(left, right);
  }

  function getActorKeysForEra(place, eraSlug) {
    return AtlasStore.getActorKeysForEra(place, eraSlug);
  }

  function placeMatchesActor(place, actorKey, eraSlug) {
    return AtlasStore.placeMatchesActor(place, actorKey, eraSlug);
  }

  function useTypeFilterLabel(state, key) {
    return AtlasStore.useTypeFilterLabel(state, key);
  }

  function isUnknownEpochFunction(place, state) {
    return AtlasStore.isUnknownEpochFunction(place, state);
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

  function createLeafletState(container, config) {
    return AtlasMap.createLeafletState(container, config);
  }

  function renderMap(state, filteredPlaces, selectedPlace) {
    AtlasMap.renderMarkers(state.leaflet, filteredPlaces, selectedPlace, {
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

  function buildPlaceMediaFigure(place, className, fallbackLabel) {
    var imageUrl = text(place.archive_image_url) || text(place.featured_image_url);
    var figure = createElement(
      'figure',
      className + (imageUrl ? EMPTY : ' is-fallback')
    );

    if (imageUrl) {
      var image = document.createElement('img');
      image.src = imageUrl;
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

  function appendFactItem(container, label, value, accentClass) {
    if (!text(value)) {
      return;
    }

    var item = createElement(
      'p',
      'iss-atlas-popup-card__fact' + (accentClass ? ' ' + accentClass : EMPTY)
    );
    item.appendChild(createElement('span', 'iss-atlas-popup-card__fact-label', label));
    item.appendChild(createElement('span', 'iss-atlas-popup-card__fact-value', value));
    container.appendChild(item);
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

  function renderPopup(container, place, state) {
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
    var primaryFacts = createElement('div', 'iss-atlas-popup-card__fact-group');
    var secondaryFacts = createElement('div', 'iss-atlas-popup-card__fact-group');
    var summaryText = activeSummary(place, state);
    var epochContext = getMatchingEpochSummary(place, state);

    close.type = 'button';
    close.setAttribute('aria-label', 'Details schließen');
    close.addEventListener('click', function () {
      container.dispatchEvent(new CustomEvent('iss-close-selection', { bubbles: true }));
    });

    card.appendChild(close);
    card.appendChild(
      buildPlaceMediaFigure(place, 'iss-card__media iss-atlas-popup-card__media', 'Ort')
    );

    body.appendChild(
      createElement('p', 'iss-card__kicker iss-kicker iss-kicker--compact', popupKickerLabel(place, state, epochContext))
    );
    body.appendChild(createElement('h3', 'iss-card__title', popupTitleLabel(place, state, epochContext)));

    var metaEraLabel = state.activeEra
      ? (state.activeEra.name || state.activeEra.legacyLabel || EMPTY)
      : (place.era_name || place.era_label);

    if (place.address || metaEraLabel) {
      body.appendChild(
        createElement(
          'p',
          'iss-atlas-popup-card__meta',
          [metaEraLabel, place.address].filter(Boolean).join(' · ')
        )
      );
    }

    if (state.activeEra) {
      var eraName = state.activeEra ? (state.activeEra.name || state.activeEra.legacyLabel || EMPTY) : EMPTY;
      appendFactItem(primaryFacts, 'Zeitfenster: ', eraName, 'is-emphasis');

      if (epochContext && text(epochContext.function_key)) {
        appendFactItem(primaryFacts, 'Funktion: ', useTypeFilterLabel(state, text(epochContext.function_key)));
      } else if (state.currentUseType) {
        appendFactItem(primaryFacts, 'Funktion: ', useTypeFilterLabel(state, state.currentUseType));
      }

      if (epochContext && text(epochContext.phase_name)) {
        appendFactItem(secondaryFacts, 'Phase: ', text(epochContext.phase_name));
      }

      if (epochContext) {
        appendFactItem(secondaryFacts, 'Zeitraum: ', epochRangeLabel(epochContext));
      }

      appendFactItem(secondaryFacts, 'Heute (zum Vergleich): ', presentLabel(place));
    } else {
      appendFactItem(primaryFacts, 'Historische Epochen: ', historyLabel(place));
      appendFactItem(primaryFacts, 'Heute: ', currentStatusLabel(place), 'is-emphasis');
      appendFactItem(primaryFacts, 'Nutzung: ', currentUseTypeLabel(place));
    }

    if (place.has_tour_usage) {
      appendFactItem(secondaryFacts, 'Führung: ', 'Im Rundgang enthalten');
    }

    if (primaryFacts.children.length) {
      facts.appendChild(primaryFacts);
    }

    if (secondaryFacts.children.length) {
      facts.appendChild(secondaryFacts);
    }

    if (facts.children.length) {
      body.appendChild(facts);
    }

    if (summaryText) {
      var summary = createElement('div', 'iss-atlas-popup-card__summary');
      var summaryLabel = 'Kurznotiz';
      if (state.activeEra && isUnknownEpochFunction(place, state)) {
        summaryLabel = 'Hinweis';
      } else if (state.activeEra && state.currentUseType) {
        summaryLabel = useTypeFilterLabel(state, state.currentUseType) + ' im Zeitfenster';
      } else if (state.activeEra) {
        summaryLabel = 'Einordnung zum gewählten Zeitfenster';
      } else if (state.currentStatus || state.currentUseType) {
        summaryLabel = 'Einordnung zur heutigen Situation';
      }
      summary.appendChild(createElement('p', 'iss-atlas-popup-card__summary-label', summaryLabel));
      summary.appendChild(createElement('p', 'iss-atlas-popup-card__summary-text', summaryText));
      body.appendChild(summary);
    }

    if (place.note_text) {
      body.appendChild(createElement('p', 'iss-atlas-popup-card__note', place.note_text));
    }

    if (place.permalink) {
      var link = createElement('a', 'iss-action-link', 'Zum Ort');
      link.href = place.permalink;
      footer.appendChild(link);
    }

    if (Array.isArray(place.related_publications) && place.related_publications.length) {
      var publication = place.related_publications[0];
      var prefersKwoPublication = text(place.name).toLowerCase().indexOf('kwo') !== -1
        || getActorKeysForEra(place, state.era).indexOf('kwo') !== -1;
      if (prefersKwoPublication) {
        var kwoPublication = place.related_publications.find(function (item) {
          return /kwo|kabelwerk/i.test(text(item.title));
        });
        if (kwoPublication) {
          publication = kwoPublication;
        }
      }
      if (publication && publication.permalink) {
        var publicationLink = createElement('a', 'iss-action-link', 'Zur Publikation');
        publicationLink.href = publication.permalink;
        publicationLink.title = publication.title || 'Publikation';
        footer.appendChild(publicationLink);
      }
    }

    if (footer.children.length) {
      body.appendChild(footer);
    }

    card.appendChild(body);
    container.appendChild(card);
  }

  function getSelectedStories(state) {
    return AtlasStore.getSelectedStories(state);
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
      body = 'Die aktuelle Auswahl zeigt Orte, die zum gewählten Zeitfenster und zur Suche passen.';
    }

    var panel = createElement('div', 'iss-atlas-app__story-note');
    panel.appendChild(createElement('p', 'iss-atlas-app__story-note-kicker', state.activeEra ? 'Zeitfenster' : 'Atlas'));
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
        createElement('p', 'iss-atlas-loading', 'Keine Geschichten in dieser Auswahl.')
      );
      return;
    }

    renderFallbackPlaceCards(container, filteredPlaces);
  }

  function render(elements, state) {
    var placeContext = AtlasPlaces.render(elements, state);

    renderStoryIntro(elements.storyIntro, state, placeContext.selectedStories, placeContext.filteredPlaces);
    renderRelations(elements.relations, state, placeContext.selectedPlace, placeContext.eraScopedPlaces);
    renderMap(state, placeContext.filteredPlaces, placeContext.selectedPlace);
    renderPopup(elements.popup, placeContext.selectedPlace, state);
    renderStories(elements.stories, state, placeContext.filteredPlaces);
    setMapStatus(elements.mapStatus, EMPTY);
  }

  function renderError(elements, message) {
    elements.eraFilters.innerHTML = EMPTY;
    if (elements.statusFilters) {
      elements.statusFilters.innerHTML = EMPTY;
    }
    elements.useTypeFilters.innerHTML = EMPTY;
    if (elements.actorFilters) {
      elements.actorFilters.innerHTML = EMPTY;
    }
    elements.summary.innerHTML = EMPTY;
    elements.storyIntro.innerHTML = EMPTY;
    if (elements.relations) {
      elements.relations.innerHTML = EMPTY;
    }
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
      actorFilters: root.querySelector('[data-iss-schoneweide-actor-filters]'),
      actorLabel: root.querySelector('[data-iss-schoneweide-actor-label]'),
      statusFilters: root.querySelector('[data-iss-schoneweide-status-filters]'),
      useTypeFilters: root.querySelector('[data-iss-schoneweide-use-type-filters]'),
      useTypeLabel: root.querySelector('[data-iss-schoneweide-use-type-label]'),
      mapSurface: root.querySelector('.iss-atlas-app__map-surface'),
      mapCanvas: root.querySelector('[data-iss-schoneweide-map]'),
      mapStatus: root.querySelector('[data-iss-schoneweide-map-status]'),
      summary: root.querySelector('[data-iss-schoneweide-summary]'),
      relations: root.querySelector('[data-iss-schoneweide-relations]'),
      storyIntro: root.querySelector('[data-iss-schoneweide-story-intro]'),
      popup: root.querySelector('[data-iss-schoneweide-popup]'),
      stories: root.querySelector('[data-iss-schoneweide-stories]'),
      count: root.querySelector('[data-iss-schoneweide-count]'),
      search: root.querySelector('[data-iss-schoneweide-search]'),
      reset: root.querySelector('[data-iss-schoneweide-reset]')
    };
  }

  function bindMapSizeSync(map, surface) {
    AtlasLayout.bindMapSizeSync(map, surface);
  }

  function getAtlasPayload(config) {
    return AtlasConfig.getAtlasPayload(config);
  }

  function reportInitError(root, message, detail) {
    AtlasConfig.reportInitError(root, message, detail);
  }

  function readAtlasConfig(root) {
    return AtlasConfig.readAtlasConfig(root);
  }

  function collectRoots() {
    return AtlasConfig.collectRoots();
  }

  function initRoot(root) {
    if (!root || root.getAttribute('data-iss-schoneweide-atlas-ready') === 'true') {
      return;
    }

    root.setAttribute('data-iss-schoneweide-atlas-ready', 'booting');

    var config = readAtlasConfig(root);
    var elements = collectElements(root);
    var requiredKeys = [
      'eraFilters',
      'actorFilters',
      'useTypeFilters',
      'mapSurface',
      'mapCanvas',
      'mapStatus',
      'summary',
      'storyIntro',
      'popup',
      'stories',
      'count',
      'search',
      'reset'
    ];
    var missingKeys = requiredKeys.filter(function (key) {
      return !elements[key];
    });

    if (missingKeys.length) {
      root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
      reportInitError(root, 'Missing required atlas hook(s).', missingKeys);
      return;
    }

    if (!text(config.placesUrl)) {
      root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
      reportInitError(root, 'Missing atlas placesUrl config.');
      return;
    }

    if (!window.L) {
      root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
      renderError(elements, 'Die Kartenbibliothek konnte nicht geladen werden.');
      reportInitError(root, 'Leaflet was not available during atlas init.');
      return;
    }

    getAtlasPayload(config)
      .then(function (results) {
        var placePayload = Array.isArray(results[0]) ? results[0] : [];
        var contextPayload = results[1] && typeof results[1] === 'object' ? results[1] : {};
        var overlayPayload = results[2] && typeof results[2] === 'object' ? results[2] : { type: 'FeatureCollection', features: [] };
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
        var actors = Array.isArray(contextPayload.actors)
          ? contextPayload.actors.map(normalizeActor).filter(function (actor) { return actor.key; })
          : [];

        if (!places.length) {
          root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
          renderError(elements, 'Keine Atlas-Orte verfügbar.');
          reportInitError(root, 'Atlas payload returned no usable places.');
          return;
        }

        var leafletState = createLeafletState(elements.mapCanvas, config);

        if (!leafletState) {
          root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
          renderError(elements, 'Die Karte konnte nicht initialisiert werden.');
          reportInitError(root, 'Leaflet map could not be initialized.');
          return;
        }

        AtlasMap.addOverlayLayer(leafletState, overlayPayload);

        var state = AtlasStore.createState({
          root: root,
          leaflet: leafletState,
          relationMapUrl: getStaticRelationMapUrl(config),
          places: places,
          eras: eras,
          actors: actors,
          stories: stories,
          render: function () {
            render(elements, state);
          }
        });

        AtlasPlaces.bindInputs(elements, state);

        elements.popup.addEventListener('iss-close-selection', function () {
          state.selectedPostId = -1;
          state.shouldPan = false;
          state.render();
        });

        bindMapSizeSync(state.leaflet.map, elements.mapSurface);
        state.render();
        root.setAttribute('data-iss-schoneweide-atlas-ready', 'true');
      })
      .catch(function (error) {
        root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
        renderError(elements, 'Der Atlas konnte nicht geladen werden.');
        reportInitError(root, 'Atlas payload could not be loaded.', error);
      });
  }

  function init() {
    collectRoots().forEach(initRoot);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
