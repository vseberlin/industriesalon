(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;

  const VARIANT_OPTIONS = [
    { label: 'Standard', value: '' },
    { label: 'Rot', value: 'red' },
    { label: 'Grün', value: 'green' },
    { label: 'Blau', value: 'blue' },
    { label: 'Gelb', value: 'yellow' },
    { label: 'Braun', value: 'brown' },
  ];

  window.wp.blocks.registerBlockType('iss/content-meta', {
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
            { title: 'Content Meta', initialOpen: true },
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
            el(SelectControl, {
              label: 'Variante',
              value: attrs.variant || '',
              options: VARIANT_OPTIONS,
              onChange: function (value) {
                setAttributes({ variant: value });
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
          { className: 'components-placeholder wp-block-iss-content-meta-editor' },
          el('strong', null, 'Content Meta'),
          el(
            'p',
            null,
            'Zeigt strukturierte Felder des aktuellen Eintrags als Info-Panel im Frontend.'
          )
        )
      );
    },
    save: function () {
      return null;
    },
  });
})();
