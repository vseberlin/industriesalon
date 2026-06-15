(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var ACTIVE_BODY_CLASS = 'iss-atlas-layout-open';
  var FIXED_CLASS = 'is-atlas-layout-fixed';
  var IDLE_RESET_MS = 90000;
  var MODES = {
    embedded: true,
    fullscreen: true,
    kiosk: true
  };

  function bindMapSizeSync(map, surface) {
    var frameId = 0;

    if (!map || typeof map.invalidateSize !== 'function') {
      return;
    }

    function sync() {
      if (frameId) {
        return;
      }

      frameId = window.requestAnimationFrame(function () {
        frameId = 0;
        map.invalidateSize({ pan: false });
      });
    }

    window.addEventListener('resize', sync);

    if (window.ResizeObserver && surface) {
      var observer = new window.ResizeObserver(sync);
      observer.observe(surface);
    }

    sync();
  }

  function syncMapSize(leaflet) {
    if (!leaflet || !leaflet.map || typeof leaflet.map.invalidateSize !== 'function') {
      return;
    }

    window.requestAnimationFrame(function () {
      leaflet.map.invalidateSize({ pan: false });
    });
  }

  function resetMapView(leaflet) {
    if (!leaflet || !leaflet.map || !leaflet.atlasBounds || typeof leaflet.map.fitBounds !== 'function') {
      return;
    }

    leaflet.map.fitBounds(leaflet.atlasBounds, {
      animate: true,
      padding: [24, 24]
    });
  }

  function normalizeMode(mode) {
    return MODES[mode] ? mode : 'embedded';
  }

  function createButtonMap(controls) {
    var buttons = {};

    if (!controls) {
      return buttons;
    }

    Array.prototype.slice.call(controls.querySelectorAll('[data-iss-atlas-layout-mode]')).forEach(function (button) {
      var mode = normalizeMode(button.getAttribute('data-iss-atlas-layout-mode'));
      buttons[mode] = button;
    });

    return buttons;
  }

  function updateButtons(buttons, activeMode) {
    Object.keys(buttons).forEach(function (mode) {
      var isActive = mode === activeMode;
      buttons[mode].classList.toggle('is-active', isActive);
      buttons[mode].setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function bindLayoutModes(root, options) {
    var settings = options || {};
    var buttons = createButtonMap(settings.controls);
    var activeMode = 'embedded';
    var idleTimer = 0;
    var nativeFullscreenRequested = false;

    function resetIdleTimer() {
      if (activeMode !== 'kiosk') {
        return;
      }

      window.clearTimeout(idleTimer);
      idleTimer = window.setTimeout(function () {
        if (typeof settings.reset === 'function') {
          settings.reset();
        }
        resetMapView(settings.leaflet);
      }, IDLE_RESET_MS);
    }

    function setMode(mode) {
      activeMode = normalizeMode(mode);
      root.setAttribute('data-atlas-layout-mode', activeMode);
      root.classList.toggle(FIXED_CLASS, activeMode !== 'embedded');
      document.body.classList.toggle(ACTIVE_BODY_CLASS, activeMode !== 'embedded');
      updateButtons(buttons, activeMode);
      syncMapSize(settings.leaflet);

      if (activeMode === 'kiosk') {
        resetIdleTimer();
      } else {
        window.clearTimeout(idleTimer);
      }
    }

    function enterViewportMode(mode) {
      setMode(mode);

      if (!root.requestFullscreen) {
        return;
      }

      root.requestFullscreen()
        .then(function () {
          nativeFullscreenRequested = true;
        })
        .catch(function () {
          nativeFullscreenRequested = false;
        });
    }

    function exitViewportMode() {
      setMode('embedded');

      if (
        nativeFullscreenRequested &&
        document.fullscreenElement === root &&
        typeof document.exitFullscreen === 'function'
      ) {
        document.exitFullscreen();
      }

      nativeFullscreenRequested = false;
    }

    Object.keys(buttons).forEach(function (mode) {
      buttons[mode].addEventListener('click', function () {
        if (mode === 'embedded') {
          exitViewportMode();
          return;
        }

        enterViewportMode(mode);
      });
    });

    document.addEventListener('fullscreenchange', function () {
      if (nativeFullscreenRequested && document.fullscreenElement !== root && activeMode !== 'embedded') {
        setMode('embedded');
        nativeFullscreenRequested = false;
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && activeMode !== 'embedded' && document.fullscreenElement !== root) {
        exitViewportMode();
      }
    });

    ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(function (eventName) {
      root.addEventListener(eventName, resetIdleTimer, { passive: true });
    });

    setMode('embedded');
  }

  Atlas.layout = {
    bindLayoutModes: bindLayoutModes,
    bindMapSizeSync: bindMapSizeSync
  };

  window.issSchoneweideAtlas = Atlas;
})();
