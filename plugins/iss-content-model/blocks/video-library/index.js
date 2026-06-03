(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library', {
    apiVersion: 3,
    title: 'ISS Video Library',
    category: 'widgets',
    icon: 'video-alt3',
    description: 'Server-rendered complete video library surface.',
    keywords: ['video', 'library', 'mediathek'],
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
      showDescriptions: {
        type: 'boolean',
        default: true,
      },
      showCounts: {
        type: 'boolean',
        default: true,
      },
      externalKicker: {
        type: 'string',
        default: '',
      },
      externalTitle: {
        type: 'string',
        default: '',
      },
      externalText: {
        type: 'string',
        default: '',
      },
      externalShowCounts: {
        type: 'boolean',
        default: true,
      },
      ctaKicker: {
        type: 'string',
        default: '',
      },
      ctaTitle: {
        type: 'string',
        default: '',
      },
      ctaText: {
        type: 'string',
        default: '',
      },
      ctaLinkUrl: {
        type: 'string',
        default: '',
      },
      ctaLinkLabel: {
        type: 'string',
        default: '',
      },
    },
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes;
      const textControl = function (label, key) {
        return TextControl
          ? el(TextControl, {
              label: label,
              value: attrs[key] || '',
              onChange: function (value) {
                const next = {};
                next[key] = value;
                setAttributes(next);
              },
            })
          : null;
      };
      const textareaControl = function (label, key) {
        return TextareaControl
          ? el(TextareaControl, {
              label: label,
              value: attrs[key] || '',
              onChange: function (value) {
                const next = {};
                next[key] = value;
                setAttributes(next);
              },
            })
          : null;
      };
      const toggleControl = function (label, key) {
        return ToggleControl
          ? el(ToggleControl, {
              label: label,
              checked: attrs[key] !== false,
              onChange: function (value) {
                const next = {};
                next[key] = value;
                setAttributes(next);
              },
            })
          : null;
      };

      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'ISS Video Library', initialOpen: true },
                textControl('Filter aria label', 'ariaLabel'),
                textControl('External filter label', 'externalLabel'),
                toggleControl('Show category descriptions', 'showDescriptions'),
                toggleControl('Show playlist counts', 'showCounts'),
                textControl('External kicker', 'externalKicker'),
                textControl('External title', 'externalTitle'),
                textareaControl('External text', 'externalText'),
                toggleControl('Show external count', 'externalShowCounts'),
                textControl('CTA kicker', 'ctaKicker'),
                textControl('CTA title', 'ctaTitle'),
                textareaControl('CTA text', 'ctaText'),
                textControl('CTA link URL', 'ctaLinkUrl'),
                textControl('CTA link label', 'ctaLinkLabel'),
                Notice ? el(Notice, { status: 'info', isDismissible: false }, 'The complete block is a compatibility surface; the Videos template uses the smaller component blocks for clearer editing.') : null
              )
            )
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Library'), el('p', null, 'Renders the complete video library surface.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
