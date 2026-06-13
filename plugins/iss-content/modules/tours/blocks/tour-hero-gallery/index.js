(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;

  window.wp.blocks.registerBlockType('iss/tour-hero-gallery', {
    edit: function () {
      return el('p', null, 'Tour Hero Gallery (frontend render).');
    },
    save: function () {
      return null;
    },
  });
})();
