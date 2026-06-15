(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var roots = document.querySelectorAll('[data-ausstellungen-browser]');
    if (!roots.length) return;

    var globalRestUrl =
      window.ISS_AUSSTELLUNGEN && window.ISS_AUSSTELLUNGEN.restUrl
        ? String(window.ISS_AUSSTELLUNGEN.restUrl)
        : '';

    function parseConfig(root) {
      var raw = root.getAttribute('data-config') || '';
      try {
        return JSON.parse(raw) || {};
      } catch (err) {
        return {};
      }
    }

    function normalizeFilter(value, fallback) {
      var filter = String(value || '').trim();
      var allowed = ['aktuell', 'dauer', 'digital', 'archiv'];
      return allowed.indexOf(filter) >= 0 ? filter : fallback || 'aktuell';
    }

    function getUrlParams() {
      return new URL(window.location.href).searchParams;
    }

    function buildStateUrl(filter, search, root) {
      var url = new URL(window.location.href);
      url.searchParams.set('ausstellung_filter', filter);
      if (search) {
        url.searchParams.set('ausstellung_search', search);
      } else {
        url.searchParams.delete('ausstellung_search');
      }
      if (root.id) {
        url.hash = root.id;
      }
      return url;
    }

    function getFilterLabel(filterLinks, filter) {
      var label = '';

      filterLinks.forEach(function (link) {
        if (String(link.getAttribute('data-ausstellungen-filter') || '') === filter) {
          label = String(link.textContent || '').trim();
        }
      });

      return label || filter;
    }

    function countLabel(count) {
      return count === 1 ? '1 Ausstellung angezeigt.' : count + ' Ausstellungen angezeigt.';
    }

    function buildSummaryText(filterLinks, filter, search, count) {
      var pieces = [getFilterLabel(filterLinks, filter)];
      var normalizedSearch = String(search || '').trim();

      if (normalizedSearch) {
        pieces.push('Suche "' + normalizedSearch + '"');
      }

      return pieces.join(' · ') + ': ' + countLabel(count);
    }

    roots.forEach(function (root) {
      var config = parseConfig(root);
      var restUrl = config.restUrl ? String(config.restUrl) : globalRestUrl;
      var results = root.querySelector('[data-ausstellungen-results]');
      var form = root.querySelector('[data-ausstellungen-search-form]');
      var searchInput = root.querySelector('[data-ausstellungen-search-input]');
      var searchClear = root.querySelector('[data-ausstellungen-search-clear]');
      var filterInput = root.querySelector('[data-ausstellungen-filter-input]');
      var status = root.querySelector('[data-ausstellungen-status]');
      var summary = root.querySelector('[data-ausstellungen-summary]');
      var filterLinks = root.querySelectorAll('[data-ausstellungen-filter]');
      var requestInFlight = null;
      var searchTimer = 0;
      var activeFilter = normalizeFilter(config.filter, config.defaultFilter || 'aktuell');
      var activeSearch = String(config.search || '').trim();

      if (!restUrl || !results) return;

      function setStatus(message) {
        if (status) {
          status.textContent = message || '';
        }
      }

      function setLoading(isLoading) {
        root.classList.toggle('is-loading', !!isLoading);
        root.setAttribute('aria-busy', isLoading ? 'true' : 'false');
      }

      function syncControls(filter, search) {
        activeFilter = normalizeFilter(filter, config.defaultFilter || 'aktuell');
        activeSearch = String(search || '').trim();

        if (filterInput) {
          filterInput.value = activeFilter;
        }
        if (searchInput && searchInput.value !== activeSearch) {
          searchInput.value = activeSearch;
        }
        if (searchClear) {
          searchClear.hidden = activeSearch === '';
        }

        filterLinks.forEach(function (link) {
          var linkFilter = String(link.getAttribute('data-ausstellungen-filter') || '');
          var isActive = linkFilter === activeFilter;
          link.classList.toggle('is-active', isActive);
          link.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          link.href = buildStateUrl(normalizeFilter(linkFilter, activeFilter), activeSearch, root).toString();
        });
      }

      function syncSummary(filter, search, count) {
        if (!summary) {
          return;
        }

        summary.textContent = buildSummaryText(filterLinks, filter, search, count);
        summary.setAttribute('data-ausstellungen-result-count', String(count));
      }

      function requestAvailability(filter, search, updateHistory) {
        var nextFilter = normalizeFilter(filter, config.defaultFilter || 'aktuell');
        var nextSearch = String(search || '').trim();
        var params = new URLSearchParams();
        params.set('kind', 'exhibition');
        params.set('filter', nextFilter);
        params.set('limit', String(config.limit || 6));
        params.set('show_meta', config.showMeta === false ? '0' : '1');
        params.set('show_summary', config.showSummary === false ? '0' : '1');
        if (config.detailsButtonText) {
          params.set('details_button_text', String(config.detailsButtonText));
        }
        if (nextSearch) {
          params.set('search', nextSearch);
        }

        if (requestInFlight) {
          requestInFlight.abort();
        }

        requestInFlight = new AbortController();
        var controller = requestInFlight;
        setLoading(true);
        setStatus('');

        fetch(restUrl + '?' + params.toString(), {
          credentials: 'same-origin',
          signal: controller.signal,
        })
          .then(function (response) {
            if (!response.ok) {
              throw new Error('Availability request failed');
            }
            return response.json();
          })
          .then(function (data) {
            if (!data || typeof data.html !== 'string') {
              throw new Error('Availability response missing html');
            }

            results.innerHTML = data.html;
            syncControls(nextFilter, nextSearch);
            root.classList.remove('has-error');

            if (updateHistory) {
              window.history.pushState(
                {
                  issAusstellungen: true,
                  filter: nextFilter,
                  search: nextSearch,
                },
                '',
                buildStateUrl(nextFilter, nextSearch, root).toString()
              );
            }

            var count = typeof data.count === 'number' ? data.count : 0;
            syncSummary(nextFilter, nextSearch, count);
            setStatus(countLabel(count));
          })
          .catch(function (err) {
            if (err && err.name === 'AbortError') {
              return;
            }
            root.classList.add('has-error');
            setStatus('Ausstellungen konnten nicht geladen werden.');
          })
          .finally(function () {
            if (requestInFlight === controller) {
              setLoading(false);
              requestInFlight = null;
            }
          });
      }

      filterLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
          event.preventDefault();
          requestAvailability(link.getAttribute('data-ausstellungen-filter'), activeSearch, true);
        });
      });

      if (form) {
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          window.clearTimeout(searchTimer);
          requestAvailability(activeFilter, searchInput ? searchInput.value : '', true);
        });
      }

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          window.clearTimeout(searchTimer);
          searchTimer = window.setTimeout(function () {
            requestAvailability(activeFilter, searchInput.value, true);
          }, 350);
        });
      }

      if (searchClear) {
        searchClear.addEventListener('click', function (event) {
          event.preventDefault();
          window.clearTimeout(searchTimer);
          requestAvailability(activeFilter, '', true);
          if (searchInput) {
            searchInput.focus();
          }
        });
      }

      window.addEventListener('popstate', function () {
        var params = getUrlParams();
        var filter = params.get('ausstellung_filter') || config.defaultFilter || 'aktuell';
        var search = params.get('ausstellung_search') || '';
        requestAvailability(filter, search, false);
      });

      syncControls(activeFilter, activeSearch);
      syncSummary(activeFilter, activeSearch, Number(summary ? summary.getAttribute('data-ausstellungen-result-count') : 0) || 0);
    });
  });
})();
