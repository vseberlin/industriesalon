(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType;
  var useBlockProps = blockEditor.useBlockProps;
  var Placeholder = components.Placeholder;
  var createElement = element.createElement;
  var __ = i18n.__;

  registerBlockType('iss-register/schoneweide-atlas', {
    apiVersion: 3,
    title: __('Schoneweide Atlas', 'iss-register'),
    description: __('Reusable public Schoneweide Atlas.', 'iss-register'),
    icon: 'location-alt',
    category: 'widgets',
    supports: {
      html: false,
      align: ['wide', 'full']
    },
    edit: function () {
      return createElement(
        'div',
        useBlockProps(),
        createElement(
          Placeholder,
          {
            icon: 'location-alt',
            label: __('Schoneweide Atlas', 'iss-register'),
            instructions: __('Renders the reusable public Atlas on the frontend.', 'iss-register')
          },
          createElement(
            'p',
            null,
            __('The Atlas shell, assets, and runtime are provided by the plugin render callback.', 'iss-register')
          )
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(
  window.wp.blocks,
  window.wp.blockEditor,
  window.wp.components,
  window.wp.element,
  window.wp.i18n
);
