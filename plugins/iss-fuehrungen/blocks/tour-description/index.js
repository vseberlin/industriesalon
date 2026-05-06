(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;

  window.wp.blocks.registerBlockType('iss/tour-description', {
    edit: function () {
      return el(
        'div',
        { className: 'components-placeholder wp-block-iss-tour-description-editor' },
        el('strong', null, 'Tour Description'),
        el(
          'p',
          null,
          'Rendert den textbasierten Führungsinhalt ohne eingebettete Zusatzbereiche wie verwandte Orte oder Medienblöcke.'
        )
      );
    },
    save: function () {
      return null;
    },
  });
})();
