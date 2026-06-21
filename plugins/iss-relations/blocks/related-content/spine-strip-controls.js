(function () {
  const editor = window.issRelationsEditor;
  if (!editor) return;

  const el = editor.components.el;
  const SelectControl = editor.components.SelectControl;
  const TextControl = editor.components.TextControl;
  const TextareaControl = editor.components.TextareaControl;
  const ToggleControl = editor.components.ToggleControl;
  const optionSets = editor.optionSets;

  function isSpineSurface(attrs, config) {
    return (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine') || config.showSpineStripFields;
  }

  function renderSpineIntroControls(attrs, setAttributes, config) {
    if (!isSpineSurface(attrs, config)) return [];

    return [
      SelectControl
        ? el(SelectControl, {
            key: 'textMode',
            label: 'Introtyp',
            value: attrs.textMode || 'text',
            options: optionSets.SPINE_TEXT_MODE_OPTIONS,
            onChange: function (value) {
              setAttributes({ textMode: value || 'text' });
            },
          })
        : null,
      config.showIntroTextField && TextareaControl
        ? el(TextareaControl, {
            key: 'text',
            label: (attrs.textMode || 'text') === 'quote' ? 'Zitat' : 'Introtext',
            value: attrs.text || '',
            help: (attrs.textMode || 'text') === 'quote'
              ? 'Nur den Zitattext eintragen. Quelle/Zuschreibung kommt ins nächste Feld.'
              : 'Leer lassen, wenn die Karte ohne Einleitung gerendert werden soll.',
            onChange: function (value) {
              setAttributes({ text: value });
            },
          })
        : null,
      (attrs.textMode || 'text') === 'quote' && TextareaControl
        ? el(TextareaControl, {
            key: 'textAttribution',
            label: 'Quelle / Zuschreibung',
            value: attrs.textAttribution || '',
            help: 'Optional. Wird als Zitatquelle unter dem Text gerendert.',
            onChange: function (value) {
              setAttributes({ textAttribution: value });
            },
          })
        : null,
    ];
  }

  function renderSpineControls(attrs, setAttributes, config) {
    if (!isSpineSurface(attrs, config)) return [];

    return [
      el(SelectControl, {
        key: 'stationMode',
        label: 'Stationsmodus',
        value: attrs.stationMode || 'selected',
        options: optionSets.ATLAS_STRIP_STATION_MODE_OPTIONS,
        help: 'Ausgewählte Orte baut die Schiene direkt aus der Block-Auswahl auf. Preset-Schiene nutzt die im Kartenpreset hinterlegte feste Stationsfolge.',
        onChange: function (value) {
          setAttributes({ stationMode: value || 'selected' });
        },
      }),
      el(SelectControl, {
        key: 'theme',
        label: 'Farbmodus',
        value: attrs.theme || 'black',
        options: optionSets.ATLAS_STRIP_THEME_OPTIONS,
        onChange: function (value) {
          setAttributes({ theme: value || 'black' });
        },
      }),
      el(SelectControl, {
        key: 'lineMode',
        label: 'Linienmodus',
        value: attrs.lineMode || 'route',
        options: optionSets.ATLAS_STRIP_LINE_MODE_OPTIONS,
        onChange: function (value) {
          setAttributes({ lineMode: value || 'route' });
        },
      }),
      TextControl
        ? el(TextControl, {
            key: 'directionLabelStart',
            label: 'Richtungslabel links',
            value: attrs.directionLabelStart || '',
            help: 'Optional. Erscheint links an der Orientierungslinie.',
            onChange: function (value) {
              setAttributes({ directionLabelStart: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'directionLabel',
            label: 'Richtungslabel rechts',
            value: attrs.directionLabel || '',
            help: 'Optional. Erscheint rechts an der Orientierungslinie.',
            onChange: function (value) {
              setAttributes({ directionLabel: value });
            },
          })
        : null,
      ToggleControl
        ? el(ToggleControl, {
            key: 'showMapMarkers',
            label: 'Kartenmarker zeigen',
            checked: !!attrs.showMapMarkers,
            help: 'Zeigt die normalen Atlas-Ortsmarker zusätzlich zur Spine-Schiene.',
            onChange: function (value) {
              setAttributes({ showMapMarkers: !!value });
            },
          })
        : null,
    ];
  }

  function renderAtlasStripPayloadControls(attrs, setAttributes, config) {
    if (!(config.showAtlasStripFields && (attrs.variant || 'place') === 'spine')) return [];

    return [
      TextControl
        ? el(TextControl, {
            key: 'mediaUrl',
            label: 'Bild-URL',
            value: attrs.mediaUrl || '',
            help: 'Optionales Vorschaubild für die rechte Karte unter dem Kartenband.',
            onChange: function (value) {
              setAttributes({ mediaUrl: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'mediaAlt',
            label: 'Bild-Alt',
            value: attrs.mediaAlt || '',
            onChange: function (value) {
              setAttributes({ mediaAlt: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'payloadKicker',
            label: 'Payload-Kicker',
            value: attrs.payloadKicker || '',
            onChange: function (value) {
              setAttributes({ payloadKicker: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'payloadTitle',
            label: 'Payload-Titel',
            value: attrs.payloadTitle || '',
            onChange: function (value) {
              setAttributes({ payloadTitle: value });
            },
          })
        : null,
      TextareaControl
        ? el(TextareaControl, {
            key: 'payloadText',
            label: 'Payload-Text',
            value: attrs.payloadText || '',
            onChange: function (value) {
              setAttributes({ payloadText: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'payloadMetaPrimaryLabel',
            label: 'Meta 1 Label',
            value: attrs.payloadMetaPrimaryLabel || '',
            onChange: function (value) {
              setAttributes({ payloadMetaPrimaryLabel: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'payloadMetaPrimaryValue',
            label: 'Meta 1 Wert',
            value: attrs.payloadMetaPrimaryValue || '',
            onChange: function (value) {
              setAttributes({ payloadMetaPrimaryValue: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'payloadMetaSecondaryLabel',
            label: 'Meta 2 Label',
            value: attrs.payloadMetaSecondaryLabel || '',
            onChange: function (value) {
              setAttributes({ payloadMetaSecondaryLabel: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'payloadMetaSecondaryValue',
            label: 'Meta 2 Wert',
            value: attrs.payloadMetaSecondaryValue || '',
            onChange: function (value) {
              setAttributes({ payloadMetaSecondaryValue: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'linkUrl',
            label: 'Link-URL',
            value: attrs.linkUrl || '',
            onChange: function (value) {
              setAttributes({ linkUrl: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            key: 'linkLabel',
            label: 'Link-Label',
            value: attrs.linkLabel || '',
            onChange: function (value) {
              setAttributes({ linkLabel: value });
            },
          })
        : null,
    ];
  }

  function renderSpineStripControls(attrs, setAttributes, config) {
    return []
      .concat(renderSpineIntroControls(attrs, setAttributes, config))
      .concat(renderSpineControls(attrs, setAttributes, config))
      .concat(renderAtlasStripPayloadControls(attrs, setAttributes, config));
  }

  window.issRelationsEditor.spineStripControls = {
    isSpineSurface: isSpineSurface,
    renderSpineStripControls: renderSpineStripControls,
  };
})();
