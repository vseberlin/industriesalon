(function (window, document) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};
  const constants = registerApp.constants || {};
  const helpers = registerApp.helpers || {};
  const dom = registerApp.dom || {};
  const bootstrapApi = registerApp.bootstrap || {};
  const storeApi = registerApp.store || {};
  const servicesApi = registerApp.services || {};
  const rendererApi = registerApp.renderer || {};

  if (
    !Array.isArray(constants.TAB_NAMES) ||
    typeof helpers.safeText !== 'function' ||
    typeof helpers.normalize !== 'function' ||
    typeof helpers.statusLabel !== 'function' ||
    typeof helpers.roleLabel !== 'function' ||
    typeof helpers.debounce !== 'function' ||
    typeof dom.setSelectOptions !== 'function' ||
    typeof bootstrapApi.parseBootstrapPayload !== 'function' ||
    typeof bootstrapApi.collectElements !== 'function' ||
    typeof storeApi.createRegisterStore !== 'function' ||
    typeof servicesApi.createRegisterServices !== 'function' ||
    typeof rendererApi.createRegisterRenderer !== 'function'
  ) {
    return;
  }

  const TAB_NAMES = constants.TAB_NAMES;
  const SEARCH_INPUT_DEBOUNCE_MS = Number.isFinite(constants.SEARCH_INPUT_DEBOUNCE_MS)
    ? constants.SEARCH_INPUT_DEBOUNCE_MS
    : 140;
  const safeText = helpers.safeText;
  const normalize = helpers.normalize;
  const statusLabel = helpers.statusLabel;
  const roleLabel = helpers.roleLabel;
  const debounce = helpers.debounce;
  const setSelectOptions = dom.setSelectOptions;

  function createRegisterController(root) {
    const bootstrap = bootstrapApi.parseBootstrapPayload(root);
    const config = bootstrap.config;
    const elements = bootstrapApi.collectElements(root);
    const store = storeApi.createRegisterStore(config);
    const services = servicesApi.createRegisterServices(config);

    function ensureOption(selectNode, value, label) {
      if (!selectNode || !value) {
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

    function ensurePlaceDetail(placeId) {
      const key = safeText(placeId, '');
      if (!key) {
        return Promise.resolve(null);
      }

      const existingDetail = store.getDetail(key);
      if (existingDetail) {
        return Promise.resolve(existingDetail);
      }

      const existingRequest = store.getDetailRequest(key);
      if (existingRequest) {
        return existingRequest;
      }

      store.clearDetailError(key);

      const request = services.fetchPlaceDetail(key)
        .then((detail) => store.mergeDetail(key, detail))
        .catch((error) => {
          store.setDetailError(key, error instanceof Error ? error.message : 'Detaildaten konnten nicht geladen werden.');
          return null;
        })
        .finally(() => {
          store.clearDetailRequest(key);

          if (store.state.activeTab === 'detail' && key === String(store.state.selectedId || '')) {
            renderer.renderDetail(store.getSelectedPlace());
          }
        });

      store.setDetailRequest(key, request);

      if (store.state.activeTab === 'detail' && key === String(store.state.selectedId || '')) {
        renderer.renderDetail(store.getSelectedPlace());
      }

      return request;
    }

    const renderer = rendererApi.createRegisterRenderer({
      elements,
      store,
      ensurePlaceDetail
    });

    function setActiveTab(tabName) {
      const requested = TAB_NAMES.includes(tabName) ? tabName : 'discover';
      const nextTab = requested === 'detail' && !store.hasSelection() ? 'discover' : requested;

      store.setActiveTab(nextTab);
      renderer.syncActiveTabUI();
      renderer.renderPanel(store.state.activeTab);
    }

    function selectPlace(placeId, switchToDetail) {
      const selected = store.getPlaceSummary(placeId);
      if (!selected) {
        return;
      }

      store.setSelectedId(selected.id);
      selectFeedbackPlaceById(selected.id);

      if (switchToDetail) {
        setActiveTab('detail');
        return;
      }

      if (store.state.activeTab === 'places') {
        renderer.renderPanel('places');
      }

      if (store.state.activeTab === 'detail') {
        ensurePlaceDetail(selected.id);
        renderer.renderDetail(store.getSelectedPlace());
      }
    }

    function populateAreaAndRoleOptions() {
      const places = store.getBasePlaces();
      const areaValues = Array.from(new Set(places.map((place) => safeText(place.area, '')).filter(Boolean)))
        .sort((left, right) => left.localeCompare(right, 'de'));
      const roleValues = Array.from(new Set(places.map((place) => safeText(place.role, '')).filter(Boolean)))
        .sort((left, right) => left.localeCompare(right, 'de'));

      elements.areaSelects.forEach((selectNode) => {
        const current = safeText(selectNode.value, '');
        const options = [{ value: '', label: 'Alle Gebiete' }]
          .concat(areaValues.map((area) => ({ value: area, label: area })));

        setSelectOptions(selectNode, options);
        if (current && areaValues.some((area) => normalize(area) === normalize(current))) {
          selectNode.value = current;
        }
      });

      elements.roleSelects.forEach((selectNode) => {
        const current = safeText(selectNode.value, '');
        const options = [{ value: '', label: 'Alle Rollen' }]
          .concat(roleValues.map((role) => ({ value: role, label: roleLabel(role) })));

        setSelectOptions(selectNode, options);
        if (current && roleValues.some((role) => normalize(role) === normalize(current))) {
          selectNode.value = current;
        }
      });
    }

    function applyConfigLimits() {
      if (config.limitArea) {
        store.state.filters.area = config.limitArea;
        elements.areaSelects.forEach((selectNode) => {
          ensureOption(selectNode, config.limitArea, config.limitArea);
          selectNode.value = config.limitArea;
          selectNode.disabled = true;
        });
      }

      if (config.limitStatus) {
        store.state.filters.status = config.limitStatus;
        elements.statusSelects.forEach((selectNode) => {
          ensureOption(selectNode, config.limitStatus, statusLabel(config.limitStatus));
          selectNode.value = config.limitStatus;
          selectNode.disabled = true;
        });
      }

      store.applyFilters();
    }

    function syncControls() {
      elements.searchInputs.forEach((inputNode) => {
        if (inputNode !== document.activeElement) {
          inputNode.value = store.state.filters.search;
        }
      });

      elements.areaSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(store.state.filters.area, '')) {
          ensureOption(selectNode, store.state.filters.area, store.state.filters.area);
          selectNode.value = store.state.filters.area;
        }
      });

      elements.statusSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(store.state.filters.status, '')) {
          ensureOption(selectNode, store.state.filters.status, statusLabel(store.state.filters.status));
          selectNode.value = store.state.filters.status;
        }
      });

      elements.roleSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(store.state.filters.role, '')) {
          ensureOption(selectNode, store.state.filters.role, roleLabel(store.state.filters.role));
          selectNode.value = store.state.filters.role;
        }
      });

      elements.unclearSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(store.state.filters.unclear, '')) {
          selectNode.value = store.state.filters.unclear;
        }
      });

      elements.sortSelects.forEach((selectNode) => {
        if (safeText(selectNode.value, '') !== safeText(store.state.filters.sort, 'id')) {
          selectNode.value = store.state.filters.sort;
        }
      });
    }

    function populateFeedbackPlaceOptions() {
      if (!elements.feedbackPlaceSelect) {
        return;
      }

      const previous = elements.feedbackPlaceSelect.value;
      const options = [{ value: '', label: 'Ohne direkten Standortbezug' }];

      store.state.places.forEach((place) => {
        const postId = Number.parseInt(place.post_id, 10);
        const optionValue = postId > 0 ? String(postId) : 'legacy:' + safeText(place.id, '');
        const label = safeText(place.name) + ' (#' + safeText(place.id) + ')';

        options.push({
          value: optionValue,
          label,
          dataset: {
            placeId: safeText(place.id, ''),
            reference: label
          }
        });
      });

      setSelectOptions(elements.feedbackPlaceSelect, options);
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

      elements.feedbackImageFields.hidden = elements.feedbackTypeSelect.value !== 'image_contribution';
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

      const selected = store.getSelectedPlace();
      if (selected) {
        selectFeedbackPlaceById(selected.id);
      }

      elements.feedbackModal.hidden = false;
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

      ['name', 'email'].forEach((field) => {
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
        await services.submitFeedback(payload);
        if (elements.feedbackStatus) {
          elements.feedbackStatus.textContent = 'Danke, Feedback wurde gespeichert.';
        }
        elements.feedbackForm.reset();
        toggleFeedbackImageFields();
        window.setTimeout(closeFeedbackModal, 650);
      } catch (error) {
        if (elements.feedbackStatus) {
          elements.feedbackStatus.textContent = error instanceof Error ? error.message : 'Fehler beim Senden.';
        }
      }
    }

    async function exportFilteredData() {
      if (!config.enableExport) {
        return;
      }

      try {
        const exported = await services.exportPlaces(store.state.filters);
        const objectUrl = window.URL.createObjectURL(exported.blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = 'schoeneweide-register-filtered.json';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(objectUrl);
      } catch (error) {
        if (elements.researchSummary) {
          elements.researchSummary.textContent = error instanceof Error ? error.message : 'Export konnte nicht geladen werden.';
        }
      }
    }

    function updateFilter(name, value) {
      store.updateFilter(name, value);
      syncControls();
      renderer.renderAll();
    }

    function bindFilters() {
      const applySearchFilter = debounce((value) => {
        updateFilter('search', value || '');
      }, SEARCH_INPUT_DEBOUNCE_MS);

      elements.searchInputs.forEach((inputNode) => {
        inputNode.addEventListener('input', function () {
          applySearchFilter(inputNode.value || '');
        });
      });

      [
        [elements.areaSelects, 'area'],
        [elements.statusSelects, 'status'],
        [elements.roleSelects, 'role'],
        [elements.unclearSelects, 'unclear'],
        [elements.sortSelects, 'sort']
      ].forEach(([nodes, key]) => {
        nodes.forEach((selectNode) => {
          selectNode.addEventListener('change', function () {
            updateFilter(key, selectNode.value || (key === 'sort' ? 'id' : ''));
          });
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

    function init() {
      bindEvents();
      store.setPlaces(bootstrap.data.places);
      populateAreaAndRoleOptions();
      applyConfigLimits();
      populateFeedbackPlaceOptions();
      syncControls();
      renderer.renderAll();
      toggleFeedbackImageFields();

      return {
        submitFeedback: services.submitFeedback
      };
    }

    return {
      init,
      submitFeedback: services.submitFeedback
    };
  }

  registerApp.controller = Object.assign({}, registerApp.controller, {
    createRegisterController
  });
})(window, document);
