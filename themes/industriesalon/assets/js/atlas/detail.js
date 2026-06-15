(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var store = Atlas.store || {};
  var labels = store.labels || {};
  var EMPTY = core.EMPTY || '';
  var DEFAULT_STATUS_LABEL = labels.DEFAULT_STATUS_LABEL || 'Alle Situationen';
  var DEFAULT_USE_TYPE_LABEL = labels.DEFAULT_USE_TYPE_LABEL || 'Alle Nutzungen';
  var CURRENT_STATUS_LABELS = labels.CURRENT_STATUS_LABELS || {
    '': DEFAULT_STATUS_LABEL
  };
  var CURRENT_USE_TYPE_LABELS = labels.CURRENT_USE_TYPE_LABELS || {
    '': DEFAULT_USE_TYPE_LABEL
  };
  var UNKNOWN_EPOCH_SUMMARY = labels.UNKNOWN_EPOCH_SUMMARY ||
    'Für diesen Ort liegen im gewählten Zeitfenster bisher keine gesicherten historischen Nachweise vor. Wenn Sie historische Dokumente, Fotos oder andere Objekte haben, freuen wir uns über Ihre Kontaktaufnahme.';

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

  function useTypeFilterLabel(state, key) {
    return store.useTypeFilterLabel(state, key);
  }

  function getActorKeysForEra(place, eraSlug) {
    return store.getActorKeysForEra(place, eraSlug);
  }

  function isUnknownEpochFunction(place, state) {
    return store.isUnknownEpochFunction(place, state);
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

  Atlas.detail = {
    buildPlaceMediaFigure: buildPlaceMediaFigure,
    renderPopup: renderPopup
  };

  window.issSchoneweideAtlas = Atlas;
})();
