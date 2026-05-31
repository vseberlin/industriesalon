(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;

  window.wp.blocks.registerBlockType('iss/tour-route', {
    edit: function () {
      return el('p', null, 'Tour Route (frontend render).');
    },
    save: function () {
      return null;
    },
  });
})();
