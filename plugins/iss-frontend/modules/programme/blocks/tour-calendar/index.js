(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;

  window.wp.blocks.registerBlockType('iss/tour-calendar', {
    edit: function () {
      return el('div', { className: 'is-tour-calendar' }, 'Interactive Calendar');
    },
    save: function () {
      return null;
    },
  });
})();

