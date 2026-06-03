(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
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
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video Filters', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Reads published Video CPT categories.') : el('p', null, 'Reads published Video CPT categories.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Filters'), el('p', null, 'Renders thematic jump links for the video library.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
