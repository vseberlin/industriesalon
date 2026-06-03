(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const InnerBlocks = window.wp.blockEditor && window.wp.blockEditor.InnerBlocks;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
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
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video Transcript', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Use inside the single-video template.') : el('p', null, 'Use inside the single-video template.')))
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
