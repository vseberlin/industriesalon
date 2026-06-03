(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-playlists', {
    apiVersion: 3,
    title: 'ISS Video Playlists',
    category: 'widgets',
    icon: 'playlist-video',
    description: 'Server-rendered grouped video playlist rails.',
    keywords: ['video', 'playlist', 'rail'],
    supports: {
      html: false,
    },
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video Playlists', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Reads published Video CPT entries and categories.') : el('p', null, 'Reads published Video CPT entries and categories.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Playlists'), el('p', null, 'Renders grouped video playlist rails.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
