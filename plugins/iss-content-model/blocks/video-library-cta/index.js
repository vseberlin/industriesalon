(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
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
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video CTA', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Static server-rendered copy.') : el('p', null, 'Static server-rendered copy.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video CTA'), el('p', null, 'Renders the video library call-to-action.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
