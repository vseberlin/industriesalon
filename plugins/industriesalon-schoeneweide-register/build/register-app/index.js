(function (wp) {
  var registerBlockType = wp.blocks.registerBlockType;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var ToggleControl = wp.components.ToggleControl;
  var TextControl = wp.components.TextControl;
  var createElement = wp.element.createElement;
  var Fragment = wp.element.Fragment;

  var VIEW_OPTIONS = [
    { label: 'Entdecken', value: 'discover' },
    { label: 'Orte', value: 'places' },
    { label: 'Damals & Heute', value: 'then-now' },
    { label: 'Recherche', value: 'research' }
  ];

  registerBlockType('iss-register/register-app', {
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;

      var blockProps = useBlockProps({
        className: 'iss-register-editor-preview'
      });

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: 'Register Einstellungen', initialOpen: true },
            createElement(SelectControl, {
              label: 'Default view',
              value: attributes.defaultView || 'discover',
              options: VIEW_OPTIONS,
              onChange: function (value) {
                setAttributes({ defaultView: value });
              }
            }),
            createElement(ToggleControl, {
              label: 'Show intro',
              checked: attributes.showIntro !== false,
              onChange: function (value) {
                setAttributes({ showIntro: value });
              }
            }),
            createElement(ToggleControl, {
              label: 'Show feedback',
              checked: attributes.showFeedback !== false,
              onChange: function (value) {
                setAttributes({ showFeedback: value });
              }
            }),
            createElement(ToggleControl, {
              label: 'Enable export',
              checked: attributes.enableExport !== false,
              onChange: function (value) {
                setAttributes({ enableExport: value });
              }
            }),
            createElement(TextControl, {
              label: 'Limit to area',
              value: attributes.limitArea || '',
              onChange: function (value) {
                setAttributes({ limitArea: value });
              }
            }),
            createElement(TextControl, {
              label: 'Limit to status',
              value: attributes.limitStatus || '',
              onChange: function (value) {
                setAttributes({ limitStatus: value });
              }
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement('strong', null, 'Schöneweide Register'),
          createElement('p', null, 'Frontend wird dynamisch über REST geladen.')
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp);
