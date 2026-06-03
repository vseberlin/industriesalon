(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-inventory', {
    apiVersion: 3,
    title: 'ISS Video Inventory',
    category: 'widgets',
    icon: 'screenoptions',
    description: 'Server-rendered complete video inventory strip.',
    keywords: ['video', 'inventory', 'library'],
    supports: {
      html: false,
    },
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video Inventory', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Intended as a secondary inventory surface.') : el('p', null, 'Intended as a secondary inventory surface.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Inventory'), el('p', null, 'Renders the complete video inventory strip.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
