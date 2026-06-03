(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-filters', {
    apiVersion: 3,
    title: 'ISS Video Filters',
    category: 'widgets',
    icon: 'filter',
    description: 'Server-rendered thematic jump links for the video library.',
    keywords: ['video', 'filter', 'category'],
    supports: {
      html: false,
    },
    attributes: {
      ariaLabel: {
        type: 'string',
        default: '',
      },
      externalLabel: {
        type: 'string',
        default: '',
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
                { title: 'ISS Video Filters', initialOpen: true },
                TextControl
                  ? el(TextControl, {
                      label: 'ARIA label',
                      value: attrs.ariaLabel || '',
                      onChange: function (value) {
                        setAttributes({ ariaLabel: value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'External group label',
                      value: attrs.externalLabel || '',
                      onChange: function (value) {
                        setAttributes({ externalLabel: value });
                      },
                    })
                  : null,
                Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Filter names come from video categories.') : null
              )
            )
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Filters'), el('p', null, 'Renders thematic jump links for the video library.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
