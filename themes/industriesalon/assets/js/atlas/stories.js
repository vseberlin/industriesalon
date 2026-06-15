(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var store = Atlas.store || {};
  var detail = Atlas.detail || {};
  var EMPTY = core.EMPTY || '';

  function text(value) {
    return typeof core.text === 'function' ? core.text(value) : (typeof value === 'string' ? value.trim() : EMPTY);
  }

  function compact(value, maxLength) {
    var normalized = text(value).replace(/\s+/g, ' ');

    if (!normalized || normalized.length <= maxLength) {
      return normalized;
    }

    return normalized.slice(0, maxLength - 1).trim() + '…';
  }

  function createElement(tagName, className, textValue) {
    return typeof core.createElement === 'function'
      ? core.createElement(tagName, className, textValue)
      : document.createElement(tagName);
  }

  function sortPlaces(left, right) {
    return store.sortPlaces(left, right);
  }

  function getSelectedStories(state) {
    return store.getSelectedStories(state);
  }

  function buildPlaceMediaFigure(place, className, fallbackLabel) {
    if (typeof detail.buildPlaceMediaFigure === 'function') {
      return detail.buildPlaceMediaFigure(place, className, fallbackLabel);
    }

    var figure = createElement('figure', className + ' is-fallback');
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

  Atlas.stories = {
    renderStoryIntro: renderStoryIntro,
    renderStories: renderStories
  };

  window.issSchoneweideAtlas = Atlas;
})();
