(function () {
  var Atlas = window.issSchoneweideAtlas || {};

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

  Atlas.layout = {
    bindMapSizeSync: bindMapSizeSync
  };

  window.issSchoneweideAtlas = Atlas;
})();
