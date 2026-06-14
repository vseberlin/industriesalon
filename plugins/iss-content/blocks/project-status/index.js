(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;

  window.wp.blocks.registerBlockType('iss/project-status', {
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};
      var controls = null;

      if (InspectorControls && PanelBody && ToggleControl) {
        controls = el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Project Status', initialOpen: true },
            el(ToggleControl, {
              label: 'Label anzeigen',
              checked: !!attrs.showLabel,
              onChange: function (value) {
                setAttributes({ showLabel: !!value });
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
          { className: 'components-placeholder wp-block-iss-project-status-editor' },
          el('strong', null, 'Project Status'),
          el(
            'p',
            null,
            'Zeigt den Projektstatus aus Start-/Enddatum, danach Taxonomie oder Zeitraum-Label.'
          )
        )
      );
    },
    save: function () {
      return null;
    },
  });
})();
