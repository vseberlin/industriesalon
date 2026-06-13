(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-player', {
    apiVersion: 3,
    title: 'ISS Video Player',
    category: 'widgets',
    icon: 'controls-play',
    description: 'Server-rendered player and metadata for the current Video CPT entry.',
    keywords: ['video', 'player', 'single'],
    usesContext: ['postId', 'postType'],
    supports: {
      html: false,
    },
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video Player', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Use inside the single-video template.') : el('p', null, 'Use inside the single-video template.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Player'), el('p', null, 'Renders the current single Video CPT player.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
