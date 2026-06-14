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
  const CAN_MANAGE_EDITORIAL_SIGNALS = settings.canManageEditorialSignals === true;

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

  function getSourceLabel(value) {
    var normalized = String(value || 'current');
    var options = SOURCE_OPTIONS.concat(RELATED_SOURCE_OPTIONS);
    var match = options.find(function (option) {
      return option.value === normalized;
    });

    return match ? match.label : normalized;
  }

  function getEditorialSignalLabel(value) {
    var normalized = String(value || '');

    if (normalized === 'feature') {
      return 'Vorne zeigen';
    }

    if (normalized === 'hide') {
      return 'Nicht automatisch zeigen';
    }

    return '';
  }

  function getEditorialSignalDraft(drafts, targetPostId, source) {
    var key = String(targetPostId || '');
    var hasDraft = !!(drafts && Object.prototype.hasOwnProperty.call(drafts, key));
    var draft = hasDraft ? drafts[key] || {} : {};
    var fallback = source || {};

    return {
      reason: typeof draft.reason === 'string'
        ? draft.reason
        : String(fallback.editorialReason || fallback.reason || ''),
      expiresAt: typeof draft.expiresAt === 'string'
        ? draft.expiresAt
        : String(fallback.editorialExpiresAt || fallback.expiresAt || ''),
    };
  }

  function setEditorialSignalDraft(drafts, setDrafts, targetPostId, patch) {
    if (!setDrafts) return;

    var key = String(targetPostId || '');
    var next = Object.assign({}, drafts || {});
    next[key] = Object.assign({}, next[key] || {}, patch || {});
    setDrafts(next);
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

  function renderEditorialActionButton(key, label, onClick, disabled, variant) {
    if (Button) {
      return el(
        Button,
        {
          key: key,
          variant: variant || 'secondary',
          disabled: !!disabled,
          onClick: onClick,
        },
        label
      );
    }

    return el(
      'button',
      {
        key: key,
        type: 'button',
        disabled: !!disabled,
        onClick: onClick,
      },
      label
    );
  }

  function renderEditorialSignalCardControls(item, options) {
    options = options || {};
    var targetPostId = item && item.id ? parseInt(item.id, 10) || 0 : 0;
    if (!targetPostId) return null;

    var currentSignal = String(item.editorialSignal || '');
    var draft = getEditorialSignalDraft(options.drafts, targetPostId, item);
    var action = options.action || {};
    var isBusy = parseInt(action.targetPostId, 10) === targetPostId && action.status === 'saving';
    var isCurrentTarget = parseInt(action.targetPostId, 10) === targetPostId;
    var isDisabled = isBusy || !options.currentPostId;

    return el(
      'div',
      {
        style: {
          borderTop: '1px solid rgba(30,30,30,0.08)',
          paddingTop: '10px',
          display: 'grid',
          gap: '8px',
        },
      },
      currentSignal
        ? el(
            'p',
            {
              style: {
                margin: 0,
                fontSize: '12px',
                fontWeight: '700',
                color: 'rgba(30,30,30,0.72)',
              },
            },
            getEditorialSignalLabel(currentSignal)
          )
        : null,
      TextControl
        ? el(TextControl, {
            label: 'Notiz',
            value: draft.reason,
            onChange: function (value) {
              setEditorialSignalDraft(options.drafts, options.setDrafts, targetPostId, { reason: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            label: 'Gilt bis',
            type: 'date',
            value: draft.expiresAt,
            onChange: function (value) {
              setEditorialSignalDraft(options.drafts, options.setDrafts, targetPostId, { expiresAt: value });
            },
          })
        : null,
      el(
        'div',
        {
          style: {
            display: 'flex',
            flexWrap: 'wrap',
            gap: '6px',
          },
        },
        renderEditorialActionButton('feature', 'Vorne zeigen', function () {
          if (options.onSave) options.onSave(item, 'feature');
        }, isDisabled, 'secondary'),
        renderEditorialActionButton('hide', 'Nicht automatisch zeigen', function () {
          if (options.onSave) options.onSave(item, 'hide');
        }, isDisabled, 'secondary'),
        currentSignal
          ? renderEditorialActionButton('remove', 'Auswahl entfernen', function () {
              if (options.onRemove) options.onRemove(targetPostId);
            }, isDisabled, 'tertiary')
          : null
      ),
      !options.currentPostId
        ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, 'Seite zuerst speichern.')
        : null,
      isCurrentTarget && action.message
        ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, action.message)
        : null
    );
  }

  function renderEditorialSignalSummary(signals, options) {
    options = options || {};
    var items = Array.isArray(signals) ? signals : [];
    var action = options.action || {};

    return el(
      'div',
      {
        style: {
          borderTop: '1px solid rgba(30,30,30,0.12)',
          marginTop: '14px',
          paddingTop: '12px',
          display: 'grid',
          gap: '8px',
        },
      },
      el(
        'strong',
        {
          style: {
            fontSize: '13px',
            lineHeight: '1.3',
          },
        },
        'Redaktionelle Auswahl'
      ),
      !items.length
        ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, 'Keine Auswahl aktiv.')
        : null,
      items.map(function (signal) {
        var target = signal.target || {};
        var targetPostId = parseInt(signal.targetPostId || target.id, 10) || 0;
        var isBusy = parseInt(action.targetPostId, 10) === targetPostId && action.status === 'saving';
        var title = target.title || 'Ohne Titel';
        var meta = [
          getEditorialSignalLabel(signal.signal),
          target.postType ? getPostTypeLabel(target.postType) : '',
          signal.expiresAt ? 'Gilt bis ' + signal.expiresAt : '',
        ].filter(Boolean).join(' · ');

        return el(
          'div',
          {
            key: 'editorial-signal-' + String(targetPostId),
            style: {
              border: '1px solid rgba(30,30,30,0.1)',
              background: '#fff',
              padding: '10px',
              display: 'grid',
              gap: '6px',
            },
          },
          el(
            'div',
            {
              style: {
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'space-between',
                gap: '8px',
              },
            },
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
                    fontWeight: '700',
                    fontSize: '13px',
                    lineHeight: '1.35',
                  },
                },
                title
              ),
              meta
                ? el(
                    'div',
                    {
                      style: {
                        fontSize: '11px',
                        color: 'rgba(30,30,30,0.66)',
                      },
                    },
                    meta
                  )
                : null
            ),
            renderEditorialActionButton('remove-' + String(targetPostId), 'Auswahl entfernen', function () {
              if (options.onRemove) options.onRemove(targetPostId);
            }, isBusy || !options.currentPostId, 'tertiary')
          ),
          signal.reason
            ? el('p', { style: { margin: 0, fontSize: '12px', lineHeight: '1.45' } }, signal.reason)
            : null,
          parseInt(action.targetPostId, 10) === targetPostId && action.message
            ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, action.message)
            : null
        );
      })
    );
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

  function isManualPlaceSource(value) {
    return String(value || '') === 'manual';
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
      'Quelle: ' + getSourceLabel(source),
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

  function renderRelatedCardsPreviewItems(preview, options) {
    options = options || {};
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
              : null,
            options.canManageEditorialSignals
              ? renderEditorialSignalCardControls(item, options)
              : null
          )
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
    if (!isManualPlaceSource(attrs.source)) return null;

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
        var editorialDraftState = useState ? useState({}) : [{}, function () {}];
        var editorialDrafts = editorialDraftState[0];
        var setEditorialDrafts = editorialDraftState[1];
        var editorialActionState = useState ? useState({ targetPostId: 0, status: 'idle', message: '' }) : [{ targetPostId: 0, status: 'idle', message: '' }, function () {}];
        var editorialAction = editorialActionState[0];
        var setEditorialAction = editorialActionState[1];
        var editorialRefreshState = useState ? useState(0) : [0, function () {}];
        var editorialRefresh = editorialRefreshState[0];
        var setEditorialRefresh = editorialRefreshState[1];
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

        function saveEditorialSignal(item, signal) {
          var targetPostId = item && item.id ? parseInt(item.id, 10) || 0 : 0;
          if (!apiFetch || !currentPostId || !targetPostId) return;

          var draft = getEditorialSignalDraft(editorialDrafts, targetPostId, item);
          setEditorialAction({ targetPostId: targetPostId, status: 'saving', message: '' });

          apiFetch({
            path: '/iss-graph/v1/editorial-signals',
            method: 'POST',
            data: {
              contextPostId: currentPostId || 0,
              targetPostId: targetPostId,
              surface: 'related',
              signal: signal,
              reason: draft.reason || '',
              expiresAt: draft.expiresAt || '',
            },
          })
            .then(function () {
              setEditorialAction({ targetPostId: targetPostId, status: 'saved', message: 'Auswahl aktualisiert.' });
              setEditorialRefresh(editorialRefresh + 1);
            })
            .catch(function (error) {
              setEditorialAction({
                targetPostId: targetPostId,
                status: 'error',
                message: error && error.message ? error.message : 'Auswahl konnte nicht gespeichert werden.',
              });
            });
        }

        function removeEditorialSignal(targetPostId) {
          targetPostId = parseInt(targetPostId, 10) || 0;
          if (!apiFetch || !currentPostId || !targetPostId) return;

          setEditorialAction({ targetPostId: targetPostId, status: 'saving', message: '' });

          apiFetch({
            path: buildApiPath('/iss-graph/v1/editorial-signals', {
              contextPostId: currentPostId || 0,
              targetPostId: targetPostId,
              surface: 'related',
            }),
            method: 'DELETE',
          })
            .then(function () {
              setEditorialAction({ targetPostId: targetPostId, status: 'saved', message: 'Auswahl entfernt.' });
              setEditorialRefresh(editorialRefresh + 1);
            })
            .catch(function (error) {
              setEditorialAction({
                targetPostId: targetPostId,
                status: 'error',
                message: error && error.message ? error.message : 'Auswahl konnte nicht entfernt werden.',
              });
            });
        }

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
            editorialRefresh: editorialRefresh,
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
                label: config.showCardLayoutFields ? 'Quelle' : 'Ortsquelle',
                value: attrs.source || 'current',
                options: config.showCardLayoutFields ? RELATED_SOURCE_OPTIONS : SOURCE_OPTIONS,
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
            CAN_MANAGE_EDITORIAL_SIGNALS && config.showCardLayoutFields && preview.status === 'ready' && preview.payload
              ? renderEditorialSignalSummary(preview.payload.editorialSignals || [], {
                  currentPostId: currentPostId || 0,
                  onRemove: removeEditorialSignal,
                  action: editorialAction,
                })
              : null,
            config.showCardLayoutFields && preview.status === 'ready' && preview.payload
              ? renderRelatedCardsPreviewItems(preview.payload, {
                  currentPostId: currentPostId || 0,
                  drafts: editorialDrafts,
                  setDrafts: setEditorialDrafts,
                  onSave: saveEditorialSignal,
                  onRemove: removeEditorialSignal,
                  action: editorialAction,
                  canManageEditorialSignals: CAN_MANAGE_EDITORIAL_SIGNALS,
                })
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
