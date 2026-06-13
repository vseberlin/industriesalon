(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;

  window.wp.blocks.registerBlockType('iss/tour-dates', {
    edit: function () {
      return el('p', null, 'Tour Dates (frontend render).');
    },
    save: function () {
      return null; // dynamic (rendered by PHP/theme later)
    },
  });
})();

