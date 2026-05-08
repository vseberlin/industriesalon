(function (window, document) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};
  const helpers = registerApp.helpers || {};
  const safeText = typeof helpers.safeText === 'function'
    ? helpers.safeText
    : function (value, fallback) {
      const text = (value || '').toString().trim();
      return text !== '' ? text : (fallback === undefined ? '—' : fallback);
    };

  function parseJsonScript(node, fallback) {
    if (!node) {
      return fallback;
    }

    try {
      const parsed = JSON.parse(node.textContent || '{}');
      if (parsed && typeof parsed === 'object') {
        return parsed;
      }
    } catch (error) {
    }

    return fallback;
  }

  function toBoolean(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
  }

  function sanitizeBootstrapConfig(rawConfig) {
    const config = rawConfig && typeof rawConfig === 'object' ? rawConfig : {};

    return {
      apiRoot: safeText(config.apiRoot, '/wp-json/iss-register/v1').replace(/\/$/, ''),
      defaultView: safeText(config.defaultView, 'discover'),
      limitArea: safeText(config.limitArea, ''),
      limitStatus: safeText(config.limitStatus, ''),
      restNonce: safeText(config.restNonce, ''),
      showFeedback: toBoolean(config.showFeedback),
      enableExport: toBoolean(config.enableExport)
    };
  }

  function sanitizeBootstrapData(rawData) {
    const data = rawData && typeof rawData === 'object' ? rawData : {};

    return {
      generatedAt: safeText(data.generatedAt, ''),
      places: Array.isArray(data.places)
        ? data.places.filter((place) => place && typeof place === 'object')
        : []
    };
  }

  function parseBootstrapPayload(root) {
    const payload = parseJsonScript(root.querySelector('[data-register-bootstrap]'), {
      config: {},
      data: {
        places: []
      }
    });

    return {
      config: sanitizeBootstrapConfig(payload.config),
      data: sanitizeBootstrapData(payload.data)
    };
  }

  function collectElements(root) {
    const scope = root.querySelector('[data-iss-register-app]') || root;
    const query = (selector) => scope.querySelector(selector);
    const queryAll = (selector) => Array.from(scope.querySelectorAll(selector));

    return {
      scope,
      tabs: queryAll('.iss-register-tab'),
      tabDetail: query('[data-detail-tab]'),
      panels: queryAll('.iss-register-panel'),
      statNodes: queryAll('[data-stat]'),
      searchInputs: queryAll('[data-filter-search]'),
      areaSelects: queryAll('[data-filter-area]'),
      statusSelects: queryAll('[data-filter-status]'),
      roleSelects: queryAll('[data-filter-role]'),
      unclearSelects: queryAll('[data-filter-unclear]'),
      sortSelects: queryAll('[data-filter-sort]'),
      discoverFeatured: query('[data-discover-featured]'),
      discoverFeaturedGrid: query('[data-discover-featured-grid]'),
      discoverFeaturedEmpty: query('[data-discover-featured-empty]'),
      discoverList: query('[data-discover-list]'),
      mapCanvas: query('[data-map-canvas]'),
      mapEmpty: query('[data-map-empty]'),
      mapList: query('.iss-register-map-list'),
      placesList: query('[data-places-list]'),
      placesGrid: query('[data-places-grid]'),
      placesEmpty: query('[data-places-empty]'),
      resultsCount: query('[data-results-count]'),
      thenNowList: query('[data-then-now-list]'),
      thenNowGrid: query('[data-then-now-grid]'),
      thenNowEmpty: query('[data-then-now-empty]'),
      researchSummary: query('[data-research-summary]'),
      researchSourcesList: query('[data-research-sources-list]'),
      researchSourcesEmpty: query('[data-research-sources-empty]'),
      researchResults: query('[data-research-results]'),
      researchTable: query('[data-research-table]'),
      researchResultsBody: query('[data-research-results-body]'),
      researchResultsEmpty: query('[data-research-results-empty]'),
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
      feedbackImageFields: query('[data-feedback-image-fields]'),
      templates: {
        featuredCard: query('[data-register-template="featured-card"]'),
        quickLinkItem: query('[data-register-template="quick-link-item"]'),
        mapMarker: query('[data-register-template="map-marker"]'),
        mapListItem: query('[data-register-template="map-list-item"]'),
        placeCard: query('[data-register-template="place-card"]'),
        thenNowCard: query('[data-register-template="then-now-card"]'),
        researchSourceItem: query('[data-register-template="research-source-item"]'),
        researchRow: query('[data-register-template="research-row"]')
      }
    };
  }

  registerApp.bootstrap = Object.assign({}, registerApp.bootstrap, {
    parseBootstrapPayload,
    collectElements
  });
})(window, document);
