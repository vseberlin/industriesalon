(function (window) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};
  const constants = registerApp.constants || {};
  const helpers = registerApp.helpers || {};

  if (
    typeof helpers.normalize !== 'function' ||
    typeof helpers.safeText !== 'function'
  ) {
    return;
  }

  const TAB_NAMES = Array.isArray(constants.TAB_NAMES)
    ? constants.TAB_NAMES
    : ['discover', 'places', 'then-now', 'research', 'detail'];
  const STATUS_SORT_ORDER = constants.STATUS_SORT_ORDER || {};
  const normalize = helpers.normalize;
  const safeText = helpers.safeText;

  function createRegisterStore(config) {
    const state = {
      places: [],
      filtered: [],
      detailsById: {},
      detailRequests: {},
      detailErrors: {},
      selectedId: '',
      activeTab: TAB_NAMES.includes(config.defaultView) ? config.defaultView : 'discover',
      filters: {
        search: '',
        area: '',
        status: '',
        role: '',
        unclear: '',
        sort: 'id'
      }
    };

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
      const sortBy = state.filters.sort || 'id';

      return places.sort((left, right) => {
        if (sortBy === 'name' || sortBy === 'area') {
          return safeText(left[sortBy], '').localeCompare(safeText(right[sortBy], ''), 'de');
        }

        if (sortBy === 'status') {
          const leftOrder = STATUS_SORT_ORDER[normalize(left.status)] ?? 999;
          const rightOrder = STATUS_SORT_ORDER[normalize(right.status)] ?? 999;
          if (leftOrder === rightOrder) {
            return safeText(left.name, '').localeCompare(safeText(right.name, ''), 'de');
          }
          return leftOrder - rightOrder;
        }

        const leftId = Number.parseInt(safeText(left.id, '0').replace(/\D+/g, ''), 10) || 0;
        const rightId = Number.parseInt(safeText(right.id, '0').replace(/\D+/g, ''), 10) || 0;
        return leftId - rightId;
      });
    }

    function syncSelectedId() {
      if (state.selectedId && !state.filtered.some((place) => String(place.id) === String(state.selectedId))) {
        state.selectedId = state.filtered.length ? String(state.filtered[0].id) : '';
      }
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
          const isUnclear = !!place.is_unclear || normalize(place.status) === 'unklar';
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
            place.summary,
            Array.isArray(place.source_labels) ? place.source_labels.join(' ') : ''
          ].join(' ').toLowerCase();

          if (!haystack.includes(searchTerm)) {
            return false;
          }
        }

        return true;
      });

      state.filtered = sortPlaces(filtered);
      syncSelectedId();

      return state.filtered;
    }

    function getPlaceSummary(placeId) {
      if (!placeId) {
        return null;
      }

      return state.places.find((place) => String(place.id) === String(placeId)) || null;
    }

    function getDetail(placeId) {
      const key = safeText(placeId, '');
      return key ? state.detailsById[key] || null : null;
    }

    function getSelectedPlace() {
      if (!state.selectedId) {
        return null;
      }

      return getDetail(state.selectedId) || getPlaceSummary(state.selectedId);
    }

    return {
      state,
      getBasePlaces,
      applyFilters,
      setPlaces(places) {
        state.places = Array.isArray(places)
          ? places.filter((place) => place && typeof place === 'object')
          : [];

        if (!state.selectedId && state.places.length) {
          state.selectedId = String(state.places[0].id || '');
        }

        applyFilters();
      },
      updateFilter(name, value) {
        if (Object.prototype.hasOwnProperty.call(state.filters, name)) {
          state.filters[name] = value || '';
        }

        return applyFilters();
      },
      setSelectedId(placeId) {
        state.selectedId = placeId ? String(placeId) : '';
      },
      setActiveTab(tabName) {
        state.activeTab = TAB_NAMES.includes(tabName) ? tabName : 'discover';
        return state.activeTab;
      },
      hasSelection() {
        return !!getSelectedPlace();
      },
      getPlaceSummary,
      getDetail,
      getSelectedPlace,
      mergeDetail(placeId, detail) {
        const key = safeText(placeId, '');
        if (!key) {
          return null;
        }

        state.detailsById[key] = Object.assign({}, getPlaceSummary(key) || {}, detail || {});
        return state.detailsById[key];
      },
      getDetailRequest(placeId) {
        const key = safeText(placeId, '');
        return key ? state.detailRequests[key] || null : null;
      },
      setDetailRequest(placeId, request) {
        const key = safeText(placeId, '');
        if (key) {
          state.detailRequests[key] = request;
        }
      },
      clearDetailRequest(placeId) {
        const key = safeText(placeId, '');
        if (key) {
          delete state.detailRequests[key];
        }
      },
      setDetailError(placeId, message) {
        const key = safeText(placeId, '');
        if (key) {
          state.detailErrors[key] = safeText(message, 'Detaildaten konnten nicht geladen werden.');
        }
      },
      clearDetailError(placeId) {
        const key = safeText(placeId, '');
        if (key) {
          delete state.detailErrors[key];
        }
      },
      getDetailError(placeId) {
        const key = safeText(placeId, '');
        return key ? safeText(state.detailErrors[key], '') : '';
      }
    };
  }

  registerApp.store = Object.assign({}, registerApp.store, {
    createRegisterStore
  });
})(window);
