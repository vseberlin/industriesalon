(function () {
  const selectors = {
    root: '.iss-tour-offer-catalog[data-filterable="1"]',
    button: '.iss-tour-offer-catalog__filter[data-offer-target]',
    panel: '.iss-tour-offer-catalog__panel[data-offer-panel]',
  };

  function resolveGroupFromHash(root, hash) {
    const buttons = Array.from(root.querySelectorAll(selectors.button));
    const value = (hash || window.location.hash || '').replace(/^#/, '');

    if (!value) {
      return '';
    }

    const matchedButton = buttons.find((button) => {
      return button.id === value || button.getAttribute('data-offer-target') === value;
    });

    return matchedButton ? matchedButton.getAttribute('data-offer-target') || '' : '';
  }

  function activateCatalog(root, groupKey) {
    const buttons = Array.from(root.querySelectorAll(selectors.button));
    const panels = Array.from(root.querySelectorAll(selectors.panel));

    buttons.forEach((button) => {
      const isActive = button.getAttribute('data-offer-target') === groupKey;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    panels.forEach((panel) => {
      const isActive = panel.getAttribute('data-offer-panel') === groupKey;
      panel.hidden = !isActive;

      if (isActive) {
        const track = panel.querySelector('.iss-tour-offer-catalog__track');
        if (track) {
          track.scrollLeft = 0;
        }
      }
    });
  }

  function initCatalog(root) {
    const buttons = Array.from(root.querySelectorAll(selectors.button));
    const panels = Array.from(root.querySelectorAll(selectors.panel));

    if (!buttons.length || !panels.length) {
      return;
    }

    root.classList.add('is-interactive');

    buttons.forEach((button) => {
      button.addEventListener('click', function () {
        activateCatalog(root, button.getAttribute('data-offer-target'));
      });
    });

    window.addEventListener('hashchange', function () {
      const hashGroup = resolveGroupFromHash(root);
      if (hashGroup) {
        activateCatalog(root, hashGroup);
      }
    });

    const initialGroup = resolveGroupFromHash(root) || buttons[0].getAttribute('data-offer-target');
    activateCatalog(root, initialGroup);
  }

  function init() {
    document.querySelectorAll(selectors.root).forEach(initCatalog);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
