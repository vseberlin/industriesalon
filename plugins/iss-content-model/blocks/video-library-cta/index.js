(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-cta', {
    apiVersion: 3,
    title: 'ISS Video CTA',
    category: 'widgets',
    icon: 'megaphone',
    description: 'Server-rendered video library call-to-action.',
    keywords: ['video', 'cta', 'collection'],
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
      linkUrl: {
        type: 'string',
        default: '',
      },
      linkLabel: {
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
                { title: 'ISS Video CTA', initialOpen: true },
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
                TextControl
                  ? el(TextControl, {
                      label: 'Link URL',
                      value: attrs.linkUrl || '',
                      onChange: function (value) {
                        setAttributes({ linkUrl: value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Link label',
                      value: attrs.linkLabel || '',
                      onChange: function (value) {
                        setAttributes({ linkLabel: value });
                      },
                    })
                  : null,
                Notice ? el(Notice, { status: 'info', isDismissible: false }, 'This block renders only the editorial CTA fields you fill here.') : null
              )
            )
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video CTA'), el('p', null, 'Renders the video library call-to-action.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
