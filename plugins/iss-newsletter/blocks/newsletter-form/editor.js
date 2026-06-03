(function (blocks, element, i18n) {
  const el = element.createElement;
  const __ = i18n.__;

  blocks.registerBlockType('iss/newsletter-form', {
    edit: function () {
      return el(
        'div',
        { className: 'iss-newsletter-form-editor' },
        el('strong', {}, __('Newsletter-Anmeldung', 'iss-newsletter')),
        el('p', {}, __('Rendert das Formular von The Newsletter Plugin auf der Website.', 'iss-newsletter'))
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.i18n);
