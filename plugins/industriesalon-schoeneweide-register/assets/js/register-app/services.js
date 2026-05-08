(function (window) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};
  const helpers = registerApp.helpers || {};

  if (typeof helpers.safeText !== 'function') {
    return;
  }

  const safeText = helpers.safeText;

  function extractErrorMessage(payload, fallbackMessage) {
    if (payload && typeof payload === 'object' && payload.message) {
      return safeText(payload.message, fallbackMessage);
    }

    return fallbackMessage;
  }

  function createRegisterServices(config) {
    function buildFilterQueryParams(filters) {
      const params = new URLSearchParams();

      if (filters.search) {
        params.set('search', filters.search);
      }
      if (filters.area) {
        params.set('area', filters.area);
      }
      if (filters.status) {
        params.set('status', filters.status);
      }
      if (filters.role) {
        params.set('role', filters.role);
      }
      if (filters.unclear !== '') {
        params.set('unclear', filters.unclear);
      }
      if (filters.sort && filters.sort !== 'id') {
        params.set('orderby', filters.sort);
      }

      return params;
    }

    async function fetchPlaceDetail(placeId) {
      const response = await fetch(config.apiRoot + '/places/' + encodeURIComponent(String(placeId)), {
        credentials: 'same-origin'
      });

      const result = await response.json();
      if (!response.ok) {
        throw new Error(extractErrorMessage(result, 'Detaildaten konnten nicht geladen werden.'));
      }

      return result;
    }

    async function exportPlaces(filters) {
      const params = buildFilterQueryParams(filters);
      const response = await fetch(
        config.apiRoot + '/export' + (params.toString() ? '?' + params.toString() : ''),
        {
          credentials: 'same-origin'
        }
      );
      const body = await response.text();

      if (!response.ok) {
        let message = 'Export konnte nicht geladen werden.';

        try {
          const parsed = JSON.parse(body);
          message = extractErrorMessage(parsed, message);
        } catch (error) {
        }

        throw new Error(message);
      }

      return {
        body,
        blob: new Blob([body], { type: 'application/json' })
      };
    }

    async function submitFeedback(payload) {
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
        throw new Error(extractErrorMessage(result, 'Feedback konnte nicht gespeichert werden.'));
      }

      return result;
    }

    return {
      buildFilterQueryParams,
      fetchPlaceDetail,
      exportPlaces,
      submitFeedback
    };
  }

  registerApp.services = Object.assign({}, registerApp.services, {
    createRegisterServices
  });
})(window);
