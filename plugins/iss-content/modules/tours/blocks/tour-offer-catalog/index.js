(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;

  window.wp.blocks.registerBlockType('iss/tour-offer-catalog', {
    edit: function () {
      return el('p', null, 'Tour Offer Catalog (frontend render).');
    },
    save: function () {
      return null;
    },
  });
})();
