(function () {
  'use strict';

  function escapeHtml(value) {
    var node = document.createElement('div');
    node.textContent = String(value === null || typeof value === 'undefined' ? '' : value);
    return node.innerHTML;
  }

  function readConfig(root) {
    try {
      return JSON.parse(root.getAttribute('data-map-config') || '{}');
    } catch (error) {
      return null;
    }
  }

  function markerPoint(marker, width, height) {
    return window.L.latLng(
      height - ((Number(marker.y) / 100) * height),
      (Number(marker.x) / 100) * width
    );
  }

  function selectImageUrl(config) {
    var sources = Array.isArray(config.imageSources)
      ? config.imageSources.slice().sort(function (left, right) {
          return Number(left.width) - Number(right.width);
        })
      : [];

    if (!sources.length) return config.imageUrl;

    if (!window.matchMedia('(max-width: 680px)').matches) {
      return sources[sources.length - 1].url || config.imageUrl;
    }

    var targetWidth = window.innerWidth * Math.min(2, Math.max(1, window.devicePixelRatio || 1));
    var selected = sources.find(function (source) {
      return Number(source.width) >= targetWidth;
    });

    return (selected || sources[sources.length - 1]).url || config.imageUrl;
  }

  function initialize(root) {
    if (!window.L || root.dataset.mapReady === 'true') return;

    var config = readConfig(root);
    var width = Math.max(1, Number(config && config.imageWidth));
    var height = Math.max(1, Number(config && config.imageHeight));
    var markers = config && Array.isArray(config.markers) ? config.markers : [];
    var stage = root.closest('.has-leaflet-image-viewport');
    var fallback = stage ? stage.querySelector('.iss-static-map-fallback') : null;
    var imageUrl = config ? selectImageUrl(config) : '';

    if (!config || !imageUrl || !markers.length) return;

    var imageBounds = window.L.latLngBounds([0, 0], [height, width]);
    var map = window.L.map(root, {
      attributionControl: false,
      boxZoom: false,
      crs: window.L.CRS.Simple,
      doubleClickZoom: true,
      dragging: true,
      fadeAnimation: false,
      keyboard: true,
      maxZoom: 2,
      maxBoundsViscosity: 1,
      markerZoomAnimation: false,
      minZoom: -5,
      scrollWheelZoom: true,
      tap: false,
      touchZoom: true,
      wheelPxPerZoomLevel: 240,
      zoomAnimation: false,
      zoomControl: true,
      zoomSnap: 0,
    });

    window.L.imageOverlay(imageUrl, imageBounds, {
      alt: config.imageAlt || '',
      interactive: false,
    }).addTo(map);

    var markerBounds = window.L.latLngBounds();
    var routePoints = [];

    markers.forEach(function (marker) {
      var point = markerPoint(marker, width, height);
      var number = Number(marker.index) + 1;
      var markerClasses = 'iss-related-place-map__marker iss-static-map-leaflet__marker-link';
      var markerAttributes = '';

      if (config.interactiveMarkers) {
        markerAttributes += ' data-place-name="' + escapeHtml(marker.placeName) + '"';
        if (Number(marker.index) === 0) {
          markerClasses += ' is-active';
          markerAttributes += ' aria-current="location"';
        }
        if (config.mapId) {
          markerAttributes += ' aria-controls="' + escapeHtml(config.mapId) + '-detail"';
        }
        markerAttributes += ' data-iss-map-marker="' + escapeHtml(marker.index) + '"';
        markerAttributes += ' data-iss-map-index="' + escapeHtml(marker.index) + '"';
      }
      var icon = window.L.divIcon({
        className: 'iss-static-map-leaflet__marker',
        html:
          '<a class="' + markerClasses + '" href="' + escapeHtml(marker.url) +
          '" aria-label="' + escapeHtml(marker.label) + '"' + markerAttributes + '>' +
          '<span class="iss-related-place-map__marker-dot" aria-hidden="true"></span>' +
          '<span class="iss-related-place-map__marker-label">' + escapeHtml(number) + '</span></a>',
        iconAnchor: [16, 16],
        iconSize: [32, 32],
      });

      window.L.marker(point, {
        icon: icon,
        interactive: true,
        keyboard: false,
        riseOnHover: true,
      }).addTo(map);
      markerBounds.extend(point);
      routePoints.push(point);
    });

    if (config.lineMode === 'route' && routePoints.length > 1) {
      var routeColor = window.getComputedStyle(root)
        .getPropertyValue('--iss-gesture-atlas-map-line')
        .trim() || '#e81d25';

      window.L.polyline(routePoints, {
        className: 'iss-static-map-leaflet__route',
        color: routeColor,
        interactive: false,
        lineCap: 'round',
        lineJoin: 'round',
        opacity: 0.95,
        weight: 4,
      }).addTo(map);
    }

    function fit() {
      map.setMinZoom(-5);
      map.setMaxBounds(null);
      map.invalidateSize({ animate: false, pan: false });
      map.fitBounds(markerBounds.pad(0.12), {
        animate: false,
        maxZoom: 0,
        padding: [32, 32],
      });
      map.setMinZoom(map.getZoom());
      map.setMaxBounds(map.getBounds());
    }

    fit();

    root.dataset.mapReady = 'true';
    root.setAttribute('aria-hidden', 'false');
    if (fallback) fallback.hidden = true;
    if (stage) stage.classList.add('is-leaflet-enhanced');

    if (window.ResizeObserver) {
      var resizeObserver = new window.ResizeObserver(fit);
      resizeObserver.observe(root);
    }
  }

  function initializeAll() {
    document.querySelectorAll('[data-iss-static-map-leaflet]').forEach(initialize);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAll, { once: true });
  } else {
    initializeAll();
  }
})();
