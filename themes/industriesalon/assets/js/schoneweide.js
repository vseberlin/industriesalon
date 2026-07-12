(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var AtlasCore = Atlas.core || {};
  var AtlasConfig = Atlas.config || {};
  var AtlasLayout = Atlas.layout || {};
  var AtlasMap = Atlas.map || {};
  var AtlasProvider = Atlas.provider || {};
  var AtlasStore = Atlas.store || {};
  var AtlasPlaces = Atlas.places || {};
  var AtlasDetail = Atlas.detail || {};
  var AtlasStories = Atlas.stories || {};
  var AtlasRelations = Atlas.relations || {};
  var AtlasMarkers = Atlas.markers || {};
  var EMPTY = AtlasCore.EMPTY || '';

  function text(value) {
    return typeof value === 'string' ? value.trim() : EMPTY;
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

  function sortPlaces(left, right) {
    return AtlasStore.sortPlaces(left, right);
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

  function createLeafletState(container, config, places) {
    return AtlasMap.createLeafletState(container, config, places);
  }

  function render(elements, state) {
    var placeContext = AtlasPlaces.render(elements, state);

    AtlasStories.renderStoryIntro(elements.storyIntro, state, placeContext.selectedStories, placeContext.filteredPlaces);
    AtlasRelations.renderRelations(elements.relations, state, placeContext.selectedPlace, placeContext.eraScopedPlaces);
    AtlasMarkers.renderMap(state, placeContext.filteredPlaces, placeContext.selectedPlace);
    AtlasDetail.renderPopup(elements.popup, placeContext.selectedPlace, state);
    AtlasStories.renderStories(elements.stories, state, placeContext.filteredPlaces);
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
      reset: root.querySelector('[data-iss-schoneweide-reset]'),
      layoutControls: root.querySelector('[data-iss-schoneweide-layout-controls]')
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

        var leafletState = createLeafletState(elements.mapCanvas, config, places);

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
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        });

        bindMapSizeSync(state.leaflet.map, elements.mapSurface);
        if (typeof AtlasLayout.bindLayoutModes === 'function') {
          AtlasLayout.bindLayoutModes(root, {
            controls: elements.layoutControls,
            leaflet: state.leaflet,
            reset: function () {
              AtlasPlaces.resetState(elements, state);
            }
          });
        }
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
