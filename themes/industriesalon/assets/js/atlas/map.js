(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var provider = Atlas.provider || {};
  var text = core.text || function (value) {
    return typeof value === 'string' ? value.trim() : '';
  };
  var MAP_BOUNDS = core.MAP_BOUNDS || {
    minLat: 52.4448,
    maxLat: 52.4724,
    minLng: 13.4988,
    maxLng: 13.5405
  };
  var OVERLAY_FAMILY_STYLES = {
    company: {
      color: '#8f1e14',
      weight: 1,
      opacity: 0.72,
      fillColor: '#d33a2c',
      fillOpacity: 0.12
    },
    infrastructure: {
      color: '#183949',
      weight: 1,
      opacity: 0.7,
      fillColor: '#2f5f7a',
      fillOpacity: 0.1
    },
    default: {
      color: '#574d44',
      weight: 1,
      opacity: 0.66,
      fillColor: '#8b7f76',
      fillOpacity: 0.08
    }
  };

  function getNavigationBounds(initialBounds, places) {
    var bounds = window.L.latLngBounds(initialBounds.getSouthWest(), initialBounds.getNorthEast());

    (Array.isArray(places) ? places : []).forEach(function (place) {
      if (Number.isFinite(Number(place.lat)) && Number.isFinite(Number(place.lng))) {
        bounds.extend([Number(place.lat), Number(place.lng)]);
      }
    });

    return bounds;
  }

  function createLeafletState(container, config, places) {
    if (!window.L) {
      return null;
    }

    var atlasBounds = window.L.latLngBounds(
      [MAP_BOUNDS.minLat, MAP_BOUNDS.minLng],
      [MAP_BOUNDS.maxLat, MAP_BOUNDS.maxLng]
    );
    var navigationBounds = getNavigationBounds(atlasBounds, places);
    var map = window.L.map(container, {
      attributionControl: true,
      maxBounds: navigationBounds.pad(0.08),
      maxBoundsViscosity: 1,
      scrollWheelZoom: false,
      tap: false,
      zoomControl: false,
      zoomSnap: 0.25,
      zoomDelta: 0.5
    });

    window.L.control.zoom({ position: 'bottomright' }).addTo(map);
    var basemap = provider.getBasemapConfig(config);
    window.L.tileLayer(basemap.tileUrl, basemap.options).addTo(map);

    map.createPane('issAtlasOverlays');
    map.getPane('issAtlasOverlays').style.zIndex = 350;

    map.fitBounds(atlasBounds, { padding: [24, 24] });
    map.setMinZoom(map.getZoom() - 0.25);

    return {
      map: map,
      atlasBounds: atlasBounds,
      navigationBounds: navigationBounds,
      markerLayer: window.L.layerGroup().addTo(map),
      overlayLayer: null
    };
  }

  function getOverlayStyle(feature) {
    var properties = feature && feature.properties ? feature.properties : {};
    var family = text(properties.overlay_family).toLowerCase();
    return OVERLAY_FAMILY_STYLES[family] || OVERLAY_FAMILY_STYLES.default;
  }

  function normalizeOverlayFeature(feature) {
    if (!feature || typeof feature !== 'object' || !feature.geometry || !feature.properties) {
      return null;
    }

    var properties = feature.properties;
    var status = text(properties.status).toLowerCase();
    var family = text(properties.overlay_family).toLowerCase();
    var visibility = properties.default_visibility;

    if (status === 'hidden' || status === 'deprecated') {
      return null;
    }

    if (visibility === false) {
      return null;
    }

    return {
      type: 'Feature',
      geometry: feature.geometry,
      properties: {
        overlay_slug: text(properties.overlay_slug),
        label: text(properties.label),
        overlay_family: family || 'default',
        overlay_kind: text(properties.overlay_kind),
        status: status || 'active',
        priority: Number.isFinite(Number(properties.priority)) ? Number(properties.priority) : 0,
        color_token: text(properties.color_token),
        panel_theme: text(properties.panel_theme),
        source_type: text(properties.source_type),
        source_confidence: text(properties.source_confidence),
        notes: text(properties.notes),
        era_slugs: Array.isArray(properties.era_slugs) ? properties.era_slugs.map(text).filter(Boolean) : [],
        function_keys: Array.isArray(properties.function_keys) ? properties.function_keys.map(text).filter(Boolean) : [],
        current_statuses: Array.isArray(properties.current_statuses) ? properties.current_statuses.map(text).filter(Boolean) : [],
        current_use_types: Array.isArray(properties.current_use_types) ? properties.current_use_types.map(text).filter(Boolean) : [],
        risk_flags: Array.isArray(properties.risk_flags) ? properties.risk_flags.map(text).filter(Boolean) : [],
        problem_flags: Array.isArray(properties.problem_flags) ? properties.problem_flags.map(text).filter(Boolean) : [],
        future_flags: Array.isArray(properties.future_flags) ? properties.future_flags.map(text).filter(Boolean) : [],
        topic_tags: Array.isArray(properties.topic_tags) ? properties.topic_tags.map(text).filter(Boolean) : [],
        relation_slugs: Array.isArray(properties.relation_slugs) ? properties.relation_slugs.map(text).filter(Boolean) : []
      }
    };
  }

  function addOverlayLayer(state, geojson) {
    if (!state || !state.map || !window.L || !geojson || !Array.isArray(geojson.features) || !geojson.features.length) {
      return;
    }

    if (state.overlayLayer) {
      state.map.removeLayer(state.overlayLayer);
      state.overlayLayer = null;
    }

    var features = geojson.features
      .map(normalizeOverlayFeature)
      .filter(Boolean);

    if (!features.length) {
      return;
    }

    state.overlayLayer = window.L.geoJSON(null, {
      pane: 'issAtlasOverlays',
      interactive: false,
      filter: function (feature) {
        return !!normalizeOverlayFeature(feature);
      },
      style: function (feature) {
        var normalized = normalizeOverlayFeature(feature);
        var style = getOverlayStyle(normalized || feature);
        style.lineJoin = 'round';
        style.lineCap = 'round';
        style.smoothFactor = 1.2;
        return style;
      }
    });

    state.overlayLayer.addData({
      type: 'FeatureCollection',
      features: features
    });
    state.overlayLayer.addTo(state.map);
  }

  function renderMarkers(leaflet, filteredPlaces, selectedPlace, options) {
    options = options || {};
    leaflet.markerLayer.clearLayers();

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
          icon: options.createMarkerIcon(place, active),
          keyboard: true,
          riseOnHover: true,
          zIndexOffset: active ? 2400 : 0
        });

        marker.on('click', function () {
          if (typeof options.onSelect === 'function') {
            options.onSelect(place);
          }
        });

        leaflet.markerLayer.addLayer(marker);
      });

    if (options.shouldResetView && leaflet.atlasBounds) {
      leaflet.map.fitBounds(leaflet.atlasBounds, {
        animate: true,
        padding: [24, 24]
      });
    } else if (options.panPlace) {
      leaflet.map.panTo([options.panPlace.lat, options.panPlace.lng], { animate: true });
    }
  }

  Atlas.map = {
    addOverlayLayer: addOverlayLayer,
    createLeafletState: createLeafletState,
    renderMarkers: renderMarkers
  };

  window.issSchoneweideAtlas = Atlas;
})();
