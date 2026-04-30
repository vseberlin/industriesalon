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
