(function () {
    if (window.__issNavInit) {
        return;
    }
    window.__issNavInit = true;

    function init() {
        var toggles = document.querySelectorAll('.iss-site-header .iss-nav-toggle button, .iss-site-header .iss-nav-toggle a');
        var panel = document.querySelector('.iss-menu-shell');
        var overlay = document.querySelector('.iss-nav-overlay');
        var close = panel ? panel.querySelector('.iss-menu-close') : null;
        var searchModal = document.querySelector('[data-iss-search-modal]');

        if (!toggles.length) {
            toggles = document.querySelectorAll('.iss-site-header .iss-nav-toggle');
        }

        if (!toggles.length || !panel || !overlay || !close || panel.dataset.issNavBound === '1') {
            return;
        }
        panel.dataset.issNavBound = '1';

        if (!panel.id) {
            panel.id = 'iss-menu-shell';
        }

        var activeToggle = null;

        function setToggleState(isExpanded) {
            Array.prototype.forEach.call(toggles, function (toggle) {
                toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                toggle.setAttribute('aria-controls', panel.id);
                if (!toggle.getAttribute('aria-label')) {
                    toggle.setAttribute('aria-label', 'Navigation öffnen');
                }
            });
        }

        setToggleState(false);
        if (!panel.getAttribute('aria-label')) {
            panel.setAttribute('aria-label', 'Hauptmenü');
        }
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        if (!close.getAttribute('aria-label')) {
            close.setAttribute('aria-label', 'Navigation schließen');
        }
        panel.setAttribute('aria-hidden', 'true');

        var focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
        var lastFocusedElement = null;
        var searchReturnFocus = null;

        function getFocusable() {
            return Array.prototype.slice.call(panel.querySelectorAll(focusableSelector)).filter(function (el) {
                return el.offsetParent !== null;
            });
        }

        function openNav() {
            lastFocusedElement = document.activeElement;
            document.documentElement.classList.add('iss-nav-open');
            document.body.classList.add('iss-nav-open');
            panel.classList.add('is-open');
            overlay.classList.add('is-open');
            setToggleState(true);
            panel.setAttribute('aria-hidden', 'false');

            var items = getFocusable();
            if (items.length) {
                window.setTimeout(function () {
                    items[0].focus();
                }, 20);
            }
        }

        function closeNav(returnFocus) {
            document.documentElement.classList.remove('iss-nav-open');
            document.body.classList.remove('iss-nav-open');
            panel.classList.remove('is-open');
            overlay.classList.remove('is-open');
            setToggleState(false);
            panel.setAttribute('aria-hidden', 'true');

            if (returnFocus === false) {
                activeToggle = null;
                return;
            }

            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            } else if (activeToggle && typeof activeToggle.focus === 'function') {
                activeToggle.focus();
            } else if (toggles[0] && typeof toggles[0].focus === 'function') {
                toggles[0].focus();
            }

            activeToggle = null;
        }

        function onKeydown(event) {
            if (!panel.classList.contains('is-open')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeNav();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var items = getFocusable();
            if (!items.length) {
                return;
            }

            var first = items[0];
            var last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        Array.prototype.forEach.call(toggles, function (toggle) {
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                activeToggle = toggle;
                if (panel.classList.contains('is-open')) {
                    closeNav();
                } else {
                    openNav();
                }
            });
        });

        close.addEventListener('click', function (event) {
            event.preventDefault();
            closeNav();
        });
        overlay.addEventListener('click', closeNav);
        document.addEventListener('keydown', onKeydown);

        if (searchModal && searchModal.dataset.issSearchBound !== '1') {
            searchModal.dataset.issSearchBound = '1';

            var searchInput = searchModal.querySelector('[data-iss-search-input]');
            var searchForm = searchModal.querySelector('[data-iss-search-form]');
            var searchStatus = searchModal.querySelector('[data-iss-search-status]');
            var searchResults = searchModal.querySelector('[data-iss-search-results]');
            var searchAll = searchModal.querySelector('[data-iss-search-all]');
            var searchEndpoint = searchModal.getAttribute('data-endpoint') || '';
            var searchUrl = searchModal.getAttribute('data-search-url') || '/';
            var searchTimer = null;
            var searchController = null;

            function setSearchStatus(message) {
                if (searchStatus) {
                    searchStatus.textContent = message;
                }
            }

            function getSearchTerm() {
                return searchInput ? searchInput.value.replace(/\s+/g, ' ').trim() : '';
            }

            function getFullSearchUrl(term) {
                var url = new URL(searchUrl, window.location.origin);
                url.searchParams.set('s', term);
                return url.toString();
            }

            function clearSearchResults() {
                if (searchResults) {
                    searchResults.replaceChildren();
                }
            }

            function renderSearchResults(items) {
                clearSearchResults();

                if (!searchResults) {
                    return;
                }

                if (!items.length) {
                    setSearchStatus('Keine schnellen Treffer.');
                    return;
                }

                setSearchStatus(items.length + ' schnelle Treffer');

                items.forEach(function (item) {
                    var link = document.createElement('a');
                    var title = document.createElement('span');
                    var meta = document.createElement('span');
                    var excerpt = document.createElement('span');

                    link.className = 'iss-search-modal__result';
                    link.href = item.url || '#';

                    title.className = 'iss-search-modal__result-title';
                    title.textContent = item.title || '';

                    meta.className = 'iss-search-modal__result-meta';
                    meta.textContent = item.type_label || item.post_type || '';

                    excerpt.className = 'iss-search-modal__result-excerpt';
                    excerpt.textContent = item.excerpt || '';

                    link.appendChild(title);
                    link.appendChild(meta);
                    if (item.excerpt) {
                        link.appendChild(excerpt);
                    }

                    searchResults.appendChild(link);
                });
            }

            function requestSearchResults(term) {
                if (!searchEndpoint || term.length < 2) {
                    if (searchController) {
                        searchController.abort();
                        searchController = null;
                    }
                    clearSearchResults();
                    setSearchStatus(term.length ? 'Mindestens zwei Zeichen eingeben.' : 'Suchbegriff eingeben.');
                    return;
                }

                if (searchController) {
                    searchController.abort();
                }

                searchController = new AbortController();
                setSearchStatus('Suche läuft...');

                var url = new URL(searchEndpoint, window.location.origin);
                url.searchParams.set('q', term);
                url.searchParams.set('limit', '8');

                window.fetch(url.toString(), {
                    signal: searchController.signal,
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Search request failed');
                    }
                    return response.json();
                }).then(function (payload) {
                    renderSearchResults(Array.isArray(payload.items) ? payload.items : []);
                }).catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    clearSearchResults();
                    setSearchStatus('Schnellsuche ist gerade nicht verfügbar.');
                });
            }

            function queueSearch() {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    requestSearchResults(getSearchTerm());
                }, 180);
            }

            function openSearchModal(trigger) {
                searchReturnFocus = trigger || document.activeElement;
                closeNav(false);
                document.documentElement.classList.add('iss-search-modal-open');
                document.body.classList.add('iss-search-modal-open');
                searchModal.classList.add('is-open');
                searchModal.setAttribute('aria-hidden', 'false');
                window.setTimeout(function () {
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }, 20);
                queueSearch();
            }

            function closeSearchModal(returnFocus) {
                document.documentElement.classList.remove('iss-search-modal-open');
                document.body.classList.remove('iss-search-modal-open');
                searchModal.classList.remove('is-open');
                searchModal.setAttribute('aria-hidden', 'true');

                if (searchController) {
                    searchController.abort();
                    searchController = null;
                }

                if (returnFocus !== false && searchReturnFocus && typeof searchReturnFocus.focus === 'function') {
                    searchReturnFocus.focus();
                }

                searchReturnFocus = null;
            }

            function goToFullSearch() {
                var term = getSearchTerm();
                if (!term) {
                    return;
                }
                window.location.href = getFullSearchUrl(term);
            }

            Array.prototype.forEach.call(document.querySelectorAll('.iss-menu-icon--search, [data-iss-search-trigger]'), function (trigger) {
                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    openSearchModal(trigger);
                });
            });

            Array.prototype.forEach.call(searchModal.querySelectorAll('[data-iss-search-close]'), function (button) {
                button.addEventListener('click', function () {
                    closeSearchModal();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', queueSearch);
            }

            if (searchForm) {
                searchForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    goToFullSearch();
                });
            }

            if (searchAll) {
                searchAll.addEventListener('click', goToFullSearch);
            }

            searchModal.addEventListener('click', function (event) {
                if (event.target.closest('.iss-search-modal__result')) {
                    closeSearchModal(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (!searchModal.classList.contains('is-open')) {
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeSearchModal();
                    return;
                }

                if (event.key !== 'Tab') {
                    return;
                }

                var items = Array.prototype.slice.call(searchModal.querySelectorAll(focusableSelector)).filter(function (el) {
                    return el.offsetParent !== null;
                });

                if (!items.length) {
                    return;
                }

                var first = items[0];
                var last = items[items.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        }

        panel.addEventListener('click', function (event) {
            var link = event.target.closest('a');
            if (!link) {
                return;
            }
            if (link.closest('.iss-menu-shell__close')) {
                return;
            }
            closeNav(false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
