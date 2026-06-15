(function () {
  const editor = window.issRelationsEditor;
  if (!editor) return;

  const el = editor.components.el;
  const RangeControl = editor.components.RangeControl;
  const SelectControl = editor.components.SelectControl;
  const TextControl = editor.components.TextControl;
  const TextareaControl = editor.components.TextareaControl;
  const optionSets = editor.optionSets;

  function getMapPresetOptions(config) {
    var presets = Array.isArray(editor.settings.mapPresets) && editor.settings.mapPresets.length
      ? editor.settings.mapPresets
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

  function renderIntroControls(attrs, setAttributes, config) {
    if (!config.showIntroTextField || !TextareaControl) return [];
    if (config.showAtlasSliceFields && (attrs.bodyMode || 'text') === 'image') return [];
    if (config.showAtlasStripFields && (attrs.variant || 'place') === 'minimal') return [];
    if (config.showSpineStripFields || (config.showAtlasStripFields && (attrs.variant || 'place') === 'spine')) return [];

    return [
      el(TextareaControl, {
        key: 'text',
        label: 'Introtext',
        value: attrs.text || '',
        help: 'Leer lassen, wenn die Karte ohne Einleitung gerendert werden soll.',
        onChange: function (value) {
          setAttributes({ text: value });
        },
      }),
    ];
  }

  function renderMapViewportControls(attrs, setAttributes, config) {
    var controls = [];

    if (config.showMapFields && config.showPresetField !== false) {
      controls.push(el(SelectControl, {
        key: 'mapPreset',
        label: 'Kartenausschnitt',
        value: attrs.mapPreset || 'default',
        options: getMapPresetOptions(config),
        onChange: function (value) {
          setAttributes({ mapPreset: value || 'default' });
        },
      }));
    }

    if (config.showAtlasSliceFields || config.showAtlasStripFields) {
      controls.push(el(SelectControl, {
        key: 'framingMode',
        label: 'Framing',
        value: attrs.framingMode || 'inherit',
        options: optionSets.FRAMING_MODE_OPTIONS,
        help: 'Preset nutzt den hinterlegten Viewport. Auto-Fokus croppt auf Basis der gewählten Orte.',
        onChange: function (value) {
          setAttributes({ framingMode: value || 'inherit' });
        },
      }));
    }

    if (!config.showMapFields || !RangeControl) {
      return controls;
    }

    return controls.concat([
      el(RangeControl, {
        key: 'rotationDeg',
        label: 'Rotation',
        value: Number(attrs.rotationDeg || 0),
        min: -180,
        max: 180,
        step: 1,
        help: 'Für eine horizontale Spree sind auch freie Winkel wie -30° möglich. Marker und Karte rotieren gemeinsam.',
        onChange: function (value) {
          setAttributes({ rotationDeg: Number(value || 0) });
        },
      }),
      el(RangeControl, {
        key: 'biasX',
        label: 'Bias X',
        value: Number(attrs.biasX || 0),
        min: -25,
        max: 25,
        step: 0.5,
        help: 'Verschiebt die Kartenebene horizontal innerhalb des Ausschnitts.',
        onChange: function (value) {
          setAttributes({ biasX: Number(value || 0) });
        },
      }),
      el(RangeControl, {
        key: 'biasY',
        label: 'Bias Y',
        value: Number(attrs.biasY || 0),
        min: -25,
        max: 25,
        step: 0.5,
        help: 'Verschiebt die Kartenebene vertikal innerhalb des Ausschnitts.',
        onChange: function (value) {
          setAttributes({ biasY: Number(value || 0) });
        },
      }),
      el(RangeControl, {
        key: 'mapScale',
        label: 'Zoom',
        value: Number(attrs.mapScale || 1),
        min: 0.9,
        max: 1.25,
        step: 0.01,
        help: 'Skaliert die Kartenebene um die Mitte. Marker und Spine-Projektion folgen derselben Skalierung.',
        onChange: function (value) {
          setAttributes({ mapScale: Number(value || 1) });
        },
      }),
    ]);
  }

  function renderMapPanelControls(attrs, setAttributes, config) {
    var showPanelFields = config.showMapFields && !config.showAtlasSliceFields && !config.showAtlasStripFields;
    if (!showPanelFields) return [];

    return [
      el(SelectControl, {
        key: 'panelMode',
        label: 'Infopanel',
        value: attrs.panelMode || 'show',
        options: optionSets.PANEL_MODE_OPTIONS,
        onChange: function (value) {
          setAttributes({ panelMode: value || 'show' });
        },
      }),
      (attrs.panelMode || 'show') === 'show'
        ? el(SelectControl, {
            key: 'panelPosition',
            label: 'Panelposition',
            value: attrs.panelPosition || 'right',
            options: optionSets.PANEL_POSITION_OPTIONS,
            onChange: function (value) {
              setAttributes({ panelPosition: value || 'right' });
            },
          })
        : null,
    ];
  }

  function renderAtlasSliceControls(attrs, setAttributes, config) {
    if (!config.showAtlasSliceFields) return [];

    return [
      el(SelectControl, {
        key: 'layoutMode',
        label: 'Layout',
        value: attrs.layoutMode || 'band',
        options: optionSets.LAYOUT_MODE_OPTIONS,
        onChange: function (value) {
          setAttributes({ layoutMode: value || 'band' });
        },
      }),
      el(SelectControl, {
        key: 'bodyMode',
        label: 'Tafeltyp',
        value: attrs.bodyMode || 'text',
        options: optionSets.BODY_MODE_OPTIONS,
        onChange: function (value) {
          setAttributes({ bodyMode: value || 'text' });
        },
      }),
      el(SelectControl, {
        key: 'bodyPosition',
        label: 'Tafelposition',
        value: attrs.bodyPosition || 'end',
        options: optionSets.BODY_POSITION_OPTIONS,
        onChange: function (value) {
          setAttributes({ bodyPosition: value || 'end' });
        },
      }),
      (attrs.bodyMode || 'text') === 'image' && TextControl
        ? el(TextControl, {
            key: 'mediaUrl',
            label: 'Bild-URL',
            value: attrs.mediaUrl || '',
            help: 'Optionales Begleitbild für die rechte oder linke Tafel.',
            onChange: function (value) {
              setAttributes({ mediaUrl: value });
            },
          })
        : null,
      (attrs.bodyMode || 'text') === 'image' && TextControl
        ? el(TextControl, {
            key: 'mediaAlt',
            label: 'Bild-Alt',
            value: attrs.mediaAlt || '',
            onChange: function (value) {
              setAttributes({ mediaAlt: value });
            },
          })
        : null,
      (attrs.bodyMode || 'text') === 'image' && TextareaControl
        ? el(TextareaControl, {
            key: 'mediaCaption',
            label: 'Bildunterschrift',
            value: attrs.mediaCaption || '',
            onChange: function (value) {
              setAttributes({ mediaCaption: value });
            },
          })
        : null,
    ];
  }

  function renderAtlasStripControls(attrs, setAttributes, config) {
    if (!config.showAtlasStripFields) return [];

    return [
      el(SelectControl, {
        key: 'variant',
        label: 'Variante',
        value: attrs.variant || 'place',
        options: optionSets.ATLAS_STRIP_VARIANT_OPTIONS,
        onChange: function (value) {
          setAttributes({ variant: value || 'place' });
        },
      }),
      (attrs.variant || 'place') !== 'minimal' && (attrs.variant || 'place') !== 'spine'
        ? el(SelectControl, {
            key: 'bodyPosition',
            label: 'Textposition',
            value: attrs.bodyPosition || 'end',
            options: optionSets.BODY_POSITION_OPTIONS,
            onChange: function (value) {
              setAttributes({ bodyPosition: value || 'end' });
            },
          })
        : null,
    ];
  }

  function renderStaticMapControls(attrs, setAttributes, config) {
    return []
      .concat(renderIntroControls(attrs, setAttributes, config))
      .concat(renderMapViewportControls(attrs, setAttributes, config))
      .concat(renderMapPanelControls(attrs, setAttributes, config))
      .concat(renderAtlasSliceControls(attrs, setAttributes, config))
      .concat(renderAtlasStripControls(attrs, setAttributes, config));
  }

  window.issRelationsEditor.staticMapControls = {
    getMapPresetOptions: getMapPresetOptions,
    renderStaticMapControls: renderStaticMapControls,
  };
})();
