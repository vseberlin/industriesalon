(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
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
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video Library', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Use on the Videos landing page only.') : el('p', null, 'Use on the Videos landing page only.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Library'), el('p', null, 'Renders the complete video library surface.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
