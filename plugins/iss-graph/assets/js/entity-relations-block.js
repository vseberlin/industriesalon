(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) {
    return;
  }

  var el = window.wp.element.createElement;
  var InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  var PanelBody = window.wp.components && window.wp.components.PanelBody;
  var TextControl = window.wp.components && window.wp.components.TextControl;
  var SelectControl = window.wp.components && window.wp.components.SelectControl;
  var RangeControl = window.wp.components && window.wp.components.RangeControl;
  var ServerSideRender = window.wp.serverSideRender;

  window.wp.blocks.registerBlockType('iss-graph/entity-relations', {
    title: 'Entity Relations',
    category: 'widgets',
    icon: 'groups',
    description: 'Renders people or organization relations connected to the current content entry.',
    attributes: {
      entityKind: {
        type: 'string',
        default: 'person',
      },
      kicker: {
        type: 'string',
        default: '',
      },
      title: {
        type: 'string',
        default: '',
      },
      intro: {
        type: 'string',
        default: '',
      },
      limit: {
        type: 'number',
        default: 8,
      },
    },
    supports: {
      html: false,
    },
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};
      var controls = null;

      if (InspectorControls && PanelBody && TextControl && SelectControl) {
        controls = el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Entity Relations', initialOpen: true },
            el(SelectControl, {
              label: 'Entity type',
              value: attrs.entityKind || 'person',
              options: [
                { label: 'Personen', value: 'person' },
                { label: 'Organisationen', value: 'organization' },
              ],
              onChange: function (value) {
                setAttributes({ entityKind: value });
              },
            }),
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
            el(TextControl, {
              label: 'Intro',
              value: attrs.intro || '',
              onChange: function (value) {
                setAttributes({ intro: value });
              },
            }),
            RangeControl
              ? el(RangeControl, {
                  label: 'Limit',
                  value: attrs.limit || 8,
                  min: 1,
                  max: 24,
                  onChange: function (value) {
                    setAttributes({ limit: value });
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
        ServerSideRender
          ? el(ServerSideRender, {
              block: 'iss-graph/entity-relations',
              attributes: attrs,
            })
          : el(
              'div',
              { className: 'components-placeholder' },
              el('strong', null, 'Entity Relations'),
              el('p', null, 'Renders graph relations connected to the current content entry.')
            )
      );
    },
    save: function () {
      return null;
    },
  });
})();
