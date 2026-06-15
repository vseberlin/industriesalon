(function () {
  var Atlas = window.issSchoneweideAtlas || {};

  function text(value) {
    return typeof value === 'string' ? value.trim() : '';
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

    return normalized.slice(0, maxLength - 1).trim() + '...';
  }

  function relativeUrl(value) {
    var normalized = text(value);

    if (!normalized) {
      return '';
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

  Atlas.core = {
    EMPTY: '',
    MAP_BOUNDS: {
      minLat: 52.4448,
      maxLat: 52.4724,
      minLng: 13.4988,
      maxLng: 13.5405
    },
    text: text,
    number: number,
    compact: compact,
    relativeUrl: relativeUrl,
    escapeHtml: escapeHtml,
    createElement: createElement
  };

  window.issSchoneweideAtlas = Atlas;
})();
