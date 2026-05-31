(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const useEffect = window.wp.element.useEffect;
  const useState = window.wp.element.useState;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const MediaUpload = window.wp.blockEditor && window.wp.blockEditor.MediaUpload;
  const MediaUploadCheck = window.wp.blockEditor && window.wp.blockEditor.MediaUploadCheck;
  const Button = window.wp.components && window.wp.components.Button;
  const BaseControl = window.wp.components && window.wp.components.BaseControl;
  const CheckboxControl = window.wp.components && window.wp.components.CheckboxControl;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;
  const RangeControl = window.wp.components && window.wp.components.RangeControl;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;
  const useSelect = window.wp.data && window.wp.data.useSelect;
  const apiFetch = window.wp.apiFetch;

  const settings = window.issRelationsSettings || {};
  const PLACE_POST_TYPE = settings.placePostType || 'register_place';

  const POST_TYPE_OPTIONS_BASE = [
    { label: 'Orte', value: PLACE_POST_TYPE },
    { label: 'Archivbeiträge', value: 'archivbeitrag' },
    { label: 'Beiträge', value: 'post' },
    { label: 'Führungen', value: 'fuehrung' },
    { label: 'Veranstaltungen', value: 'veranstaltung' },
    { label: 'Publikationen', value: 'publication' },
    { label: 'Videos', value: 'video' },
    { label: 'Ausstellungen', value: 'ausstellung' },
    { label: 'Projekte', value: 'projekt' },
    { label: 'Seiten', value: 'page' },
  ];
  const POST_TYPE_OPTIONS = (function () {
    var supported = Array.isArray(settings.supportedPostTypes) && settings.supportedPostTypes.length
      ? settings.supportedPostTypes.map(function (value) {
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
  const PANEL_MODE_OPTIONS = [
    { label: 'Mit Infopanel', value: 'show' },
    { label: 'Nur Karte', value: 'hide' },
  ];
  const PANEL_POSITION_OPTIONS = [
    { label: 'Panel rechts', value: 'right' },
    { label: 'Panel unter der Karte', value: 'below' },
  ];
  const BODY_MODE_OPTIONS = [
    { label: 'Texttafel', value: 'text' },
    { label: 'Bildtafel', value: 'image' },
  ];
  const BODY_POSITION_OPTIONS = [
    { label: 'Tafel rechts', value: 'end' },
    { label: 'Tafel links', value: 'start' },
  ];
  const FRAMING_MODE_OPTIONS = [
    { label: 'Bestehend', value: 'inherit' },
    { label: 'Preset / Viewport', value: 'preset' },
    { label: 'Auto-Fokus', value: 'auto' },
  ];
  const LAYOUT_MODE_OPTIONS = [
    { label: 'Horizontaler Streifen', value: 'band' },
    { label: 'Asymmetrisches Split-Feld', value: 'split' },
  ];
  const ATLAS_STRIP_VARIANT_OPTIONS = [
    { label: 'Ort-Streifen', value: 'place' },
    { label: 'Korridor-Streifen', value: 'corridor' },
    { label: 'Minimal-Streifen', value: 'minimal' },
    { label: 'Spine-Zeile', value: 'spine' },
  ];
  const ATLAS_STRIP_THEME_OPTIONS = [
    { label: 'Schwarz', value: 'black' },
    { label: 'Rot', value: 'red' },
    { label: 'Ocker', value: 'ochre' },
    { label: 'Blau', value: 'blue' },
    { label: 'Grün', value: 'green' },
    { label: 'Violett', value: 'violet' },
  ];
  const ATLAS_STRIP_LINE_MODE_OPTIONS = [
    { label: 'Route', value: 'route' },
    { label: 'Korridor', value: 'corridor' },
    { label: 'Keine Linie', value: 'none' },
  ];
  const ATLAS_STRIP_STATION_MODE_OPTIONS = [
    { label: 'Ausgewählte Orte', value: 'selected' },
    { label: 'Preset-Schiene', value: 'preset' },
  ];
  const SPINE_TEXT_MODE_OPTIONS = [
    { label: 'Normaler Introtext', value: 'text' },
    { label: 'Zitat', value: 'quote' },
  ];
  const DENSE_IMAGE_WALL_COLUMN_OPTIONS = [
    { label: '2 Spalten', value: '2' },
    { label: '3 Spalten', value: '3' },
    { label: '4 Spalten', value: '4' },
    { label: '5 Spalten', value: '5' },
    { label: '6 Spalten', value: '6' },
  ];
  const ASYMMETRIC_SPLIT_PRESET_OPTIONS = [
    { label: 'Karte - Bild - Text', value: 'map-image-text' },
    { label: 'Text - Karte - Bild', value: 'text-map-image' },
    { label: 'Karte - Text', value: 'map-text' },
  ];
  const ASYMMETRIC_SPLIT_HEIGHT_OPTIONS = [
    { label: 'Mittel', value: 'md' },
    { label: 'Groß', value: 'lg' },
    { label: 'Extra hoch', value: 'xl' },
  ];
  const RELATED_CARDS_LAYOUT_OPTIONS = [
    { label: 'Raster', value: 'grid' },
    { label: 'Strip', value: 'strip' },
    { label: 'Gestapelt', value: 'stack' },
    { label: 'Kompakt', value: 'compact' },
    { label: 'Rail', value: 'rail' },
  ];
  const RELATED_CARDS_SORT_OPTIONS = [
    { label: 'Automatisch', value: 'auto' },
    { label: 'Relevanz', value: 'relevance' },
    { label: 'Relevanz + Titel', value: 'relevance_title' },
    { label: 'Relevanz + Datum', value: 'relevance_date' },
  ];
  const RELATED_CARDS_COLUMN_OPTIONS = [
    { label: '1 Spalte', value: '1' },
    { label: '2 Spalten', value: '2' },
    { label: '3 Spalten', value: '3' },
    { label: '4 Spalten', value: '4' },
    { label: '5 Spalten', value: '5' },
    { label: '6 Spalten', value: '6' },
  ];
  const RELATED_CARDS_SKIN_OPTIONS = [
    { label: 'Global / Accent', value: 'default' },
    { label: 'Ausstellung / Braun', value: 'exhibition' },
  ];

  function stripTags(value) {
    return String(value || '').replace(/<[^>]*>/g, '').trim();
  }

  function getPostTypeLabel(value) {
    var match = POST_TYPE_OPTIONS.find(function (option) {
      return option.value === value;
    });

    return match ? match.label : String(value || '');
  }

  function getSkinLabel(value) {
    var match = RELATED_CARDS_SKIN_OPTIONS.find(function (option) {
      return option.value === value;
    });

    return match ? match.label : String(value || 'default');
  }

  function getSelectedRelatedPostTypes(attrs, config) {
    if (config.fixedPostType) {
      return [config.fixedPostType];
    }

    var values = Array.isArray(attrs.postTypes) ? attrs.postTypes : [];

    if (!values.length && attrs.postType) {
      values = [attrs.postType];
    }

    if (!values.length) {
      values = ['post'];
    }

    values = values
      .map(function (value) {
        return String(value || '');
      })
      .filter(function (value, index, list) {
        return value && list.indexOf(value) === index && POST_TYPE_OPTIONS.some(function (option) {
          return option.value === value;
        });
      });

    return values.length ? values : ['post'];
  }

  function setSelectedRelatedPostTypes(nextValues, attrs, setAttributes, config) {
    var values = Array.isArray(nextValues) ? nextValues : [];
    var normalized = values
      .map(function (value) {
        return String(value || '');
      })
      .filter(function (value, index, list) {
        return value && list.indexOf(value) === index && POST_TYPE_OPTIONS.some(function (option) {
          return option.value === value;
        });
      });
    var fallback = config.fixedPostType || attrs.postType || 'post';

    if (!normalized.length) {
      normalized = [fallback];
    }

    setAttributes({
      postTypes: normalized,
      postType: normalized[0],
    });
  }

  function shouldShowRelatedCardColumns(attrs) {
    return ['strip', 'rail'].indexOf(attrs.layoutVariant || 'grid') === -1;
  }

  function renderRelatedPostTypeControl(attrs, setAttributes, config) {
    if (config.showPostTypeField === false) {
      return null;
    }

    if (!CheckboxControl || !BaseControl) {
      return el(SelectControl, {
        label: 'Inhaltstyp',
        value: attrs.postType || config.fixedPostType || 'post',
        options: POST_TYPE_OPTIONS,
        onChange: function (value) {
          setSelectedRelatedPostTypes([value], attrs, setAttributes, config);
        },
      });
    }

    var selected = getSelectedRelatedPostTypes(attrs, config);

    return el(
      BaseControl,
      {
        label: 'Inhaltstypen',
        help: 'Mehrfachauswahl mischt mehrere Inhaltstypen in einem gemeinsamen Feed oder Strip.',
      },
      el(
        'div',
        {
          style: {
            display: 'grid',
            gap: '4px',
            marginTop: '8px',
          },
        },
        POST_TYPE_OPTIONS.map(function (option) {
          return el(CheckboxControl, {
            key: 'post-type-' + option.value,
            label: option.label,
            checked: selected.indexOf(option.value) !== -1,
            onChange: function (checked) {
              var next = checked
                ? selected.concat([option.value])
                : selected.filter(function (value) {
                    return value !== option.value;
                  });

              if (!next.length) {
                next = [option.value];
              }

              setSelectedRelatedPostTypes(next, attrs, setAttributes, config);
            },
          });
        })
      )
    );
  }

  function getRelatedCardsPreviewText(attrs, config) {
    var postTypes = getSelectedRelatedPostTypes(attrs, config);
    var perPage = attrs.perPage || 3;
    var source = attrs.source || 'current';
    var layout = attrs.layoutVariant || 'grid';
    var sortMode = attrs.sortMode || 'auto';
    var columns = attrs.columns || 3;
    var skin = attrs.skin || 'default';
    var parts = [
      String(perPage) + ' Einträge',
      'Typen: ' + postTypes.map(getPostTypeLabel).join(', '),
      'Quelle: ' + source,
      shouldShowRelatedCardColumns(attrs)
        ? 'Layout: ' + layout + ' / ' + String(columns) + ' Sp.'
        : 'Layout: ' + layout,
      'Sortierung: ' + sortMode
    ];

    if (skin !== 'default') {
      parts.push('Skin: ' + getSkinLabel(skin));
    }

    if (attrs.showImage === false) {
      parts.push('ohne Bild');
    }

    return parts.join(' · ');
  }

  function renderRelatedCardsPreviewItems(preview) {
    var items = preview && Array.isArray(preview.items) ? preview.items : [];
    var isStrip = preview && preview.layoutVariant === 'strip';
    var isSingleColumn = preview && (preview.layoutVariant === 'stack' || preview.layoutVariant === 'compact');

    if (!items.length) {
      return el('p', { className: 'components-base-control__help' }, 'Keine passenden Einträge für diese Auswahl gefunden.');
    }

    return el(
      'div',
      {
        style: {
          display: isStrip ? 'flex' : 'grid',
          gap: '12px',
          gridTemplateColumns: isStrip
            ? undefined
            : (isSingleColumn
              ? '1fr'
              : 'repeat(' + String(Math.max(1, Math.min(6, preview.columns || 3))) + ', minmax(0, 1fr))'),
          flexWrap: isStrip ? 'nowrap' : undefined,
          overflowX: isStrip ? 'auto' : 'visible',
          paddingBottom: isStrip ? '6px' : 0,
          width: '100%',
          marginTop: '12px',
        },
      },
      items.map(function (item) {
        return el(
          'article',
          {
            key: 'related-preview-' + String(item.id),
            style: {
              border: '1px solid rgba(30,30,30,0.14)',
              background: '#fff',
              flex: isStrip ? '0 0 min(19rem, 82vw)' : undefined,
              minWidth: 0,
              height: '100%',
            },
          },
          preview.showImage === false
            ? null
            : (item.thumbnail
              ? el('img', {
                  src: item.thumbnail,
                  alt: '',
                  style: {
                    display: 'block',
                    width: '100%',
                    height: preview.layoutVariant === 'compact' ? '5rem' : '7.5rem',
                    objectFit: 'cover',
                    borderBottom: '1px solid rgba(30,30,30,0.08)',
                  },
                })
              : el('div', {
                  style: {
                    display: 'block',
                    width: '100%',
                    height: preview.layoutVariant === 'compact' ? '5rem' : '7.5rem',
                    background: 'linear-gradient(180deg, rgba(30,30,30,0.05), rgba(255,255,255,0.95))',
                    borderBottom: '1px solid rgba(30,30,30,0.08)',
                  },
                })),
          el(
            'div',
            {
              style: {
                padding: '12px',
                display: 'grid',
                gap: '8px',
              },
            },
            item.kicker
              ? el(
                  'p',
                  {
                    style: {
                      margin: 0,
                      fontSize: '11px',
                      lineHeight: '1.4',
                      letterSpacing: '0.08em',
                      textTransform: 'uppercase',
                      color: 'rgba(30,30,30,0.65)',
                    },
                  },
                  item.kicker
                )
              : null,
            el(
              'h4',
              {
                style: {
                  margin: 0,
                  fontSize: '18px',
                  lineHeight: '1.25',
                },
              },
              item.title || 'Ohne Titel'
            ),
            item.detail
              ? el(
                  'p',
                  {
                    style: {
                      margin: 0,
                      fontSize: '12px',
                      lineHeight: '1.45',
                      fontWeight: '600',
                      color: 'rgba(30,30,30,0.72)',
                    },
                  },
                  item.detail
                )
              : null,
            item.meta
              ? el(
                  'p',
                  {
                    style: {
                      margin: 0,
                      fontSize: '11px',
                      lineHeight: '1.4',
                      fontWeight: '700',
                      color: 'rgba(30,30,30,0.62)',
                    },
                  },
                  item.meta
                )
              : null
          )
        );
      })
    );
  }

  function normalizeDenseImageWallItems(items) {
    if (!Array.isArray(items)) return [];

    var maxColSpan = arguments.length > 1 ? parseInt(arguments[1], 10) || 4 : 4;
    var maxRowSpan = arguments.length > 2 ? parseInt(arguments[2], 10) || 6 : 6;

    maxColSpan = Math.max(1, Math.min(6, maxColSpan));
    maxRowSpan = Math.max(1, Math.min(6, maxRowSpan));

    return items.map(function (item) {
      var source = item && typeof item === 'object' ? item : {};
      var span = source.span === 'wide' || source.span === 'tall' ? source.span : 'normal';
      var colSpan = parseInt(source.colSpan, 10);
      var rowSpan = parseInt(source.rowSpan, 10);

      if (!colSpan || colSpan < 1) {
        colSpan = span === 'wide' ? 2 : 1;
      }

      if (!rowSpan || rowSpan < 1) {
        rowSpan = span === 'tall' ? 2 : 1;
      }

      colSpan = Math.max(1, Math.min(maxColSpan, colSpan));
      rowSpan = Math.max(1, Math.min(maxRowSpan, rowSpan));

      return {
        id: source.id ? parseInt(source.id, 10) || 0 : 0,
        url: typeof source.url === 'string' ? source.url : '',
        alt: typeof source.alt === 'string' ? source.alt : '',
        kicker: typeof source.kicker === 'string' ? source.kicker : '',
        title: typeof source.title === 'string' ? source.title : '',
        text: typeof source.text === 'string' ? source.text : '',
        caption: typeof source.caption === 'string' ? source.caption : '',
        captionHtml: typeof source.captionHtml === 'string' ? source.captionHtml : '',
        className: typeof source.className === 'string' ? source.className : '',
        captionClassName: typeof source.captionClassName === 'string' ? source.captionClassName : '',
        linkUrl: typeof source.linkUrl === 'string' ? source.linkUrl : '',
        colSpan: colSpan,
        rowSpan: rowSpan,
      };
    });
  }

  function normalizeDenseImageWallColumns(value) {
    var columns = parseInt(value, 10);
    if (!columns || columns < 2) return 4;
    return Math.max(2, Math.min(6, columns));
  }

  function normalizeDenseImageWallRowHeight(value) {
    var rowHeight = parseInt(value, 10);
    if (!rowHeight || rowHeight < 80) return 118;
    return Math.max(80, Math.min(260, rowHeight));
  }

  function denseImageWallHasOverlay(item) {
    return !!(
      item &&
      (
        item.kicker ||
        item.title ||
        item.text ||
        item.caption ||
        item.captionHtml
      )
    );
  }

  function denseImageWallUsesStructuredCaption(item) {
    return !!(
      item &&
      (
        item.kicker ||
        item.title ||
        item.text ||
        item.captionHtml ||
        (item.captionClassName && item.captionClassName.trim())
      )
    );
  }

  function getDenseImageWallCellClass(item) {
    var options = arguments.length > 1 ? arguments[1] : {};
    var classes = ['wp-block-image', 'iss-dense-image-wall__cell'];

    if (item && item.colSpan >= 2) {
      classes.push('iss-dense-image-wall__cell--wide');
    }

    if (item && item.rowSpan >= 2) {
      classes.push('iss-dense-image-wall__cell--tall');
    }

    if (!options.legacy && denseImageWallHasOverlay(item)) {
      classes.push('iss-dense-image-wall__cell--captioned');
    }

    if (item && typeof item.className === 'string' && item.className.trim()) {
      classes.push(item.className.trim());
    }

    return classes.join(' ');
  }

  function getDenseImageWallCellStyle(item) {
    var style = {};
    var colSpan = item && item.colSpan ? parseInt(item.colSpan, 10) : 1;
    var rowSpan = item && item.rowSpan ? parseInt(item.rowSpan, 10) : 1;

    colSpan = Math.max(1, Math.min(6, colSpan || 1));
    rowSpan = Math.max(1, Math.min(6, rowSpan || 1));

    if (colSpan > 1) {
      style.gridColumn = 'span ' + String(colSpan);
    }

    if (rowSpan > 1) {
      style.gridRow = 'span ' + String(rowSpan);
    }

    return style;
  }

  function updateDenseImageWallItems(setAttributes, items) {
    var columns = arguments.length > 2 ? arguments[2] : 4;
    setAttributes({ items: normalizeDenseImageWallItems(items, columns, 6) });
  }

  function getDenseImageWallCaptionClass(item) {
    var classes = ['iss-dense-image-wall__caption'];

    if (item && typeof item.captionClassName === 'string' && item.captionClassName.trim()) {
      classes.push(item.captionClassName.trim());
    }

    return classes.join(' ');
  }

  function renderDenseImageWallCaptionNode(item) {
    var options = arguments.length > 1 ? arguments[1] : {};
    if (!item) return null;

    if (options.legacy && item.caption) {
      return el('figcaption', null, item.caption);
    }

    if (item.captionHtml) {
      return el('figcaption', {
        className: getDenseImageWallCaptionClass(item),
        dangerouslySetInnerHTML: { __html: item.captionHtml },
      });
    }

    if (item.kicker || item.title || item.text) {
      return el(
        'figcaption',
        { className: getDenseImageWallCaptionClass(item) },
        item.kicker ? el('p', { className: 'iss-dense-image-wall__kicker' }, item.kicker) : null,
        item.title ? el('p', { className: 'iss-dense-image-wall__title' }, item.title) : null,
        item.text ? el('p', { className: 'iss-dense-image-wall__text' }, item.text) : null
      );
    }

    if (item.caption) {
      return el('figcaption', { className: getDenseImageWallCaptionClass(item) }, item.caption);
    }

    return null;
  }

  function renderDenseImageWallEditor(props) {
    var attrs = props.attributes || {};
    var setAttributes = props.setAttributes || function () {};
    var columns = normalizeDenseImageWallColumns(attrs.columns);
    var rowHeight = normalizeDenseImageWallRowHeight(attrs.rowHeight);
    var items = normalizeDenseImageWallItems(attrs.items, columns, 6);
    var controls = null;
    var activeState = useState ? useState(items.length ? 0 : -1) : [items.length ? 0 : -1, function () {}];
    var activeIndex = activeState[0];
    var setActiveIndex = activeState[1];
    var currentIndex = items.length ? Math.max(0, Math.min(activeIndex, items.length - 1)) : -1;
    var currentItem = currentIndex >= 0 ? items[currentIndex] : null;

    function getItemLabel(item, index) {
      var sizeLabel = String(item.colSpan || 1) + 'x' + String(item.rowSpan || 1);
      var text = item.caption || item.alt || '';
      if (text.length > 42) {
        text = text.slice(0, 39) + '...';
      }

      return String(index + 1) + '. Bild - ' + sizeLabel + (text ? ' - ' + text : '');
    }

    function patchItem(index, patch) {
      var nextItems = items.slice();
      nextItems[index] = Object.assign({}, nextItems[index] || {}, patch || {});
      updateDenseImageWallItems(setAttributes, nextItems, columns);
    }

    function removeItem(index) {
      var nextLength = Math.max(0, items.length - 1);
      updateDenseImageWallItems(
        setAttributes,
        items.filter(function (_item, position) {
          return position !== index;
        }),
        columns
      );
      setActiveIndex(nextLength ? Math.max(0, Math.min(index, nextLength - 1)) : -1);
    }

    function moveItem(index, direction) {
      var target = index + direction;
      if (target < 0 || target >= items.length) return;

      var nextItems = items.slice();
      var current = nextItems[target];
      nextItems[target] = nextItems[index];
      nextItems[index] = current;
      updateDenseImageWallItems(setAttributes, nextItems, columns);
      setActiveIndex(target);
    }

    function addItem() {
      updateDenseImageWallItems(
        setAttributes,
        items.concat([
          {
            id: 0,
            url: '',
            alt: '',
            kicker: '',
            title: '',
            text: '',
            caption: '',
            linkUrl: '',
            colSpan: 1,
            rowSpan: 1,
          },
        ])
        ,
        columns
      );
      setActiveIndex(items.length);
    }

    if (InspectorControls && PanelBody) {
      controls = el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Dense Image Wall', initialOpen: true },
          SelectControl
            ? el(SelectControl, {
                label: 'Raster',
                value: String(columns),
                options: DENSE_IMAGE_WALL_COLUMN_OPTIONS,
                onChange: function (value) {
                  var nextColumns = normalizeDenseImageWallColumns(value);
                  setAttributes({
                    columns: nextColumns,
                    items: normalizeDenseImageWallItems(items, nextColumns, 6),
                  });
                },
              })
            : null,
          RangeControl
            ? el(RangeControl, {
                label: 'Grundhöhe je Reihe',
                min: 80,
                max: 260,
                step: 10,
                value: rowHeight,
                help: 'Steuert die Grundhöhe der Wand. Größere Werte ergeben eine höhere Gesamtfläche.',
                onChange: function (value) {
                  setAttributes({ rowHeight: normalizeDenseImageWallRowHeight(value) });
                },
              })
            : null,
          el(
            'p',
            { className: 'components-base-control__help' },
            'Bildwand mit steuerbarem Raster. Reihenfolge sowie Spalten- und Reihenspannen werden strukturiert gepflegt, nicht über freie Klassen.'
          ),
          el(
            'p',
            { className: 'components-base-control__help' },
            'Asymmetrie entsteht aus Reihenfolge, Breite, Höhe und dem gewählten Grundraster.'
          )
        )
      );
    }

    return el(
      Fragment,
      null,
      controls,
      el(
        'div',
        { className: 'components-placeholder wp-block-iss-dense-image-wall-editor' },
        el('strong', null, 'Dense Image Wall'),
        el(
          'p',
          null,
          'Pflegt Bilder, Reihenfolge und Orientierung strukturiert. Die Frontend-Gestalt bleibt CSS-gesteuert.'
        ),
        el(
          'div',
          {
            style: {
              display: 'grid',
              gap: '0.85rem',
              width: '100%',
              gridTemplateColumns: 'repeat(' + String(columns) + ', minmax(0, 1fr))',
              gridAutoRows: String(rowHeight) + 'px',
            },
          },
          items.length
            ? items.map(function (item, index) {
                return el(
                  'div',
                  {
                    key: 'dense-image-wall-item-' + String(index),
                    style: {
                      display: 'grid',
                      gridTemplateColumns: '3.5rem minmax(0, 1fr) auto',
                      alignItems: 'center',
                      gap: '0.75rem',
                      padding: '0.55rem 0.65rem',
                      border: index === currentIndex ? '1px solid #1e1e1e' : '1px solid rgba(30, 30, 30, 0.14)',
                      background: index === currentIndex ? '#fff' : 'rgba(255,255,255,0.72)',
                      cursor: 'pointer',
                    },
                    onClick: function () {
                      setActiveIndex(index);
                    },
                  },
                  item.url
                    ? el('img', {
                        src: item.url,
                        alt: item.alt || '',
                        style: {
                          display: 'block',
                          width: '3.5rem',
                          height: '3.5rem',
                          objectFit: 'cover',
                          borderRadius: '4px',
                        },
                      })
                    : el(
                        'div',
                        {
                          style: {
                            display: 'grid',
                            placeItems: 'center',
                            width: '3.5rem',
                            height: '3.5rem',
                            borderRadius: '4px',
                            background: '#ddd2c2',
                            color: '#666',
                            fontSize: '10px',
                            textAlign: 'center',
                            lineHeight: '1.2',
                            padding: '0.25rem',
                          },
                        },
                        'Kein Bild'
                      ),
                  el(
                    'div',
                    {
                      style: {
                        minWidth: 0,
                      },
                    },
                    el(
                      'div',
                      {
                        style: {
                          fontWeight: '600',
                          fontSize: '12px',
                          lineHeight: '1.35',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        },
                      },
                      getItemLabel(item, index)
                    ),
                    el(
                      'div',
                      {
                        style: {
                          fontSize: '11px',
                          color: '#666',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        },
                      },
                      item.url ? (item.linkUrl ? 'Mit Link' : 'Ohne Link') : 'Bild fehlt'
                    )
                  ),
                  Button
                    ? el(
                      'div',
                      {
                        style: {
                          display: 'flex',
                          gap: '0.35rem',
                          flexWrap: 'wrap',
                          justifyContent: 'flex-end',
                        },
                        onClick: function (event) {
                          event.stopPropagation();
                        },
                      },
                      el(
                        Button,
                          {
                            variant: 'secondary',
                            disabled: index === 0,
                            onClick: function () {
                              moveItem(index, -1);
                            },
                          },
                          'Nach oben'
                        ),
                        el(
                          Button,
                          {
                            variant: 'secondary',
                            disabled: index >= items.length - 1,
                            onClick: function () {
                              moveItem(index, 1);
                            },
                          },
                          'Nach unten'
                        ),
                        el(
                          Button,
                          {
                            variant: 'tertiary',
                            onClick: function () {
                              removeItem(index);
                            },
                          },
                          'Entfernen'
                        )
                      )
                    : null
                );
              })
            : el(
                'p',
                { className: 'components-base-control__help' },
                'Noch keine Bilder hinzugefügt.'
              )
        ),
        currentItem
          ? el(
              'div',
              {
                style: {
                  width: '100%',
                  marginTop: '0.9rem',
                  padding: '0.9rem',
                  border: '1px solid rgba(30, 30, 30, 0.14)',
                  background: '#fff',
                },
              },
              el(
                'div',
                {
                  style: {
                    display: 'grid',
                    gridTemplateColumns: '5rem minmax(0, 1fr)',
                    gap: '0.9rem',
                    alignItems: 'start',
                    marginBottom: '0.9rem',
                  },
                },
                currentItem.url
                  ? el('img', {
                      src: currentItem.url,
                      alt: currentItem.alt || '',
                      style: {
                        display: 'block',
                        width: '5rem',
                        height: '5rem',
                        objectFit: 'cover',
                        borderRadius: '4px',
                      },
                    })
                  : el(
                      'div',
                      {
                        style: {
                          display: 'grid',
                          placeItems: 'center',
                          width: '5rem',
                          height: '5rem',
                          background: '#ddd2c2',
                          color: '#666',
                          fontSize: '11px',
                          textAlign: 'center',
                          padding: '0.25rem',
                        },
                      },
                      'Kein Bild'
                    ),
                el(
                  'div',
                  null,
                  el(
                    'strong',
                    null,
                    'Eintrag ' + String(currentIndex + 1)
                  ),
                  el(
                    'p',
                    {
                      style: {
                        margin: '0.25rem 0 0',
                        fontSize: '12px',
                        color: '#666',
                      },
                    },
                    'Breite ' + String(currentItem.colSpan || 1) + ' | Höhe ' + String(currentItem.rowSpan || 1)
                  )
                )
              ),
              TextControl
                ? el(TextControl, {
                    label: 'Kicker',
                    value: currentItem.kicker || '',
                    onChange: function (value) {
                      patchItem(currentIndex, { kicker: value });
                    },
                  })
                : null,
              TextControl
                ? el(TextControl, {
                    label: 'Titel',
                    value: currentItem.title || '',
                    onChange: function (value) {
                      patchItem(currentIndex, { title: value });
                    },
                  })
                : null,
              TextareaControl
                ? el(TextareaControl, {
                    label: 'Text',
                    value: currentItem.text || '',
                    onChange: function (value) {
                      patchItem(currentIndex, { text: value });
                    },
                  })
                : null,
              TextControl
                ? el(TextControl, {
                    label: 'Link-URL',
                    value: currentItem.linkUrl || '',
                    onChange: function (value) {
                      patchItem(currentIndex, { linkUrl: value });
                    },
                  })
                : null,
              SelectControl
                ? el(SelectControl, {
                    label: 'Breite',
                    value: String(currentItem.colSpan || 1),
                    options: Array.from({ length: columns }, function (_entry, position) {
                      var span = position + 1;
                      return {
                        label: span === 1 ? '1 Spalte' : String(span) + ' Spalten',
                        value: String(span),
                      };
                    }),
                    onChange: function (value) {
                      patchItem(currentIndex, { colSpan: parseInt(value, 10) || 1 });
                    },
                  })
                : null,
              SelectControl
                ? el(SelectControl, {
                    label: 'Höhe',
                    value: String(currentItem.rowSpan || 1),
                    options: Array.from({ length: 6 }, function (_entry, position) {
                      var span = position + 1;
                      return {
                        label: span === 1 ? '1 Reihe' : String(span) + ' Reihen',
                        value: String(span),
                      };
                    }),
                    onChange: function (value) {
                      patchItem(currentIndex, { rowSpan: parseInt(value, 10) || 1 });
                    },
                  })
                : null,
              MediaUpload && MediaUploadCheck && Button
                ? el(
                    MediaUploadCheck,
                    null,
                    el(MediaUpload, {
                      onSelect: function (media) {
                        var mediaTitle = '';
                        var mediaCaption = '';

                        if (media && media.title) {
                          if (typeof media.title === 'string') {
                            mediaTitle = media.title;
                          } else if (typeof media.title.raw === 'string') {
                            mediaTitle = media.title.raw;
                          } else if (typeof media.title.rendered === 'string') {
                            mediaTitle = media.title.rendered;
                          }
                        }

                        if (media && media.caption) {
                          if (typeof media.caption === 'string') {
                            mediaCaption = media.caption;
                          } else if (typeof media.caption.raw === 'string') {
                            mediaCaption = media.caption.raw;
                          } else if (typeof media.caption.rendered === 'string') {
                            mediaCaption = media.caption.rendered;
                          }
                        }

                        if (!mediaCaption && media && media.description) {
                          if (typeof media.description === 'string') {
                            mediaCaption = media.description;
                          } else if (typeof media.description.raw === 'string') {
                            mediaCaption = media.description.raw;
                          } else if (typeof media.description.rendered === 'string') {
                            mediaCaption = media.description.rendered;
                          }
                        }

                        patchItem(currentIndex, {
                          id: media && media.id ? media.id : 0,
                          url: media && media.url ? media.url : '',
                          alt: currentItem.alt || (media && media.alt ? media.alt : ''),
                          title: currentItem.title || stripTags(mediaTitle),
                          text: currentItem.text || stripTags(mediaCaption),
                        });
                      },
                      allowedTypes: ['image'],
                      value: currentItem.id || 0,
                      render: function (renderProps) {
                        return el(
                          Button,
                          {
                            variant: 'secondary',
                            onClick: renderProps.open,
                          },
                          currentItem.url ? 'Bild ersetzen' : 'Bild wählen'
                        );
                      },
                    })
                  )
                : null
            )
          : null,
        Button
          ? el(
              Button,
              {
                variant: 'primary',
                onClick: addItem,
              },
              'Bild hinzufügen'
            )
          : null
      )
    );
  }

  function saveDenseImageWall(props) {
    var attrs = props.attributes || {};
    var columns = normalizeDenseImageWallColumns(attrs.columns);
    var rowHeight = normalizeDenseImageWallRowHeight(attrs.rowHeight);
    var items = normalizeDenseImageWallItems(attrs.items, columns, 6).filter(function (item) {
      return item.url;
    });

    return el(
      'div',
      (columns === 4 && rowHeight === 118)
        ? { className: 'wp-block-group iss-dense-image-wall' }
        : {
            className: 'wp-block-group iss-dense-image-wall',
            style: {
              '--iss-dense-image-wall-columns': String(columns),
              '--iss-dense-image-wall-row-size': String(rowHeight) + 'px',
            },
          },
      items.map(function (item, index) {
        var legacyCaption = !denseImageWallUsesStructuredCaption(item) && !!item.caption;
        var imageNode = el('img', {
          src: item.url,
          alt: item.alt || '',
        });
        var mediaNode = item.linkUrl
          ? el(
              'a',
              {
                href: item.linkUrl,
              },
              imageNode
            )
          : imageNode;

        return el(
          'figure',
          {
            key: 'dense-image-wall-save-' + String(index),
            className: getDenseImageWallCellClass(item, { legacy: legacyCaption }),
            style: getDenseImageWallCellStyle(item),
          },
          mediaNode,
          renderDenseImageWallCaptionNode(item, { legacy: legacyCaption })
        );
      })
    );
  }

  function renderAsymmetricSplitFieldEditor(props) {
    var attrs = props.attributes || {};
    var setAttributes = props.setAttributes || function () {};
    var selectedIds = parsePlaceIds(attrs.placeIds || '');
    var placeRecords = useSelect
      ? useSelect(function (select) {
          return select('core').getEntityRecords('postType', PLACE_POST_TYPE, {
            per_page: 100,
            orderby: 'title',
            order: 'asc',
            status: 'publish',
          });
        }, [])
      : [];
    var placePosts = Array.isArray(placeRecords) ? placeRecords : [];
    var placeOptions = buildPlaceOptions(placePosts, selectedIds);
    var controls = null;
    var layoutPreset = attrs.layoutPreset || 'map-image-text';
    var mapEnabled = attrs.mapEnabled !== false;
    var imageEnabled = !!attrs.imageEnabled;
    var textEnabled = attrs.textEnabled !== false;

    if (InspectorControls && PanelBody) {
      controls = el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Asymmetric Split Field', initialOpen: true },
          SelectControl
            ? el(SelectControl, {
                label: 'Layout',
                value: layoutPreset,
                options: ASYMMETRIC_SPLIT_PRESET_OPTIONS,
                onChange: function (value) {
                  setAttributes({ layoutPreset: value || 'map-image-text' });
                },
              })
            : null,
          SelectControl
            ? el(SelectControl, {
                label: 'Höhe',
                value: attrs.heightMode || 'lg',
                options: ASYMMETRIC_SPLIT_HEIGHT_OPTIONS,
                onChange: function (value) {
                  setAttributes({ heightMode: value || 'lg' });
                },
              })
            : null,
          ToggleControl
            ? el(ToggleControl, {
                label: 'Kartenzone',
                checked: mapEnabled,
                onChange: function (value) {
                  setAttributes({ mapEnabled: !!value });
                },
              })
            : null,
          mapEnabled && SelectControl
            ? el(SelectControl, {
                label: 'Ortsquelle',
                value: attrs.source || 'current',
                options: SOURCE_OPTIONS,
                onChange: function (value) {
                  setAttributes({ source: value || 'current' });
                },
              })
            : null,
          mapEnabled ? renderManualPlaceControl(attrs, setAttributes, placeOptions, selectedIds) : null,
          mapEnabled && RangeControl
            ? el(RangeControl, {
                label: 'Anzahl Orte',
                min: 1,
                max: 12,
                value: attrs.perPage || 3,
                onChange: function (value) {
                  setAttributes({ perPage: value || 3 });
                },
              })
            : null,
          ToggleControl && layoutPreset !== 'map-text'
            ? el(ToggleControl, {
                label: 'Bildzone',
                checked: imageEnabled,
                onChange: function (value) {
                  setAttributes({ imageEnabled: !!value });
                },
              })
            : null,
          imageEnabled && TextControl
            ? el(TextControl, {
                label: 'Bild-URL',
                value: attrs.imageUrl || '',
                onChange: function (value) {
                  setAttributes({ imageUrl: value });
                },
              })
            : null,
          imageEnabled && TextControl
            ? el(TextControl, {
                label: 'Bild-Alt',
                value: attrs.imageAlt || '',
                onChange: function (value) {
                  setAttributes({ imageAlt: value });
                },
              })
            : null,
          imageEnabled && TextareaControl
            ? el(TextareaControl, {
                label: 'Bildunterschrift',
                value: attrs.imageCaption || '',
                onChange: function (value) {
                  setAttributes({ imageCaption: value });
                },
              })
            : null,
          ToggleControl
            ? el(ToggleControl, {
                label: 'Textzone',
                checked: textEnabled,
                onChange: function (value) {
                  setAttributes({ textEnabled: !!value });
                },
              })
            : null,
          textEnabled && TextControl
            ? el(TextControl, {
                label: 'Kicker',
                value: attrs.textKicker || '',
                onChange: function (value) {
                  setAttributes({ textKicker: value });
                },
              })
            : null,
          textEnabled && TextControl
            ? el(TextControl, {
                label: 'Titel',
                value: attrs.textTitle || '',
                onChange: function (value) {
                  setAttributes({ textTitle: value });
                },
              })
            : null,
          textEnabled && TextareaControl
            ? el(TextareaControl, {
                label: 'Text',
                value: attrs.textBody || '',
                onChange: function (value) {
                  setAttributes({ textBody: value });
                },
              })
            : null,
          textEnabled && TextControl
            ? el(TextControl, {
                label: 'Link-URL',
                value: attrs.textLinkUrl || '',
                onChange: function (value) {
                  setAttributes({ textLinkUrl: value });
                },
              })
            : null,
          textEnabled && TextControl
            ? el(TextControl, {
                label: 'Link-Label',
                value: attrs.textLinkLabel || '',
                onChange: function (value) {
                  setAttributes({ textLinkLabel: value });
                },
              })
            : null
        )
      );
    }

    return el(
      'div',
      null,
      controls,
      el(
        'div',
        { className: 'components-placeholder wp-block-iss-asymmetric-split-field-editor' },
        el('strong', null, 'Asymmetric Split Field'),
        el(
          'p',
          null,
          'Editoriales Feld mit asymmetrischer Ordnung. Die Kartenzone nutzt die kanonische statische Karte; Bild- und Textzone bleiben optional.'
        ),
        el(
          'p',
          { className: 'components-base-control__help' },
          'Aktives Layout: ' + layoutPreset + ' | Höhe: ' + (attrs.heightMode || 'lg')
        ),
        el(
          'p',
          { className: 'components-base-control__help' },
          'Zonen: '
            + (mapEnabled ? 'Karte ' : '')
            + (imageEnabled ? 'Bild ' : '')
            + (textEnabled ? 'Text' : '')
        )
      )
    );
  }

  function parsePlaceIds(value) {
    if (typeof value !== 'string') return [];

    const seen = {};
    return value
      .split(/[\s,]+/)
      .map(function (part) {
        return parseInt(part, 10);
      })
      .filter(function (id) {
        if (!id || seen[id]) return false;
        seen[id] = true;
        return true;
      });
  }

  function getPlaceLabel(post) {
    const title = stripTags(post && post.title && post.title.rendered ? post.title.rendered : '');
    return title !== '' ? title + ' (#' + post.id + ')' : '#' + post.id;
  }

  function stringifyPlaceIds(ids) {
    return ids.join(',');
  }

  function buildPlaceOptions(placePosts, selectedIds) {
    var seen = {};
    var options = [];

    (Array.isArray(placePosts) ? placePosts : []).forEach(function (post) {
      var value = String(post.id || '');
      if (!value || seen[value]) return;

      seen[value] = true;
      options.push({
        label: getPlaceLabel(post),
        value: value,
      });
    });

    selectedIds.forEach(function (id) {
      var value = String(id);
      if (!value || seen[value]) return;

      seen[value] = true;
      options.push({
        label: '#' + value + ' (nicht gefunden)',
        value: value,
      });
    });

    return options;
  }

  function getAddPlaceOptions(placeOptions, selectedIds) {
    var selectedLookup = {};

    selectedIds.forEach(function (id) {
      selectedLookup[String(id)] = true;
    });

    return [{ label: 'Ort auswählen', value: '' }].concat(
      placeOptions.filter(function (option) {
        return !selectedLookup[String(option.value || '')];
      })
    );
  }

  function getRowPlaceOptions(placeOptions, selectedIds, currentId) {
    var selectedLookup = {};

    selectedIds.forEach(function (id) {
      if (id !== currentId) {
        selectedLookup[String(id)] = true;
      }
    });

    return placeOptions.filter(function (option) {
      var value = String(option.value || '');
      return value === String(currentId) || !selectedLookup[value];
    });
  }

  function updateSelectedPlaceIds(setAttributes, selectedIds) {
    setAttributes({ placeIds: stringifyPlaceIds(selectedIds) });
  }

  function renderManualPlaceRow(placeOptions, selectedIds, setAttributes, placeId, index) {
    if (!SelectControl) {
      return null;
    }

    return el(
      'div',
      { key: String(placeId) + '-' + index },
      el(SelectControl, {
        label: 'Ort ' + String(index + 1),
        value: String(placeId),
        options: getRowPlaceOptions(placeOptions, selectedIds, placeId),
        onChange: function (value) {
          var nextId = parseInt(value, 10);
          if (!nextId) return;

          var nextIds = selectedIds.slice();
          nextIds[index] = nextId;
          nextIds = nextIds.filter(function (id, position) {
            return nextIds.indexOf(id) === position;
          });
          updateSelectedPlaceIds(setAttributes, nextIds);
        },
      }),
      Button
        ? el(
            'div',
            null,
            el(
              Button,
              {
                variant: 'secondary',
                disabled: index === 0,
                onClick: function () {
                  if (index === 0) return;

                  var nextIds = selectedIds.slice();
                  var current = nextIds[index - 1];
                  nextIds[index - 1] = nextIds[index];
                  nextIds[index] = current;
                  updateSelectedPlaceIds(setAttributes, nextIds);
                },
              },
              'Weiter nach oben'
            ),
            ' ',
            el(
              Button,
              {
                variant: 'secondary',
                disabled: index >= selectedIds.length - 1,
                onClick: function () {
                  if (index >= selectedIds.length - 1) return;

                  var nextIds = selectedIds.slice();
                  var current = nextIds[index + 1];
                  nextIds[index + 1] = nextIds[index];
                  nextIds[index] = current;
                  updateSelectedPlaceIds(setAttributes, nextIds);
                },
              },
              'Weiter nach unten'
            ),
            ' ',
            el(
              Button,
              {
                variant: 'tertiary',
                onClick: function () {
                  updateSelectedPlaceIds(
                    setAttributes,
                    selectedIds.filter(function (id, position) {
                      return position !== index;
                    })
                  );
                },
              },
              'Entfernen'
            )
          )
        : null
    );
  }

  function renderManualPlaceControl(attrs, setAttributes, placeOptions, selectedIds) {
    if (attrs.source !== 'manual') return null;

    if (!SelectControl) {
      return TextControl
        ? el(TextControl, {
            label: 'Orts-IDs',
            help: 'Kommagetrennte register_place IDs, z.B. 12921,12865',
            value: attrs.placeIds || '',
            onChange: function (value) {
              setAttributes({ placeIds: value });
            },
          })
        : null;
    }

    var addOptions = getAddPlaceOptions(placeOptions, selectedIds);
    var addControlOptions = [];

    if (!placeOptions.length) {
      addControlOptions = [{ label: 'Keine veröffentlichten Orte verfügbar', value: '' }];
    } else if (addOptions.length > 1) {
      addControlOptions = addOptions;
    } else {
      addControlOptions = [{ label: 'Alle veröffentlichten Orte sind bereits ausgewählt', value: '' }];
    }

    return el(
      'div',
      null,
      el(
        'p',
        { className: 'components-base-control__help' },
        'Wählt veröffentlichte Orte aus. Die Reihenfolge bleibt erhalten und steuert beim Kartenblock Marker und Liste.'
      ),
      selectedIds.map(function (placeId, index) {
        return renderManualPlaceRow(placeOptions, selectedIds, setAttributes, placeId, index);
      }),
      el(SelectControl, {
        label: 'Ort hinzufügen',
        value: '',
        options: addControlOptions,
        disabled: addOptions.length <= 1,
        onChange: function (value) {
          var nextId = parseInt(value, 10);
          if (!nextId) return;

          updateSelectedPlaceIds(setAttributes, selectedIds.concat([nextId]));
        },
      }),
      !selectedIds.length
        ? el(
            'p',
            { className: 'components-base-control__help' },
            'Noch keine Orte ausgewählt.'
          )
        : null
    );
  }

  function getMapPresetOptions(config) {
    var presets = Array.isArray(settings.mapPresets) && settings.mapPresets.length
      ? settings.mapPresets
      : [{ label: 'Atlas Übersicht', value: 'default' }];

    if (config.showSpineStripFields) {
      var railPresets = presets.filter(function (option) {
        return !!option.hasRail;
      });

      if (railPresets.length) {
        return railPresets;
      }
    }

    return presets;
  }

  function registerRelatedBlock(name, config) {
    var blockSettings = {
      edit: function (props) {
        var attrs = props.attributes || {};
        var setAttributes = props.setAttributes || function () {};
        var controls = null;
        var isPreviewBlock = !!config.showCardLayoutFields;
        var previewState = useState ? useState({ status: 'idle', payload: null, message: '' }) : [{ status: 'idle', payload: null, message: '' }, function () {}];
        var preview = previewState[0];
        var setPreview = previewState[1];
        var selectedIds = parsePlaceIds(attrs.placeIds || '');
        var currentPostId = useSelect
          ? useSelect(function (select) {
              var editorStore = select('core/editor');
              return editorStore && editorStore.getCurrentPostId ? editorStore.getCurrentPostId() : 0;
            }, [])
          : 0;
        var placeRecords = useSelect
          ? useSelect(function (select) {
              return select('core').getEntityRecords('postType', PLACE_POST_TYPE, {
                per_page: 100,
                orderby: 'title',
                order: 'asc',
                status: 'publish',
              });
            }, [])
          : [];
        var placePosts = Array.isArray(placeRecords) ? placeRecords : [];
        var placeOptions = buildPlaceOptions(placePosts, selectedIds);
        var showPanelFields = config.showMapFields && !config.showAtlasSliceFields && !config.showAtlasStripFields;

        if (isPreviewBlock && useEffect && apiFetch) {
          useEffect(function () {
            var isMounted = true;

            setPreview({ status: 'loading', payload: null, message: '' });

            apiFetch({
              path: '/iss-relations/v1/related-preview',
              method: 'POST',
              data: {
                attributes: attrs,
                postId: currentPostId || 0,
              },
            })
              .then(function (payload) {
                if (!isMounted) return;
                setPreview({ status: 'ready', payload: payload || null, message: '' });
              })
              .catch(function () {
                if (!isMounted) return;
                setPreview({
                  status: 'error',
                  payload: null,
                  message: 'Vorschau konnte nicht geladen werden. Konfiguration bleibt bearbeitbar.',
                });
              });

            return function () {
              isMounted = false;
            };
          }, [JSON.stringify({
            postTypes: getSelectedRelatedPostTypes(attrs, config),
            perPage: attrs.perPage || 3,
            source: attrs.source || 'current',
            placeIds: attrs.placeIds || '',
            layoutVariant: attrs.layoutVariant || 'grid',
            sortMode: attrs.sortMode || 'auto',
            columns: attrs.columns || 3,
            skin: attrs.skin || 'default',
            showImage: attrs.showImage !== false,
            currentPostId: currentPostId || 0,
          })]);
        }

        if (InspectorControls && PanelBody && SelectControl && RangeControl) {
          controls = el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: config.panelTitle, initialOpen: true },
              renderRelatedPostTypeControl(attrs, setAttributes, config),
              el(SelectControl, {
                label: 'Ortsquelle',
                value: attrs.source || 'current',
                options: SOURCE_OPTIONS,
                onChange: function (value) {
                  setAttributes({ source: value });
                },
              }),
              renderManualPlaceControl(attrs, setAttributes, placeOptions, selectedIds),
              config.showHeadingFields && TextControl
                ? el(TextControl, {
                    label: 'Kicker',
                    value: attrs.kicker || '',
                    onChange: function (value) {
                      setAttributes({ kicker: value });
                    },
                  })
                : null,
              config.showHeadingFields && TextControl
                ? el(TextControl, {
                    label: 'Titel',
                    value: attrs.title || '',
                    onChange: function (value) {
                      setAttributes({ title: value });
                    },
                  })
                : null,
              config.showCardLayoutFields
                ? el(SelectControl, {
                    label: 'Kartenlayout',
                    value: attrs.layoutVariant || 'grid',
                    options: RELATED_CARDS_LAYOUT_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ layoutVariant: value || 'grid' });
                    },
                  })
                : null,
              config.showCardLayoutFields
                ? el(SelectControl, {
                    label: 'Sortierung',
                    value: attrs.sortMode || 'auto',
                    options: RELATED_CARDS_SORT_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ sortMode: value || 'auto' });
                    },
                  })
                : null,
              config.showCardLayoutFields && shouldShowRelatedCardColumns(attrs)
                ? el(SelectControl, {
                    label: 'Spalten',
                    value: String(attrs.columns || 3),
                    options: RELATED_CARDS_COLUMN_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ columns: parseInt(value, 10) || 3 });
                    },
                  })
                : null,
              config.showCardLayoutFields && ToggleControl
                ? el(ToggleControl, {
                    label: 'Bild zeigen',
                    checked: attrs.showImage !== false,
                    onChange: function (value) {
                      setAttributes({ showImage: !!value });
                    },
                  })
                : null,
              config.showCardLayoutFields
                ? el(SelectControl, {
                    label: 'Skin',
                    value: attrs.skin || 'default',
                    options: RELATED_CARDS_SKIN_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ skin: value || 'default' });
                    },
                  })
                : null,
              ((config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields) && SelectControl
                ? el(SelectControl, {
                    label: 'Introtyp',
                    value: attrs.textMode || 'text',
                    options: SPINE_TEXT_MODE_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ textMode: value || 'text' });
                    },
                  })
                : null,
              config.showIntroTextField && TextareaControl
                && !(config.showAtlasSliceFields && (attrs.bodyMode || 'text') === 'image')
                && !(config.showAtlasStripFields && (attrs.variant || 'place') === 'minimal')
                ? el(TextareaControl, {
                    label: (((config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields) && (attrs.textMode || 'text') === 'quote') ? 'Zitat' : 'Introtext',
                    value: attrs.text || '',
                    help: (((config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields) && (attrs.textMode || 'text') === 'quote') ? 'Nur den Zitattext eintragen. Quelle/Zuschreibung kommt ins nächste Feld.' : 'Leer lassen, wenn die Karte ohne Einleitung gerendert werden soll.',
                    onChange: function (value) {
                      setAttributes({ text: value });
                    },
                  })
                : null,
              ((config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields) && (attrs.textMode || 'text') === 'quote' && TextareaControl
                ? el(TextareaControl, {
                    label: 'Quelle / Zuschreibung',
                    value: attrs.textAttribution || '',
                    help: 'Optional. Wird als Zitatquelle unter dem Text gerendert.',
                    onChange: function (value) {
                      setAttributes({ textAttribution: value });
                    },
                  })
                : null,
              config.showMapFields && config.showPresetField !== false
                ? el(SelectControl, {
                    label: 'Kartenausschnitt',
                    value: attrs.mapPreset || 'default',
                    options: getMapPresetOptions(config),
                    onChange: function (value) {
                      setAttributes({ mapPreset: value || 'default' });
                    },
                  })
                : null,
              (config.showAtlasSliceFields || config.showAtlasStripFields)
                ? el(SelectControl, {
                    label: 'Framing',
                    value: attrs.framingMode || 'inherit',
                    options: FRAMING_MODE_OPTIONS,
                    help: 'Preset nutzt den hinterlegten Viewport. Auto-Fokus croppt auf Basis der gewählten Orte.',
                    onChange: function (value) {
                      setAttributes({ framingMode: value || 'inherit' });
                    },
                  })
                : null,
              config.showMapFields && RangeControl
                ? el(RangeControl, {
                    label: 'Rotation',
                    value: Number(attrs.rotationDeg || 0),
                    min: -180,
                    max: 180,
                    step: 1,
                    help: 'Für eine horizontale Spree sind auch freie Winkel wie -30° möglich. Marker und Karte rotieren gemeinsam.',
                    onChange: function (value) {
                      setAttributes({ rotationDeg: Number(value || 0) });
                    },
                  })
                : null,
              config.showMapFields && RangeControl
                ? el(RangeControl, {
                    label: 'Bias X',
                    value: Number(attrs.biasX || 0),
                    min: -25,
                    max: 25,
                    step: 0.5,
                    help: 'Verschiebt die Kartenebene horizontal innerhalb des Ausschnitts.',
                    onChange: function (value) {
                      setAttributes({ biasX: Number(value || 0) });
                    },
                  })
                : null,
              config.showMapFields && RangeControl
                ? el(RangeControl, {
                    label: 'Bias Y',
                    value: Number(attrs.biasY || 0),
                    min: -25,
                    max: 25,
                    step: 0.5,
                    help: 'Verschiebt die Kartenebene vertikal innerhalb des Ausschnitts.',
                    onChange: function (value) {
                      setAttributes({ biasY: Number(value || 0) });
                    },
                  })
                : null,
              config.showMapFields && RangeControl
                ? el(RangeControl, {
                    label: 'Zoom',
                    value: Number(attrs.mapScale || 1),
                    min: 0.9,
                    max: 1.25,
                    step: 0.01,
                    help: 'Skaliert die Kartenebene um die Mitte. Marker und Spine-Projektion folgen derselben Skalierung.',
                    onChange: function (value) {
                      setAttributes({ mapScale: Number(value || 1) });
                    },
                  })
                : null,
              showPanelFields
                ? el(SelectControl, {
                    label: 'Infopanel',
                    value: attrs.panelMode || 'show',
                    options: PANEL_MODE_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ panelMode: value || 'show' });
                    },
                  })
                : null,
              showPanelFields && (attrs.panelMode || 'show') === 'show'
                ? el(SelectControl, {
                    label: 'Panelposition',
                    value: attrs.panelPosition || 'right',
                    options: PANEL_POSITION_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ panelPosition: value || 'right' });
                    },
                  })
                : null,
              config.showAtlasSliceFields
                ? el(SelectControl, {
                    label: 'Layout',
                    value: attrs.layoutMode || 'band',
                    options: LAYOUT_MODE_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ layoutMode: value || 'band' });
                    },
                  })
                : null,
              config.showAtlasSliceFields
                ? el(SelectControl, {
                    label: 'Tafeltyp',
                    value: attrs.bodyMode || 'text',
                    options: BODY_MODE_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ bodyMode: value || 'text' });
                    },
                  })
                : null,
              config.showAtlasSliceFields
                ? el(SelectControl, {
                    label: 'Tafelposition',
                    value: attrs.bodyPosition || 'end',
                    options: BODY_POSITION_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ bodyPosition: value || 'end' });
                    },
                  })
                : null,
              config.showAtlasSliceFields && (attrs.bodyMode || 'text') === 'image' && TextControl
                ? el(TextControl, {
                    label: 'Bild-URL',
                    value: attrs.mediaUrl || '',
                    help: 'Optionales Begleitbild für die rechte oder linke Tafel.',
                    onChange: function (value) {
                      setAttributes({ mediaUrl: value });
                    },
                  })
                : null,
              config.showAtlasSliceFields && (attrs.bodyMode || 'text') === 'image' && TextControl
                ? el(TextControl, {
                    label: 'Bild-Alt',
                    value: attrs.mediaAlt || '',
                    onChange: function (value) {
                      setAttributes({ mediaAlt: value });
                    },
                  })
                : null,
              config.showAtlasSliceFields && (attrs.bodyMode || 'text') === 'image' && TextareaControl
                ? el(TextareaControl, {
                    label: 'Bildunterschrift',
                    value: attrs.mediaCaption || '',
                    onChange: function (value) {
                      setAttributes({ mediaCaption: value });
                    },
                  })
                : null,
              config.showAtlasStripFields
                ? el(SelectControl, {
                    label: 'Variante',
                    value: attrs.variant || 'place',
                    options: ATLAS_STRIP_VARIANT_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ variant: value || 'place' });
                    },
                  })
                : null,
              config.showAtlasStripFields && (attrs.variant || 'place') !== 'minimal'
                && (attrs.variant || 'place') !== 'spine'
                ? el(SelectControl, {
                    label: 'Textposition',
                    value: attrs.bodyPosition || 'end',
                    options: BODY_POSITION_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ bodyPosition: value || 'end' });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields
                ? el(SelectControl, {
                    label: 'Stationsmodus',
                    value: attrs.stationMode || 'selected',
                    options: ATLAS_STRIP_STATION_MODE_OPTIONS,
                    help: 'Ausgewählte Orte baut die Schiene direkt aus der Block-Auswahl auf. Preset-Schiene nutzt die im Kartenpreset hinterlegte feste Stationsfolge.',
                    onChange: function (value) {
                      setAttributes({ stationMode: value || 'selected' });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields
                ? el(SelectControl, {
                    label: 'Farbmodus',
                    value: attrs.theme || 'black',
                    options: ATLAS_STRIP_THEME_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ theme: value || 'black' });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields
                ? el(SelectControl, {
                    label: 'Linienmodus',
                    value: attrs.lineMode || 'route',
                    options: ATLAS_STRIP_LINE_MODE_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ lineMode: value || 'route' });
                    },
                  })
                : null,
              ((config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields) && TextControl
                ? el(TextControl, {
                    label: 'Richtungslabel',
                    value: attrs.directionLabel || '',
                    help: 'Optional. Leer lassen, um kein Richtunglabel zu zeigen.',
                    onChange: function (value) {
                      setAttributes({ directionLabel: value });
                    },
                  })
                : null,
              ((config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields) && ToggleControl
                ? el(ToggleControl, {
                    label: 'Kartenmarker zeigen',
                    checked: !!attrs.showMapMarkers,
                    help: 'Zeigt die normalen Atlas-Ortsmarker zusätzlich zur Spine-Schiene.',
                    onChange: function (value) {
                      setAttributes({ showMapMarkers: !!value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Bild-URL',
                    value: attrs.mediaUrl || '',
                    help: 'Optionales Vorschaubild für die rechte Karte unter dem Kartenband.',
                    onChange: function (value) {
                      setAttributes({ mediaUrl: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Bild-Alt',
                    value: attrs.mediaAlt || '',
                    onChange: function (value) {
                      setAttributes({ mediaAlt: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Payload-Kicker',
                    value: attrs.payloadKicker || '',
                    onChange: function (value) {
                      setAttributes({ payloadKicker: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Payload-Titel',
                    value: attrs.payloadTitle || '',
                    onChange: function (value) {
                      setAttributes({ payloadTitle: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextareaControl
                ? el(TextareaControl, {
                    label: 'Payload-Text',
                    value: attrs.payloadText || '',
                    onChange: function (value) {
                      setAttributes({ payloadText: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Meta 1 Label',
                    value: attrs.payloadMetaPrimaryLabel || '',
                    onChange: function (value) {
                      setAttributes({ payloadMetaPrimaryLabel: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Meta 1 Wert',
                    value: attrs.payloadMetaPrimaryValue || '',
                    onChange: function (value) {
                      setAttributes({ payloadMetaPrimaryValue: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Meta 2 Label',
                    value: attrs.payloadMetaSecondaryLabel || '',
                    onChange: function (value) {
                      setAttributes({ payloadMetaSecondaryLabel: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Meta 2 Wert',
                    value: attrs.payloadMetaSecondaryValue || '',
                    onChange: function (value) {
                      setAttributes({ payloadMetaSecondaryValue: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Link-URL',
                    value: attrs.linkUrl || '',
                    onChange: function (value) {
                      setAttributes({ linkUrl: value });
                    },
                  })
                : null,
              (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') && TextControl
                ? el(TextControl, {
                    label: 'Link-Label',
                    value: attrs.linkLabel || '',
                    onChange: function (value) {
                      setAttributes({ linkLabel: value });
                    },
                  })
                : null,
              el(RangeControl, {
                label: 'Anzahl',
                min: 1,
                max: config.perPageMax || 12,
                value: attrs.perPage || 3,
                onChange: function (value) {
                  setAttributes({ perPage: value || 3 });
                },
              })
            )
          );
        }

        return el(
          'div',
          null,
          controls,
          el(
            'div',
            { className: 'components-placeholder ' + config.placeholderClassName },
            el('strong', null, config.placeholderTitle),
            el('p', null, config.placeholderText),
            config.showCardLayoutFields
              ? el('p', { className: 'components-base-control__help' }, getRelatedCardsPreviewText(attrs, config))
              : null,
            config.showCardLayoutFields && preview.status === 'loading'
              ? el('p', { className: 'components-base-control__help' }, 'Lade Vorschau …')
              : null,
            config.showCardLayoutFields && preview.status === 'error'
              ? el('p', { className: 'components-base-control__help' }, preview.message || 'Vorschau nicht verfügbar.')
              : null,
            config.showCardLayoutFields && preview.status === 'ready' && preview.payload
              ? renderRelatedCardsPreviewItems(preview.payload)
              : null
          )
        );
      },
      save: function () {
        return null;
      },
    };

    if (config.supports) {
      blockSettings.supports = config.supports;
    }

    window.wp.blocks.registerBlockType(name, blockSettings);
  }

  registerRelatedBlock('iss/related-content', {
    panelTitle: 'Related Section',
    placeholderClassName: 'wp-block-iss-related-content-editor',
    placeholderTitle: 'Related Section',
    placeholderText:
      'Rendert einen vollständigen Abschnitt mit optionalem Intro und Karten oder Strip, basierend auf verknüpften Orten oder einer manuellen Ortsauswahl.',
    showHeadingFields: true,
    showIntroTextField: true,
    showCardLayoutFields: true,
    perPageMax: 24,
  });

  registerRelatedBlock('iss/related-cards', {
    panelTitle: 'Related Cards',
    placeholderClassName: 'wp-block-iss-related-cards-editor',
    placeholderTitle: 'Related Cards',
    placeholderText:
      'Rendert nur Karten. Abschnitt, Überschrift und Container bleiben der umgebenden Vorlage oder dem Pattern überlassen.',
    showHeadingFields: false,
    showCardLayoutFields: true,
    perPageMax: 24,
  });

  registerRelatedBlock('iss/related-place-links', {
    panelTitle: 'Related Place Links',
    placeholderClassName: 'wp-block-iss-related-place-links-editor',
    placeholderTitle: 'Related Place Links',
    placeholderText:
      'Rendert verknüpfte Orte als reine Textliste ohne Karten, Bilder oder Abschnittscontainer.',
    showHeadingFields: true,
    showPostTypeField: false,
    showCardLayoutFields: false,
    perPageMax: 12,
  });

  registerRelatedBlock('iss/related-place-map', {
    panelTitle: 'Related Place Map',
    placeholderClassName: 'wp-block-iss-related-place-map-editor',
    placeholderTitle: 'Related Place Map',
    placeholderText:
      'Rendert eine kleine Kartenbühne mit verknüpften Orten, ideal für Videos, Führungen und Atlas-Teaser auf anderen Seiten.',
    showHeadingFields: true,
    showPostTypeField: false,
    fixedPostType: PLACE_POST_TYPE,
    showIntroTextField: true,
    showMapFields: true,
    supports: {
      align: ['wide', 'full'],
    },
  });

  registerRelatedBlock('iss/atlas-slice', {
    panelTitle: 'Atlas Slice',
    placeholderClassName: 'wp-block-iss-atlas-slice-editor',
    placeholderTitle: 'Atlas Slice',
    placeholderText:
      'Rendert einen gecroppten und gezoomten Atlasstreifen mit echten Ortsmarkern und einer begleitenden Text- oder Bildtafel.',
    showHeadingFields: true,
    showPostTypeField: false,
    fixedPostType: PLACE_POST_TYPE,
    showIntroTextField: true,
    showMapFields: true,
    showAtlasSliceFields: true,
    supports: {
      align: ['wide', 'full'],
    },
  });

  registerRelatedBlock('iss/atlas-strip', {
    panelTitle: 'Atlas Strip',
    placeholderClassName: 'wp-block-iss-atlas-strip-editor',
    placeholderTitle: 'Atlas Strip',
    placeholderText:
      'Rendert einen editoriellen Kartenstreifen auf Basis der kanonischen statischen Karte und echter Ortsmarker.',
    showHeadingFields: true,
    showPostTypeField: false,
    fixedPostType: PLACE_POST_TYPE,
    showIntroTextField: true,
    showMapFields: true,
    showAtlasStripFields: true,
    supports: {
      align: ['wide', 'full'],
    },
  });

  registerRelatedBlock('iss/spine-strip', {
    panelTitle: 'Spine Strip',
    placeholderClassName: 'wp-block-iss-spine-strip-editor',
    placeholderTitle: 'Spine Strip',
    placeholderText:
      'Rendert eine eigene Rail-Komposition mit horizontaler oder vertikaler Achse und Stationsprojektion.',
    showHeadingFields: true,
    showPostTypeField: false,
    fixedPostType: PLACE_POST_TYPE,
    showIntroTextField: true,
    showMapFields: true,
    showPresetField: false,
    showSpineStripFields: true,
    supports: {
      align: ['wide', 'full'],
    },
  });

  window.wp.blocks.registerBlockType('iss/dense-image-wall', {
    supports: {
      html: false,
      align: ['wide', 'full'],
    },
    attributes: {
      columns: {
        type: 'number',
        default: 4,
      },
      rowHeight: {
        type: 'number',
        default: 118,
      },
      items: {
        type: 'array',
        default: [],
      },
    },
    edit: renderDenseImageWallEditor,
    save: saveDenseImageWall,
  });

  window.wp.blocks.registerBlockType('iss/asymmetric-split-field', {
    supports: {
      html: false,
      align: ['wide', 'full'],
    },
    attributes: {
      layoutPreset: {
        type: 'string',
        default: 'map-image-text',
      },
      heightMode: {
        type: 'string',
        default: 'lg',
      },
      source: {
        type: 'string',
        default: 'current',
      },
      placeIds: {
        type: 'string',
        default: '',
      },
      perPage: {
        type: 'number',
        default: 3,
      },
      mapEnabled: {
        type: 'boolean',
        default: true,
      },
      imageEnabled: {
        type: 'boolean',
        default: true,
      },
      textEnabled: {
        type: 'boolean',
        default: true,
      },
      imageUrl: {
        type: 'string',
        default: '',
      },
      imageAlt: {
        type: 'string',
        default: '',
      },
      imageCaption: {
        type: 'string',
        default: '',
      },
      textKicker: {
        type: 'string',
        default: '',
      },
      textTitle: {
        type: 'string',
        default: '',
      },
      textBody: {
        type: 'string',
        default: '',
      },
      textLinkUrl: {
        type: 'string',
        default: '',
      },
      textLinkLabel: {
        type: 'string',
        default: '',
      },
    },
    edit: renderAsymmetricSplitFieldEditor,
    save: function () {
      return null;
    },
  });
})();
