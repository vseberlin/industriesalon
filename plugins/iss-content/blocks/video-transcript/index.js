(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const InnerBlocks = window.wp.blockEditor && window.wp.blockEditor.InnerBlocks;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-transcript', {
    apiVersion: 3,
    title: 'ISS Video Transcript',
    category: 'widgets',
    icon: 'text-page',
    description: 'Server-rendered transcript or context text for the current Video CPT entry.',
    keywords: ['video', 'transcript', 'timecode'],
    usesContext: ['postId', 'postType'],
    supports: {
      html: false,
    },
    attributes: {
      railKicker: {
        type: 'string',
        default: '',
      },
      timecodeNavLabel: {
        type: 'string',
        default: '',
      },
      emptyRailText: {
        type: 'string',
        default: '',
      },
      transcriptKicker: {
        type: 'string',
        default: '',
      },
      transcriptTitle: {
        type: 'string',
        default: '',
      },
      contextKicker: {
        type: 'string',
        default: '',
      },
      contextTitle: {
        type: 'string',
        default: '',
      },
      relatedAriaLabel: {
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

      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'ISS Video Transcript', initialOpen: true },
                textControl('Rail kicker', 'railKicker'),
                textControl('Timecode navigation label', 'timecodeNavLabel'),
                textControl('Empty rail text', 'emptyRailText'),
                textControl('Transcript kicker', 'transcriptKicker'),
                textControl('Transcript title', 'transcriptTitle'),
                textControl('Context kicker', 'contextKicker'),
                textControl('Context title', 'contextTitle'),
                textControl('Related aria label', 'relatedAriaLabel'),
                Notice ? el(Notice, { status: 'info', isDismissible: false }, 'The transcript body is read from the current Video CPT entry.') : null
              )
            )
          : null,
        el(
          'div',
          { className: 'components-placeholder iss-content-model-video-block-editor' },
          el('strong', null, 'ISS Video Transcript'),
          el('p', null, 'Renders the current Video CPT body as transcript or context text. Add rail links below the chapters.')
        ),
        InnerBlocks
          ? el(InnerBlocks, {
              allowedBlocks: ['iss/related-content'],
              templateLock: false,
            })
          : null
      );
    },
    save: function () {
      return InnerBlocks ? el(InnerBlocks.Content) : null;
    },
  });
})();
