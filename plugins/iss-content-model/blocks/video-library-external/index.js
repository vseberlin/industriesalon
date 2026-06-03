(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-external', {
    apiVersion: 3,
    title: 'ISS Video External Reports',
    category: 'widgets',
    icon: 'external',
    description: 'Server-rendered external video reports section.',
    keywords: ['video', 'external', 'reports'],
    supports: {
      html: false,
    },
    edit: function () {
      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(InspectorControls, null, el(PanelBody, { title: 'ISS Video External Reports', initialOpen: true }, Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Uses the current video source-family grouping.') : el('p', null, 'Uses the current video source-family grouping.')))
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video External Reports'), el('p', null, 'Renders externally sourced video reports.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
