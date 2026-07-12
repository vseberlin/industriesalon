(function () {
  const editor = window.issRelationsEditor;
  if (!editor || !window.wp || !window.wp.blocks) return;

  const el = editor.components.el;
  const InspectorControls = editor.components.InspectorControls;
  const PanelBody = editor.components.PanelBody;
  const RangeControl = editor.components.RangeControl;
  const SelectControl = editor.components.SelectControl;
  const TextControl = editor.components.TextControl;
  const TextareaControl = editor.components.TextareaControl;
  const ToggleControl = editor.components.ToggleControl;
  const useEffect = editor.components.useEffect;
  const useState = editor.components.useState;
  const useSelect = editor.components.useSelect;
  const apiFetch = editor.components.apiFetch;

  const sourceControls = editor.sourceControls;
  const relatedCardsControls = editor.relatedCardsControls;
  const staticMapControls = editor.staticMapControls;
  const spineStripControls = editor.spineStripControls;
  const editorialSignalControls = editor.editorialSignalControls;
  const optionSets = editor.optionSets;
  const PLACE_POST_TYPE = editor.PLACE_POST_TYPE;

  function compactControls(items) {
    return items.filter(function (item) {
      return item !== null && item !== undefined && item !== false;
    });
  }

  function renderHeadingControls(attrs, setAttributes, config) {
    if (!config.showHeadingFields || !TextControl) return [];

    return [
      el(TextControl, {
        key: 'kicker',
        label: 'Kicker',
        value: attrs.kicker || '',
        onChange: function (value) {
          setAttributes({ kicker: value });
        },
      }),
      el(TextControl, {
        key: 'title',
        label: 'Titel',
        value: attrs.title || '',
        onChange: function (value) {
          setAttributes({ title: value });
        },
      }),
    ];
  }

  function getPlaceRecords() {
    return useSelect
      ? useSelect(function (select) {
          return select('core').getEntityRecords('postType', PLACE_POST_TYPE, {
            per_page: 100,
            orderby: 'title',
            order: 'asc',
            status: 'publish',
          });
        }, [])
      : [];
  }

  function getCurrentPostId() {
    return useSelect
      ? useSelect(function (select) {
          var editorStore = select('core/editor');
          return editorStore && editorStore.getCurrentPostId ? editorStore.getCurrentPostId() : 0;
        }, [])
      : 0;
  }

  function getPreviewDependency(attrs, config, currentPostId, editorialRefresh) {
    return JSON.stringify({
      postTypes: relatedCardsControls.getSelectedPostTypes(attrs, config),
      perPage: attrs.perPage || 3,
      source: editor.getRelatedBlockSource(attrs, config),
      placeIds: attrs.placeIds || '',
      layoutVariant: attrs.layoutVariant || 'grid',
      sortMode: attrs.sortMode || 'auto',
      columns: attrs.columns || 3,
      skin: attrs.skin || 'default',
      showImage: attrs.showImage !== false,
      currentPostId: currentPostId || 0,
      editorialRefresh: editorialRefresh,
    });
  }

  function renderInspectorPanel(attrs, setAttributes, config, placeOptions, selectedIds) {
    if (!InspectorControls || !PanelBody || !SelectControl || !RangeControl) {
      return null;
    }

    var spineFirst = spineStripControls.isSpineSurface(attrs, config);
    var controls = compactControls([
      config.variantOptions && config.variantOptions.length
        ? el(SelectControl, {
            key: 'variant',
            label: 'Variante',
            value: attrs.variant || config.defaultVariant || config.variantOptions[0].value,
            options: config.variantOptions,
            onChange: function (value) {
              setAttributes({ variant: value || config.defaultVariant || config.variantOptions[0].value });
            },
          })
        : null,
      config.showPostTypeField === false ? null : relatedCardsControls.renderPostTypeControl(attrs, setAttributes, config),
      config.showSourceFields === false
        ? null
        : el(SelectControl, {
            key: 'source',
            label: config.showCardLayoutFields ? 'Quelle' : 'Ortsquelle',
            value: editor.getRelatedBlockSource(attrs, config),
            options: config.showCardLayoutFields ? optionSets.RELATED_SOURCE_OPTIONS : optionSets.SOURCE_OPTIONS,
            onChange: function (value) {
              setAttributes({ source: value || editor.getRelatedBlockDefaultSource(config) });
            },
          }),
      config.showSourceFields === false ? null : sourceControls.renderManualPlaceControl(attrs, setAttributes, placeOptions, selectedIds, config),
    ])
      .concat(renderHeadingControls(attrs, setAttributes, config))
      .concat(config.showCardLayoutFields ? relatedCardsControls.renderInspectorControls(attrs, setAttributes) : [])
      .concat(spineFirst ? spineStripControls.renderSpineStripControls(attrs, setAttributes, config) : [])
      .concat(staticMapControls.renderStaticMapControls(attrs, setAttributes, config))
      .concat(spineFirst ? [] : spineStripControls.renderSpineStripControls(attrs, setAttributes, config))
      .concat(
        config.showPerPageField === false
          ? []
          : [
              el(RangeControl, {
                key: 'perPage',
                label: 'Anzahl',
                min: 1,
                max: config.perPageMax || 12,
                value: attrs.perPage || 3,
                onChange: function (value) {
                  setAttributes({ perPage: value || 3 });
                },
              }),
            ]
      );

    return el(
      InspectorControls,
      null,
      el(
        PanelBody,
        { title: config.panelTitle, initialOpen: true },
        compactControls(controls)
      )
    );
  }

  function useRelatedPreview(attrs, config, currentPostId, editorialRefresh) {
    var previewState = useState ? useState({ status: 'idle', payload: null, message: '' }) : [{ status: 'idle', payload: null, message: '' }, function () {}];
    var preview = previewState[0];
    var setPreview = previewState[1];

    if (config.showCardLayoutFields && useEffect && apiFetch) {
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
      }, [getPreviewDependency(attrs, config, currentPostId, editorialRefresh)]);
    }

    return preview;
  }

  function registerRelatedBlock(name, config) {
    config = Object.assign({}, config || {}, { name: name });

    var blockSettings = {
      edit: function (props) {
        var attrs = props.attributes || {};
        var setAttributes = props.setAttributes || function () {};
        var selectedIds = sourceControls.parsePlaceIds(attrs.placeIds || '');
        var placeRecords = getPlaceRecords();
        var currentPostId = getCurrentPostId();
        var placeOptions = sourceControls.buildPlaceOptions(Array.isArray(placeRecords) ? placeRecords : [], selectedIds);
        var editorialDraftState = useState ? useState({}) : [{}, function () {}];
        var editorialDrafts = editorialDraftState[0];
        var setEditorialDrafts = editorialDraftState[1];
        var editorialActionState = useState ? useState({ targetPostId: 0, status: 'idle', message: '' }) : [{ targetPostId: 0, status: 'idle', message: '' }, function () {}];
        var editorialAction = editorialActionState[0];
        var setEditorialAction = editorialActionState[1];
        var editorialRefreshState = useState ? useState(0) : [0, function () {}];
        var editorialRefresh = editorialRefreshState[0];
        var setEditorialRefresh = editorialRefreshState[1];
        var editorialActions = editorialSignalControls.createActions({
          currentPostId: currentPostId || 0,
          drafts: editorialDrafts,
          setAction: setEditorialAction,
          refresh: function () {
            setEditorialRefresh(editorialRefresh + 1);
          },
        });
        var preview = useRelatedPreview(attrs, config, currentPostId, editorialRefresh);

        return el(
          'div',
          null,
          renderInspectorPanel(attrs, setAttributes, config, placeOptions, selectedIds),
          el(
            'div',
            { className: 'components-placeholder ' + config.placeholderClassName },
            el('strong', null, config.placeholderTitle),
            el('p', null, config.placeholderText),
            config.showCardLayoutFields
              ? el('p', { className: 'components-base-control__help' }, relatedCardsControls.getPreviewText(attrs, config))
              : null,
            config.showCardLayoutFields && preview.status === 'loading'
              ? el('p', { className: 'components-base-control__help' }, 'Lade Vorschau ...')
              : null,
            config.showCardLayoutFields && preview.status === 'error'
              ? el('p', { className: 'components-base-control__help' }, preview.message || 'Vorschau nicht verfügbar.')
              : null,
            editor.canManageEditorialSignals && config.showCardLayoutFields && preview.status === 'ready' && preview.payload
              ? editorialSignalControls.renderSummary(preview.payload.editorialSignals || [], {
                  currentPostId: currentPostId || 0,
                  onRemove: editorialActions.remove,
                  action: editorialAction,
                })
              : null,
            config.showCardLayoutFields && preview.status === 'ready' && preview.payload
              ? relatedCardsControls.renderPreviewItems(preview.payload, {
                  currentPostId: currentPostId || 0,
                  drafts: editorialDrafts,
                  setDrafts: setEditorialDrafts,
                  onSave: editorialActions.save,
                  onRemove: editorialActions.remove,
                  action: editorialAction,
                  canManageEditorialSignals: editor.canManageEditorialSignals,
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

  function renderAsymmetricSplitFieldEditor(props) {
    var attrs = props.attributes || {};
    var setAttributes = props.setAttributes || function () {};
    var selectedIds = sourceControls.parsePlaceIds(attrs.placeIds || '');
    var placeRecords = getPlaceRecords();
    var placeOptions = sourceControls.buildPlaceOptions(Array.isArray(placeRecords) ? placeRecords : [], selectedIds);
    var layoutPreset = attrs.layoutPreset || 'map-image-text';
    var mapEnabled = attrs.mapEnabled !== false;
    var imageEnabled = !!attrs.imageEnabled;
    var textEnabled = attrs.textEnabled !== false;
    var controls = null;

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
                options: optionSets.ASYMMETRIC_SPLIT_PRESET_OPTIONS,
                onChange: function (value) {
                  setAttributes({ layoutPreset: value || 'map-image-text' });
                },
              })
            : null,
          SelectControl
            ? el(SelectControl, {
                label: 'Höhe',
                value: attrs.heightMode || 'lg',
                options: optionSets.ASYMMETRIC_SPLIT_HEIGHT_OPTIONS,
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
                options: optionSets.SOURCE_OPTIONS,
                onChange: function (value) {
                  setAttributes({ source: value || 'current' });
                },
              })
            : null,
          mapEnabled ? sourceControls.renderManualPlaceControl(attrs, setAttributes, placeOptions, selectedIds, { name: 'iss/asymmetric-split-field' }) : null,
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
    defaultSource: 'current',
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

  registerRelatedBlock('iss/atlas-map', {
    defaultSource: 'current',
    defaultVariant: 'place-locator',
    panelTitle: 'Atlas Map',
    placeholderClassName: 'wp-block-iss-atlas-map-editor',
    placeholderTitle: 'Atlas Map',
    placeholderText:
      'Rendert eine registrierte Atlas-Map-Variante über den gemeinsamen Static-Map-Renderer.',
    variantOptions: [
      { label: 'Ort verorten', value: 'place-locator' },
      { label: 'Führungsroute', value: 'tour-route' },
      { label: 'Kartenband', value: 'map-only' },
    ],
    showHeadingFields: true,
    showSourceFields: false,
    showPerPageField: false,
    showPostTypeField: false,
    fixedPostType: PLACE_POST_TYPE,
    showIntroTextField: true,
    supports: {
      align: ['wide', 'full'],
    },
  });

  registerRelatedBlock('iss/atlas-slice', {
    defaultSource: 'current',
    panelTitle: 'Atlas Slice',
    placeholderClassName: 'wp-block-iss-atlas-slice-editor',
    placeholderTitle: 'Atlas Slice',
    placeholderText:
      'Rendert einen interaktiven Atlasstreifen mit echten Ortsmarkern und einer begleitenden Text- oder Bildtafel.',
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
    defaultSource: 'current',
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
      inserter: false,
      align: ['wide', 'full'],
    },
  });

  registerRelatedBlock('iss/spine-strip', {
    defaultSource: 'manual',
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
      inserter: false,
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
