(function () {
  function parseJsonArray(raw) {
    if (!raw) {
      return [];
    }

    try {
      var decoded = JSON.parse(raw);
      return Array.isArray(decoded) ? decoded : [];
    } catch (error) {
      return [];
    }
  }

  function normalizeBoolean(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
  }

  function createRow(item) {
    var row = document.createElement('div');
    row.className = 'iss-register-image-row';
    row.innerHTML =
      '<div class="iss-register-image-row__top">' +
        '<div class="iss-register-image-row__preview"><img alt="" /></div>' +
        '<div class="iss-register-image-row__controls">' +
          '<div><button type="button" class="button iss-image-select">Bild wählen</button> <button type="button" class="button-link-delete iss-image-remove">Entfernen</button></div>' +
          '<label>Media ID <input type="number" class="small-text iss-image-media-id" readonly></label>' +
          '<label>URL <input type="text" class="widefat iss-image-url" readonly></label>' +
        '</div>' +
      '</div>' +
      '<div class="iss-register-image-row__fields">' +
        '<label>Caption <input type="text" class="widefat iss-image-caption"></label>' +
        '<label>Jahr <input type="text" class="widefat iss-image-year"></label>' +
        '<label>Quelle <input type="text" class="widefat iss-image-source"></label>' +
        '<label>Fotograf/in <input type="text" class="widefat iss-image-photographer"></label>' +
        '<label>Rechte <input type="text" class="widefat iss-image-rights"></label>' +
        '<label>Sichtbarkeit <select class="iss-image-visibility"><option value="private">private</option><option value="pending">pending</option><option value="public">public</option></select></label>' +
        '<label class="iss-image-featured-wrap"><input type="checkbox" class="iss-image-featured"> Featured</label>' +
      '</div>';

    var mediaIdInput = row.querySelector('.iss-image-media-id');
    var urlInput = row.querySelector('.iss-image-url');
    var previewImage = row.querySelector('.iss-register-image-row__preview img');

    var mediaId = parseInt(item.media_id || 0, 10);
    mediaIdInput.value = mediaId > 0 ? String(mediaId) : '';
    urlInput.value = item.url || '';
    row.querySelector('.iss-image-caption').value = item.caption || '';
    row.querySelector('.iss-image-year').value = item.year || '';
    row.querySelector('.iss-image-source').value = item.source || '';
    row.querySelector('.iss-image-photographer').value = item.photographer || '';
    row.querySelector('.iss-image-rights').value = item.rights || '';
    row.querySelector('.iss-image-visibility').value = item.visibility || 'private';
    row.querySelector('.iss-image-featured').checked = normalizeBoolean(item.is_featured);

    if (item.url) {
      previewImage.src = item.url;
      previewImage.style.display = 'block';
    } else {
      previewImage.style.display = 'none';
    }

    return row;
  }

  function readRow(row) {
    return {
      media_id: parseInt(row.querySelector('.iss-image-media-id').value || '0', 10),
      url: row.querySelector('.iss-image-url').value || '',
      caption: row.querySelector('.iss-image-caption').value || '',
      year: row.querySelector('.iss-image-year').value || '',
      source: row.querySelector('.iss-image-source').value || '',
      photographer: row.querySelector('.iss-image-photographer').value || '',
      rights: row.querySelector('.iss-image-rights').value || '',
      is_featured: !!row.querySelector('.iss-image-featured').checked,
      visibility: row.querySelector('.iss-image-visibility').value || 'private'
    };
  }

  function updateEmptyState(group) {
    var existing = group.container.querySelector('.iss-register-image-group__empty');
    if (existing) {
      existing.remove();
    }

    var rowCount = group.rows.querySelectorAll('.iss-register-image-row').length;
    if (rowCount === 0) {
      var empty = document.createElement('p');
      empty.className = 'iss-register-image-group__empty';
      empty.textContent = 'Keine Bilder hinterlegt.';
      group.container.insertBefore(empty, group.rows.nextSibling);
    }
  }

  function syncGroup(group) {
    var rows = Array.prototype.slice.call(group.rows.querySelectorAll('.iss-register-image-row'));
    var payload = rows.map(readRow).filter(function (item) {
      return item.media_id > 0;
    });

    group.input.value = JSON.stringify(payload);
    updateEmptyState(group);
  }

  function bindRowEvents(group, row) {
    row.querySelectorAll('input,select').forEach(function (input) {
      input.addEventListener('input', function () {
        syncGroup(group);
      });
      input.addEventListener('change', function () {
        syncGroup(group);
      });
    });

    row.querySelector('.iss-image-remove').addEventListener('click', function () {
      row.remove();
      syncGroup(group);
    });

    row.querySelector('.iss-image-select').addEventListener('click', function () {
      if (!window.wp || !window.wp.media) {
        return;
      }

      var frame = wp.media({
        title: 'Bild auswählen',
        button: { text: 'Bild verwenden' },
        multiple: false,
        library: { type: 'image' }
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        var mediaIdInput = row.querySelector('.iss-image-media-id');
        var urlInput = row.querySelector('.iss-image-url');
        var captionInput = row.querySelector('.iss-image-caption');
        var previewImage = row.querySelector('.iss-register-image-row__preview img');

        mediaIdInput.value = attachment.id ? String(attachment.id) : '';
        urlInput.value = attachment.url || '';

        if (!captionInput.value) {
          if (typeof attachment.caption === 'string') {
            captionInput.value = attachment.caption;
          } else if (attachment.caption && attachment.caption.raw) {
            captionInput.value = attachment.caption.raw;
          } else if (attachment.caption && attachment.caption.rendered) {
            captionInput.value = attachment.caption.rendered;
          }
        }

        if (attachment.url) {
          previewImage.src = attachment.url;
          previewImage.style.display = 'block';
        } else {
          previewImage.style.display = 'none';
        }

        syncGroup(group);
      });

      frame.open();
    });
  }

  function addRow(group, item) {
    var row = createRow(item || {});
    group.rows.appendChild(row);
    bindRowEvents(group, row);
    syncGroup(group);
  }

  function initGroup(container) {
    var field = container.getAttribute('data-field');
    if (!field) {
      return;
    }

    var input = document.getElementById('iss-register-' + field);
    if (!input) {
      return;
    }

    var rows = container.querySelector('.iss-register-image-group__rows');
    var addButton = container.querySelector('.iss-register-image-group__add');
    if (!rows || !addButton) {
      return;
    }

    var group = {
      container: container,
      input: input,
      rows: rows
    };

    var items = parseJsonArray(input.value);
    items.forEach(function (item) {
      addRow(group, item);
    });
    updateEmptyState(group);

    addButton.addEventListener('click', function () {
      addRow(group, {
        media_id: 0,
        url: '',
        caption: '',
        year: '',
        source: '',
        photographer: '',
        rights: '',
        is_featured: false,
        visibility: 'private'
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.iss-register-image-group').forEach(initGroup);
  });
})();
