(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const Button = window.wp.components && window.wp.components.Button;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;
  const RangeControl = window.wp.components && window.wp.components.RangeControl;
  const useSelect = window.wp.data && window.wp.data.useSelect;

  const settings = window.issRelationsSettings || {};
  const PLACE_POST_TYPE = settings.placePostType || 'register_place';

  const POST_TYPE_OPTIONS = [
    { label: 'Orte', value: PLACE_POST_TYPE },
    { label: 'Beiträge', value: 'post' },
    { label: 'Führungen', value: 'fuehrung' },
    { label: 'Veranstaltungen', value: 'veranstaltung' },
    { label: 'Ausstellungen', value: 'ausstellung' },
    { label: 'Projekte', value: 'projekt' },
    { label: 'Seiten', value: 'page' },
  ];

  const SOURCE_OPTIONS = [
    { label: 'Aktueller Kontext', value: 'current' },
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

  function stripTags(value) {
    return String(value || '').replace(/<[^>]*>/g, '').trim();
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

  function registerRelatedBlock(name, config) {
    window.wp.blocks.registerBlockType(name, {
      supports: config.supports || undefined,
      edit: function (props) {
        var attrs = props.attributes || {};
        var setAttributes = props.setAttributes || function () {};
        var controls = null;
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
        var mapPresetOptions =
          Array.isArray(settings.mapPresets) && settings.mapPresets.length
            ? settings.mapPresets
            : [{ label: 'Atlas Übersicht', value: 'default' }];

        if (InspectorControls && PanelBody && SelectControl && RangeControl) {
          controls = el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: config.panelTitle, initialOpen: true },
              config.showPostTypeField !== false
                ? el(SelectControl, {
                    label: 'Inhaltstyp',
                    value: attrs.postType || config.fixedPostType || 'post',
                    options: POST_TYPE_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ postType: value });
                    },
                  })
                : null,
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
              config.showIntroTextField && TextareaControl
                ? el(TextareaControl, {
                    label: 'Introtext',
                    value: attrs.text || '',
                    help: 'Leer lassen, wenn die Karte ohne Einleitung gerendert werden soll.',
                    onChange: function (value) {
                      setAttributes({ text: value });
                    },
                  })
                : null,
              config.showMapFields
                ? el(SelectControl, {
                    label: 'Kartenausschnitt',
                    value: attrs.mapPreset || mapPresetOptions[0].value,
                    options: mapPresetOptions,
                    onChange: function (value) {
                      setAttributes({ mapPreset: value || mapPresetOptions[0].value });
                    },
                  })
                : null,
              config.showMapFields
                ? el(SelectControl, {
                    label: 'Infopanel',
                    value: attrs.panelMode || 'show',
                    options: PANEL_MODE_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ panelMode: value || 'show' });
                    },
                  })
                : null,
              config.showMapFields && (attrs.panelMode || 'show') === 'show'
                ? el(SelectControl, {
                    label: 'Panelposition',
                    value: attrs.panelPosition || 'right',
                    options: PANEL_POSITION_OPTIONS,
                    onChange: function (value) {
                      setAttributes({ panelPosition: value || 'right' });
                    },
                  })
                : null,
              el(RangeControl, {
                label: 'Anzahl',
                min: 1,
                max: 12,
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
            el('p', null, config.placeholderText)
          )
        );
      },
      save: function () {
        return null;
      },
    });
  }

  registerRelatedBlock('iss/related-content', {
    panelTitle: 'Related Section',
    placeholderClassName: 'wp-block-iss-related-content-editor',
    placeholderTitle: 'Related Section',
    placeholderText:
      'Rendert einen vollständigen Abschnitt mit Überschrift und Karten, basierend auf verknüpften Orten oder einer manuellen Ortsauswahl.',
    showHeadingFields: true,
  });

  registerRelatedBlock('iss/related-cards', {
    panelTitle: 'Related Cards',
    placeholderClassName: 'wp-block-iss-related-cards-editor',
    placeholderTitle: 'Related Cards',
    placeholderText:
      'Rendert nur Karten. Abschnitt, Überschrift und Container bleiben der umgebenden Vorlage oder dem Pattern überlassen.',
    showHeadingFields: false,
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
})();
