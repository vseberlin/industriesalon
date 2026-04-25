(function () {
  const TAB_NAMES = ['discover', 'places', 'then-now', 'research', 'detail'];
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

  function safeText(value, fallback = '—') {
    const text = (value || '').toString().trim();
    return text !== '' ? text : fallback;
  }

  function escapeHtml(value) {
    return (value || '')
      .toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
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

  function placeSummary(place, maxLength = 160) {
    const options = [place.current, place.history, place.vornutzung];
    for (let index = 0; index < options.length; index += 1) {
      const summary = truncateText(options[index], maxLength);
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

  function placeMedia(place, fallback, className, groups) {
    const image = firstImage(place, groups);
    if (!image) {
      return '<div class="' + className + ' ' + className + '--empty"><span>' + escapeHtml(fallback) + '</span></div>';
    }

    const alt = image.caption || safeText(place.name, 'Standortbild');
    return '<div class="' + className + '"><img src="' + escapeHtml(image.url) + '" alt="' + escapeHtml(alt) + '" loading="lazy" decoding="async"></div>';
  }

  function parseSources(place) {
    const sourceLinks = toArray(place.source_links).map((item) => safeText(item, '')).filter(Boolean);
    const sourceSummary = safeText(place.sources, '');
    const summaryParts = sourceSummary === ''
      ? []
      : sourceSummary.split(/[|,;\n]/).map((part) => part.trim()).filter(Boolean);

    const merged = sourceLinks.concat(summaryParts);
    const deduped = [];
    merged.forEach((entry) => {
      if (!deduped.some((existing) => normalize(existing) === normalize(entry))) {
        deduped.push(entry);
      }
    });

    return deduped;
  }

  function parseLocalSource(root) {
    const sourceNode = root.querySelector('[data-register-source]');
    if (!sourceNode) {
      return { places: [] };
    }

    try {
      const parsed = JSON.parse(sourceNode.textContent || '{}');
      if (parsed && Array.isArray(parsed.places)) {
        return parsed;
      }
      return { places: [] };
    } catch (error) {
      return { places: [] };
    }
  }

  function toBoolean(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
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

  function initRegister(root) {
    const scope = root.querySelector('[data-iss-register-app]') || root;
    const query = (selector) => scope.querySelector(selector);
    const queryAll = (selector) => Array.from(scope.querySelectorAll(selector));

    const config = {
      apiRoot: (root.dataset.apiRoot || '/wp-json/iss-register/v1').replace(/\/$/, ''),
      defaultView: TAB_NAMES.includes(root.dataset.view) ? root.dataset.view : 'discover',
      limitArea: safeText(root.dataset.limitArea, ''),
      limitStatus: safeText(root.dataset.limitStatus, ''),
      restNonce: safeText(root.dataset.restNonce, ''),
      showFeedback: root.dataset.showFeedback === '1',
      enableExport: root.dataset.enableExport === '1'
    };

    const elements = {
      tabs: queryAll('.iss-register-tab'),
      tabDetail: query('[data-detail-tab]'),
      panels: queryAll('.iss-register-panel'),
      searchInputs: queryAll('[data-filter-search]'),
      areaSelects: queryAll('[data-filter-area]'),
      statusSelects: queryAll('[data-filter-status]'),
      roleSelects: queryAll('[data-filter-role]'),
      unclearSelects: queryAll('[data-filter-unclear]'),
      sortSelects: queryAll('[data-filter-sort]'),
      discoverFeatured: query('[data-discover-featured]'),
      discoverList: query('[data-discover-list]'),
      mapCanvas: query('[data-map-canvas]'),
      mapEmpty: query('[data-map-empty]'),
      mapList: query('.iss-register-map-list'),
      placesList: query('[data-places-list]'),
      resultsCount: query('[data-results-count]'),
      thenNowList: query('[data-then-now-list]'),
      researchSummary: query('[data-research-summary]'),
      researchSources: query('[data-research-sources]'),
      researchResults: query('[data-research-results]'),
      exportButton: query('[data-action-export-json]'),
      detailTitle: query('[data-detail-title]'),
      detailOwner: query('[data-detail-owner]'),
      detailStatus: query('[data-detail-status]'),
      detailRole: query('[data-detail-role]'),
      detailArea: query('[data-detail-area]'),
      detailNarrative: query('[data-detail-narrative]'),
      detailAddress: query('[data-detail-address]'),
      detailSize: query('[data-detail-size]'),
      detailInvestment: query('[data-detail-investment]'),
      detailJobs: query('[data-detail-jobs]'),
      detailBranch: query('[data-detail-branch]'),
      detailOld: query('[data-detail-old]'),
      detailHistory: query('[data-detail-history]'),
      detailCurrent: query('[data-detail-current]'),
      detailQuestions: query('[data-detail-questions]'),
      detailSourcesList: query('[data-detail-sources-list]'),
      detailWebsite: query('[data-detail-website]'),
      detailHeroImage: query('[data-detail-hero-image]'),
      detailHeroEmpty: query('[data-detail-hero-empty]'),
      detailOldImage: query('[data-detail-old-image]'),
      detailOldImageEmpty: query('[data-detail-old-image-empty]'),
      detailNewImage: query('[data-detail-new-image]'),
      detailNewImageEmpty: query('[data-detail-new-image-empty]'),
      feedbackOpenButtons: queryAll('[data-action="open-feedback"]'),
      feedbackModal: query('[data-feedback-modal]'),
      feedbackCloseButtons: queryAll('[data-action="close-feedback"]'),
      feedbackForm: query('[data-feedback-form]'),
      feedbackStatus: query('[data-feedback-status]'),
      feedbackPlaceSelect: query('[data-feedback-place]'),
      feedbackTypeSelect: query('[name="feedback_type"]'),
      feedbackImageFields: query('[data-feedback-image-fields]')
    };

    const state = {
      places: [],
      filtered: [],
      selectedId: '',
      activeTab: config.defaultView,
      filters: {
        search: '',
        area: '',
        status: '',
        role: '',
        unclear: '',
        sort: 'id'
      }
    };

    function setStat(name, value) {
      queryAll('[data-stat="' + name + '"]').forEach((node) => {
        node.textContent = String(value);
      });
    }

    function getBasePlaces() {
      return state.places.filter((place) => {
        if (config.limitArea && normalize(place.area) !== normalize(config.limitArea)) {
          return false;
        }
        if (config.limitStatus && normalize(place.status) !== normalize(config.limitStatus)) {
          return false;
        }
        return true;
      });
    }

    function sortPlaces(places) {
      const direction = 1;
      const sortBy = state.filters.sort || 'id';

      return places.sort((left, right) => {
        if (sortBy === 'name' || sortBy === 'area') {
          return safeText(left[sortBy], '').localeCompare(safeText(right[sortBy], ''), 'de') * direction;
        }

        if (sortBy === 'status') {
          const leftOrder = STATUS_SORT_ORDER[normalize(left.status)] ?? 999;
          const rightOrder = STATUS_SORT_ORDER[normalize(right.status)] ?? 999;
          if (leftOrder === rightOrder) {
            return safeText(left.name, '').localeCompare(safeText(right.name, ''), 'de');
          }
          return (leftOrder - rightOrder) * direction;
        }

        const leftId = Number.parseInt(safeText(left.id, '0').replace(/\D+/g, ''), 10) || 0;
        const rightId = Number.parseInt(safeText(right.id, '0').replace(/\D+/g, ''), 10) || 0;
        return (leftId - rightId) * direction;
      });
    }

    function applyFilters() {
      const searchTerm = normalize(state.filters.search);
      const roleFilter = normalize(state.filters.role);
      const areaFilter = normalize(state.filters.area);
      const statusFilter = normalize(state.filters.status);
      const unclearFilter = safeText(state.filters.unclear, '');

      const filtered = getBasePlaces().filter((place) => {
        if (areaFilter && normalize(place.area) !== areaFilter) {
          return false;
        }

        if (statusFilter && normalize(place.status) !== statusFilter) {
          return false;
        }

        if (roleFilter && normalize(place.role) !== roleFilter) {
          return false;
        }

        if (unclearFilter !== '') {
          const isUnclear = toBoolean(place.is_unclear) || normalize(place.status) === 'unklar';
          if (String(isUnclear ? 1 : 0) !== unclearFilter) {
            return false;
          }
        }

        if (searchTerm !== '') {
          const haystack = [
            place.name,
            place.owner,
            place.operator,
            place.developer,
            place.tenant,
            place.address,
            place.area,
            place.status,
            place.role,
            place.branche,
            place.vornutzung,
            place.current,
            place.history,
            place.sources
          ].join(' ').toLowerCase();

          if (!haystack.includes(searchTerm)) {
            return false;
          }
        }

        return true;
      });

      state.filtered = sortPlaces(filtered);

      if (state.selectedId && !state.filtered.some((place) => String(place.id) === String(state.selectedId))) {
        state.selectedId = state.filtered.length ? String(state.filtered[0].id) : '';
      }
    }

    function getSelectedPlace() {
      if (!state.selectedId) {
        return null;
      }

      return state.places.find((place) => String(place.id) === String(state.selectedId)) || null;
    }

    function updateStats() {
      const base = state.filtered;
      const activeCount = base.filter((place) => normalize(place.status) === 'aktiv').length;
      const developmentCount = base.filter((place) => normalize(place.status) === 'entwicklung').length;
      const plannedCount = base.filter((place) => ['geplant', 'unklar'].includes(normalize(place.status))).length;

      setStat('count', base.length);
      setStat('active', activeCount);
      setStat('development', developmentCount);
      setStat('planned', plannedCount);

      if (elements.researchSummary) {
        elements.researchSummary.textContent = base.length + ' Datensätze nach aktiver Filterkombination.';
      }
    }

    function renderDiscoverFeatured() {
      if (!elements.discoverFeatured) {
        return;
      }

      const featured = state.filtered.slice(0, 6);
      if (featured.length === 0) {
        elements.discoverFeatured.innerHTML = '<p class="iss-register-empty">Keine Standorte verfügbar.</p>';
        return;
      }

      elements.discoverFeatured.innerHTML = '<div class="iss-register-featured-grid">' + featured.map((place) => {
        return '<article class="iss-register-featured-card">' +
          '<button type="button" class="iss-register-featured-card__button" data-place-id="' + escapeHtml(place.id) + '">' +
            placeMedia(place, 'Bild gesucht', 'iss-register-featured-card__media') +
            '<div class="iss-register-featured-card__body">' +
              '<h4 class="iss-register-featured-card__title">' + escapeHtml(safeText(place.name)) + '</h4>' +
              '<p class="iss-register-featured-card__meta">' +
                '<span class="iss-register-badge iss-register-badge--' + statusClass(place.status) + '">' + escapeHtml(statusLabel(place.status)) + '</span>' +
                '<span>' + escapeHtml(safeText(place.area, 'Gebiet offen')) + '</span>' +
              '</p>' +
              '<p class="iss-register-featured-card__text">' + escapeHtml(placeSummary(place, 150)) + '</p>' +
              '<span class="iss-register-inline-link">Details ansehen</span>' +
            '</div>' +
          '</button>' +
        '</article>';
      }).join('') + '</div>';
    }

    function renderDiscoverList() {
      if (!elements.discoverList) {
        return;
      }

      const entries = state.filtered.slice(0, 8);
      if (entries.length === 0) {
        elements.discoverList.innerHTML = '<li class="iss-register-empty">Keine passenden Standorte.</li>';
        return;
      }

      elements.discoverList.innerHTML = entries.map((place) => {
        return '<li>' +
          '<button type="button" class="iss-register-quick-link" data-place-id="' + escapeHtml(place.id) + '">' +
            '<strong>' + escapeHtml(safeText(place.name)) + '</strong>' +
            '<span>' + escapeHtml(safeText(place.area, 'Gebiet offen')) + ' · ' + escapeHtml(statusLabel(place.status)) + '</span>' +
          '</button>' +
        '</li>';
      }).join('');
    }

    function renderMap() {
      if (!elements.mapCanvas) {
        return;
      }

      const geoPlaces = state.filtered
        .filter((place) => !Number.isNaN(Number.parseFloat(place.lat)) && !Number.isNaN(Number.parseFloat(place.lng)))
        .slice(0, 36)
        .map((place) => ({
          id: safeText(place.id, ''),
          name: safeText(place.name, 'Standort'),
          lat: Number.parseFloat(place.lat),
          lng: Number.parseFloat(place.lng)
        }))
        .filter((place) => place.id !== '');

      if (geoPlaces.length === 0) {
        elements.mapCanvas.innerHTML = '';
        elements.mapCanvas.hidden = true;
        if (elements.mapEmpty) {
          elements.mapEmpty.hidden = false;
        }
        if (elements.mapList) {
          elements.mapList.innerHTML = '';
        }
        return;
      }

      let minLat = geoPlaces[0].lat;
      let maxLat = geoPlaces[0].lat;
      let minLng = geoPlaces[0].lng;
      let maxLng = geoPlaces[0].lng;

      geoPlaces.forEach((place) => {
        minLat = Math.min(minLat, place.lat);
        maxLat = Math.max(maxLat, place.lat);
        minLng = Math.min(minLng, place.lng);
        maxLng = Math.max(maxLng, place.lng);
      });

      const latRange = Math.max(0.0001, maxLat - minLat);
      const lngRange = Math.max(0.0001, maxLng - minLng);

      elements.mapCanvas.innerHTML = geoPlaces.map((place) => {
        const x = Math.max(4, Math.min(96, ((place.lng - minLng) / lngRange) * 100));
        const y = Math.max(6, Math.min(94, ((maxLat - place.lat) / latRange) * 100));
        return '<button type="button" class="iss-register-map-marker" data-place-id="' + escapeHtml(place.id) + '" style="--x:' + x.toFixed(3) + '%;--y:' + y.toFixed(3) + '%" aria-label="' + escapeHtml(place.name) + '">' +
          '<span class="iss-register-map-marker__dot" aria-hidden="true"></span>' +
        '</button>';
      }).join('');

      elements.mapCanvas.hidden = false;
      if (elements.mapEmpty) {
        elements.mapEmpty.hidden = true;
      }
      if (elements.mapList) {
        elements.mapList.innerHTML = geoPlaces.map((place) => {
          return '<li><button type="button" class="iss-register-map-list__button" data-place-id="' + escapeHtml(place.id) + '">' + escapeHtml(place.name) + '</button></li>';
        }).join('');
      }
    }

    function renderPlaces() {
      if (!elements.placesList) {
        return;
      }

      if (elements.resultsCount) {
        elements.resultsCount.textContent = state.filtered.length + ' Ergebnisse';
      }

      if (state.filtered.length === 0) {
        elements.placesList.innerHTML = '<p class="iss-register-empty">Keine Treffer für die aktuelle Filterkombination.</p>';
        return;
      }

      elements.placesList.innerHTML = '<div class="iss-register-places-grid">' + state.filtered.map((place) => {
        const activeClass = String(place.id) === String(state.selectedId) ? ' is-active' : '';

        return '<article class="iss-register-place-card">' +
          '<button type="button" class="iss-register-place-card__button' + activeClass + '" data-place-id="' + escapeHtml(place.id) + '">' +
            placeMedia(place, 'Bild gesucht', 'iss-register-place-card__media') +
            '<div class="iss-register-place-card__body">' +
              '<h4 class="iss-register-place-card__title">' + escapeHtml(safeText(place.name)) + '</h4>' +
              '<p class="iss-register-place-card__meta">' +
                '<span>' + escapeHtml(safeText(place.area, 'Gebiet offen')) + '</span>' +
                '<span class="iss-register-badge iss-register-badge--' + statusClass(place.status) + '">' + escapeHtml(statusLabel(place.status)) + '</span>' +
              '</p>' +
              '<p class="iss-register-place-card__text">' + escapeHtml(placeSummary(place, 135)) + '</p>' +
              '<span class="iss-register-inline-link">Details ansehen</span>' +
            '</div>' +
          '</button>' +
        '</article>';
      }).join('') + '</div>';
    }

    function renderThenNow() {
      if (!elements.thenNowList) {
        return;
      }

      const list = state.filtered.slice(0, 12);
      if (list.length === 0) {
        elements.thenNowList.innerHTML = '<p class="iss-register-empty">Keine Standorte für den Vergleich.</p>';
        return;
      }

      elements.thenNowList.innerHTML = '<div class="iss-register-then-now-grid">' + list.map((place) => {
        return '<article class="iss-register-then-now-card">' +
          '<button type="button" class="iss-register-then-now-card__button" data-place-id="' + escapeHtml(place.id) + '">' +
            '<div class="iss-register-then-now-card__media-grid">' +
              placeMedia(place, 'Archivbild gesucht', 'iss-register-then-now-card__media', ['archive_images', 'document_images']) +
              placeMedia(place, 'Aktuelles Bild gesucht', 'iss-register-then-now-card__media', ['current_images', 'document_images']) +
            '</div>' +
            '<div class="iss-register-then-now-card__body">' +
              '<h4 class="iss-register-then-now-card__title">' + escapeHtml(safeText(place.name)) + '</h4>' +
              '<p class="iss-register-then-now-card__text">' + escapeHtml(placeSummary(place, 120)) + '</p>' +
              '<span class="iss-register-inline-link">Mehr erfahren</span>' +
            '</div>' +
          '</button>' +
        '</article>';
      }).join('') + '</div>';
    }

    function renderResearch() {
      if (elements.researchSources) {
        const sourceCount = new Map();
        state.filtered.forEach((place) => {
          parseSources(place).forEach((source) => {
            const key = safeText(source, '');
            if (!key) {
              return;
            }
            sourceCount.set(key, (sourceCount.get(key) || 0) + 1);
          });
        });

        const sortedSources = Array.from(sourceCount.entries())
          .sort((left, right) => right[1] - left[1])
          .slice(0, 12);

        if (sortedSources.length === 0) {
          elements.researchSources.innerHTML = '<p class="iss-register-empty">Keine Quellenhinweise vorhanden.</p>';
        } else {
          elements.researchSources.innerHTML = '<h4>Quellen im aktuellen Filter</h4><ul>' + sortedSources.map((entry) => {
            return '<li><span>' + escapeHtml(entry[0]) + '</span><strong>' + escapeHtml(entry[1]) + '</strong></li>';
          }).join('') + '</ul>';
        }
      }

      if (elements.researchResults) {
        if (state.filtered.length === 0) {
          elements.researchResults.innerHTML = '<p class="iss-register-empty">Keine Datensätze für die Recherche-Ansicht.</p>';
          return;
        }

        elements.researchResults.innerHTML = '<table class="iss-register-research-table"><thead><tr>' +
          '<th>Standort</th><th>Status</th><th>Rollen</th><th>Aktion</th>' +
          '</tr></thead><tbody>' +
          state.filtered.slice(0, 60).map((place) => {
            return '<tr>' +
              '<td><strong>' + escapeHtml(safeText(place.name)) + '</strong><br><small>' + escapeHtml(safeText(place.area, 'Gebiet offen')) + '</small></td>' +
              '<td><span class="iss-register-badge iss-register-badge--' + statusClass(place.status) + '">' + escapeHtml(statusLabel(place.status)) + '</span></td>' +
              '<td>' + escapeHtml(buildRoleSummary(place)) + '</td>' +
              '<td><button type="button" class="iss-register-inline-link-button" data-place-id="' + escapeHtml(place.id) + '">Detail</button></td>' +
            '</tr>';
          }).join('') +
          '</tbody></table>';
      }
    }

    function setImageState(imageNode, emptyNode, image, fallback, altText) {
      if (!imageNode || !emptyNode) {
        return;
      }

      if (!image || !image.url) {
        imageNode.hidden = true;
        imageNode.removeAttribute('src');
        imageNode.removeAttribute('alt');
        emptyNode.hidden = false;
        emptyNode.textContent = fallback;
        return;
      }

      imageNode.hidden = false;
      imageNode.src = image.url;
      imageNode.alt = image.caption || altText;
      emptyNode.hidden = true;
    }

    function renderDetail(place) {
      if (!elements.detailTitle) {
        return;
      }

      if (!place) {
        elements.detailTitle.textContent = 'Kein Standort ausgewählt';
        if (elements.detailOwner) elements.detailOwner.textContent = 'Bitte im Tab „Orte“ einen Standort auswählen.';
        if (elements.detailStatus) {
          elements.detailStatus.textContent = '—';
          elements.detailStatus.className = 'iss-register-badge';
        }
        if (elements.detailRole) elements.detailRole.textContent = '—';
        if (elements.detailArea) elements.detailArea.textContent = '—';
        if (elements.detailNarrative) elements.detailNarrative.textContent = 'Kurze Einordnung folgt nach Auswahl eines Standorts.';
        if (elements.detailAddress) elements.detailAddress.textContent = '—';
        if (elements.detailSize) elements.detailSize.textContent = '—';
        if (elements.detailInvestment) elements.detailInvestment.textContent = '—';
        if (elements.detailJobs) elements.detailJobs.textContent = '—';
        if (elements.detailBranch) elements.detailBranch.textContent = '—';
        if (elements.detailOld) elements.detailOld.textContent = '—';
        if (elements.detailHistory) elements.detailHistory.textContent = '—';
        if (elements.detailCurrent) elements.detailCurrent.textContent = '—';
        if (elements.detailQuestions) {
          elements.detailQuestions.innerHTML = '<li>—</li>';
        }
        if (elements.detailSourcesList) {
          elements.detailSourcesList.innerHTML = '<li>—</li>';
        }
        if (elements.detailWebsite) {
          elements.detailWebsite.hidden = true;
          elements.detailWebsite.removeAttribute('href');
        }
        setImageState(elements.detailHeroImage, elements.detailHeroEmpty, null, 'Bild gesucht', 'Standortbild');
        setImageState(elements.detailOldImage, elements.detailOldImageEmpty, null, 'Archivbild gesucht', 'Archivbild');
        setImageState(elements.detailNewImage, elements.detailNewImageEmpty, null, 'Aktuelles Bild gesucht', 'Aktuelles Bild');
        return;
      }

      elements.detailTitle.textContent = safeText(place.name);
      if (elements.detailOwner) elements.detailOwner.textContent = safeText(place.owner, 'Eigentümer unbekannt');
      if (elements.detailStatus) {
        elements.detailStatus.textContent = statusLabel(place.status);
        elements.detailStatus.className = 'iss-register-badge iss-register-badge--' + statusClass(place.status);
      }
      if (elements.detailRole) elements.detailRole.textContent = roleLabel(place.role);
      if (elements.detailArea) elements.detailArea.textContent = safeText(place.area);
      if (elements.detailNarrative) elements.detailNarrative.textContent = placeSummary(place, 220);
      if (elements.detailAddress) elements.detailAddress.textContent = safeText(place.address);
      if (elements.detailSize) elements.detailSize.textContent = safeText(place.size);
      if (elements.detailInvestment) elements.detailInvestment.textContent = safeText(place.investment);
      if (elements.detailJobs) elements.detailJobs.textContent = safeText(place.jobs);
      if (elements.detailBranch) elements.detailBranch.textContent = safeText(place.branche);
      if (elements.detailOld) elements.detailOld.textContent = safeText(place.vornutzung);
      if (elements.detailHistory) elements.detailHistory.textContent = safeText(place.history);
      if (elements.detailCurrent) elements.detailCurrent.textContent = safeText(place.current);

      const questions = toArray(place.questions).map((question) => safeText(question, '')).filter(Boolean);
      if (elements.detailQuestions) {
        elements.detailQuestions.innerHTML = questions.length
          ? questions.map((question) => '<li>' + escapeHtml(question) + '</li>').join('')
          : '<li>Keine offenen Fragen hinterlegt.</li>';
      }

      const sources = parseSources(place);
      if (elements.detailSourcesList) {
        elements.detailSourcesList.innerHTML = sources.length
          ? sources.map((source) => '<li>' + escapeHtml(source) + '</li>').join('')
          : '<li>Keine Quellen eingetragen.</li>';
      }

      if (elements.detailWebsite) {
        const website = safeText(place.website, '');
        if (website) {
          elements.detailWebsite.hidden = false;
          elements.detailWebsite.href = website;
        } else {
          elements.detailWebsite.hidden = true;
          elements.detailWebsite.removeAttribute('href');
        }
      }

      const heroImage = firstImage(place, ['current_images', 'archive_images', 'document_images']);
      const oldImage = firstImage(place, ['archive_images', 'document_images']);
      const newImage = firstImage(place, ['current_images', 'document_images']);

      setImageState(elements.detailHeroImage, elements.detailHeroEmpty, heroImage, 'Bild gesucht', safeText(place.name, 'Standortbild'));
      setImageState(elements.detailOldImage, elements.detailOldImageEmpty, oldImage, 'Archivbild gesucht', safeText(place.name, 'Archivbild'));
      setImageState(elements.detailNewImage, elements.detailNewImageEmpty, newImage, 'Aktuelles Bild gesucht', safeText(place.name, 'Aktuelles Bild'));
    }

    function setActiveTab(tabName) {
      const requested = TAB_NAMES.includes(tabName) ? tabName : 'discover';
      const hasSelection = !!getSelectedPlace();
      state.activeTab = requested === 'detail' && !hasSelection ? 'discover' : requested;

      elements.tabs.forEach((button) => {
        const isActive = button.dataset.tabTarget === state.activeTab;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      elements.panels.forEach((panel) => {
        panel.classList.toggle('is-active', panel.dataset.panel === state.activeTab);
      });

      if (elements.tabDetail) {
        elements.tabDetail.hidden = !hasSelection;
      }
    }

    function selectFeedbackPlaceById(placeId) {
      if (!elements.feedbackPlaceSelect || !placeId) {
        return;
      }

      const target = normalize(String(placeId));
      const options = Array.from(elements.feedbackPlaceSelect.options);
      const match = options.find((option) => normalize(option.dataset.placeId || '') === target);
      if (match) {
        elements.feedbackPlaceSelect.value = match.value;
      }
    }

    function selectPlace(placeId, switchToDetail) {
      const selected = state.places.find((place) => String(place.id) === String(placeId));
      if (!selected) {
        return;
      }

      state.selectedId = String(selected.id);
      renderPlaces();
      renderDetail(selected);
      selectFeedbackPlaceById(selected.id);

      if (switchToDetail) {
        setActiveTab('detail');
      }
    }

    function renderAll() {
      applyFilters();
      updateStats();
      renderDiscoverFeatured();
      renderDiscoverList();
      renderMap();
      renderPlaces();
      renderThenNow();
      renderResearch();
      renderDetail(getSelectedPlace());

      if (state.activeTab === 'detail' && !getSelectedPlace()) {
        setActiveTab('discover');
      } else {
        setActiveTab(state.activeTab);
      }
    }

    function ensureOption(selectNode, value, label) {
      if (!selectNode) {
        return;
      }
      const exists = Array.from(selectNode.options).some((option) => normalize(option.value) === normalize(value));
      if (!exists) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        selectNode.appendChild(option);
      }
    }

    function populateAreaAndRoleOptions() {
      const places = getBasePlaces();
      const areaValues = Array.from(new Set(places.map((place) => safeText(place.area, '')).filter(Boolean)))
        .sort((left, right) => left.localeCompare(right, 'de'));
      const roleValues = Array.from(new Set(places.map((place) => safeText(place.role, '')).filter(Boolean)))
        .sort((left, right) => left.localeCompare(right, 'de'));

      elements.areaSelects.forEach((selectNode) => {
        const current = safeText(selectNode.value, '');
        const options = ['<option value="">Alle Gebiete</option>']
          .concat(areaValues.map((area) => '<option value="' + escapeHtml(area) + '">' + escapeHtml(area) + '</option>'));
        selectNode.innerHTML = options.join('');
        if (current && areaValues.some((area) => normalize(area) === normalize(current))) {
          selectNode.value = current;
        }
      });

      elements.roleSelects.forEach((selectNode) => {
        const current = safeText(selectNode.value, '');
        const options = ['<option value="">Alle Rollen</option>']
          .concat(roleValues.map((role) => '<option value="' + escapeHtml(role) + '">' + escapeHtml(roleLabel(role)) + '</option>'));
        selectNode.innerHTML = options.join('');
        if (current && roleValues.some((role) => normalize(role) === normalize(current))) {
          selectNode.value = current;
        }
      });
    }

    function applyConfigLimits() {
      if (config.limitArea) {
        state.filters.area = config.limitArea;
        elements.areaSelects.forEach((selectNode) => {
          ensureOption(selectNode, config.limitArea, config.limitArea);
          selectNode.value = config.limitArea;
          selectNode.disabled = true;
        });
      }

      if (config.limitStatus) {
        state.filters.status = config.limitStatus;
        elements.statusSelects.forEach((selectNode) => {
          ensureOption(selectNode, config.limitStatus, statusLabel(config.limitStatus));
          selectNode.value = config.limitStatus;
          selectNode.disabled = true;
        });
      }
    }

    function syncControls() {
      elements.searchInputs.forEach((inputNode) => {
        if (inputNode !== document.activeElement) {
          inputNode.value = state.filters.search;
        }
      });
      elements.areaSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(state.filters.area, '')) {
          ensureOption(selectNode, state.filters.area, state.filters.area);
          selectNode.value = state.filters.area;
        }
      });
      elements.statusSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(state.filters.status, '')) {
          ensureOption(selectNode, state.filters.status, statusLabel(state.filters.status));
          selectNode.value = state.filters.status;
        }
      });
      elements.roleSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(state.filters.role, '')) {
          ensureOption(selectNode, state.filters.role, roleLabel(state.filters.role));
          selectNode.value = state.filters.role;
        }
      });
      elements.unclearSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(state.filters.unclear, '')) {
          selectNode.value = state.filters.unclear;
        }
      });
      elements.sortSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(state.filters.sort, 'id')) {
          selectNode.value = state.filters.sort;
        }
      });
    }

    function populateFeedbackPlaceOptions() {
      if (!elements.feedbackPlaceSelect) {
        return;
      }

      const previous = elements.feedbackPlaceSelect.value;
      const options = ['<option value="">Ohne direkten Standortbezug</option>'];

      state.places.forEach((place) => {
        const postId = Number.parseInt(place.post_id, 10);
        const optionValue = postId > 0 ? String(postId) : 'legacy:' + safeText(place.id, '');
        const label = safeText(place.name) + ' (#' + safeText(place.id) + ')';
        options.push(
          '<option value="' + escapeHtml(optionValue) + '" data-place-id="' + escapeHtml(safeText(place.id, '')) + '" data-reference="' + escapeHtml(label) + '">' +
            escapeHtml(label) +
          '</option>'
        );
      });

      elements.feedbackPlaceSelect.innerHTML = options.join('');
      if (previous && Array.from(elements.feedbackPlaceSelect.options).some((option) => option.value === previous)) {
        elements.feedbackPlaceSelect.value = previous;
      }
    }

    function closeFeedbackModal() {
      if (elements.feedbackModal) {
        elements.feedbackModal.hidden = true;
      }
    }

    function toggleFeedbackImageFields() {
      if (!elements.feedbackImageFields || !elements.feedbackTypeSelect) {
        return;
      }
      const isImageMode = elements.feedbackTypeSelect.value === 'image_contribution';
      elements.feedbackImageFields.hidden = !isImageMode;
    }

    function openFeedbackModal(type) {
      if (!elements.feedbackModal) {
        return;
      }

      if (elements.feedbackTypeSelect && type) {
        const hasOption = Array.from(elements.feedbackTypeSelect.options).some((option) => option.value === type);
        if (hasOption) {
          elements.feedbackTypeSelect.value = type;
        }
      }

      toggleFeedbackImageFields();

      const selected = getSelectedPlace();
      if (selected) {
        selectFeedbackPlaceById(selected.id);
      }

      elements.feedbackModal.hidden = false;
    }

    async function submitFeedbackPayload(payload) {
      const headers = {
        'Content-Type': 'application/json'
      };

      if (config.restNonce) {
        headers['X-WP-Nonce'] = config.restNonce;
      }

      const response = await fetch(config.apiRoot + '/feedback', {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(payload)
      });

      const result = await response.json();
      if (!response.ok) {
        const message = result && result.message ? result.message : 'Feedback konnte nicht gespeichert werden.';
        throw new Error(message);
      }

      return result;
    }

    async function handleFeedbackSubmit(event) {
      event.preventDefault();
      if (!elements.feedbackForm) {
        return;
      }

      const formData = new FormData(elements.feedbackForm);
      const feedbackType = safeText(formData.get('feedback_type'), 'general');
      const messageRaw = safeText(formData.get('message'), '');
      const placeReference = safeText(formData.get('place_reference'), '');
      const sourceUrl = safeText(formData.get('source_url'), '') || safeText(formData.get('image_source_url'), '');
      const imageAttachmentId = Number.parseInt(safeText(formData.get('image_attachment_id'), ''), 10);
      const rightsConfirmed = formData.get('rights_confirmation') ? 1 : 0;

      const selectedReferenceOption = elements.feedbackPlaceSelect
        ? elements.feedbackPlaceSelect.options[elements.feedbackPlaceSelect.selectedIndex]
        : null;
      const referenceLabel = selectedReferenceOption && selectedReferenceOption.dataset
        ? safeText(selectedReferenceOption.dataset.reference, '')
        : '';

      const parsedReferenceId = Number.parseInt(placeReference, 10);
      const relatedPlaceId = Number.isInteger(parsedReferenceId) && parsedReferenceId > 0
        ? parsedReferenceId
        : 0;

      if (messageRaw === '') {
        if (elements.feedbackStatus) {
          elements.feedbackStatus.textContent = 'Bitte Nachricht eingeben.';
        }
        return;
      }

      if (feedbackType === 'image_contribution') {
        if (rightsConfirmed !== 1) {
          if (elements.feedbackStatus) {
            elements.feedbackStatus.textContent = 'Bitte Nutzungsrechte bestätigen.';
          }
          return;
        }

        if (!(Number.isInteger(imageAttachmentId) && imageAttachmentId > 0) && sourceUrl === '') {
          if (elements.feedbackStatus) {
            elements.feedbackStatus.textContent = 'Bitte Bild-ID oder Bild-/Quellen-URL angeben.';
          }
          return;
        }
      }

      const payload = {
        feedback_type: feedbackType,
        message: relatedPlaceId > 0
          ? messageRaw
          : (referenceLabel ? messageRaw + '\n\nStandortbezug: ' + referenceLabel : messageRaw),
        rights_confirmation: rightsConfirmed
      };

      if (relatedPlaceId > 0) {
        payload.related_place_id = relatedPlaceId;
      }

      if (Number.isInteger(imageAttachmentId) && imageAttachmentId > 0) {
        payload.image_attachment_id = imageAttachmentId;
      }

      const optionalFields = ['name', 'email'];
      optionalFields.forEach((field) => {
        const value = safeText(formData.get(field), '');
        if (value !== '') {
          payload[field] = value;
        }
      });

      if (sourceUrl !== '') {
        payload.source_url = sourceUrl;
      }

      if (elements.feedbackStatus) {
        elements.feedbackStatus.textContent = 'Sende…';
      }

      try {
        await submitFeedbackPayload(payload);
        if (elements.feedbackStatus) {
          elements.feedbackStatus.textContent = 'Danke, Feedback wurde gespeichert.';
        }
        elements.feedbackForm.reset();
        toggleFeedbackImageFields();
        setTimeout(closeFeedbackModal, 650);
      } catch (error) {
        if (elements.feedbackStatus) {
          elements.feedbackStatus.textContent = error instanceof Error ? error.message : 'Fehler beim Senden.';
        }
      }
    }

    function exportFilteredData() {
      if (!config.enableExport) {
        return;
      }

      const payload = {
        generatedAt: new Date().toISOString(),
        total: state.filtered.length,
        places: state.filtered
      };

      const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = objectUrl;
      link.download = 'schoeneweide-register-filtered.json';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(objectUrl);
    }

    function bindFilters() {
      elements.searchInputs.forEach((inputNode) => {
        inputNode.addEventListener('input', function () {
          state.filters.search = inputNode.value || '';
          syncControls();
          renderAll();
        });
      });

      elements.areaSelects.forEach((selectNode) => {
        selectNode.addEventListener('change', function () {
          state.filters.area = selectNode.value || '';
          syncControls();
          renderAll();
        });
      });

      elements.statusSelects.forEach((selectNode) => {
        selectNode.addEventListener('change', function () {
          state.filters.status = selectNode.value || '';
          syncControls();
          renderAll();
        });
      });

      elements.roleSelects.forEach((selectNode) => {
        selectNode.addEventListener('change', function () {
          state.filters.role = selectNode.value || '';
          syncControls();
          renderAll();
        });
      });

      elements.unclearSelects.forEach((selectNode) => {
        selectNode.addEventListener('change', function () {
          state.filters.unclear = selectNode.value || '';
          syncControls();
          renderAll();
        });
      });

      elements.sortSelects.forEach((selectNode) => {
        selectNode.addEventListener('change', function () {
          state.filters.sort = selectNode.value || 'id';
          syncControls();
          renderAll();
        });
      });
    }

    function bindPlaceClicks(container) {
      if (!container) {
        return;
      }
      container.addEventListener('click', function (event) {
        const target = event.target.closest('[data-place-id]');
        if (!target) {
          return;
        }
        selectPlace(target.dataset.placeId, true);
      });
    }

    function bindEvents() {
      elements.tabs.forEach((button) => {
        button.addEventListener('click', function () {
          setActiveTab(button.dataset.tabTarget);
        });
      });

      bindFilters();
      bindPlaceClicks(elements.discoverFeatured);
      bindPlaceClicks(elements.discoverList);
      bindPlaceClicks(elements.mapCanvas);
      bindPlaceClicks(elements.mapList);
      bindPlaceClicks(elements.placesList);
      bindPlaceClicks(elements.thenNowList);
      bindPlaceClicks(elements.researchResults);

      elements.feedbackOpenButtons.forEach((button) => {
        button.addEventListener('click', function () {
          if (!config.showFeedback) {
            return;
          }
          openFeedbackModal(button.dataset.feedbackType || '');
        });
      });

      elements.feedbackCloseButtons.forEach((button) => {
        button.addEventListener('click', closeFeedbackModal);
      });

      if (elements.feedbackTypeSelect) {
        elements.feedbackTypeSelect.addEventListener('change', toggleFeedbackImageFields);
      }

      if (elements.feedbackForm) {
        elements.feedbackForm.addEventListener('submit', handleFeedbackSubmit);
      }

      if (elements.exportButton) {
        elements.exportButton.addEventListener('click', exportFilteredData);
      }
    }

    function loadLocalData() {
      const payload = parseLocalSource(root);
      state.places = toArray(payload.places).filter((place) => place && typeof place === 'object');

      populateAreaAndRoleOptions();
      applyConfigLimits();
      populateFeedbackPlaceOptions();

      if (!state.selectedId && state.places.length) {
        state.selectedId = String(state.places[0].id || '');
      }

      syncControls();
      renderAll();
      setActiveTab(config.defaultView);
      toggleFeedbackImageFields();
    }

    bindEvents();
    loadLocalData();

    root.__issRegisterSubmitFeedback = submitFeedbackPayload;
  }

  function boot() {
    document.querySelectorAll('.iss-register').forEach(initRegister);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.issRegisterSubmitFeedback = async function (payload) {
    const root = document.querySelector('.iss-register');
    if (!root || typeof root.__issRegisterSubmitFeedback !== 'function') {
      throw new Error('Register app is not initialized.');
    }
    return root.__issRegisterSubmitFeedback(payload);
  };
})();
