(function () {
  function canUsePanelSelection(event) {
    return !event.defaultPrevented
      && event.button === 0
      && !event.metaKey
      && !event.ctrlKey
      && !event.shiftKey
      && !event.altKey;
  }

  function selectStation(root, index) {
    var markers = root.querySelectorAll('[data-iss-map-marker]');
    var detail = root.querySelector('[data-iss-map-active-detail]');
    var template = root.querySelector('[data-iss-map-detail-template="' + index + '"]');

    Array.prototype.forEach.call(markers, function (marker) {
      var isActive = marker.getAttribute('data-iss-map-index') === index;

      marker.classList.toggle('is-active', isActive);
      if (isActive) {
        marker.setAttribute('aria-current', 'location');
      } else {
        marker.removeAttribute('aria-current');
      }
    });

    if (detail && template) {
      detail.innerHTML = template.innerHTML;
      detail.hidden = false;
      detail.scrollTop = 0;
    }
  }

  function bindAtlasMap(root) {
    var detail;
    var fallback;
    var firstMarker;

    if (!root || root.getAttribute('data-iss-atlas-map-bound') === '1') {
      return;
    }

    detail = root.querySelector('[data-iss-map-active-detail]');
    fallback = root.querySelector('[data-iss-map-panel-fallback]');

    if (!detail) {
      return;
    }

    root.setAttribute('data-iss-atlas-map-bound', '1');
    root.classList.add('is-enhanced');
    if (fallback) {
      fallback.hidden = true;
    }
    firstMarker = root.querySelector('[data-iss-map-marker]');
    if (firstMarker) {
      selectStation(root, firstMarker.getAttribute('data-iss-map-index') || '0');
    }

    root.addEventListener('click', function (event) {
      var marker = event.target.closest
        ? event.target.closest('[data-iss-map-marker]')
        : null;

      if (!marker || !root.contains(marker) || !canUsePanelSelection(event)) {
        return;
      }

      event.preventDefault();
      selectStation(root, marker.getAttribute('data-iss-map-index') || '0');
    });
  }

  function initAtlasMaps() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-iss-atlas-map-interactive="station-detail"]'),
      bindAtlasMap
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAtlasMaps);
  } else {
    initAtlasMaps();
  }
}());
