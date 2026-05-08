(function (window) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};

  const TAB_NAMES = ['discover', 'places', 'then-now', 'research', 'detail'];
  const SEARCH_INPUT_DEBOUNCE_MS = 140;
  const STATUS_LABELS = {
    aktiv: 'Aktiv',
    entwicklung: 'In Entwicklung',
    geplant: 'Geplant',
    unklar: 'Unklar',
    abzug: 'Abzug geplant',
    sucht: 'Sucht Standort',
    mieter: 'Mieter'
  };

  const STATUS_SORT_ORDER = {
    aktiv: 0,
    entwicklung: 1,
    geplant: 2,
    unklar: 3,
    abzug: 4,
    sucht: 5,
    mieter: 6
  };

  const ROLE_LABELS = {
    E: 'Eigentümer',
    M: 'Mieter',
    P: 'Projektentwickler',
    'E+P': 'Eigentümer + Projektentwickler',
    'E/M': 'Eigentum unklar'
  };

  function normalize(value) {
    return (value || '').toString().trim().toLowerCase();
  }

  function safeText(value, fallback) {
    const text = (value || '').toString().trim();
    return text !== '' ? text : (fallback === undefined ? '—' : fallback);
  }

  function toArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function statusLabel(status) {
    const key = normalize(status);
    return STATUS_LABELS[key] || safeText(status, 'Unbekannt');
  }

  function statusClass(status) {
    const key = normalize(status).replace(/[^a-z0-9_-]/g, '');
    return key !== '' ? key : 'neutral';
  }

  function roleLabel(role) {
    const key = safeText(role, '');
    return ROLE_LABELS[key] || safeText(key, 'Unbekannt');
  }

  function truncateText(value, maxLength) {
    const text = safeText(value, '').replace(/\s+/g, ' ').trim();
    if (text === '') {
      return '';
    }
    if (text.length <= maxLength) {
      return text;
    }
    return text.slice(0, Math.max(1, maxLength - 1)).trimEnd() + '…';
  }

  function placeSummary(place, maxLength) {
    const summaryLimit = Number.isFinite(maxLength) ? maxLength : 160;
    const options = [place.summary, place.current, place.history, place.vornutzung];
    for (let index = 0; index < options.length; index += 1) {
      const summary = truncateText(options[index], summaryLimit);
      if (summary !== '') {
        return summary;
      }
    }
    return 'Kein Kurztext vorhanden.';
  }

  function firstImage(place, groups) {
    const selectedGroups = Array.isArray(groups) && groups.length
      ? groups
      : ['current_images', 'archive_images', 'document_images'];

    for (let groupIndex = 0; groupIndex < selectedGroups.length; groupIndex += 1) {
      const groupKey = selectedGroups[groupIndex];
      const images = toArray(place[groupKey]);
      for (let imageIndex = 0; imageIndex < images.length; imageIndex += 1) {
        const image = images[imageIndex];
        if (!image || typeof image !== 'object') {
          continue;
        }
        const url = safeText(image.url, '');
        if (url === '') {
          continue;
        }
        return {
          url,
          caption: safeText(image.caption, ''),
          source: safeText(image.source, ''),
          year: safeText(image.year, '')
        };
      }
    }

    return null;
  }

  function parseSources(place) {
    const inlineLabels = toArray(place.source_labels).map((item) => safeText(item, '')).filter(Boolean);
    const sourceLinks = toArray(place.source_links).map((item) => safeText(item, '')).filter(Boolean);
    const sourceSummary = safeText(place.sources, '');
    const summaryParts = sourceSummary === ''
      ? []
      : sourceSummary.split(/[|;\n]/).map((part) => part.trim()).filter(Boolean);

    const merged = sourceLinks.length || summaryParts.length
      ? sourceLinks.concat(summaryParts)
      : inlineLabels;
    const deduped = [];
    merged.forEach((entry) => {
      if (!deduped.some((existing) => normalize(existing) === normalize(entry))) {
        deduped.push(entry);
      }
    });

    return deduped;
  }

  function toBoolean(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
  }

  function debounce(callback, delay) {
    let timeoutId = 0;

    return function () {
      const args = Array.prototype.slice.call(arguments);
      if (timeoutId) {
        window.clearTimeout(timeoutId);
      }

      timeoutId = window.setTimeout(function () {
        timeoutId = 0;
        callback.apply(null, args);
      }, delay);
    };
  }

  function buildRoleSummary(place) {
    const pieces = [];
    const owner = safeText(place.owner, '');
    const operator = safeText(place.operator, '');
    const developer = safeText(place.developer, '');
    const tenant = safeText(place.tenant, '');

    if (owner) pieces.push('Eigentümer: ' + owner);
    if (operator) pieces.push('Operator: ' + operator);
    if (developer) pieces.push('Entwickler: ' + developer);
    if (tenant) pieces.push('Mieter: ' + tenant);
    if (pieces.length === 0) pieces.push(roleLabel(place.role));

    return pieces.join(' · ');
  }

  registerApp.constants = Object.assign({}, registerApp.constants, {
    TAB_NAMES,
    SEARCH_INPUT_DEBOUNCE_MS,
    STATUS_SORT_ORDER
  });

  registerApp.helpers = Object.assign({}, registerApp.helpers, {
    normalize,
    safeText,
    toArray,
    statusLabel,
    statusClass,
    roleLabel,
    truncateText,
    placeSummary,
    firstImage,
    parseSources,
    toBoolean,
    debounce,
    buildRoleSummary
  });
})(window);
