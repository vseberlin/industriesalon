(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var store = Atlas.store || {};
  var labels = store.labels || {};
  var EMPTY = core.EMPTY || '';
  var DEFAULT_ERA_LABEL = labels.DEFAULT_ERA_LABEL || 'Alle Zeiten';
  var DEFAULT_STATUS_LABEL = labels.DEFAULT_STATUS_LABEL || 'Alle Situationen';
  var CURRENT_STATUS_LABELS = labels.CURRENT_STATUS_LABELS || {
    '': DEFAULT_STATUS_LABEL
  };
  var CURRENT_USE_TYPE_LABELS = labels.CURRENT_USE_TYPE_LABELS || {
    '': labels.DEFAULT_USE_TYPE_LABEL || 'Alle Nutzungen'
  };
  var HISTORICAL_FUNCTION_LABELS = labels.HISTORICAL_FUNCTION_LABELS || {
    '': 'Alle Funktionen'
  };
  var HISTORICAL_NO_DATA_KEY = labels.HISTORICAL_NO_DATA_KEY || 'no_data';
  var ERA_FILTER_CONTEXT_ONLY = labels.ERA_FILTER_CONTEXT_ONLY !== undefined
    ? labels.ERA_FILTER_CONTEXT_ONLY
    : true;
  var DEFAULT_ACTOR_LABEL = labels.DEFAULT_ACTOR_LABEL || 'Alle Akteure';

  function text(value) {
    return typeof core.text === 'function' ? core.text(value) : (typeof value === 'string' ? value.trim() : EMPTY);
  }

  function createElement(tagName, className, textValue) {
    return typeof core.createElement === 'function'
      ? core.createElement(tagName, className, textValue)
      : document.createElement(tagName);
  }

  function sortPlaces(left, right) {
    return store.sortPlaces(left, right);
  }

  function deriveFallbackActors(places) {
    return store.deriveFallbackActors(places);
  }

  function deriveFallbackEras(places) {
    return store.deriveFallbackEras(places);
  }

  function matchesEra(place, eraSlug) {
    return store.matchesEra(place, eraSlug);
  }

  function getEraScopedPlaces(places, eraSlug) {
    return store.getEraScopedPlaces(places, eraSlug);
  }

  function getEpochFunctionKeysForEra(place, eraSlug) {
    return store.getEpochFunctionKeysForEra(place, eraSlug);
  }

  function getActorKeysForEra(place, eraSlug) {
    return store.getActorKeysForEra(place, eraSlug);
  }

  function actorFilterLabel(state, key) {
    return store.actorFilterLabel(state, key);
  }

  function useTypeFilterLabel(state, key) {
    return store.useTypeFilterLabel(state, key);
  }

  function filterPlaces(places, state) {
    return store.filterPlaces(places, state);
  }

  function getSelectedPlace(state, filteredPlaces) {
    return store.getSelectedPlace(state, filteredPlaces);
  }

  function getSelectedStories(state) {
    return store.getSelectedStories(state);
  }

  function buildScopeLabel(state) {
    return store.buildScopeLabel(state);
  }

  function buildFilterButton(options) {
    var button = createElement(
      'button',
      'iss-atlas-app__filter-button' +
        (options.className ? ' ' + options.className : EMPTY) +
        (options.active ? ' is-active' : EMPTY)
    );

    button.type = 'button';
    button.setAttribute('aria-pressed', options.active ? 'true' : 'false');
    if (options.attributes && typeof options.attributes === 'object') {
      Object.keys(options.attributes).forEach(function (key) {
        if (options.attributes[key] !== undefined && options.attributes[key] !== null) {
          button.setAttribute(key, String(options.attributes[key]));
        }
      });
    }
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

    if (ERA_FILTER_CONTEXT_ONLY) {
      eras.forEach(function (era) {
        eraCounts[era.slug] = state.places.length;
      });
    } else {
      state.places.forEach(function (place) {
        eras.forEach(function (era) {
          if (matchesEra(place, era.slug)) {
            eraCounts[era.slug] = (eraCounts[era.slug] || 0) + 1;
          }
        });
      });
    }

    container.innerHTML = EMPTY;
    container.appendChild(buildFilterButton({
      label: DEFAULT_ERA_LABEL,
      count: state.places.length,
      active: state.era === EMPTY,
      className: 'iss-atlas-app__filter-button--era',
      attributes: {
        'data-era-slug': 'all'
      },
      onClick: function () {
        state.era = EMPTY;
        state.currentUseType = EMPTY;
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
        className: 'iss-atlas-app__filter-button--era',
        attributes: {
          'data-era-slug': era.slug
        },
        onClick: function () {
          state.era = era.slug;
          state.currentUseType = EMPTY;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderCurrentStatusFilters(container, state, eraScopedPlaces) {
    var counts = { '': eraScopedPlaces.length };
    var statuses = [EMPTY].concat(Object.keys(CURRENT_STATUS_LABELS).filter(Boolean));

    eraScopedPlaces.forEach(function (place) {
      counts[place.current_status] = (counts[place.current_status] || 0) + 1;
    });

    container.innerHTML = EMPTY;

    statuses.forEach(function (status) {
      container.appendChild(buildFilterButton({
        label: CURRENT_STATUS_LABELS[status] || status,
        count: counts[status] || 0,
        active: state.currentStatus === status,
        onClick: function () {
          state.currentStatus = status;
          if (status && state.currentUseType) {
            var useTypeStillExists = eraScopedPlaces.some(function (place) {
              return place.current_status === status && place.current_use_type === state.currentUseType;
            });
            if (!useTypeStillExists) {
              state.currentUseType = EMPTY;
            }
          }
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderActorFilters(container, state, eraScopedPlaces) {
    var counts = { '': eraScopedPlaces.length };
    var actors = state.actors.length ? state.actors : deriveFallbackActors(state.places);
    var activeKeys = [];

    eraScopedPlaces.forEach(function (place) {
      getActorKeysForEra(place, state.era).forEach(function (key) {
        counts[key] = (counts[key] || 0) + 1;
      });
    });

    actors.forEach(function (actor) {
      if (actor.key && counts[actor.key]) {
        activeKeys.push(actor.key);
      }
    });

    if (state.actorKey && activeKeys.indexOf(state.actorKey) === -1) {
      state.actorKey = EMPTY;
    }

    container.innerHTML = EMPTY;
    container.appendChild(buildFilterButton({
      label: DEFAULT_ACTOR_LABEL,
      count: counts[''] || 0,
      active: state.actorKey === EMPTY,
      className: 'iss-atlas-app__filter-button--actor',
      attributes: {
        'data-actor-key': 'all'
      },
      onClick: function () {
        state.actorKey = EMPTY;
        state.selectedPostId = 0;
        state.shouldPan = false;
        state.render();
      }
    }));

    activeKeys.forEach(function (key) {
      container.appendChild(buildFilterButton({
        label: actorFilterLabel(state, key),
        count: counts[key] || 0,
        active: state.actorKey === key,
        className: 'iss-atlas-app__filter-button--actor',
        attributes: {
          'data-actor-key': key
        },
        onClick: function () {
          state.actorKey = key;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderCurrentUseTypeFilters(container, state, statusScopedPlaces) {
    var counts = { '': statusScopedPlaces.length };
    var types = state.activeEra
      ? Object.keys(HISTORICAL_FUNCTION_LABELS).filter(Boolean)
      : Object.keys(CURRENT_USE_TYPE_LABELS).filter(Boolean);
    var activeTypes = [];

    statusScopedPlaces.forEach(function (place) {
      if (state.activeEra) {
        var epochKeys = getEpochFunctionKeysForEra(place, state.era);
        if (!epochKeys.length) {
          counts[HISTORICAL_NO_DATA_KEY] = (counts[HISTORICAL_NO_DATA_KEY] || 0) + 1;
          return;
        }

        epochKeys.forEach(function (key) {
          counts[key] = (counts[key] || 0) + 1;
        });
        return;
      }

      if (place.current_use_type) {
        counts[place.current_use_type] = (counts[place.current_use_type] || 0) + 1;
      }
    });

    types.forEach(function (type) {
      if (counts[type]) {
        activeTypes.push(type);
      }
    });

    container.innerHTML = EMPTY;
    container.parentElement.hidden = activeTypes.length === 0;

    if (!activeTypes.length) {
      state.currentUseType = EMPTY;
      return;
    }

    if (state.currentUseType && state.currentUseType !== HISTORICAL_NO_DATA_KEY && activeTypes.indexOf(state.currentUseType) === -1) {
      state.currentUseType = EMPTY;
    }

    if (state.currentUseType === HISTORICAL_NO_DATA_KEY && !counts[HISTORICAL_NO_DATA_KEY]) {
      state.currentUseType = EMPTY;
    }

    [EMPTY].concat(activeTypes).forEach(function (type) {
      container.appendChild(buildFilterButton({
        label: useTypeFilterLabel(state, type),
        count: counts[type] || 0,
        active: state.currentUseType === type,
        onClick: function () {
          state.currentUseType = type;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderCount(container, filteredPlaces, state) {
    if (!filteredPlaces.length) {
      container.textContent = 'Keine Orte in dieser Auswahl.';
      return;
    }

    var label = buildScopeLabel(state);
    container.textContent = label
      ? String(filteredPlaces.length) + ' Orte in ' + label
      : String(filteredPlaces.length) + ' Orte in der aktuellen Auswahl';
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
    } else if (state.currentStatus) {
      headline = CURRENT_STATUS_LABELS[state.currentStatus] || DEFAULT_STATUS_LABEL;
    }

    container.appendChild(createElement('strong', EMPTY, headline));
    container.appendChild(
      createElement('span', EMPTY, String(filteredPlaces.length) + ' Orte in der aktuellen Auswahl')
    );
  }

  function setRootAttributes(state) {
    if (!state.root) {
      return;
    }

    if (state.era) {
      state.root.setAttribute('data-active-era', state.era);
    } else {
      state.root.removeAttribute('data-active-era');
    }

    if (state.actorKey) {
      state.root.setAttribute('data-active-actor', state.actorKey);
    } else {
      state.root.removeAttribute('data-active-actor');
    }
  }

  function buildRenderContext(elements, state) {
    if (!elements.statusFilters && state.currentStatus) {
      state.currentStatus = EMPTY;
    }

    state.activeEra = state.era ? (state.eraMap[state.era] || null) : null;
    setRootAttributes(state);

    var eraScopedPlaces = getEraScopedPlaces(state.places, state.era).sort(sortPlaces);
    var statusScopedPlaces = state.currentStatus
      ? eraScopedPlaces.filter(function (place) {
          return place.current_status === state.currentStatus;
        })
      : eraScopedPlaces.slice();
    var filteredPlaces = filterPlaces(eraScopedPlaces, state).sort(sortPlaces);
    var selectedPlace = getSelectedPlace(state, filteredPlaces);

    if (selectedPlace) {
      state.selectedPostId = selectedPlace.post_id;
    }

    return {
      eraScopedPlaces: eraScopedPlaces,
      statusScopedPlaces: statusScopedPlaces,
      filteredPlaces: filteredPlaces,
      selectedPlace: selectedPlace,
      selectedStories: getSelectedStories(state)
    };
  }

  function renderFilters(elements, state, context) {
    renderEraFilters(elements.eraFilters, state);
    if (elements.actorFilters) {
      renderActorFilters(elements.actorFilters, state, context.eraScopedPlaces);
    }
    if (elements.actorLabel) {
      elements.actorLabel.textContent = 'Industrieakteure';
    }
    if (elements.useTypeLabel) {
      elements.useTypeLabel.textContent = state.activeEra ? 'Funktion im Zeitfenster' : 'Nutzung heute';
    }
    if (elements.statusFilters) {
      renderCurrentStatusFilters(elements.statusFilters, state, context.eraScopedPlaces);
    } else {
      state.currentStatus = EMPTY;
    }
    renderCurrentUseTypeFilters(elements.useTypeFilters, state, context.statusScopedPlaces);
    renderCount(elements.count, context.filteredPlaces, state);
    renderSummary(elements.summary, context.filteredPlaces, context.selectedPlace, state);
  }

  function render(elements, state) {
    var context = buildRenderContext(elements, state);
    renderFilters(elements, state, context);
    return context;
  }

  function resetState(elements, state) {
    state.era = EMPTY;
    state.actorKey = EMPTY;
    state.currentStatus = EMPTY;
    state.currentUseType = EMPTY;
    state.search = EMPTY;
    state.selectedPostId = 0;
    state.shouldPan = false;
    if (elements.search) {
      elements.search.value = EMPTY;
    }
    state.render();
  }

  function bindInputs(elements, state) {
    elements.search.addEventListener('input', function (event) {
      state.search = event.target.value || EMPTY;
      state.selectedPostId = 0;
      state.shouldPan = false;
      state.render();
    });

    elements.reset.addEventListener('click', function () {
      resetState(elements, state);
    });
  }

  Atlas.places = {
    bindInputs: bindInputs,
    buildRenderContext: buildRenderContext,
    render: render,
    renderFilters: renderFilters,
    resetState: resetState
  };

  window.issSchoneweideAtlas = Atlas;
})();
