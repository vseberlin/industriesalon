/* Archive, media and album controls use the existing owner pickers and APIs. */
(function () {
  function create(options) {
    var editorUi = window.issEditorialUi;
    var createTextInput = editorUi.createTextInput, createTextarea = editorUi.createTextarea;
    var config = options.config;
    var postId = options.postId;
    var sections = options.sections;
    var sectionEditor = options.sectionEditor;
    var render = options.render;
    var scheduleAutosave = options.scheduleAutosave;
    var collectionCount = options.collectionCount;
    var createElement = options.createElement;
    var clear = options.clear;

    function referenceFromArchiveItem(item) {
      return {
        kind: 'archive_object',
        source: 'iss-archive',
        id: String(item.id || ''),
        label: item.title || '',
        thumbnail: item.thumbnail || '',
        set_id: item.setId ? String(item.setId) : '',
        set_title: item.setTitle || '',
        member_id: item.memberId ? String(item.memberId) : '',
        member_caption: item.memberCaption || ''
      };
    }

    function referenceFromMediaAttachment(attachment) {
      var sizes = attachment.sizes || {};
      var thumbnail = (sizes.medium && sizes.medium.url) || (sizes.thumbnail && sizes.thumbnail.url) || attachment.url || '';
      return {
        kind: 'media',
        source: 'wp-media',
        id: String(attachment.id || ''),
        label: attachment.title || attachment.caption || '',
        thumbnail: thumbnail,
        width: attachment.width ? String(attachment.width) : '',
        height: attachment.height ? String(attachment.height) : '',
        mime: attachment.mime || ''
      };
    }

    function renderReferenceTray(section, target, rerender) {
      clear(target);
      (section.object_refs || []).forEach(function (reference, index) {
        var item = createElement('div', 'iss-editorial-ref');
        var label = createElement('span', '', reference.label || 'Ausgewähltes Archivobjekt');
        var remove = createElement('button', 'button button-link-delete', 'Entfernen');
        remove.type = 'button';
        remove.addEventListener('click', function () {
          section.object_refs.splice(index, 1);
          rerender();
          render();
          scheduleAutosave();
        });
        if (sectionEditor(section.type).reference_captions) {
          item.className = 'iss-editorial-media-item';
          var preview = createElement('div', 'iss-editorial-media-item__preview');
          if (reference.thumbnail) {
            var image = document.createElement('img');
            image.src = reference.thumbnail;
            image.alt = '';
            preview.appendChild(image);
          }
          var controls = createElement('div', 'iss-editorial-media-item__controls');
          controls.appendChild(createTextInput('Beschriftung', reference.label || '', function (value) {
            reference.label = value;
            render();
            scheduleAutosave();
          }));
          controls.appendChild(createTextarea('Bildunterschrift / Kontext', reference.member_caption || '', function (value) {
            reference.member_caption = value;
            render();
            scheduleAutosave();
          }, 3));
          controls.appendChild(remove);
          item.appendChild(preview);
          item.appendChild(controls);
        } else {
          item.appendChild(label);
          item.appendChild(remove);
        }
        target.appendChild(item);
      });

      if (!(section.object_refs || []).length) {
        target.appendChild(createElement('p', 'description', 'Noch kein Archivobjekt ausgewählt.'));
      }
    }

    function mediaRatioText(reference) {
      var width = parseInt(reference.width || '0', 10);
      var height = parseInt(reference.height || '0', 10);
      if (!width || !height) {
        return '';
      }

      return String(width) + ' x ' + String(height);
    }

    function mediaIsNearSixteenNine(reference) {
      var width = parseInt(reference.width || '0', 10);
      var height = parseInt(reference.height || '0', 10);
      var ratio = height ? width / height : 0;

      return ratio > 1.7 && ratio < 1.85;
    }

    function mediaReferenceIsImage(reference) {
      return String(reference && reference.mime ? reference.mime : '').indexOf('image/') === 0;
    }

    function sectionUsesViewportGallery(section) {
      return options.presentationValue(section, 'gallery_layout') === 'viewport' || Object.keys(sections).some(function (type) {
        return ((sections[type].legacy_types || {})[section.type] || {}).gallery_layout === 'viewport';
      });
    }

    function sectionMediaRefsForDisplay(section) {
      var refs = section && Array.isArray(section.media_refs) ? section.media_refs : [];
      if (!sectionEditor(section.type).media_hide_images) {
        return refs;
      }

      return refs.filter(function (reference) {
        return !mediaReferenceIsImage(reference);
      });
    }

    function renderMediaTray(section, target, rerender) {
      var isFullViewport = sectionUsesViewportGallery(section);
      var isMaterial = sectionEditor(section.type).media_kind === 'files';
      clear(target);
      if (isFullViewport) {
        target.appendChild(createElement(
          'p',
          'description iss-editorial-media-rule',
          'Vollbild verwendet genau ein 16:9-Bild. Andere Formate werden vollflächig beschnitten.'
        ));
      }
      if (sectionEditor(section.type).media_hint) {
        target.appendChild(createElement('p', 'description iss-editorial-media-rule', sectionEditor(section.type).media_hint));
      }
      (section.media_refs || []).forEach(function (reference, index) {
        var item = createElement('article', 'iss-editorial-media-item');
        var preview = createElement('div', 'iss-editorial-media-item__preview');
        var controls = createElement('div', 'iss-editorial-media-item__controls');
        var remove = createElement('button', 'button button-link-delete', 'Entfernen');
        var ratioText = mediaRatioText(reference);
        var ratioWarning = isFullViewport && !mediaIsNearSixteenNine(reference);
        if (reference.thumbnail) {
          var image = document.createElement('img');
          image.src = reference.thumbnail;
          image.alt = '';
          preview.appendChild(image);
        } else {
          preview.textContent = isMaterial ? 'Datei' : 'Bild';
        }

        controls.appendChild(createTextInput(isMaterial ? 'Dateiname / Label' : 'Bildunterschrift', reference.label || '', function (value) {
          reference.label = value;
          render();
          scheduleAutosave();
        }));
        if (sectionEditor(section.type).media_hide_images && mediaReferenceIsImage(reference)) {
          controls.appendChild(createElement('p', 'description is-warning', 'Bild wird im Material nicht gerendert.'));
        }
        if (ratioText) {
          controls.appendChild(createElement(
            'p',
            'description iss-editorial-media-ratio' + (ratioWarning ? ' is-warning' : ''),
            ratioWarning ? ratioText + ' - kein 16:9, wird beschnitten.' : ratioText
          ));
        }

        remove.type = 'button';
        remove.addEventListener('click', function () {
          section.media_refs.splice(index, 1);
          rerender();
          render();
          scheduleAutosave();
        });
        controls.appendChild(remove);

        item.appendChild(preview);
        item.appendChild(controls);
        target.appendChild(item);
      });

      if (!(section.media_refs || []).length) {
        target.appendChild(createElement('p', 'description', isMaterial ? 'Noch keine Dateien ausgewählt.' : 'Noch keine Bilder ausgewählt.'));
      }
    }

    function renderObjectPicker(section, body, setCount) {
      var refs = createElement('div', 'iss-editorial-field iss-editorial-field--refs');
      var tray = createElement('div', 'iss-editorial-ref-tray');
      var pickerMount = createElement('div', 'iss-editorial-picker');
      var pickerButton = createElement('button', 'button', 'Archivobjekte auswählen');

      function rerenderTray() {
        renderReferenceTray(section, tray, rerenderTray);
        setCount(collectionCount(section, 'object_refs'));
      }

      pickerButton.type = 'button';
      pickerButton.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (!window.issArchiveObjectPicker || !window.issArchiveObjectPicker.create) {
          pickerMount.textContent = 'Archiv-Auswahl ist nicht geladen.';
          return;
        }
        window.issArchiveObjectPicker.create(pickerMount, {
          mode: 'multiple',
          modal: true,
          bucketFirst: true,
          manageModalFocus: editorUi.manageModalFocus,
          contextPostId: postId,
          initialSelection: (section.object_refs || []).map(function (reference) { return reference.id; }),
          onConfirm: function (items) {
            var previous = section.object_refs || [];
            section.object_refs = (items || []).map(function (item) {
              return previous.find(function (reference) { return String(reference.id) === String(item.id); }) || referenceFromArchiveItem(item);
            }).filter(function (reference) {
              return reference.id;
            });
            rerenderTray();
            render();
            scheduleAutosave();
          }
        });
      });

      refs.appendChild(tray);
      refs.appendChild(pickerButton);
      refs.appendChild(pickerMount);
      body.appendChild(refs);
      rerenderTray();
    }

    function renderGatewayItemMedia(item, target, rerender) {
      clear(target);
      item.media_refs = Array.isArray(item.media_refs) ? item.media_refs : [];

      if (!item.media_refs.length) {
        target.appendChild(createElement('p', 'description', 'Kein Bild ausgewählt.'));
        return;
      }

      item.media_refs.slice(0, 1).forEach(function (reference) {
        var preview = createElement('div', 'iss-editorial-media-thumb');
        var remove = createElement('button', 'button button-link-delete', 'Bild entfernen');
        if (reference.thumbnail) {
          var image = document.createElement('img');
          image.src = reference.thumbnail;
          image.alt = '';
          preview.appendChild(image);
        } else {
          preview.textContent = reference.label || 'Bild';
        }
        remove.type = 'button';
        remove.addEventListener('click', function () {
          item.media_refs = [];
          rerender();
          render();
          scheduleAutosave();
        });
        target.appendChild(preview);
        target.appendChild(remove);
      });
    }

    function openGatewayItemMediaLibrary(item, tray, rerender) {
      if (!window.wp || !wp.media) {
        tray.textContent = 'Medienauswahl ist nicht geladen.';
        return;
      }

      var frame = wp.media({
        title: 'Bild auswählen',
        button: { text: 'Bild übernehmen' },
        multiple: false,
        library: { type: 'image' }
      });

      frame.on('open', function () {
        var selection = frame.state().get('selection');
        (item.media_refs || []).slice(0, 1).forEach(function (reference) {
          if (!reference.id) {
            return;
          }
          var attachment = wp.media.attachment(reference.id);
          attachment.fetch();
          selection.add(attachment);
        });
      });

      frame.on('select', function () {
        item.media_refs = frame.state().get('selection').map(function (attachment) {
          return referenceFromMediaAttachment(attachment.toJSON());
        }).filter(function (reference) {
          return reference.id;
        }).slice(0, 1);
        rerender();
        render();
        scheduleAutosave();
      });

      frame.open();
    }

    function albumSheetKey(sheet) {
      return [
        sheet.source_kind || '',
        sheet.source_id || '',
        sheet.member_id || '',
        sheet.source_item_id || ''
      ].join(':');
    }

    function normalizeAlbumPositions(section) {
      section.sheets = Array.isArray(section.sheets) ? section.sheets : [];
      section.sheets.forEach(function (sheet, index) {
        sheet.position = index + 1;
      });
    }

    function mergeAlbumSheets(section, nextSheets) {
      var existing = {};
      (section.sheets || []).forEach(function (sheet) {
        existing[albumSheetKey(sheet)] = sheet;
      });

      section.sheets = (nextSheets || []).map(function (sheet, index) {
        var old = existing[albumSheetKey(sheet)] || {};
        return Object.assign({}, sheet, {
          visible: Object.prototype.hasOwnProperty.call(old, 'visible') ? old.visible : true,
          label: old.label || sheet.label || '',
          nav_title: old.nav_title || sheet.nav_title || '',
          caption_override: old.caption_override || '',
          position: index + 1
        });
      }).filter(function (sheet) {
        return sheet.source_kind && sheet.source_id;
      });
      normalizeAlbumPositions(section);
    }

    function albumSheetFromArchiveMember(member, index, source) {
      var label = member.pageLabel || ('Blatt ' + String(index + 1).padStart(2, '0'));
      var caption = member.caption || member.displayTitle || member.title || member.memberTitle || member.objectTitle || '';
      return {
        source_kind: 'archive_object',
        source_id: String(member.objectPostId || ''),
        source_set_id: String((source && source.set_id) || member.setId || ''),
        member_id: String(member.id || ''),
        visible: true,
        label: label,
        nav_title: member.title || member.objectTitle || label,
        caption: caption,
        caption_override: '',
        thumbnail: member.thumbnail || '',
        position: index + 1
      };
    }

    function albumSheetFromEditorialSetItem(item, index) {
      var preview = item.preview || {};
      var sourceId = item.sourceId || '';
      if (item.kind === 'external_upload' && item.provenance && item.provenance.imported_attachment_id) {
        sourceId = item.provenance.imported_attachment_id;
      }
      return {
        source_kind: 'wp_media',
        source_id: String(sourceId || ''),
        source_set_id: String(item.setId || ''),
        source_item_id: String(item.id || ''),
        visible: true,
        label: 'Bild ' + String(index + 1).padStart(2, '0'),
        nav_title: item.label || preview.title || 'Bild ' + String(index + 1).padStart(2, '0'),
        caption: item.label || preview.title || '',
        caption_override: '',
        thumbnail: preview.thumbnail || '',
        position: index + 1
      };
    }

    function fetchJson(url) {
      return window.fetch(url, {
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': config.nonce || ''
        }
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('request failed');
        }
        return response.json();
      });
    }

    function fetchAllSetItems(url) {
      var items = [], total = null, seen = new Set();
      function page(number) {
        return fetchJson(url + '&page=' + number).then(function (payload) {
          var batch = payload && payload.items;
          if (!Array.isArray(batch) || !Number.isInteger(payload.total) || payload.total < 0 || payload.page !== number || (total !== null && total !== payload.total)) { throw new Error('Set changed or incomplete'); }
          total = payload.total;
          batch.forEach(function (item) {
            if (!item || !item.id || seen.has(String(item.id))) { throw new Error('Set changed while loading'); }
            seen.add(String(item.id)); items.push(item);
          });
          if (items.length > total || (!batch.length && items.length < total)) { throw new Error('Incomplete Set'); }
          return items.length < total ? page(number + 1) : items;
        });
      }
      return page(1);
    }

    function importAlbumSource(section, status) {
      var source = section.album_source || {};
      var kind = source.kind || 'archive_set';
      var setId = parseInt(source.set_id || '0', 10);
      var url;

      if (!setId) {
        status.textContent = 'Set-ID fehlt.';
        return;
      }

      status.textContent = 'Quelle wird geladen...';

      if (kind === 'archive_set') {
        if (!config.archiveRestRoot) {
          status.textContent = 'Archivset-API ist nicht verfügbar.';
          return;
        }
        url = config.archiveRestRoot.replace(/\/$/, '') + '/sets/' + encodeURIComponent(String(setId));
        fetchJson(url).then(function (payload) {
          var item = payload && payload.item ? payload.item : {};
          var members = Array.isArray(item.members) ? item.members : [];
          var sheets = members.filter(function (member) {
            return member && member.memberKind === 'object' && member.objectPostId;
          }).map(function (member, index) {
            return albumSheetFromArchiveMember(member, index, source);
          });
          section.album_source.set_title = item.title || section.album_source.set_title || '';
          mergeAlbumSheets(section, sheets);
          render();
          scheduleAutosave();
          status.textContent = String(sheets.length) + ' Blatt/Blätter importiert.';
        }).catch(function () {
          status.textContent = 'Archivset konnte nicht geladen werden.';
        });
        return;
      }

      if (kind === 'editorial_set') {
        if (!config.contentRestRoot) {
          status.textContent = 'Set-API ist nicht verfügbar.';
          return;
        }
        url = config.contentRestRoot.replace(/\/$/, '') + '/editorial-set-items?setId=' + encodeURIComponent(String(setId)) + '&perPage=120';
        fetchAllSetItems(url).then(function (items) {
          var sheets = items.filter(function (item) {
            var mime = item && item.preview ? String(item.preview.mime || '') : '';
            return item && ['rejected', 'stale'].indexOf(item.status || '') === -1 && (item.kind === 'wp_media' || item.kind === 'external_upload') && (mime === '' || mime.indexOf('image/') === 0);
          }).map(albumSheetFromEditorialSetItem).filter(function (sheet) {
            return sheet.source_id;
          });
          mergeAlbumSheets(section, sheets);
          render();
          scheduleAutosave();
          status.textContent = String(sheets.length) + ' Bild(er) importiert.';
        }).catch(function () {
          status.textContent = 'Set konnte nicht geladen werden.';
        });
      }
    }

    function renderAlbumSheetList(section, target, rerender) {
      clear(target);
      section.sheets = Array.isArray(section.sheets) ? section.sheets : [];
      normalizeAlbumPositions(section);

      if (!section.sheets.length) {
        target.appendChild(createElement('p', 'description', 'Noch keine Albumblätter importiert.'));
        return;
      }

      section.sheets.forEach(function (sheet, index) {
        var row = createElement('article', 'iss-editorial-album-sheet');
        var preview = createElement('div', 'iss-editorial-album-sheet__preview');
        var fields = createElement('div', 'iss-editorial-album-sheet__fields');
        var tools = createElement('div', 'iss-editorial-album-sheet__tools');
        var up = createElement('button', 'button', 'Hoch');
        var down = createElement('button', 'button', 'Runter');
        var remove = createElement('button', 'button button-link-delete', 'Entfernen');
        var visible = document.createElement('input');
        var visibleLabel = createElement('label', 'iss-editorial-album-sheet__visible');

        if (sheet.thumbnail) {
          var image = document.createElement('img');
          image.src = sheet.thumbnail;
          image.alt = '';
          preview.appendChild(image);
        } else {
          preview.textContent = sheet.source_kind === 'archive_object' ? 'Archiv' : 'Bild';
        }

        visible.type = 'checkbox';
        visible.checked = sheet.visible !== false;
        visible.addEventListener('change', function () {
          sheet.visible = visible.checked;
          render();
          scheduleAutosave();
        });
        visibleLabel.appendChild(visible);
        visibleLabel.appendChild(document.createTextNode(' sichtbar'));

        fields.appendChild(createTextInput('Label', sheet.label || '', function (value) {
          sheet.label = value;
          render();
          scheduleAutosave();
        }));
        fields.appendChild(createTextInput('Nav-Titel', sheet.nav_title || '', function (value) {
          sheet.nav_title = value;
          render();
          scheduleAutosave();
        }));
        fields.appendChild(createTextarea('Beschreibung', sheet.caption_override || sheet.caption || '', function (value) {
          sheet.caption_override = value;
          render();
          scheduleAutosave();
        }, 4));

        [up, down, remove].forEach(function (button) {
          button.type = 'button';
        });
        up.disabled = index === 0;
        down.disabled = index >= section.sheets.length - 1;
        up.addEventListener('click', function () {
          var current = section.sheets.splice(index, 1)[0];
          section.sheets.splice(index - 1, 0, current);
          normalizeAlbumPositions(section);
          rerender();
          render();
          scheduleAutosave();
        });
        down.addEventListener('click', function () {
          var current = section.sheets.splice(index, 1)[0];
          section.sheets.splice(index + 1, 0, current);
          normalizeAlbumPositions(section);
          rerender();
          render();
          scheduleAutosave();
        });
        remove.addEventListener('click', function () {
          section.sheets.splice(index, 1);
          normalizeAlbumPositions(section);
          rerender();
          render();
          scheduleAutosave();
        });

        tools.appendChild(createElement('span', 'iss-editorial-album-sheet__position', String(index + 1)));
        tools.appendChild(visibleLabel);
        tools.appendChild(up);
        tools.appendChild(down);
        tools.appendChild(remove);
        row.appendChild(preview);
        row.appendChild(fields);
        row.appendChild(tools);
        target.appendChild(row);
      });
    }

    function renderAlbumEditor(section, body, setCount) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--album');
      var source = section.album_source && typeof section.album_source === 'object' ? section.album_source : {};
      var sourceRow = createElement('div', 'iss-editorial-album-source');
      var kindLabel = createElement('label', 'iss-editorial-field');
      var kindSelect = document.createElement('select');
      var idField;
      var titleField;
      var importButton = createElement('button', 'button button-primary', 'Aus Quelle importieren / synchronisieren');
      var status = createElement('p', 'description');
      var list = createElement('div', 'iss-editorial-album-sheets');

      section.album_source = {
        kind: source.kind || 'archive_set',
        set_id: source.set_id || '',
        set_title: source.set_title || ''
      };
      section.sheets = Array.isArray(section.sheets) ? section.sheets : [];

      [
        { value: 'archive_set', label: 'Archivset' },
        { value: 'editorial_set', label: 'Set' },
        { value: 'manual', label: 'Manuell' }
      ].forEach(function (optionData) {
        var option = document.createElement('option');
        option.value = optionData.value;
        option.textContent = optionData.label;
        option.selected = optionData.value === section.album_source.kind;
        kindSelect.appendChild(option);
      });
      kindSelect.addEventListener('change', function () {
        section.album_source.kind = kindSelect.value;
        render();
        scheduleAutosave();
      });

      idField = createTextInput('Set-ID', section.album_source.set_id || '', function (value) {
        section.album_source.set_id = value.replace(/[^0-9]/g, '');
        scheduleAutosave();
      });
      titleField = createTextInput('Quellentitel', section.album_source.set_title || '', function (value) {
        section.album_source.set_title = value;
        render();
        scheduleAutosave();
      });

      importButton.type = 'button';
      importButton.disabled = section.album_source.kind === 'manual';
      importButton.addEventListener('click', function () {
        importAlbumSource(section, status);
      });

      function rerenderSheets() {
        renderAlbumSheetList(section, list, rerenderSheets);
        setCount(collectionCount(section, 'sheets'));
      }

      kindLabel.appendChild(createElement('span', '', 'Quelle'));
      kindLabel.appendChild(kindSelect);
      sourceRow.appendChild(kindLabel);
      if (section.album_source.kind !== 'manual') {
        sourceRow.appendChild(idField);
        sourceRow.appendChild(titleField);
        sourceRow.appendChild(importButton);
      }
      wrapper.appendChild(sourceRow);
      wrapper.appendChild(status);
      wrapper.appendChild(list);
      body.appendChild(wrapper);
      rerenderSheets();
    }

    function renderMediaPicker(section, body, setCount) {
      var refs = createElement('div', 'iss-editorial-field iss-editorial-field--media');
      var tray = createElement('div', 'iss-editorial-media-tray');
      var isFullViewport = sectionUsesViewportGallery(section);
      var isMaterial = sectionEditor(section.type).media_kind === 'files';
      var noun = isMaterial ? 'Dateien' : (isFullViewport ? 'Bild' : 'Bilder');
      var actions = createElement('div', 'iss-editorial-media-actions');
      var setPickerButton = createElement('button', 'button button-primary', 'Aus Set auswählen');
      var mediaLibraryButton = createElement('button', 'button', isMaterial ? 'Dateien suchen' : 'Medien suchen');

      function rerenderTray() {
        renderMediaTray(section, tray, rerenderTray);
        setCount(sectionMediaRefsForDisplay(section).length);
      }

      function existingReferencesById() {
        var existing = {};
        (section.media_refs || []).forEach(function (reference) {
          if (reference.id) {
            existing[String(reference.id)] = reference;
          }
        });
        return existing;
      }

      function applyMediaReferences(references) {
        var existing = existingReferencesById();
        section.media_refs = (references || []).map(function (reference) {
          if (existing[reference.id] && existing[reference.id].label) {
            reference.label = existing[reference.id].label;
          }
          return reference;
        }).filter(function (reference) {
          return reference.id && (!sectionEditor(section.type).media_hide_images || !mediaReferenceIsImage(reference));
        });
        if (isFullViewport) {
          section.media_refs = section.media_refs.slice(0, 1);
        }
        rerenderTray();
        render();
        scheduleAutosave();
      }

      function openMediaLibrary(afterSelect) {
        if (!window.wp || !wp.media) {
          tray.textContent = 'Medienauswahl ist nicht geladen.';
          return;
        }

        var frame = wp.media({
          title: noun + ' auswählen',
          button: { text: noun + ' übernehmen' },
          multiple: !isFullViewport,
          library: isMaterial ? (sectionEditor(section.type).media_hide_images ? { type: 'application' } : {}) : { type: 'image' }
        });

        frame.on('open', function () {
          var selection = frame.state().get('selection');
          (section.media_refs || []).forEach(function (reference) {
            if (!reference.id) {
              return;
            }
            var attachment = wp.media.attachment(reference.id);
            attachment.fetch();
            selection.add(attachment);
          });
        });

        frame.on('select', function () {
          var references = frame.state().get('selection').map(function (attachment) {
            var reference = referenceFromMediaAttachment(attachment.toJSON());
            return reference;
          }).filter(function (reference) {
            return reference.id;
          });
          applyMediaReferences(references);
          if (typeof afterSelect === 'function') {
            afterSelect();
          }
        });

        frame.open();
      }

      setPickerButton.type = 'button';
      setPickerButton.addEventListener('click', function () {
        if (!window.issEditorialSetMediaPicker || !window.issEditorialSetMediaPicker.create) {
          openMediaLibrary();
          return;
        }

        window.issEditorialSetMediaPicker.create(document.createElement('div'), {
          modal: true,
          manageModalFocus: editorUi.manageModalFocus,
          mode: isFullViewport ? 'single' : 'multiple',
          mediaType: isMaterial ? (sectionEditor(section.type).media_hide_images ? 'file' : '') : 'image',
          contextId: config.postId || 0,
          initialSelection: section.media_refs || [],
          onConfirm: function (references) {
            applyMediaReferences(references);
          },
          onMediaSearch: function (api) {
            openMediaLibrary(function () {
              if (api && typeof api.close === 'function') {
                api.close();
              }
            });
          }
        });
      });

      mediaLibraryButton.type = 'button';
      if (options.getStudio()) { options.getStudio().help.hint(mediaLibraryButton, 'media'); }
      mediaLibraryButton.addEventListener('click', function () {
        openMediaLibrary();
      });

      refs.appendChild(tray);
      actions.appendChild(setPickerButton);
      actions.appendChild(mediaLibraryButton);
      refs.appendChild(actions);
      body.appendChild(refs);
      rerenderTray();
    }

    return {renderObjectPicker: renderObjectPicker, renderMediaPicker: renderMediaPicker, renderAlbumEditor: renderAlbumEditor, sectionMediaRefsForDisplay: sectionMediaRefsForDisplay, renderGatewayItemMedia: renderGatewayItemMedia, openGatewayItemMediaLibrary: openGatewayItemMediaLibrary};
  }
  window.issEditorialPickers = { create: create };
})();
