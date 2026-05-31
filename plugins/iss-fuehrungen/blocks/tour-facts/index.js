(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;

  window.wp.blocks.registerBlockType('iss/tour-facts', {
    edit: function () {
      return el('p', null, 'Tour Facts (frontend render).');
    },
    save: function () {
      return null;
    },
  });
})();

