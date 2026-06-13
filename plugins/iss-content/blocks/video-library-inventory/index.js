(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-inventory', {
    apiVersion: 3,
    title: 'ISS Video Inventory',
    category: 'widgets',
    icon: 'screenoptions',
    description: 'Server-rendered complete video inventory strip.',
    keywords: ['video', 'inventory', 'library'],
    supports: {
      html: false,
    },
    attributes: {
      kicker: {
        type: 'string',
        default: '',
      },
      title: {
        type: 'string',
        default: '',
      },
      text: {
        type: 'string',
        default: '',
      },
      showCounts: {
        type: 'boolean',
        default: true,
      },
    },
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes;

      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'ISS Video Inventory', initialOpen: true },
                TextControl
                  ? el(TextControl, {
                      label: 'Kicker',
                      value: attrs.kicker || '',
                      onChange: function (value) {
                        setAttributes({ kicker: value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Title',
                      value: attrs.title || '',
                      onChange: function (value) {
                        setAttributes({ title: value });
                      },
                    })
                  : null,
                TextareaControl
                  ? el(TextareaControl, {
                      label: 'Text',
                      value: attrs.text || '',
                      onChange: function (value) {
                        setAttributes({ text: value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show video count',
                      checked: attrs.showCounts !== false,
                      onChange: function (value) {
                        setAttributes({ showCounts: value });
                      },
                    })
                  : null,
                Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Video rows come from the full published Video CPT inventory.') : null
              )
            )
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Inventory'), el('p', null, 'Renders the complete video inventory strip.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
