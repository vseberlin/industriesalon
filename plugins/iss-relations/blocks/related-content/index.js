(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;
  const RangeControl = window.wp.components && window.wp.components.RangeControl;
  const FormTokenField = window.wp.components && window.wp.components.FormTokenField;
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
    { label: 'Manuelle Ortsauswahl', value: 'manual' },
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

  window.wp.blocks.registerBlockType('iss/related-content', {
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
      var tokenToPlaceId = {};
      var placeIdToToken = {};
      var suggestions = [];
      var selectedTokens = [];

      placePosts.forEach(function (post) {
        var label = getPlaceLabel(post);
        tokenToPlaceId[label] = post.id;
        placeIdToToken[post.id] = label;
        suggestions.push(label);
      });

      selectedTokens = selectedIds
        .map(function (id) {
          return placeIdToToken[id] || '';
        })
        .filter(Boolean);

      if (InspectorControls && PanelBody && TextControl && SelectControl && RangeControl) {
        controls = el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Related Content', initialOpen: true },
            el(SelectControl, {
              label: 'Inhaltstyp',
              value: attrs.postType || 'post',
              options: POST_TYPE_OPTIONS,
              onChange: function (value) {
                setAttributes({ postType: value });
              },
            }),
            el(SelectControl, {
              label: 'Ortsquelle',
              value: attrs.source || 'current',
              options: SOURCE_OPTIONS,
              onChange: function (value) {
                setAttributes({ source: value });
              },
            }),
            attrs.source === 'manual'
              ? FormTokenField
                ? el(
                    'div',
                    null,
                    el(
                      'p',
                      { className: 'components-base-control__help' },
                      'Wählt veröffentlichte Orte aus. Gespeichert wird weiterhin die relationstaugliche Ortsliste im Block.'
                    ),
                    el(FormTokenField, {
                      value: selectedTokens,
                      suggestions: suggestions,
                      onChange: function (tokens) {
                        var ids = [];
                        (Array.isArray(tokens) ? tokens : []).forEach(function (token) {
                          var id = tokenToPlaceId[token] || 0;
                          if (id && ids.indexOf(id) === -1) {
                            ids.push(id);
                          }
                        });
                        setAttributes({ placeIds: ids.join(',') });
                      },
                      placeholder: 'Orte auswählen',
                    }),
                    el(TextControl, {
                      label: 'Orts-IDs',
                      help: 'Fallback für direkte Eingabe oder Kontrolle der gespeicherten Auswahl.',
                      value: attrs.placeIds || '',
                      onChange: function (value) {
                        setAttributes({ placeIds: value });
                      },
                    })
                  )
                : el(TextControl, {
                    label: 'Orts-IDs',
                    help: 'Kommagetrennte register_place IDs, z.B. 12921,12865',
                    value: attrs.placeIds || '',
                    onChange: function (value) {
                      setAttributes({ placeIds: value });
                    },
                  })
              : null,
            el(TextControl, {
              label: 'Kicker',
              value: attrs.kicker || '',
              onChange: function (value) {
                setAttributes({ kicker: value });
              },
            }),
            el(TextControl, {
              label: 'Titel',
              value: attrs.title || '',
              onChange: function (value) {
                setAttributes({ title: value });
              },
            }),
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
          { className: 'components-placeholder wp-block-iss-related-content-editor' },
          el('strong', null, 'Related Content'),
          el(
            'p',
            null,
            'Rendert Inhalte, die über verknüpfte Orte mit dem aktuellen Eintrag oder einer manuellen Ortsauswahl verbunden sind.'
          )
        )
      );
    },
    save: function () {
      return null;
    },
  });
})();
