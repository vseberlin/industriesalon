(function () {
    if (window.__issNavInit) {
        return;
    }
    window.__issNavInit = true;

    function initHeader(header, index) {
        if (!header || header.dataset.issNavBound === '1') {
            return;
        }
        header.dataset.issNavBound = '1';

        var toggle = header.querySelector('.iss-nav-toggle a, .iss-nav-toggle button, .iss-nav-toggle');
        var panel = header.querySelector('.iss-menu-shell');
        var overlay = header.querySelector('.iss-nav-overlay');
        var close = header.querySelector('.iss-menu-shell__close a, .iss-menu-shell__close button, .iss-menu-shell__close');
        var panelButtons = panel ? panel.querySelectorAll('.iss-supernav__button[data-panel]') : [];
        var panelViews = panel ? panel.querySelectorAll('.iss-menu-panel[data-panel]') : [];
        var activePanel = 'nav';

        if (!toggle || !panel || !overlay || !close) {
            return;
        }

        panel.id = 'iss-menu-shell-' + (index + 1);
        if (toggle.tagName && toggle.tagName.toLowerCase() === 'a') {
            toggle.setAttribute('href', '#' + panel.id);
        }

        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', panel.id);
        if (!toggle.getAttribute('aria-label')) {
            toggle.setAttribute('aria-label', 'Navigation öffnen');
        }
        if (!close.getAttribute('aria-label')) {
            close.setAttribute('aria-label', 'Navigation schließen');
        }

        var focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
        var lastFocusedElement = null;

        function setActivePanel(nextPanel) {
            if (!nextPanel) {
                return;
            }

            activePanel = nextPanel;

            Array.prototype.forEach.call(panelButtons, function (button) {
                var isActive = button.getAttribute('data-panel') === nextPanel;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                button.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            Array.prototype.forEach.call(panelViews, function (view) {
                var isActive = view.getAttribute('data-panel') === nextPanel;
                view.classList.toggle('is-visible', isActive);
                if (isActive) {
                    view.removeAttribute('hidden');
                } else {
                    view.setAttribute('hidden', 'hidden');
                }
            });
        }

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
            toggle.setAttribute('aria-expanded', 'true');

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
            toggle.setAttribute('aria-expanded', 'false');

            if (returnFocus === false) {
                return;
            }

            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            } else {
                toggle.focus();
            }
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

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            if (panel.classList.contains('is-open')) {
                closeNav();
            } else {
                openNav();
            }
        });

        close.addEventListener('click', function (event) {
            event.preventDefault();
            closeNav();
        });
        overlay.addEventListener('click', closeNav);
        document.addEventListener('keydown', onKeydown);

        if (panelButtons.length && panelViews.length) {
            Array.prototype.forEach.call(panelButtons, function (button) {
                button.addEventListener('click', function () {
                    setActivePanel(button.getAttribute('data-panel'));
                });
            });
            var defaultPanelButton = panel.querySelector('.iss-supernav__button.is-active');
            var defaultPanel = defaultPanelButton ? defaultPanelButton.getAttribute('data-panel') : activePanel;
            setActivePanel(defaultPanel);
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

    function init() {
        var headers = document.querySelectorAll('.iss-site-header');
        if (!headers.length) {
            return;
        }

        Array.prototype.forEach.call(headers, function (header, index) {
            initHeader(header, index);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
