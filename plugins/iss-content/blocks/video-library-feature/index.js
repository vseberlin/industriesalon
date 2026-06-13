(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-feature', {
    apiVersion: 3,
    title: 'ISS Video Feature',
    category: 'widgets',
    icon: 'format-video',
    description: 'Server-rendered active video feature, player, metadata, transcript, and chapters panel.',
    keywords: ['video', 'player', 'transcript'],
    supports: {
      html: false,
    },
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video Feature', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Pair with filters, playlists, external reports, and CTA blocks.') : el('p', null, 'Pair with filters, playlists, external reports, and CTA blocks.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Feature'), el('p', null, 'Renders the active video player and metadata panel.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
