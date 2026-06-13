(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-external', {
    apiVersion: 3,
    title: 'ISS Video External Reports',
    category: 'widgets',
    icon: 'external',
    description: 'Server-rendered external video reports section.',
    keywords: ['video', 'external', 'reports'],
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
                { title: 'ISS Video External Reports', initialOpen: true },
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
                Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Video rows come from the external-report source family.') : null
              )
            )
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video External Reports'), el('p', null, 'Renders externally sourced video reports.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
