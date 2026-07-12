(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const settings = window.issRelationsSettings || {};
  const PLACE_POST_TYPE = settings.placePostType || 'register_place';

  const components = {
    el: window.wp.element.createElement,
    Fragment: window.wp.element.Fragment,
    useEffect: window.wp.element.useEffect,
    useState: window.wp.element.useState,
    InspectorControls: window.wp.blockEditor && window.wp.blockEditor.InspectorControls,
    Button: window.wp.components && window.wp.components.Button,
    BaseControl: window.wp.components && window.wp.components.BaseControl,
    CheckboxControl: window.wp.components && window.wp.components.CheckboxControl,
    PanelBody: window.wp.components && window.wp.components.PanelBody,
    TextControl: window.wp.components && window.wp.components.TextControl,
    TextareaControl: window.wp.components && window.wp.components.TextareaControl,
    SelectControl: window.wp.components && window.wp.components.SelectControl,
    RangeControl: window.wp.components && window.wp.components.RangeControl,
    ToggleControl: window.wp.components && window.wp.components.ToggleControl,
    useSelect: window.wp.data && window.wp.data.useSelect,
    apiFetch: window.wp.apiFetch,
  };

  const POST_TYPE_OPTIONS_BASE = [
    { label: 'Orte', value: PLACE_POST_TYPE },
    { label: 'Archivbeiträge', value: 'archivbeitrag' },
    { label: 'Archivobjekte', value: 'archivobjekt' },
    { label: 'Beiträge', value: 'post' },
    { label: 'Führungen', value: 'fuehrung' },
    { label: 'Veranstaltungen', value: 'veranstaltung' },
    { label: 'Publikationen', value: 'publication' },
    { label: 'Videos', value: 'video' },
    { label: 'Ausstellungen', value: 'ausstellung' },
    { label: 'Projekte', value: 'projekt' },
    { label: 'Profile', value: 'entity_profile' },
    { label: 'Seiten', value: 'page' },
  ];

  const POST_TYPE_OPTIONS = (function () {
    var sourceTypes = Array.isArray(settings.relatedPostTypes) && settings.relatedPostTypes.length
      ? settings.relatedPostTypes
      : settings.supportedPostTypes;
    var supported = Array.isArray(sourceTypes) && sourceTypes.length
      ? sourceTypes.map(function (value) {
          return String(value || '');
        })
      : POST_TYPE_OPTIONS_BASE.map(function (option) {
          return option.value;
        });
    var lookup = {};
    var options = [];

    POST_TYPE_OPTIONS_BASE.forEach(function (option) {
      lookup[option.value] = option;
    });

    supported.forEach(function (value) {
      if (!value || options.some(function (option) { return option.value === value; })) {
        return;
      }

      options.push(lookup[value] || {
        label: value,
        value: value,
      });
    });

    return options;
  }());

  const SOURCE_OPTIONS = [
    { label: 'Aktueller Kontext', value: 'current' },
    { label: 'Verknüpfte Orte', value: 'related' },
    { label: 'Aktuelle Route', value: 'route' },
    { label: 'Manuelle Ortsauswahl', value: 'manual' },
  ];

  const RELATED_SOURCE_OPTIONS = SOURCE_OPTIONS.concat([
    { label: 'Graph: alle Bezüge', value: 'entity' },
    { label: 'Graph: Orte', value: 'entity_place' },
    { label: 'Graph: Personen', value: 'entity_person' },
    { label: 'Graph: Organisationen', value: 'entity_organization' },
  ]);

  const optionSets = {
    SOURCE_OPTIONS: SOURCE_OPTIONS,
    RELATED_SOURCE_OPTIONS: RELATED_SOURCE_OPTIONS,
    PANEL_MODE_OPTIONS: [
      { label: 'Mit Infopanel', value: 'show' },
      { label: 'Nur Karte', value: 'hide' },
    ],
    PANEL_POSITION_OPTIONS: [
      { label: 'Panel rechts', value: 'right' },
      { label: 'Panel unter der Karte', value: 'below' },
    ],
    BODY_MODE_OPTIONS: [
      { label: 'Texttafel', value: 'text' },
      { label: 'Bildtafel', value: 'image' },
    ],
    BODY_POSITION_OPTIONS: [
      { label: 'Tafel rechts', value: 'end' },
      { label: 'Tafel links', value: 'start' },
    ],
    LAYOUT_MODE_OPTIONS: [
      { label: 'Horizontaler Streifen', value: 'band' },
      { label: 'Asymmetrisches Split-Feld', value: 'split' },
    ],
    ATLAS_STRIP_VARIANT_OPTIONS: [
      { label: 'Ort-Streifen', value: 'place' },
      { label: 'Korridor-Streifen', value: 'corridor' },
      { label: 'Minimal-Streifen', value: 'minimal' },
      { label: 'Spine-Zeile', value: 'spine' },
    ],
    ATLAS_STRIP_THEME_OPTIONS: [
      { label: 'Schwarz', value: 'black' },
      { label: 'Rot', value: 'red' },
      { label: 'Ocker', value: 'ochre' },
      { label: 'Blau', value: 'blue' },
      { label: 'Grün', value: 'green' },
      { label: 'Violett', value: 'violet' },
    ],
    ATLAS_STRIP_LINE_MODE_OPTIONS: [
      { label: 'Route', value: 'route' },
      { label: 'Korridor', value: 'corridor' },
      { label: 'Keine Linie', value: 'none' },
    ],
    ATLAS_STRIP_STATION_MODE_OPTIONS: [
      { label: 'Ausgewählte Orte', value: 'selected' },
      { label: 'Preset-Schiene', value: 'preset' },
    ],
    SPINE_TEXT_MODE_OPTIONS: [
      { label: 'Normaler Introtext', value: 'text' },
      { label: 'Zitat', value: 'quote' },
    ],
    ASYMMETRIC_SPLIT_PRESET_OPTIONS: [
      { label: 'Karte - Bild - Text', value: 'map-image-text' },
      { label: 'Text - Karte - Bild', value: 'text-map-image' },
      { label: 'Karte - Text', value: 'map-text' },
    ],
    ASYMMETRIC_SPLIT_HEIGHT_OPTIONS: [
      { label: 'Mittel', value: 'md' },
      { label: 'Groß', value: 'lg' },
      { label: 'Extra hoch', value: 'xl' },
    ],
    RELATED_CARDS_LAYOUT_OPTIONS: [
      { label: 'Raster', value: 'grid' },
      { label: 'Strip', value: 'strip' },
      { label: 'Gestapelt', value: 'stack' },
      { label: 'Kompakt', value: 'compact' },
      { label: 'Rail', value: 'rail' },
      { label: 'Register', value: 'register' },
    ],
    RELATED_CARDS_SORT_OPTIONS: [
      { label: 'Automatisch', value: 'auto' },
      { label: 'Relevanz', value: 'relevance' },
      { label: 'Relevanz + Titel', value: 'relevance_title' },
      { label: 'Relevanz + Datum', value: 'relevance_date' },
    ],
    RELATED_CARDS_COLUMN_OPTIONS: [
      { label: '1 Spalte', value: '1' },
      { label: '2 Spalten', value: '2' },
      { label: '3 Spalten', value: '3' },
      { label: '4 Spalten', value: '4' },
      { label: '5 Spalten', value: '5' },
      { label: '6 Spalten', value: '6' },
    ],
    RELATED_CARDS_SKIN_OPTIONS: [
      { label: 'Global / Accent', value: 'default' },
      { label: 'Ausstellung / Braun', value: 'exhibition' },
    ],
  };

  function stripTags(value) {
    return String(value || '').replace(/<[^>]*>/g, '').trim();
  }

  function getOptionLabel(options, value, fallback) {
    var normalized = String(value || fallback || '');
    var match = options.find(function (option) {
      return option.value === normalized;
    });

    return match ? match.label : normalized;
  }

  function getPostTypeLabel(value) {
    return getOptionLabel(POST_TYPE_OPTIONS, value, '');
  }

  function getSkinLabel(value) {
    return getOptionLabel(optionSets.RELATED_CARDS_SKIN_OPTIONS, value, 'default');
  }

  function getSourceLabel(value) {
    var normalized = String(value || 'current');
    return getOptionLabel(SOURCE_OPTIONS.concat(RELATED_SOURCE_OPTIONS), normalized, normalized);
  }

  function getMapBlockContract(config) {
    var contracts = settings.mapBlockContracts || {};
    var name = config && config.name ? String(config.name) : '';

    return name && contracts[name] && typeof contracts[name] === 'object' ? contracts[name] : {};
  }

  function getRelatedBlockDefaultSource(config) {
    var contract = getMapBlockContract(config);
    return String(contract.defaultSource || config.defaultSource || 'current');
  }

  function getRelatedBlockSource(attrs, config) {
    var source = attrs && attrs.source ? String(attrs.source) : '';
    if (source) {
      return source;
    }

    var contract = getMapBlockContract(config);
    if (contract.manualIdsImplyManualSource && attrs && String(attrs.placeIds || '').trim()) {
      return 'manual';
    }

    return getRelatedBlockDefaultSource(config);
  }

  function buildApiPath(path, args) {
    var query = Object.keys(args || {})
      .filter(function (key) {
        return args[key] !== undefined && args[key] !== null && args[key] !== '';
      })
      .map(function (key) {
        return encodeURIComponent(key) + '=' + encodeURIComponent(String(args[key]));
      })
      .join('&');

    return query ? path + '?' + query : path;
  }

  window.issRelationsEditor = Object.assign(window.issRelationsEditor || {}, {
    components: components,
    settings: settings,
    optionSets: optionSets,
    POST_TYPE_OPTIONS: POST_TYPE_OPTIONS,
    PLACE_POST_TYPE: PLACE_POST_TYPE,
    canManageEditorialSignals: settings.canManageEditorialSignals === true,
    stripTags: stripTags,
    getPostTypeLabel: getPostTypeLabel,
    getSkinLabel: getSkinLabel,
    getSourceLabel: getSourceLabel,
    getRelatedBlockDefaultSource: getRelatedBlockDefaultSource,
    getRelatedBlockSource: getRelatedBlockSource,
    buildApiPath: buildApiPath,
  });
})();
