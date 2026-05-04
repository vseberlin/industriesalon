(function () {
  var ROLE_ORDER = ['E+P', 'E', 'P', 'M'];
  var STATUS_ORDER = ['aktiv', 'entwicklung', 'geplant', 'unklar', 'abzug', 'sucht'];
  var EMPTY_FILTER_VALUE = '';

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

  function getStatusLabel(status) {
    var normalized = textOrEmpty(status).toLowerCase();
    var labels = {
      aktiv: 'Aktiv',
      entwicklung: 'In Entwicklung',
      geplant: 'Geplant',
      unklar: 'Unklar',
      abzug: 'Im Abzug',
      sucht: 'Standortsuche'
    };

    return labels[normalized] || (status || 'Unbekannt');
  }

  function getRoleLabel(role) {
    var normalized = textOrEmpty(role).toUpperCase();
    var labels = {
      E: 'Bestand',
      M: 'Nutzung',
      P: 'Projekt',
      'E+P': 'Bestand + Projekt'
    };

    return labels[normalized] || (role || 'Ohne Rolle');
  }

  function getSummary(place) {
    return (
      textOrEmpty(place.excerpt) ||
      textOrEmpty(place.current) ||
      textOrEmpty(place.history) ||
      textOrEmpty(place.address)
    );
  }

  function getAxisLabel(name) {
    var cleaned = textOrEmpty(name).replace(/\s*\([^)]*\)\s*/g, ' ').replace(/\s+/g, ' ');
    return compactText(cleaned, 34);
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

  function sortAreas(values) {
    return values.sort(function (left, right) {
      return left.localeCompare(right, 'de');
    });
  }

  function sortRoles(values) {
    return values.sort(function (left, right) {
      var leftIndex = ROLE_ORDER.indexOf(left);
      var rightIndex = ROLE_ORDER.indexOf(right);

      if (leftIndex !== -1 || rightIndex !== -1) {
        if (leftIndex === -1) {
          return 1;
        }
        if (rightIndex === -1) {
          return -1;
        }
        return leftIndex - rightIndex;
      }

      return left.localeCompare(right, 'de');
    });
  }

  function sortStatuses(values) {
    return values.sort(function (left, right) {
      var leftIndex = STATUS_ORDER.indexOf(textOrEmpty(left).toLowerCase());
      var rightIndex = STATUS_ORDER.indexOf(textOrEmpty(right).toLowerCase());

      if (leftIndex !== -1 || rightIndex !== -1) {
        if (leftIndex === -1) {
          return 1;
        }
        if (rightIndex === -1) {
          return -1;
        }
        return leftIndex - rightIndex;
      }

      return left.localeCompare(right, 'de');
    });
  }

  function getFilterValues(places, key) {
    var values = places
      .map(function (place) {
        return textOrEmpty(place[key]);
      })
      .filter(Boolean);

    var uniqueValues = Array.from(new Set(values));

    if (key === 'area') {
      return sortAreas(uniqueValues);
    }

    if (key === 'role') {
      return sortRoles(uniqueValues);
    }

    if (key === 'status') {
      return sortStatuses(uniqueValues);
    }

    return uniqueValues.sort();
  }

  function buildFilterButton(label, value, key, state, onSelect) {
    var button = createElement(
      'button',
      'iss-schoneweide-map__filter-button' + (state.filters[key] === value ? ' is-active' : ''),
      label
    );

    button.type = 'button';
    button.dataset.filterKey = key;
    button.dataset.filterValue = value;
    button.setAttribute('aria-pressed', state.filters[key] === value ? 'true' : 'false');
    button.addEventListener('click', function () {
      onSelect(key, value);
    });

    return button;
  }

  function renderFilters(container, state, onSelect) {
    ['area', 'role', 'status'].forEach(function (key) {
      var target = container.querySelector('[data-filter-options="' + key + '"]');
      if (!target) {
        return;
      }

      target.innerHTML = '';
      target.appendChild(buildFilterButton('Alle', EMPTY_FILTER_VALUE, key, state, onSelect));

      getFilterValues(state.places, key).forEach(function (value) {
        var label = value;

        if (key === 'role') {
          label = getRoleLabel(value);
        }
        if (key === 'status') {
          label = getStatusLabel(value);
        }

        target.appendChild(buildFilterButton(label, value, key, state, onSelect));
      });
    });
  }

  function passesFilters(place, filters) {
    return ['area', 'role', 'status'].every(function (key) {
      if (!filters[key]) {
        return true;
      }

      return textOrEmpty(place[key]) === filters[key];
    });
  }

  function buildAnchorIndexes(places, selectedIndex) {
    if (!places.length) {
      return [];
    }

    var desired = Math.min(6, places.length);
    var indexes = new Set();

    if (selectedIndex >= 0 && selectedIndex < places.length) {
      indexes.add(selectedIndex);
    }

    for (var step = 0; step < desired; step += 1) {
      indexes.add(Math.round((step * (places.length - 1)) / Math.max(desired - 1, 1)));
    }

    return Array.from(indexes).sort(function (left, right) {
      return left - right;
    });
  }

  function getFactRows(place) {
    var rows = [
      { label: 'Gebiet', value: textOrEmpty(place.area) },
      { label: 'Rolle', value: getRoleLabel(place.role) },
      { label: 'Profil', value: compactText(place.branche, 58) },
      { label: 'Fläche', value: compactText(place.size, 58) }
    ];

    return rows.filter(function (row) {
      return textOrEmpty(row.value);
    }).slice(0, 4);
  }

  function renderStats(container, places) {
    var cards = container.querySelectorAll('.iss-schoneweide-map__stat');
    if (!cards.length) {
      return;
    }

    var values = [
      String(places.length),
      String(new Set(places.map(function (place) { return textOrEmpty(place.area); }).filter(Boolean)).size),
      String(new Set(places.map(function (place) { return textOrEmpty(place.status); }).filter(Boolean)).size)
    ];

    cards.forEach(function (card, index) {
      var target = card.querySelector('.iss-schoneweide-map__stat-value');
      if (target) {
        target.textContent = values[index] || '0';
      }
    });
  }

  function renderEmptyState(stage, detail) {
    stage.innerHTML = '';
    detail.innerHTML = '';
    detail.appendChild(createElement('p', 'iss-schoneweide-map__empty', 'Keine Orte für diese Filterkombination gefunden.'));
  }

  function renderStage(stage, filteredPlaces, selectedPostId, onSelect) {
    stage.innerHTML = '';

    if (!filteredPlaces.length) {
      return;
    }

    var stageInner = createElement('div', 'iss-schoneweide-map__stage-inner');
    stage.appendChild(stageInner);

    var minLng = Math.min.apply(null, filteredPlaces.map(function (place) { return place.lng; }));
    var maxLng = Math.max.apply(null, filteredPlaces.map(function (place) { return place.lng; }));
    var baseAxisY = 54;
    var bucketCounts = {};

    filteredPlaces.forEach(function (place) {
      var normalizedX = normalize(place.lng, minLng, maxLng);
      var x = 8 + normalizedX * 84;
      var bucketKey = String(Math.round(x / 4));
      var bucketCount = bucketCounts[bucketKey] || 0;
      var baseSide = Number(bucketKey) % 2 === 0 ? 'above' : 'below';
      var side = bucketCount % 2 === 0 ? baseSide : (baseSide === 'above' ? 'below' : 'above');
      var tier = Math.floor(bucketCount / 2);
      var y = side === 'above'
        ? baseAxisY - 9 - tier * 6
        : baseAxisY + 9 + tier * 6;

      bucketCounts[bucketKey] = bucketCount + 1;

      place.__axisX = x;
      place.__axisY = clamp(y, 14, 88);
      place.__axisSide = side;
      place.__axisStem = Math.max(1.25, Math.abs(baseAxisY - place.__axisY) - 1.25);

      var marker = createElement('button', 'iss-schoneweide-map__marker');
      marker.type = 'button';
      marker.dataset.status = textOrEmpty(place.status).toLowerCase();
      marker.dataset.side = side;
      marker.setAttribute('aria-label', place.name || 'Ort');
      marker.setAttribute('aria-pressed', place.post_id === selectedPostId ? 'true' : 'false');
      marker.classList.toggle('is-active', place.post_id === selectedPostId);
      marker.style.setProperty('--iss-marker-x', x.toFixed(2) + '%');
      marker.style.setProperty('--iss-marker-y', place.__axisY.toFixed(2) + '%');
      marker.style.setProperty('--iss-marker-stem', place.__axisStem.toFixed(2) + 'rem');
      marker.addEventListener('click', function () {
        onSelect(place.post_id);
      });

      var dot = createElement('span', 'iss-schoneweide-map__marker-dot');
      dot.setAttribute('aria-hidden', 'true');
      marker.appendChild(dot);
      stageInner.appendChild(marker);
    });

    buildAnchorIndexes(filteredPlaces, filteredPlaces.findIndex(function (place) {
      return place.post_id === selectedPostId;
    })).forEach(function (index) {
      var place = filteredPlaces[index];
      var anchor = createElement(
        'button',
        'iss-schoneweide-map__anchor' + (place.post_id === selectedPostId ? ' is-active' : ''),
        getAxisLabel(place.name)
      );

      anchor.type = 'button';
      anchor.style.setProperty('--iss-anchor-x', place.__axisX.toFixed(2) + '%');
      anchor.addEventListener('click', function () {
        onSelect(place.post_id);
      });

      stageInner.appendChild(anchor);
    });
  }

  function renderGeneratedVisual(container, place) {
    var visual = createElement('div', 'iss-schoneweide-map__detail-visual iss-schoneweide-map__detail-visual--generated');
    visual.style.background = textOrEmpty(place.color) || 'linear-gradient(135deg, #1e1e1e, #cf222c)';

    var icon = createElement('div', 'iss-schoneweide-map__detail-icon', textOrEmpty(place.icon) || '◉');
    visual.appendChild(icon);

    if (textOrEmpty(place.area)) {
      visual.appendChild(createElement('p', 'iss-schoneweide-map__detail-overline', place.area));
    }

    visual.appendChild(createElement('h4', 'iss-schoneweide-map__detail-visual-title', place.name || 'Ort'));

    return visual;
  }

  function renderDetail(detail, place, hasSelection, registerUrl, onClear) {
    detail.innerHTML = '';

    if (!place || !hasSelection) {
      detail.appendChild(createElement('p', 'iss-schoneweide-map__empty', 'Einen Ort auswählen, um Details zu sehen.'));
      return;
    }

    var header = createElement('div', 'iss-schoneweide-map__detail-head');
    var meta = createElement('p', 'iss-schoneweide-map__detail-meta');
    meta.textContent = [getStatusLabel(place.status), textOrEmpty(place.area)].filter(Boolean).join(' · ');
    header.appendChild(meta);

    var closeButton = createElement('button', 'iss-schoneweide-map__detail-close', '×');
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Auswahl schließen');
    closeButton.addEventListener('click', onClear);
    header.appendChild(closeButton);
    detail.appendChild(header);

    detail.appendChild(createElement('h3', 'iss-schoneweide-map__detail-title', place.name || 'Ort'));

    if (textOrEmpty(place.address)) {
      detail.appendChild(createElement('p', 'iss-schoneweide-map__detail-address', place.address));
    }

    var summary = getSummary(place);
    if (summary) {
      detail.appendChild(createElement('p', 'iss-schoneweide-map__detail-text', compactText(summary, 320)));
    }

    var factRows = getFactRows(place);
    if (factRows.length) {
      var facts = createElement('div', 'iss-schoneweide-map__detail-facts');
      factRows.forEach(function (row) {
        var fact = createElement('div', 'iss-schoneweide-map__detail-fact');
        fact.appendChild(createElement('span', 'iss-schoneweide-map__detail-fact-label', row.label));
        fact.appendChild(createElement('span', 'iss-schoneweide-map__detail-fact-value', row.value));
        facts.appendChild(fact);
      });
      detail.appendChild(facts);
    }

    var actions = createElement('div', 'iss-schoneweide-map__detail-actions');
    if (textOrEmpty(place.permalink)) {
      actions.appendChild(createActionLink(place.permalink, 'Dossier öffnen', 'iss-schoneweide-map__detail-link iss-schoneweide-map__detail-link--primary'));
    }
    if (textOrEmpty(registerUrl)) {
      actions.appendChild(createActionLink(registerUrl, 'Vollständiges Register', 'iss-schoneweide-map__detail-link'));
    }
    detail.appendChild(actions);

    if (textOrEmpty(place.featured_image_url)) {
      var imageWrap = createElement('div', 'iss-schoneweide-map__detail-media');
      var image = document.createElement('img');
      image.src = place.featured_image_url;
      image.alt = place.name || '';
      imageWrap.appendChild(image);
      detail.appendChild(imageWrap);
    } else {
      detail.appendChild(renderGeneratedVisual(detail, place));
    }
  }

  function render(container, state) {
    var stats = container.querySelector('[data-iss-schoneweide-stats]');
    var stage = container.querySelector('[data-iss-schoneweide-stage]');
    var detail = container.querySelector('[data-iss-schoneweide-detail]');
    var filters = container.querySelector('[data-iss-schoneweide-filters]');

    if (!stats || !stage || !detail || !filters) {
      return;
    }

    renderFilters(filters, state, function (key, value) {
      state.filters[key] = state.filters[key] === value ? EMPTY_FILTER_VALUE : value;
      render(container, state);
    });

    var filteredPlaces = state.places
      .filter(function (place) {
        return passesFilters(place, state.filters);
      })
      .sort(function (left, right) {
        if (left.lng === right.lng) {
          return (right.lat || 0) - (left.lat || 0);
        }

        return (left.lng || 0) - (right.lng || 0);
      });

    renderStats(stats, filteredPlaces);

    if (!filteredPlaces.length) {
      renderEmptyState(stage, detail);
      return;
    }

    var selectedPlace = filteredPlaces.find(function (place) {
      return place.post_id === state.selectedPostId;
    }) || null;

    var hasSelection = !!selectedPlace;
    if (!selectedPlace) {
      if (state.selectedPostId === 0) {
        selectedPlace = filteredPlaces[0];
        state.selectedPostId = selectedPlace.post_id;
        hasSelection = true;
      } else if (state.selectedPostId !== null) {
        selectedPlace = filteredPlaces[0];
        state.selectedPostId = selectedPlace.post_id;
        hasSelection = true;
      }
    }

    renderStage(stage, filteredPlaces, hasSelection ? selectedPlace.post_id : 0, function (postId) {
      state.selectedPostId = postId;
      render(container, state);
    });

    renderDetail(
      detail,
      selectedPlace,
      hasSelection,
      state.registerUrl,
      function () {
        state.selectedPostId = null;
        render(container, state);
      }
    );
  }

  function init() {
    var container = document.querySelector('[data-iss-schoneweide-explore]');
    if (!container) {
      return;
    }

    var config = window.industriesalonSchoneweide || {};
    if (!textOrEmpty(config.placesUrl)) {
      return;
    }

    fetch(config.placesUrl, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Could not load register places.');
        }

        return response.json();
      })
      .then(function (payload) {
        var places = Array.isArray(payload) ? payload : [];
        var filtered = places
          .map(function (place) {
            return Object.assign({}, place, {
              lat: numberOrNull(place.lat),
              lng: numberOrNull(place.lng),
              post_id: Number.parseInt(place.post_id, 10) || 0
            });
          })
          .filter(function (place) {
            return place.lat !== null && place.lng !== null && place.post_id > 0 && textOrEmpty(place.permalink);
          });

        render(container, {
          places: filtered,
          selectedPostId: 0,
          registerUrl: textOrEmpty(config.registerUrl),
          filters: {
            area: EMPTY_FILTER_VALUE,
            role: EMPTY_FILTER_VALUE,
            status: EMPTY_FILTER_VALUE
          }
        });
      })
      .catch(function () {
        var detail = container.querySelector('[data-iss-schoneweide-detail]');
        var stage = container.querySelector('[data-iss-schoneweide-stage]');
        if (stage) {
          stage.innerHTML = '';
        }
        if (detail) {
          detail.innerHTML = '';
          detail.appendChild(createElement('p', 'iss-schoneweide-map__empty', 'Die Schöneweide-Orte konnten gerade nicht geladen werden.'));
        }
      });
  }

  var hasBooted = false;

  function boot() {
    if (hasBooted) {
      return;
    }

    if (!document.querySelector('[data-iss-schoneweide-explore]')) {
      return;
    }

    hasBooted = true;
    init();
  }

  boot();
  document.addEventListener('DOMContentLoaded', boot, { once: true });
  window.addEventListener('load', boot, { once: true });
})();
