(function () {
  var EMPTY = '';
  var STATUS_LABELS = {
    aktiv: 'Aktiv',
    entwicklung: 'In Entwicklung',
    geplant: 'Geplant',
    unklar: 'Unklar',
    abzug: 'Im Abzug',
    sucht: 'Standortsuche'
  };
  var ROLE_LABELS = {
    E: 'Bestand',
    M: 'Nutzung',
    P: 'Projekt',
    'E+P': 'Bestand + Projekt'
  };
  var PREFERRED_SLUGS = [
    'spreepark-grun-berlin',
    'behrens-ufer-be-u',
    'kabelwerk-oberspree',
    'rathenau-hallen-basecamp-campus',
    'barenquell-brauerei',
    'electropolis-berlin-industriemuseum-projekt',
    'wilhelmine-trockland',
    'funkytown-trockland'
  ];
  var ERAS = [
    { id: '1890-1910', label: '1890–1910', shortLabel: '1890', caption: 'Aufbruch', start: 1890, end: 1910 },
    { id: '1910-1930', label: '1910–1930', shortLabel: '1910', caption: 'Wachstum', start: 1910, end: 1930 },
    { id: '1930-1945', label: '1930–1945', shortLabel: '1930', caption: 'Krise und Krieg', start: 1930, end: 1945 },
    { id: '1945-1960', label: '1945–1960', shortLabel: '1945', caption: 'Neubeginn', start: 1945, end: 1960 },
    { id: '1960-1990', label: '1960–1990', shortLabel: '1960', caption: 'Sozialistische Industrie', start: 1960, end: 1990 },
    { id: 'heute', label: '1990–heute', shortLabel: 'Heute', caption: 'Transformation', start: 1990, end: 2100 }
  ];

  function textOrEmpty(value) {
    return typeof value === 'string' ? value.trim() : '';
  }

  function numberOrNull(value) {
    var number = Number.parseFloat(value);
    return Number.isFinite(number) ? number : null;
  }

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function normalize(value, min, max) {
    if (max <= min) {
      return 0.5;
    }

    return (value - min) / (max - min);
  }

  function compactText(value, maxLength) {
    var text = textOrEmpty(value).replace(/\s+/g, ' ');
    if (!text || text.length <= maxLength) {
      return text;
    }

    return text.slice(0, maxLength - 1).trim() + '…';
  }

  function createElement(tagName, className, text) {
    var element = document.createElement(tagName);
    if (className) {
      element.className = className;
    }
    if (typeof text === 'string') {
      element.textContent = text;
    }
    return element;
  }

  function createActionLink(href, label, className) {
    var link = createElement('a', className, label);
    link.href = href;
    return link;
  }

  function formatPlaceCount(count) {
    return String(count) + ' ' + (count === 1 ? 'Ort' : 'Orte');
  }

  function getStatusLabel(status) {
    var normalized = textOrEmpty(status).toLowerCase();
    return STATUS_LABELS[normalized] || (status || 'Offen');
  }

  function getRoleLabel(role) {
    var normalized = textOrEmpty(role).toUpperCase();
    return ROLE_LABELS[normalized] || (role || 'Ort');
  }

  function slugPriority(slug) {
    var index = PREFERRED_SLUGS.indexOf(slug);
    return index === -1 ? 999 : index;
  }

  function extractYears(text) {
    var matches = text.match(/\b(18\d{2}|19\d{2}|20\d{2})\b/g);
    if (!matches) {
      return [];
    }

    return matches
      .map(function (value) {
        return Number.parseInt(value, 10);
      })
      .filter(function (value) {
        return Number.isFinite(value);
      });
  }

  function getNarrativeText(place) {
    return [
      textOrEmpty(place.history),
      textOrEmpty(place.current),
      textOrEmpty(place.excerpt),
      textOrEmpty(place.sources)
    ].filter(Boolean).join(' ');
  }

  function detectEra(place) {
    var narrative = getNarrativeText(place);
    var years = extractYears(narrative);
    var firstYear = years.length ? Math.min.apply(null, years) : null;

    if (firstYear !== null) {
      for (var index = 0; index < ERAS.length; index += 1) {
        var era = ERAS[index];
        if (firstYear >= era.start && firstYear < era.end) {
          return era.id;
        }
      }
    }

    var status = textOrEmpty(place.status).toLowerCase();
    if (status === 'entwicklung' || status === 'geplant' || textOrEmpty(place.role).toUpperCase() === 'P') {
      return 'heute';
    }

    if (/ddr|kombinat|v[eé]b|sozial/i.test(narrative)) {
      return '1960-1990';
    }

    return 'heute';
  }

  function getEraById(eraId) {
    return ERAS.find(function (era) {
      return era.id === eraId;
    }) || ERAS[ERAS.length - 1];
  }

  function getPlaceScore(place) {
    var score = 0;

    score += Math.min(textOrEmpty(place.history).length, 600);
    score += Math.min(textOrEmpty(place.excerpt).length, 220) * 1.2;
    score += Math.min(textOrEmpty(place.current).length, 220) * 0.8;
    score += textOrEmpty(place.featured_image_url) ? 120 : 0;
    score += slugPriority(place.slug) === 999 ? 0 : 800 - slugPriority(place.slug) * 50;

    if (textOrEmpty(place.area)) {
      score += 30;
    }

    if (textOrEmpty(place.role).toUpperCase() === 'E+P') {
      score += 40;
    }

    return score;
  }

  function getPrimarySummary(place) {
    return (
      compactText(place.excerpt, 260) ||
      compactText(place.current, 260) ||
      compactText(place.history, 260) ||
      compactText(place.address, 260)
    );
  }

  function getSecondaryText(place) {
    var source = textOrEmpty(place.history) || textOrEmpty(place.current) || textOrEmpty(place.sources);
    return compactText(source, 180);
  }

  function normalizePlace(place) {
    var lat = numberOrNull(place.lat);
    var lng = numberOrNull(place.lng);
    var slug = textOrEmpty(place.slug);
    var eraId = detectEra(place);

    return Object.assign({}, place, {
      lat: lat,
      lng: lng,
      slug: slug,
      post_id: Number.parseInt(place.post_id, 10) || 0,
      eraId: eraId,
      era: getEraById(eraId),
      storyScore: getPlaceScore(place),
      summary: getPrimarySummary(place),
      secondary: getSecondaryText(place)
    });
  }

  function corridorSort(left, right) {
    if (left.lng === right.lng) {
      return (right.lat || 0) - (left.lat || 0);
    }

    return (left.lng || 0) - (right.lng || 0);
  }

  function storySort(left, right) {
    var leftPriority = slugPriority(left.slug);
    var rightPriority = slugPriority(right.slug);

    if (leftPriority !== rightPriority) {
      return leftPriority - rightPriority;
    }

    if (left.storyScore !== right.storyScore) {
      return right.storyScore - left.storyScore;
    }

    return textOrEmpty(left.name).localeCompare(textOrEmpty(right.name), 'de');
  }

  function getPoolByEra(places, eraId) {
    var pool = eraId
      ? places.filter(function (place) {
          return place.eraId === eraId;
        })
      : places.slice();

    return pool.sort(storySort);
  }

  function pickSelectedPlace(state) {
    var pool = getPoolByEra(state.places, state.selectedEraId);
    if (!pool.length) {
      return null;
    }

    var selected = pool.find(function (place) {
      return place.post_id === state.selectedPostId;
    });

    if (!selected) {
      selected = pool[0];
      state.selectedPostId = selected.post_id;
    }

    return selected;
  }

  function renderStats(container, places) {
    var cards = container.querySelectorAll('.iss-atlas-hero__stat');
    if (!cards.length) {
      return;
    }

    var values = [
      String(places.length),
      String(new Set(places.map(function (place) { return textOrEmpty(place.area); }).filter(Boolean)).size),
      String(ERAS.length)
    ];

    cards.forEach(function (card, index) {
      var value = card.querySelector('.iss-atlas-hero__stat-value');
      if (value) {
        value.textContent = values[index] || '0';
      }
    });
  }

  function renderHeroYears(container, state, onSelectEra) {
    container.innerHTML = '';

    ERAS.forEach(function (era, index) {
      var button = createElement(
        'button',
        'iss-atlas-hero__year' + (era.id === state.selectedEraId ? ' is-active' : ''),
        era.shortLabel
      );

      button.type = 'button';
      button.style.setProperty('--iss-era-index', String(index));
      button.setAttribute('aria-pressed', era.id === state.selectedEraId ? 'true' : 'false');
      button.addEventListener('click', function () {
        onSelectEra(era.id);
      });

      container.appendChild(button);
    });
  }

  function renderEraList(container, state, onSelectEra) {
    container.innerHTML = '';

    var title = createElement('div', 'iss-atlas-eras__intro');
    title.appendChild(createElement('span', 'iss-atlas-eras__eyebrow', 'Zeitschichten'));
    title.appendChild(createElement('strong', 'iss-atlas-eras__title', 'Vom Industrieaufbruch zur Transformation'));
    container.appendChild(title);

    ERAS.forEach(function (era) {
      var count = state.places.filter(function (place) {
        return place.eraId === era.id;
      }).length;

      var button = createElement(
        'button',
        'iss-atlas-eras__item' + (era.id === state.selectedEraId ? ' is-active' : '')
      );

      button.type = 'button';
      button.setAttribute('aria-pressed', era.id === state.selectedEraId ? 'true' : 'false');
      button.addEventListener('click', function () {
        onSelectEra(era.id);
      });

      var dot = createElement('span', 'iss-atlas-eras__dot');
      var body = createElement('span', 'iss-atlas-eras__body');
      body.appendChild(createElement('strong', 'iss-atlas-eras__label', era.label));
      body.appendChild(createElement('span', 'iss-atlas-eras__caption', era.caption));
      var meta = createElement('span', 'iss-atlas-eras__count', formatPlaceCount(count));

      button.appendChild(dot);
      button.appendChild(body);
      button.appendChild(meta);
      container.appendChild(button);
    });
  }

  function getSuggestedPlaces(pool, selectedPlace) {
    if (!pool.length) {
      return [];
    }

    var suggestions = [];
    if (selectedPlace) {
      suggestions.push(selectedPlace);
    }

    pool.forEach(function (place) {
      if (suggestions.length >= 5) {
        return;
      }
      if (!suggestions.some(function (item) { return item.post_id === place.post_id; })) {
        suggestions.push(place);
      }
    });

    return suggestions;
  }

  function renderStoryNav(container, pool, selectedPlace, onSelectPlace) {
    container.innerHTML = '';

    getSuggestedPlaces(pool, selectedPlace).forEach(function (place) {
      var button = createElement(
        'button',
        'iss-atlas-story__chip' + (selectedPlace && selectedPlace.post_id === place.post_id ? ' is-active' : '')
      );

      button.type = 'button';
      button.addEventListener('click', function () {
        onSelectPlace(place);
      });

      button.appendChild(createElement('span', 'iss-atlas-story__chip-eyebrow', place.era.label));
      button.appendChild(createElement('strong', 'iss-atlas-story__chip-title', compactText(place.name, 42)));
      container.appendChild(button);
    });
  }

  function renderVisualPanel(className, label, place, options) {
    var panel = createElement('article', className);
    var imageUrl = textOrEmpty(place.featured_image_url);
    var media = createElement(
      'div',
      'iss-atlas-stage__panel-media' + (imageUrl ? ' has-image' : ' is-fallback')
    );
    var body = createElement('div', 'iss-atlas-stage__panel-body');
    var meta = createElement('p', 'iss-atlas-stage__panel-meta');
    var summary = compactText(options.summary || place.summary || place.secondary, 140);

    if (imageUrl) {
      media.style.backgroundImage = 'url("' + imageUrl.replace(/"/g, '\\"') + '")';
    } else if (options.background) {
      media.style.background = options.background;
    }

    meta.textContent = [label, options.meta].filter(Boolean).join(' · ');
    body.appendChild(meta);
    body.appendChild(createElement('strong', 'iss-atlas-stage__panel-title', compactText(place.name, 46)));

    if (options.note) {
      body.appendChild(createElement('span', 'iss-atlas-stage__panel-note', options.note));
    }

    if (summary) {
      body.appendChild(createElement('p', 'iss-atlas-stage__panel-summary', summary));
    }

    panel.appendChild(media);
    panel.appendChild(body);

    return panel;
  }

  function renderStage(container, place) {
    container.innerHTML = '';

    var frame = createElement('div', 'iss-atlas-stage__frame');
    var archivePanel = renderVisualPanel(
      'iss-atlas-stage__panel iss-atlas-stage__panel--archive',
      place.era.label,
      place,
      {
        background: 'linear-gradient(145deg, rgba(64, 63, 61, 0.92), rgba(180, 177, 172, 0.82))',
        meta: place.era.caption,
        note: textOrEmpty(place.area) || getStatusLabel(place.status),
        summary: textOrEmpty(place.history) || textOrEmpty(place.excerpt)
      }
    );
    var currentPanel = renderVisualPanel(
      'iss-atlas-stage__panel iss-atlas-stage__panel--current',
      'Heute',
      place,
      {
        background: textOrEmpty(place.color) || 'linear-gradient(145deg, rgba(191, 40, 46, 0.92), rgba(68, 55, 51, 0.88))',
        meta: getRoleLabel(place.role),
        note: textOrEmpty(place.area) || getStatusLabel(place.status),
        summary: textOrEmpty(place.current) || textOrEmpty(place.excerpt)
      }
    );
    var badge = createElement('div', 'iss-atlas-stage__badge');
    badge.appendChild(createElement('strong', '', getRoleLabel(place.role)));
    badge.appendChild(createElement('span', '', getStatusLabel(place.status)));

    frame.appendChild(archivePanel);
    frame.appendChild(currentPanel);
    frame.appendChild(badge);
    container.appendChild(frame);
  }

  function getFactRows(place) {
    return [
      { label: 'Zeit', value: place.era.label + ' · ' + place.era.caption },
      { label: 'Ort', value: textOrEmpty(place.address) || textOrEmpty(place.area) },
      { label: 'Profil', value: compactText(textOrEmpty(place.branche) || textOrEmpty(place.current), 80) },
      { label: 'Status', value: getStatusLabel(place.status) }
    ].filter(function (row) {
      return textOrEmpty(row.value);
    });
  }

  function renderStory(container, place, registerUrl) {
    container.innerHTML = '';

    var meta = createElement('p', 'iss-atlas-story__meta');
    meta.textContent = [place.era.caption, textOrEmpty(place.area)].filter(Boolean).join(' · ');
    container.appendChild(meta);

    container.appendChild(createElement('h2', 'iss-atlas-story__title', place.name || 'Ort'));

    if (place.summary) {
      container.appendChild(createElement('p', 'iss-atlas-story__summary', place.summary));
    }

    var facts = createElement('div', 'iss-atlas-story__facts');
    getFactRows(place).forEach(function (row) {
      var fact = createElement('div', 'iss-atlas-story__fact');
      fact.appendChild(createElement('span', 'iss-atlas-story__fact-label', row.label));
      fact.appendChild(createElement('span', 'iss-atlas-story__fact-value', row.value));
      facts.appendChild(fact);
    });
    container.appendChild(facts);

    var aside = createElement('div', 'iss-atlas-story__notes');

    var notePrimary = createElement('div', 'iss-atlas-story__note-card');
    notePrimary.appendChild(createElement('strong', 'iss-atlas-story__note-title', 'Kontext'));
    notePrimary.appendChild(createElement('p', 'iss-atlas-story__note-text', place.secondary || 'Dieser Ort wird im Atlas über Raum, Nutzung und Zeitbezüge erschlossen.'));
    aside.appendChild(notePrimary);

    var noteSecondary = createElement('div', 'iss-atlas-story__note-card iss-atlas-story__note-card--light');
    noteSecondary.appendChild(createElement('strong', 'iss-atlas-story__note-title', 'Atlas-Lesart'));
    noteSecondary.appendChild(createElement('p', 'iss-atlas-story__note-text', compactText(textOrEmpty(place.sources) || textOrEmpty(place.current) || textOrEmpty(place.history), 120) || 'Räumliche Bezüge, Quellen und Transformation werden im Dossier vertieft.'));
    aside.appendChild(noteSecondary);

    container.appendChild(aside);

    var actions = createElement('div', 'iss-atlas-story__actions');
    if (textOrEmpty(place.permalink)) {
      actions.appendChild(createActionLink(place.permalink, 'Mehr erfahren', 'iss-atlas-button iss-atlas-button--primary'));
    }
    if (textOrEmpty(registerUrl)) {
      actions.appendChild(createActionLink(registerUrl, 'Vollständiges Register', 'iss-atlas-button'));
    }
    container.appendChild(actions);
  }

  function renderMinimap(container, state, selectedPlace, onSelectPlace) {
    container.innerHTML = '';

    var places = state.places.slice().sort(corridorSort);
    var selectedIndex = places.findIndex(function (place) {
      return selectedPlace && place.post_id === selectedPlace.post_id;
    });

    var start = Math.max(0, selectedIndex - 3);
    var end = Math.min(places.length, selectedIndex + 4);
    var neighbors = places.slice(start, end);

    container.appendChild(createElement('p', 'iss-atlas-minimap__eyebrow', 'Orte im Korridor'));
    container.appendChild(createElement('h3', 'iss-atlas-minimap__title', textOrEmpty(selectedPlace.area) || 'Schöneweide'));
    container.appendChild(createElement('p', 'iss-atlas-minimap__meta', String(new Set(places.map(function (place) { return textOrEmpty(place.area); }).filter(Boolean)).size) + ' Raumcluster · ' + formatPlaceCount(places.length)));

    var track = createElement('div', 'iss-atlas-minimap__track');
    neighbors.forEach(function (place, index) {
      var item = createElement(
        'button',
        'iss-atlas-minimap__node' + (selectedPlace && selectedPlace.post_id === place.post_id ? ' is-active' : '')
      );
      item.type = 'button';
      item.style.setProperty('--iss-node-y', String(8 + (index * 84) / Math.max(neighbors.length - 1, 1)) + '%');
      item.style.setProperty('--iss-node-x', String(index % 2 === 0 ? 28 : 68) + '%');
      item.addEventListener('click', function () {
        onSelectPlace(place);
      });

      item.appendChild(createElement('span', 'iss-atlas-minimap__dot'));
      var label = createElement('span', 'iss-atlas-minimap__label');
      label.appendChild(createElement('strong', '', compactText(place.name, 36)));
      label.appendChild(createElement('span', '', textOrEmpty(place.area) || getStatusLabel(place.status)));
      item.appendChild(label);
      track.appendChild(item);
    });

    container.appendChild(track);
    container.appendChild(createActionLink('/register-schoneweide/', 'Alle Orte aufrufen', 'iss-atlas-minimap__link'));
  }

  function renderCorridor(container, state, selectedPlace, onSelectPlace) {
    container.innerHTML = '';

    var places = state.places.slice().sort(corridorSort);
    if (!places.length) {
      return;
    }

    var minLng = Math.min.apply(null, places.map(function (place) { return place.lng; }));
    var maxLng = Math.max.apply(null, places.map(function (place) { return place.lng; }));

    var header = createElement('div', 'iss-atlas-corridor__header');
    var headerText = createElement('div', 'iss-atlas-corridor__header-copy');
    headerText.appendChild(createElement('strong', 'iss-atlas-corridor__title', 'Korridor-Panorama'));
    headerText.appendChild(createElement('span', 'iss-atlas-corridor__text', 'Die Orte entlang der Spree lesen, von West nach Ost.'));
    header.appendChild(headerText);
    header.appendChild(createElement('span', 'iss-atlas-corridor__direction', 'West ↔ Ost'));
    container.appendChild(header);

    var band = createElement('div', 'iss-atlas-corridor__band');
    var scenic = createElement('div', 'iss-atlas-corridor__scenic');
    var track = createElement('div', 'iss-atlas-corridor__track');

    places.forEach(function (place, index) {
      var marker = createElement(
        'button',
        'iss-atlas-corridor__marker' + (selectedPlace && selectedPlace.post_id === place.post_id ? ' is-active' : '')
      );

      marker.type = 'button';
      marker.style.setProperty('--iss-marker-x', (4 + normalize(place.lng, minLng, maxLng) * 92).toFixed(2) + '%');
      marker.addEventListener('click', function () {
        onSelectPlace(place);
      });

      var dot = createElement('span', 'iss-atlas-corridor__dot');
      marker.appendChild(dot);

      if ((selectedPlace && selectedPlace.post_id === place.post_id) || index % 8 === 0) {
        var label = createElement('span', 'iss-atlas-corridor__label');
        label.appendChild(createElement('strong', '', compactText(place.name, 34)));
        label.appendChild(createElement('span', '', textOrEmpty(place.area) || getStatusLabel(place.status)));
        marker.appendChild(label);
      }

      track.appendChild(marker);
    });

    band.appendChild(scenic);
    band.appendChild(track);
    container.appendChild(band);
    container.appendChild(createElement('p', 'iss-atlas-corridor__hint', 'Punkte anklicken, um die Bühnenansicht oben zu wechseln.'));
  }

  function renderFeaturedTimeline(root, payload) {
    var title = root.querySelector('[data-iss-atlas-timeline-title]');
    var intro = root.querySelector('[data-iss-atlas-timeline-intro]');
    var container = root.querySelector('[data-iss-atlas-timeline]');

    if (!title || !intro || !container) {
      return;
    }

    if (!payload || !Array.isArray(payload.events) || !payload.events.length) {
      container.innerHTML = '';
      container.appendChild(createElement('p', 'iss-atlas-loading', 'Keine lokale Zeitleiste verfuegbar.'));
      return;
    }

    title.textContent = textOrEmpty(payload.title) || 'Historische Zeitleiste';
    intro.textContent = textOrEmpty(payload.intro) || 'Historische Entwicklung aus lokal importierten Touchtable-Inhalten.';
    container.innerHTML = '';

    var rail = createElement('div', 'iss-atlas-timeline__rail');
    var scroller = createElement('div', 'iss-atlas-timeline__scroller');

    payload.events.forEach(function (event) {
      var card = createElement('article', 'iss-atlas-timeline__card');
      var year = createElement('p', 'iss-atlas-timeline__year', event.year_label || event.year || '');
      var media = createElement('div', 'iss-atlas-timeline__media');
      var body = createElement('div', 'iss-atlas-timeline__body');
      var titleElement = textOrEmpty(event.title) ? createElement('h3', 'iss-atlas-timeline__card-title', event.title) : null;
      var summary = textOrEmpty(event.summary) ? createElement('p', 'iss-atlas-timeline__summary', event.summary) : null;

      if (textOrEmpty(event.image_url)) {
        media.style.backgroundImage = 'url("' + event.image_url.replace(/"/g, '\\"') + '")';
      } else {
        media.classList.add('is-empty');
      }

      card.appendChild(year);
      card.appendChild(media);

      if (titleElement) {
        body.appendChild(titleElement);
      }
      if (summary) {
        body.appendChild(summary);
      }

      card.appendChild(body);
      scroller.appendChild(card);
    });

    rail.appendChild(scroller);
    container.appendChild(rail);
  }

  function renderEmpty(root, message) {
    [
      root.querySelector('[data-iss-atlas-hero-years]'),
      root.querySelector('[data-iss-atlas-eras]'),
      root.querySelector('[data-iss-atlas-stage]'),
      root.querySelector('[data-iss-atlas-story]'),
      root.querySelector('[data-iss-atlas-minimap]'),
      root.querySelector('[data-iss-atlas-corridor]')
    ].forEach(function (target) {
      if (target) {
        target.innerHTML = '';
        target.appendChild(createElement('p', 'iss-atlas-loading', message));
      }
    });
  }

  function render(root, state) {
    var stats = root.querySelector('[data-iss-atlas-stats]');
    var years = root.querySelector('[data-iss-atlas-hero-years]');
    var eras = root.querySelector('[data-iss-atlas-eras]');
    var stage = root.querySelector('[data-iss-atlas-stage]');
    var story = root.querySelector('[data-iss-atlas-story]');
    var storyNav = root.querySelector('[data-iss-atlas-story-nav]');
    var minimap = root.querySelector('[data-iss-atlas-minimap]');
    var corridor = root.querySelector('[data-iss-atlas-corridor]');

    if (!stats || !years || !eras || !stage || !story || !storyNav || !minimap || !corridor) {
      return;
    }

    if (!state.places.length) {
      renderEmpty(root, 'Keine Orte mit Koordinaten verfügbar.');
      return;
    }

    var selectedPlace = pickSelectedPlace(state);
    var pool = getPoolByEra(state.places, state.selectedEraId);

    renderStats(stats, state.places);
    renderHeroYears(years, state, function (eraId) {
      state.selectedEraId = eraId;
      state.selectedPostId = 0;
      render(root, state);
    });
    renderEraList(eras, state, function (eraId) {
      state.selectedEraId = eraId;
      state.selectedPostId = 0;
      render(root, state);
    });
    renderStoryNav(storyNav, pool, selectedPlace, function (place) {
      state.selectedEraId = place.eraId;
      state.selectedPostId = place.post_id;
      render(root, state);
    });
    renderStage(stage, selectedPlace);
    renderStory(story, selectedPlace, state.registerUrl);
    renderMinimap(minimap, state, selectedPlace, function (place) {
      state.selectedEraId = place.eraId;
      state.selectedPostId = place.post_id;
      render(root, state);
    });
    renderCorridor(corridor, state, selectedPlace, function (place) {
      state.selectedEraId = place.eraId;
      state.selectedPostId = place.post_id;
      render(root, state);
    });
  }

  function init() {
    var root = document.querySelector('[data-iss-oberschoeneweide-atlas]');
    if (!root) {
      return;
    }

    var config = window.industriesalonSchoneweide || {};
    renderFeaturedTimeline(root, config.featuredTimeline || null);

    if (!textOrEmpty(config.placesUrl)) {
      return;
    }

    fetch(config.placesUrl, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Could not load places.');
        }

        return response.json();
      })
      .then(function (payload) {
        var places = (Array.isArray(payload) ? payload : [])
          .map(normalizePlace)
          .filter(function (place) {
            return place.lat !== null && place.lng !== null && place.post_id > 0 && textOrEmpty(place.permalink);
          })
          .sort(corridorSort);

        if (!places.length) {
          renderEmpty(root, 'Keine Orte mit Koordinaten verfügbar.');
          return;
        }

        var defaultPlace = places.slice().sort(storySort)[0];
        render(root, {
          places: places,
          registerUrl: textOrEmpty(config.registerUrl),
          selectedEraId: defaultPlace.eraId,
          selectedPostId: defaultPlace.post_id
        });
      })
      .catch(function () {
        renderEmpty(root, 'Der Atlas konnte gerade nicht geladen werden.');
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
