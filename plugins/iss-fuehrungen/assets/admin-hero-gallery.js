(function ($) {
  function parseIds(value) {
    if (!value) return [];
    return value
      .split(',')
      .map(function (item) {
        return parseInt(item, 10);
      })
      .filter(function (item) {
        return Number.isInteger(item) && item > 0;
      });
  }

  function renderPreview($field, ids) {
    var $preview = $field.find('.iss-hero-gallery-preview');
    $preview.empty();

    if (!ids.length) return;

    ids.forEach(function (id) {
      var attachment = wp.media.attachment(id);
      attachment.fetch().done(function () {
        var data = attachment.toJSON();
        var thumb = data && data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
        if (!thumb) return;

        $('<img />', {
          src: thumb,
          alt: data.alt || '',
          css: {
            width: '52px',
            height: '52px',
            objectFit: 'cover',
            borderRadius: '4px',
            border: '1px solid #dcdcde',
          },
        }).appendTo($preview);
      });
    });
  }

  function initField($field) {
    var inputId = $field.data('input-id');
    var $input = $('#' + inputId);
    if (!$input.length) return;

    renderPreview($field, parseIds($input.val()));

    $field.on('click', '.iss-hero-gallery-clear', function (event) {
      event.preventDefault();
      $input.val('');
      renderPreview($field, []);
    });

    $field.on('click', '.iss-hero-gallery-select', function (event) {
      event.preventDefault();

      var frame = wp.media({
        title: 'Hero-Galerie auswählen',
        button: { text: 'Bilder übernehmen' },
        multiple: true,
        library: { type: 'image' },
      });

      frame.on('open', function () {
        var selection = frame.state().get('selection');
        parseIds($input.val()).forEach(function (id) {
          var attachment = wp.media.attachment(id);
          attachment.fetch();
          selection.add(attachment);
        });
      });

      frame.on('select', function () {
        var selection = frame.state().get('selection');
        var selectedIds = selection
          .map(function (attachment) {
            if (!attachment) return 0;
            return attachment.id || attachment.get('id') || 0;
          })
          .filter(function (id) {
            return id > 0;
          });

        var existingIds = parseIds($input.val());
        var ids = existingIds.concat(selectedIds).filter(function (id, index, items) {
          return items.indexOf(id) === index;
        });

        $input.val(ids.join(','));
        renderPreview($field, ids);
      });

      frame.open();
    });
  }

  $(function () {
    if (!window.wp || !wp.media) return;
    $('.iss-hero-gallery-field').each(function () {
      initField($(this));
    });
  });
})(window.jQuery);
