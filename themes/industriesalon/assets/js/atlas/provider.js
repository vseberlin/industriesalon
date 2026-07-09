(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var text = core.text || function (value) {
    return typeof value === 'string' ? value.trim() : '';
  };
  var relativeUrl = core.relativeUrl || function (value) {
    return text(value);
  };
  var DEFAULT_RELATION_MAP_URL = '/wp-content/themes/industriesalon/assets/maps/schoneweide-map-canonical-display.webp';
  var CARTO_BASEMAP = {
    tileUrl: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
    options: {
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, ' +
        '&copy; <a href="https://carto.com/attributions">CARTO</a>',
      maxZoom: 20,
      subdomains: 'abcd'
    }
  };

  function getBasemapConfig(config) {
    config = config && typeof config === 'object' ? config : {};
    var provider = text(config.basemapProvider).toLowerCase();
    var maptilerKey = text(config.maptilerKey);
    var maptilerStyle = text(config.maptilerStyle) || 'streets-v2';

    if (provider === 'maptiler' && maptilerKey) {
      return {
        tileUrl: 'https://api.maptiler.com/maps/' + encodeURIComponent(maptilerStyle) + '/{z}/{x}/{y}.png?key=' + encodeURIComponent(maptilerKey),
        options: {
          attribution:
            '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> ' +
            '<a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
          tileSize: 512,
          zoomOffset: -1,
          minZoom: 1,
          maxZoom: 20,
          crossOrigin: true
        }
      };
    }

    return CARTO_BASEMAP;
  }

  function getStaticRelationMapUrl(config) {
    config = config && typeof config === 'object' ? config : {};
    var configured = text(config.relationStaticMapUrl);
    return configured ? relativeUrl(configured) : DEFAULT_RELATION_MAP_URL;
  }

  Atlas.provider = {
    getBasemapConfig: getBasemapConfig,
    getStaticRelationMapUrl: getStaticRelationMapUrl
  };

  window.issSchoneweideAtlas = Atlas;
})();
