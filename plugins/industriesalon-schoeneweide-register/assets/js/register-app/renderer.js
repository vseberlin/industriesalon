(function (window, document) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};
  const helpers = registerApp.helpers || {};
  const dom = registerApp.dom || {};

  if (
    typeof helpers.safeText !== 'function' ||
    typeof helpers.toArray !== 'function' ||
    typeof helpers.statusLabel !== 'function' ||
    typeof helpers.statusClass !== 'function' ||
    typeof helpers.placeSummary !== 'function' ||
    typeof helpers.firstImage !== 'function' ||
    typeof helpers.parseSources !== 'function' ||
    typeof helpers.normalize !== 'function' ||
    typeof helpers.buildRoleSummary !== 'function' ||
    typeof helpers.roleLabel !== 'function' ||
    typeof dom.createMediaNode !== 'function' ||
    typeof dom.createEmptyNode !== 'function' ||
    typeof dom.replaceNodeChildren !== 'function' ||
    typeof dom.renderSimpleList !== 'function'
  ) {
    return;
  }

  const safeText = helpers.safeText;
  const toArray = helpers.toArray;
  const statusLabel = helpers.statusLabel;
  const statusClass = helpers.statusClass;
  const placeSummary = helpers.placeSummary;
  const firstImage = helpers.firstImage;
  const parseSources = helpers.parseSources;
  const normalize = helpers.normalize;
  const buildRoleSummary = helpers.buildRoleSummary;
  const roleLabel = helpers.roleLabel;
  const createMediaNode = dom.createMediaNode;
  const createEmptyNode = dom.createEmptyNode;
  const replaceNodeChildren = dom.replaceNodeChildren;
  const renderSimpleList = dom.renderSimpleList;

  function createRegisterRenderer(options) {
    const elements = options.elements;
    const store = options.store;
    const ensurePlaceDetail = typeof options.ensurePlaceDetail === 'function'
      ? options.ensurePlaceDetail
      : function () {
        return Promise.resolve(null);
      };

    function cloneTemplate(templateKey) {
      const template = elements.templates[templateKey];
      if (!template || !template.content || !template.content.firstElementChild) {
        return null;
      }

      return template.content.firstElementChild.cloneNode(true);
    }

    function assignPlaceId(node, placeId) {
      if (!node) {
        return;
      }

      node.dataset.placeId = safeText(placeId, '');
    }

    function setStatusBadge(node, status) {
      if (!node) {
        return;
      }

      node.textContent = statusLabel(status);
      node.className = 'iss-register-badge iss-register-badge--' + statusClass(status);
    }

    function replaceTemplateSlot(root, selector, replacement) {
      if (!root || !replacement) {
        return;
      }

      const slot = root.querySelector(selector);
      if (!slot) {
        return;
      }

      slot.replaceWith(replacement);
    }

    function createFeaturedCard(place) {
      const card = cloneTemplate('featuredCard');
      if (!card) {
        return null;
      }

      assignPlaceId(card.querySelector('[data-place-id]'), place.id);
      replaceTemplateSlot(card, '[data-template-slot="media"]', createMediaNode(place, 'Bild gesucht', 'iss-register-featured-card__media'));

      const nameNode = card.querySelector('[data-template-field="name"]');
      if (nameNode) nameNode.textContent = safeText(place.name);
      setStatusBadge(card.querySelector('[data-template-field="status"]'), place.status);
      const areaNode = card.querySelector('[data-template-field="area"]');
      if (areaNode) areaNode.textContent = safeText(place.area, 'Gebiet offen');
      const summaryNode = card.querySelector('[data-template-field="summary"]');
      if (summaryNode) summaryNode.textContent = placeSummary(place, 150);

      return card;
    }

    function createQuickLinkItem(place) {
      const item = cloneTemplate('quickLinkItem');
      if (!item) {
        return null;
      }

      assignPlaceId(item.querySelector('[data-place-id]'), place.id);
      const nameNode = item.querySelector('[data-template-field="name"]');
      if (nameNode) nameNode.textContent = safeText(place.name);
      const metaNode = item.querySelector('[data-template-field="meta"]');
      if (metaNode) metaNode.textContent = safeText(place.area, 'Gebiet offen') + ' · ' + statusLabel(place.status);

      return item;
    }

    function createMapMarker(place, x, y) {
      const marker = cloneTemplate('mapMarker');
      if (!marker) {
        return null;
      }

      assignPlaceId(marker, place.id);
      marker.style.setProperty('--x', x.toFixed(3) + '%');
      marker.style.setProperty('--y', y.toFixed(3) + '%');
      marker.setAttribute('aria-label', place.name);

      return marker;
    }

    function createMapListItem(place) {
      const item = cloneTemplate('mapListItem');
      if (!item) {
        return null;
      }

      assignPlaceId(item.querySelector('[data-place-id]'), place.id);
      const nameNode = item.querySelector('[data-template-field="name"]');
      if (nameNode) nameNode.textContent = place.name;

      return item;
    }

    function createPlaceCard(place) {
      const card = cloneTemplate('placeCard');
      if (!card) {
        return null;
      }

      const button = card.querySelector('[data-place-id]');
      assignPlaceId(button, place.id);
      button.classList.toggle('is-active', String(place.id) === String(store.state.selectedId));
      replaceTemplateSlot(card, '[data-template-slot="media"]', createMediaNode(place, 'Bild gesucht', 'iss-register-place-card__media'));

      const nameNode = card.querySelector('[data-template-field="name"]');
      if (nameNode) nameNode.textContent = safeText(place.name);
      const areaNode = card.querySelector('[data-template-field="area"]');
      if (areaNode) areaNode.textContent = safeText(place.area, 'Gebiet offen');
      setStatusBadge(card.querySelector('[data-template-field="status"]'), place.status);
      const summaryNode = card.querySelector('[data-template-field="summary"]');
      if (summaryNode) summaryNode.textContent = placeSummary(place, 135);

      return card;
    }

    function createThenNowCard(place) {
      const card = cloneTemplate('thenNowCard');
      if (!card) {
        return null;
      }

      assignPlaceId(card.querySelector('[data-place-id]'), place.id);
      replaceTemplateSlot(card, '[data-template-slot="old-media"]', createMediaNode(place, 'Archivbild gesucht', 'iss-register-then-now-card__media', ['archive_images', 'document_images']));
      replaceTemplateSlot(card, '[data-template-slot="new-media"]', createMediaNode(place, 'Aktuelles Bild gesucht', 'iss-register-then-now-card__media', ['current_images', 'document_images']));

      const nameNode = card.querySelector('[data-template-field="name"]');
      if (nameNode) nameNode.textContent = safeText(place.name);
      const summaryNode = card.querySelector('[data-template-field="summary"]');
      if (summaryNode) summaryNode.textContent = placeSummary(place, 120);

      return card;
    }

    function createResearchSourceItem(label, count) {
      const item = cloneTemplate('researchSourceItem');
      if (!item) {
        return null;
      }

      const labelNode = item.querySelector('[data-template-field="label"]');
      if (labelNode) labelNode.textContent = label;
      const countNode = item.querySelector('[data-template-field="count"]');
      if (countNode) countNode.textContent = String(count);

      return item;
    }

    function createResearchRow(place) {
      const row = cloneTemplate('researchRow');
      if (!row) {
        return null;
      }

      assignPlaceId(row.querySelector('[data-place-id]'), place.id);
      const nameNode = row.querySelector('[data-template-field="name"]');
      if (nameNode) nameNode.textContent = safeText(place.name);
      const areaNode = row.querySelector('[data-template-field="area"]');
      if (areaNode) areaNode.textContent = safeText(place.area, 'Gebiet offen');
      setStatusBadge(row.querySelector('[data-template-field="status"]'), place.status);
      const roleSummaryNode = row.querySelector('[data-template-field="role-summary"]');
      if (roleSummaryNode) roleSummaryNode.textContent = buildRoleSummary(place);

      return row;
    }

    function setStat(name, value) {
      elements.statNodes.forEach((node) => {
        if (node.dataset.stat === name) {
          node.textContent = String(value);
        }
      });
    }

    function updateStats() {
      const filtered = store.state.filtered;
      const activeCount = filtered.filter((place) => normalize(place.status) === 'aktiv').length;
      const developmentCount = filtered.filter((place) => normalize(place.status) === 'entwicklung').length;
      const plannedCount = filtered.filter((place) => ['geplant', 'unklar'].includes(normalize(place.status))).length;

      setStat('count', filtered.length);
      setStat('active', activeCount);
      setStat('development', developmentCount);
      setStat('planned', plannedCount);

      if (elements.researchSummary) {
        elements.researchSummary.textContent = filtered.length + ' Datensätze nach aktiver Filterkombination.';
      }
    }

    function renderDiscoverFeatured() {
      if (!elements.discoverFeaturedGrid || !elements.discoverFeaturedEmpty) {
        return;
      }

      const featured = store.state.filtered.slice(0, 6);
      if (featured.length === 0) {
        elements.discoverFeaturedGrid.replaceChildren();
        elements.discoverFeaturedEmpty.hidden = false;
        return;
      }

      elements.discoverFeaturedEmpty.hidden = true;
      replaceNodeChildren(elements.discoverFeaturedGrid, featured.map((place) => createFeaturedCard(place)));
    }

    function renderDiscoverList() {
      if (!elements.discoverList) {
        return;
      }

      const entries = store.state.filtered.slice(0, 8);
      if (entries.length === 0) {
        elements.discoverList.replaceChildren(createEmptyNode('li', 'Keine passenden Standorte.'));
        return;
      }

      replaceNodeChildren(elements.discoverList, entries.map((place) => createQuickLinkItem(place)));
    }

    function renderMap() {
      if (!elements.mapCanvas) {
        return;
      }

      const geoPlaces = store.state.filtered
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
        elements.mapCanvas.replaceChildren();
        elements.mapCanvas.hidden = true;
        if (elements.mapEmpty) {
          elements.mapEmpty.hidden = false;
        }
        if (elements.mapList) {
          elements.mapList.replaceChildren();
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

      const markers = geoPlaces.map((place) => {
        const x = Math.max(4, Math.min(96, ((place.lng - minLng) / lngRange) * 100));
        const y = Math.max(6, Math.min(94, ((maxLat - place.lat) / latRange) * 100));
        return createMapMarker(place, x, y);
      }).filter(Boolean);

      elements.mapCanvas.replaceChildren(...markers);
      elements.mapCanvas.hidden = false;

      if (elements.mapEmpty) {
        elements.mapEmpty.hidden = true;
      }
      if (elements.mapList) {
        replaceNodeChildren(elements.mapList, geoPlaces.map((place) => createMapListItem(place)));
      }
    }

    function renderPlaces() {
      if (!elements.placesGrid || !elements.placesEmpty) {
        return;
      }

      if (elements.resultsCount) {
        elements.resultsCount.textContent = store.state.filtered.length + ' Ergebnisse';
      }

      if (store.state.filtered.length === 0) {
        elements.placesGrid.replaceChildren();
        elements.placesEmpty.hidden = false;
        elements.placesEmpty.textContent = 'Keine Treffer für die aktuelle Filterkombination.';
        return;
      }

      elements.placesEmpty.hidden = true;
      replaceNodeChildren(elements.placesGrid, store.state.filtered.map((place) => createPlaceCard(place)));
    }

    function renderThenNow() {
      if (!elements.thenNowGrid || !elements.thenNowEmpty) {
        return;
      }

      const list = store.state.filtered.slice(0, 12);
      if (list.length === 0) {
        elements.thenNowGrid.replaceChildren();
        elements.thenNowEmpty.hidden = false;
        elements.thenNowEmpty.textContent = 'Keine Standorte für den Vergleich.';
        return;
      }

      elements.thenNowEmpty.hidden = true;
      replaceNodeChildren(elements.thenNowGrid, list.map((place) => createThenNowCard(place)));
    }

    function renderResearch() {
      if (elements.researchSourcesList && elements.researchSourcesEmpty) {
        const sourceCount = new Map();

        store.state.filtered.forEach((place) => {
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
          elements.researchSourcesList.replaceChildren();
          elements.researchSourcesEmpty.hidden = false;
        } else {
          elements.researchSourcesEmpty.hidden = true;
          replaceNodeChildren(elements.researchSourcesList, sortedSources.map((entry) => createResearchSourceItem(entry[0], entry[1])));
        }
      }

      if (elements.researchResults && elements.researchTable && elements.researchResultsBody && elements.researchResultsEmpty) {
        if (store.state.filtered.length === 0) {
          elements.researchResultsBody.replaceChildren();
          elements.researchTable.hidden = true;
          elements.researchResultsEmpty.hidden = false;
          return;
        }

        elements.researchResultsEmpty.hidden = true;
        elements.researchTable.hidden = false;
        replaceNodeChildren(elements.researchResultsBody, store.state.filtered.slice(0, 60).map((place) => createResearchRow(place)));
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
          renderSimpleList(elements.detailQuestions, [], '—');
        }
        if (elements.detailSourcesList) {
          renderSimpleList(elements.detailSourcesList, [], '—');
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

      const placeId = String(place.id || '');
      const hasDetail = !!store.getDetail(placeId);
      const detailLoading = !!store.getDetailRequest(placeId) && !hasDetail;
      const detailError = store.getDetailError(placeId);

      elements.detailTitle.textContent = safeText(place.name);
      if (elements.detailOwner) {
        elements.detailOwner.textContent = detailLoading && !safeText(place.owner, '')
          ? 'Lade Detaildaten…'
          : safeText(place.owner, 'Eigentümer unbekannt');
      }
      if (elements.detailStatus) {
        elements.detailStatus.textContent = statusLabel(place.status);
        elements.detailStatus.className = 'iss-register-badge iss-register-badge--' + statusClass(place.status);
      }
      if (elements.detailRole) elements.detailRole.textContent = roleLabel(place.role);
      if (elements.detailArea) elements.detailArea.textContent = safeText(place.area);
      if (elements.detailNarrative) elements.detailNarrative.textContent = placeSummary(place, 220);
      if (elements.detailAddress) elements.detailAddress.textContent = safeText(place.address);
      if (elements.detailSize) elements.detailSize.textContent = hasDetail ? safeText(place.size) : (detailLoading ? 'Lade Detaildaten…' : '—');
      if (elements.detailInvestment) elements.detailInvestment.textContent = hasDetail ? safeText(place.investment) : (detailLoading ? 'Lade Detaildaten…' : '—');
      if (elements.detailJobs) elements.detailJobs.textContent = hasDetail ? safeText(place.jobs) : (detailLoading ? 'Lade Detaildaten…' : '—');
      if (elements.detailBranch) elements.detailBranch.textContent = safeText(place.branche);
      if (elements.detailOld) elements.detailOld.textContent = hasDetail ? safeText(place.vornutzung) : (detailLoading ? 'Lade Detaildaten…' : '—');
      if (elements.detailHistory) elements.detailHistory.textContent = hasDetail ? safeText(place.history) : (detailLoading ? 'Lade Detaildaten…' : '—');
      if (elements.detailCurrent) elements.detailCurrent.textContent = hasDetail ? safeText(place.current) : (detailLoading ? 'Lade Detaildaten…' : '—');

      const questions = toArray(place.questions).map((question) => safeText(question, '')).filter(Boolean);
      if (elements.detailQuestions) {
        if (!hasDetail && detailLoading) {
          renderSimpleList(elements.detailQuestions, [], 'Lade Detaildaten…');
        } else if (!hasDetail && detailError) {
          renderSimpleList(elements.detailQuestions, [], detailError);
        } else {
          renderSimpleList(elements.detailQuestions, questions, 'Keine offenen Fragen hinterlegt.');
        }
      }

      if (elements.detailSourcesList) {
        renderSimpleList(elements.detailSourcesList, parseSources(place), 'Keine Quellen eingetragen.');
      }

      if (elements.detailWebsite) {
        const website = safeText(place.website, '');
        if (website && hasDetail) {
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

    function renderPanel(tabName) {
      if (tabName === 'places') {
        renderPlaces();
        return;
      }

      if (tabName === 'then-now') {
        renderThenNow();
        return;
      }

      if (tabName === 'research') {
        renderResearch();
        return;
      }

      if (tabName === 'detail') {
        const selected = store.getSelectedPlace();
        renderDetail(selected);
        if (selected) {
          ensurePlaceDetail(selected.id);
        }
        return;
      }

      renderDiscoverFeatured();
      renderDiscoverList();
      renderMap();
    }

    function syncActiveTabUI() {
      const hasSelection = store.hasSelection();

      elements.tabs.forEach((button) => {
        const isActive = button.dataset.tabTarget === store.state.activeTab;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      elements.panels.forEach((panel) => {
        panel.classList.toggle('is-active', panel.dataset.panel === store.state.activeTab);
      });

      if (elements.tabDetail) {
        elements.tabDetail.hidden = !hasSelection;
      }
    }

    function renderAll() {
      updateStats();

      if (store.state.activeTab === 'detail' && !store.hasSelection()) {
        store.setActiveTab('discover');
      }

      syncActiveTabUI();
      renderPanel(store.state.activeTab);
    }

    return {
      renderAll,
      renderPanel,
      renderDetail,
      syncActiveTabUI,
      updateStats
    };
  }

  registerApp.renderer = Object.assign({}, registerApp.renderer, {
    createRegisterRenderer
  });
})(window, document);
