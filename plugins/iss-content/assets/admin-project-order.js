(function ($) {
  var config = window.issContentProjectOrder || {};
  var request = null;
  var originalRows = [];

  function isEnabled(value) {
    return value === true || value === '1' || value === 1;
  }

  function text(key, fallback) {
    return (config.strings && config.strings[key]) || fallback || key;
  }

  function tableBody() {
    return $('#the-list');
  }

  function projectRows() {
    return tableBody().children('tr.type-projekt');
  }

  function orderedIds() {
    return projectRows().map(function () {
      return parseInt($(this).find('.iss-project-order-handle').attr('data-post-id'), 10) || 0;
    }).get().filter(Boolean);
  }

  function notice(message, type) {
    var wrap = $('.wrap').first();
    var node = $('.iss-project-order-notice');

    if (!wrap.length) {
      return;
    }

    if (!node.length) {
      node = $('<div class="notice iss-project-order-notice"><p></p></div>');
      wrap.find('h1').first().after(node);
    }

    node
      .removeClass('notice-success notice-error notice-info')
      .addClass('notice-' + (type || 'info'))
      .find('p')
      .text(message || '');
  }

  function setSaving(isSaving) {
    $('body').toggleClass('iss-project-order-saving', !!isSaving);
    $('.iss-project-order-handle').prop('disabled', !!isSaving);
  }

  function restoreRows() {
    var body = tableBody();

    originalRows.forEach(function (row) {
      body.append(row);
    });
  }

  function applyOrders(orders) {
    if (!orders) {
      return;
    }

    $('.iss-project-order-handle').each(function () {
      var id = parseInt($(this).attr('data-post-id'), 10) || 0;
      var value = orders[id] || orders[String(id)] || '';
      if (value !== '') {
        $(this).siblings('.iss-project-order-value').text(value);
      }
    });
  }

  function saveOrder() {
    var ids = orderedIds();

    if (request) {
      return;
    }

    setSaving(true);
    notice(text('saving', 'Saving project order ...'), 'info');

    request = $.ajax({
      url: config.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'iss_content_project_reorder',
        nonce: config.nonce || '',
        post_ids: ids
      }
    }).done(function (response) {
      if (!response || !response.success) {
        restoreRows();
        notice((response && response.data && response.data.message) || text('error', 'Project order could not be saved.'), 'error');
        return;
      }

      applyOrders(response.data && response.data.orders);
      notice((response.data && response.data.message) || text('saved', 'Project order saved.'), 'success');
    }).fail(function (xhr, status) {
      var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;

      if (status !== 'abort') {
        restoreRows();
        notice((response && response.data && response.data.message) || text('error', 'Project order could not be saved.'), 'error');
      }
    }).always(function () {
      setSaving(false);
      request = null;
    });
  }

  function moveFocusedRow(button, direction) {
    var row = button.closest('tr.type-projekt');
    var target = direction < 0 ? row.prev('tr.type-projekt') : row.next('tr.type-projekt');

    if (!target.length) {
      return;
    }

    originalRows = projectRows().get();
    if (direction < 0) {
      row.insertBefore(target);
    } else {
      row.insertAfter(target);
    }
    button.trigger('focus');
    saveOrder();
  }

  $(function () {
    var body = tableBody();
    var rows = projectRows();

    if (!isEnabled(config.enabled) || !body.length || rows.length < 2) {
      return;
    }

    $('body').addClass('iss-project-order-enabled');

    body.sortable({
      axis: 'y',
      cancel: 'input, textarea, select, option, a',
      cursor: 'move',
      handle: '.iss-project-order-handle',
      helper: function (event, row) {
        row.children().each(function () {
          $(this).width($(this).width());
        });
        return row;
      },
      items: '> tr.type-projekt',
      placeholder: 'iss-project-order-placeholder',
      start: function (event, ui) {
        originalRows = projectRows().get();
        ui.placeholder.height(ui.item.outerHeight());
      },
      update: saveOrder
    });

    body.on('keydown', '.iss-project-order-handle', function (event) {
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveFocusedRow($(this), -1);
      } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveFocusedRow($(this), 1);
      }
    });
  });
}(jQuery));
