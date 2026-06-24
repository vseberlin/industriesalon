(function () {
  function parseJson(value, fallback) {
    try {
      return JSON.parse(value || '');
    } catch (error) {
      return fallback;
    }
  }

  function createElement(tag, className, text) {
    var element = document.createElement(tag);
    if (className) {
      element.className = className;
    }
    if (typeof text === 'string') {
      element.textContent = text;
    }
    return element;
  }

  function clear(element) {
    while (element && element.firstChild) {
      element.removeChild(element.firstChild);
    }
  }

  function supports(config, field) {
    return (config.supports || []).indexOf(field) !== -1;
  }

  function compactText(value, limit) {
    var text = String(value || '').replace(/\s+/g, ' ').trim();
    if (!limit || text.length <= limit) {
      return text;
    }

    return text.slice(0, limit - 1).trim() + '…';
  }

  function archiveReferenceLabel(item) {
    return compactText(item.memberTitle || item.inventoryNumber || item.objectTypeLabel || item.setTitle || item.title || '', 140);
  }

  function referenceFromArchiveItem(item) {
    return {
      kind: 'archive_object',
      source: 'iss-archive',
      id: String(item.id || ''),
      label: archiveReferenceLabel(item),
      thumbnail: item.thumbnail || '',
      set_id: item.setId ? String(item.setId) : '',
      set_title: item.setTitle || '',
      member_id: item.memberId ? String(item.memberId) : ''
    };
  }

  function referenceFromMediaAttachment(attachment) {
    var sizes = attachment.sizes || {};
    var thumbnail = (sizes.medium && sizes.medium.url) || (sizes.thumbnail && sizes.thumbnail.url) || attachment.url || '';
    return {
      kind: 'media',
      source: 'wp-media',
      id: String(attachment.id || ''),
      label: attachment.caption || attachment.title || '',
      thumbnail: thumbnail,
      width: attachment.width ? String(attachment.width) : '',
      height: attachment.height ? String(attachment.height) : ''
    };
  }

  function compactReferences(references, kind, source) {
    return (Array.isArray(references) ? references : []).map(function (reference) {
      return {
        kind: reference.kind || kind,
        source: reference.source || source,
        id: String(reference.id || '').trim(),
        label: String(reference.label || '').trim(),
        thumbnail: String(reference.thumbnail || '').trim(),
        set_id: String(reference.set_id || '').trim(),
        set_title: String(reference.set_title || '').trim(),
        member_id: String(reference.member_id || '').trim(),
        width: String(reference.width || '').trim(),
        height: String(reference.height || '').trim()
      };
    }).filter(function (reference) {
      return reference.id;
    }).map(function (reference) {
      var compact = {
        kind: reference.kind,
        source: reference.source,
        id: reference.id
      };
      reference.label = compactText(reference.label, reference.source === 'iss-archive' ? 140 : 0);
      ['label', 'thumbnail', 'set_id', 'set_title', 'member_id', 'width', 'height'].forEach(function (key) {
        if (key === 'thumbnail' && reference.source === 'wp-media') {
          return;
        }
        if (reference[key]) {
          compact[key] = reference[key];
        }
      });
      return compact;
    });
  }

  function compactDynamicReferences(references) {
    return (Array.isArray(references) ? references : []).map(function (reference) {
      return {
        kind: reference.kind || 'control_field',
        source: reference.source || 'industriesalon-steuerung',
        key: String(reference.key || '').trim(),
        label: String(reference.label || '').trim(),
        tagName: String(reference.tagName || '').trim(),
        linkMode: String(reference.linkMode || '').trim(),
        text: String(reference.text || '').trim(),
        cssClass: String(reference.cssClass || '').trim(),
        hrefSuffix: String(reference.hrefSuffix || '').trim()
      };
    }).filter(function (reference) {
      return reference.key;
    }).map(function (reference) {
      var compact = {
        kind: reference.kind,
        source: reference.source,
        key: reference.key
      };
      ['label', 'tagName', 'linkMode', 'text', 'cssClass', 'hrefSuffix'].forEach(function (key) {
        if (reference[key]) {
          compact[key] = reference[key];
        }
      });
      return compact;
    });
  }

  function compactSection(section) {
    var compact = {
      type: section.type || 'intro'
    };
    ['kicker', 'title', 'body', 'quote', 'attribution'].forEach(function (key) {
      var value = String(section[key] || '').trim();
      if (value) {
        compact[key] = value;
      }
    });
    var items = Array.isArray(section.items) ? section.items.filter(function (item) {
      return String(item || '').trim();
    }) : [];
    if (items.length) {
      compact.items = items;
    }
    var mediaRefs = compactReferences(section.media_refs, 'media', 'wp-media');
    if (mediaRefs.length) {
      compact.media_refs = mediaRefs;
    }
    var objectRefs = compactReferences(section.object_refs, 'archive_object', 'iss-archive');
    if (objectRefs.length) {
      compact.object_refs = objectRefs;
    }
    var dynamicRefs = compactDynamicReferences(section.dynamic_refs);
    if (dynamicRefs.length) {
      compact.dynamic_refs = dynamicRefs;
    }

    return compact;
  }

  function sectionHasContent(section) {
    var compact = compactSection(section);
    return Object.keys(compact).some(function (key) {
      return key !== 'type';
    });
  }

  function itemsToText(items) {
    return (Array.isArray(items) ? items : []).join('\n');
  }

  function textToItems(value) {
    return String(value || '').split(/\r?\n/).map(function (line) {
      return line.trim();
    }).filter(Boolean);
  }

  function mediaDisplayThumbnail(reference) {
    return reference.preview_thumbnail || reference.thumbnail || '';
  }

  function initEditor(container) {
    var root = container.querySelector('.iss-veranstaltung-content-editor__root');
    var field = container.querySelector('.iss-veranstaltung-content-editor__field');
    if (!root || !field) {
      return;
    }

    var entityKey = container.getAttribute('data-entity-key') || '';
    var gestures = parseJson(container.getAttribute('data-gestures'), {});
    var mediaPreviews = parseJson(container.getAttribute('data-media-previews'), {});
    var dynamicPreviews = parseJson(container.getAttribute('data-dynamic-previews'), {});
    var gestureKeys = Object.keys(gestures);
    var state = parseJson(container.getAttribute('data-document'), {
      schema_version: 1,
      entity_key: entityKey,
      sections: []
    });

    if (!Array.isArray(state.sections)) {
      state.sections = [];
    }
    state.entity_key = entityKey || state.entity_key || '';
    state.schema_version = 1;
    var previewPane = null;

    function firstGesture() {
      return gestureKeys[0] || 'intro';
    }

    function gestureConfig(type) {
      return gestures[type] || {
        label: type,
        description: '',
        supports: ['kicker', 'title', 'body']
      };
    }

    function currentSections() {
      return state.sections.map(compactSection).filter(sectionHasContent);
    }

    function updateField() {
      var sections = currentSections();
      if (!sections.length) {
        field.value = '';
        return sections;
      }

      field.value = JSON.stringify({
        schema_version: 1,
        entity_key: entityKey || state.entity_key || '',
        sections: sections
      });

      return sections;
    }

    function applyMediaPreview(reference) {
      var preview = mediaPreviews[String(reference.id || '')] || null;
      if (!preview || !preview.thumbnail) {
        return false;
      }

      reference.preview_thumbnail = preview.thumbnail;
      if (!reference.label && preview.label) {
        reference.label = preview.label;
      }
      if (!reference.width && preview.width) {
        reference.width = preview.width;
      }
      if (!reference.height && preview.height) {
        reference.height = preview.height;
      }

      return true;
    }

    function hydrateMediaReference(reference, onReady) {
      if (!reference || reference.source !== 'wp-media' || !reference.id || reference.preview_loading) {
        return;
      }
      if (applyMediaPreview(reference)) {
        return;
      }
      if (mediaDisplayThumbnail(reference) && reference.width && reference.height) {
        return;
      }
      if (!window.wp || !window.wp.media) {
        if (!reference.preview_waiting) {
          reference.preview_waiting = true;
          window.setTimeout(function () {
            reference.preview_waiting = false;
            hydrateMediaReference(reference, onReady);
          }, 500);
        }
        return;
      }

      reference.preview_loading = true;
      var attachment = window.wp.media.attachment(reference.id);
      var applyAttachment = function () {
        var hydrated = referenceFromMediaAttachment(attachment.toJSON());
        reference.preview_thumbnail = hydrated.thumbnail;
        if (!reference.label) {
          reference.label = hydrated.label;
        }
        if (!reference.width) {
          reference.width = hydrated.width;
        }
        if (!reference.height) {
          reference.height = hydrated.height;
        }
        reference.preview_loading = false;
        if (typeof onReady === 'function') {
          onReady();
        }
      };
      var clearLoading = function () {
        reference.preview_loading = false;
      };
      var request = attachment.fetch();
      if (request && typeof request.done === 'function') {
        request.done(applyAttachment).fail(clearLoading);
        return;
      }
      if (request && typeof request.then === 'function') {
        request.then(applyAttachment).catch(clearLoading);
        return;
      }
      applyAttachment();
    }

    function refreshPreview() {
      if (!previewPane) {
        return;
      }
      clear(previewPane);
      renderPreview(previewPane, currentSections());
    }

    function addSection(type) {
      state.sections.push({
        type: type || firstGesture(),
        kicker: '',
        title: '',
        body: '',
        quote: '',
        attribution: '',
        items: []
      });
      updateField();
      render();
    }

    function removeSection(index) {
      state.sections.splice(index, 1);
      updateField();
      render();
    }

    function moveSection(index, offset) {
      var next = index + offset;
      if (next < 0 || next >= state.sections.length) {
        return;
      }
      var section = state.sections.splice(index, 1)[0];
      state.sections.splice(next, 0, section);
      updateField();
      render();
    }

    function createField(label, value, onInput, multiline) {
      var wrapper = createElement('label', 'iss-veranstaltung-content-editor__field-row');
      var labelText = createElement('span', '', label);
      var input = multiline ? document.createElement('textarea') : document.createElement('input');
      if (!multiline) {
        input.type = 'text';
      }
      input.value = value || '';
      input.addEventListener('input', function () {
        onInput(input.value);
        updateField();
        refreshPreview();
      });
      wrapper.appendChild(labelText);
      wrapper.appendChild(input);
      return wrapper;
    }

    function renderReferenceList(label, references, removeCallback) {
      var wrapper = createElement('div', 'iss-veranstaltung-content-editor__refs');
      wrapper.appendChild(createElement('span', '', label));
      if (!references.length) {
        wrapper.appendChild(createElement('p', 'description', 'Noch keine Auswahl.'));
        return wrapper;
      }

      references.forEach(function (reference, index) {
        var item = createElement('article', 'iss-veranstaltung-content-editor__ref');
        var body = createElement('div', 'iss-veranstaltung-content-editor__ref-body');
        var labelText = createElement('strong', '', compactText(reference.label || reference.id || 'Auswahl', 140));
        var metaText = [reference.set_title, reference.id ? 'Archivobjekt #' + reference.id : ''].filter(Boolean).join(' - ');
        var remove = createElement('button', 'button button-link-delete', 'Entfernen');
        if (reference.thumbnail) {
          var thumb = createElement('div', 'iss-veranstaltung-content-editor__ref-thumb');
          var image = document.createElement('img');
          image.src = reference.thumbnail;
          image.alt = '';
          thumb.appendChild(image);
          item.appendChild(thumb);
        }
        remove.type = 'button';
        remove.addEventListener('click', function () {
          removeCallback(index);
        });
        body.appendChild(labelText);
        if (metaText) {
          body.appendChild(createElement('span', '', metaText));
        }
        item.appendChild(body);
        item.appendChild(remove);
        wrapper.appendChild(item);
      });

      return wrapper;
    }

    function renderObjectPicker(section, target) {
      section.object_refs = compactReferences(section.object_refs, 'archive_object', 'iss-archive');
      var wrapper = createElement('div', 'iss-veranstaltung-content-editor__field-row');
      var pickerMount = createElement('div', 'iss-veranstaltung-content-editor__archive-picker');
      var button = createElement('button', 'button', 'Archivobjekte auswaehlen');

      wrapper.appendChild(renderReferenceList('Archivobjekte', section.object_refs, function (index) {
        section.object_refs.splice(index, 1);
        updateField();
        render();
      }));

      button.type = 'button';
      button.addEventListener('click', function (event) {
        event.preventDefault();
        if (!window.issArchiveObjectPicker || !window.issArchiveObjectPicker.create) {
          pickerMount.textContent = 'Archiv-Auswahl ist nicht geladen.';
          return;
        }
        window.issArchiveObjectPicker.create(pickerMount, {
          mode: 'multiple',
          modal: true,
          bucketFirst: true,
          initialSelection: section.object_refs.map(function (reference) { return reference.id; }),
          onConfirm: function (items) {
            section.object_refs = (items || []).map(referenceFromArchiveItem).filter(function (reference) {
              return reference.id;
            });
            updateField();
            render();
          }
        });
      });

      wrapper.appendChild(button);
      wrapper.appendChild(pickerMount);
      target.appendChild(wrapper);
    }

    function renderDynamicReferenceList(section, target) {
      section.dynamic_refs = compactDynamicReferences(section.dynamic_refs);
      if (!section.dynamic_refs.length) {
        return;
      }

      var wrapper = createElement('div', 'iss-veranstaltung-content-editor__field-row');
      var list = createElement('div', 'iss-veranstaltung-content-editor__dynamic-refs');
      wrapper.appendChild(createElement('span', '', 'Zentralfelder'));

      section.dynamic_refs.forEach(function (reference, index) {
        var item = createElement('article', 'iss-veranstaltung-content-editor__dynamic-ref');
        var body = createElement('div', 'iss-veranstaltung-content-editor__dynamic-ref-body');
        var title = createElement('strong', '', reference.label || reference.key);
        var meta = createElement('span', '', reference.key);
        var preview = dynamicPreviews[reference.key] || {};
        var value = createElement('p', '', preview.value || 'Kein aktueller Wert aus Steuerung.');
        var remove = createElement('button', 'button button-link-delete', 'Entfernen');

        remove.type = 'button';
        remove.addEventListener('click', function () {
          section.dynamic_refs.splice(index, 1);
          updateField();
          render();
        });

        body.appendChild(title);
        body.appendChild(meta);
        body.appendChild(value);
        item.appendChild(body);
        item.appendChild(remove);
        list.appendChild(item);
      });

      wrapper.appendChild(list);
      target.appendChild(wrapper);
    }

    function renderMediaPicker(section, target) {
      section.media_refs = compactReferences(section.media_refs, 'media', 'wp-media');
      var wrapper = createElement('div', 'iss-veranstaltung-content-editor__field-row');
      var tray = createElement('div', 'iss-veranstaltung-content-editor__media-tray');
      var button = createElement('button', 'button', 'Bilder auswaehlen');

      function renderTray() {
        clear(tray);
        if (!section.media_refs.length) {
          tray.appendChild(createElement('p', 'description', 'Noch keine Bilder ausgewaehlt.'));
          return;
        }
        section.media_refs.forEach(function (reference, index) {
          var item = createElement('article', 'iss-veranstaltung-content-editor__media-item');
          var preview = createElement('div', 'iss-veranstaltung-content-editor__media-preview');
          var controls = createElement('div', 'iss-veranstaltung-content-editor__media-controls');
          var remove = createElement('button', 'button button-link-delete', 'Entfernen');
          hydrateMediaReference(reference, render);
          var thumbnail = mediaDisplayThumbnail(reference);
          if (thumbnail) {
            var image = document.createElement('img');
            image.src = thumbnail;
            image.alt = '';
            preview.appendChild(image);
          } else {
            preview.textContent = 'Bild';
          }
          controls.appendChild(createField('Bildunterschrift', reference.label || '', function (value) {
            reference.label = value;
          }, false));
          remove.type = 'button';
          remove.addEventListener('click', function () {
            section.media_refs.splice(index, 1);
            updateField();
            render();
          });
          controls.appendChild(remove);
          item.appendChild(preview);
          item.appendChild(controls);
          tray.appendChild(item);
        });
      }

      button.type = 'button';
      button.addEventListener('click', function (event) {
        event.preventDefault();
        if (!window.wp || !window.wp.media) {
          tray.textContent = 'Medienauswahl ist nicht geladen.';
          return;
        }

        var existing = {};
        section.media_refs.forEach(function (reference) {
          existing[reference.id] = reference;
        });

        var frame = window.wp.media({
          title: 'Bilder auswaehlen',
          button: { text: 'Bilder uebernehmen' },
          multiple: true,
          library: { type: 'image' }
        });

        frame.on('open', function () {
          var selection = frame.state().get('selection');
          section.media_refs.forEach(function (reference) {
            var attachment = window.wp.media.attachment(reference.id);
            attachment.fetch();
            selection.add(attachment);
          });
        });

        frame.on('select', function () {
          section.media_refs = frame.state().get('selection').map(function (attachment) {
            var reference = referenceFromMediaAttachment(attachment.toJSON());
            if (existing[reference.id] && existing[reference.id].label) {
              reference.label = existing[reference.id].label;
            }
            return reference;
          }).filter(function (reference) {
            return reference.id;
          });
          updateField();
          render();
        });

        frame.open();
      });

      wrapper.appendChild(createElement('span', '', 'Bilder'));
      wrapper.appendChild(tray);
      wrapper.appendChild(button);
      target.appendChild(wrapper);
      renderTray();
    }

    function renderPalette(target) {
      var palette = createElement('div', 'iss-veranstaltung-content-editor__palette');
      gestureKeys.forEach(function (type) {
        var config = gestureConfig(type);
        var button = createElement('button', 'button', config.label || type);
        button.type = 'button';
        button.title = config.description || '';
        button.addEventListener('click', function () {
          addSection(type);
        });
        palette.appendChild(button);
      });
      target.appendChild(palette);
    }

    function renderTypeSelect(section, index, target) {
      var label = createElement('label', 'iss-veranstaltung-content-editor__field-row');
      var labelText = createElement('span', '', 'Geste');
      var select = document.createElement('select');
      gestureKeys.forEach(function (type) {
        var option = document.createElement('option');
        option.value = type;
        option.textContent = gestureConfig(type).label || type;
        option.selected = section.type === type;
        select.appendChild(option);
      });
      select.addEventListener('change', function () {
        section.type = select.value || firstGesture();
        updateField();
        render();
      });
      label.appendChild(labelText);
      label.appendChild(select);
      target.appendChild(label);
    }

    function renderSection(section, index, target) {
      var type = section.type || firstGesture();
      var config = gestureConfig(type);
      var card = createElement('article', 'iss-veranstaltung-content-editor__card');
      var head = createElement('div', 'iss-veranstaltung-content-editor__card-head');
      var title = createElement('h4', '', (config.label || type) + ' ' + String(index + 1));
      var actions = createElement('div', 'iss-veranstaltung-content-editor__actions');
      var up = createElement('button', 'button', 'Hoch');
      var down = createElement('button', 'button', 'Runter');
      var remove = createElement('button', 'button button-link-delete', 'Entfernen');

      [up, down, remove].forEach(function (button) {
        button.type = 'button';
      });
      up.disabled = index === 0;
      down.disabled = index >= state.sections.length - 1;
      up.addEventListener('click', function () { moveSection(index, -1); });
      down.addEventListener('click', function () { moveSection(index, 1); });
      remove.addEventListener('click', function () { removeSection(index); });

      actions.appendChild(up);
      actions.appendChild(down);
      actions.appendChild(remove);
      head.appendChild(title);
      head.appendChild(actions);
      card.appendChild(head);

      renderTypeSelect(section, index, card);
      if (supports(config, 'kicker')) {
        card.appendChild(createField('Kicker', section.kicker || '', function (value) { section.kicker = value; }, false));
      }
      if (supports(config, 'title')) {
        card.appendChild(createField('Titel', section.title || '', function (value) { section.title = value; }, false));
      }
      if (supports(config, 'body')) {
        card.appendChild(createField('Text', section.body || '', function (value) { section.body = value; }, true));
      }
      if (supports(config, 'quote')) {
        card.appendChild(createField('Zitat', section.quote || '', function (value) { section.quote = value; }, true));
      }
      if (supports(config, 'attribution')) {
        card.appendChild(createField('Zuordnung', section.attribution || '', function (value) { section.attribution = value; }, false));
      }
      if (supports(config, 'items')) {
        card.appendChild(createField('Punkte, je Zeile ein Eintrag', itemsToText(section.items), function (value) {
          section.items = textToItems(value);
        }, true));
      }
      if (supports(config, 'media_refs')) {
        renderMediaPicker(section, card);
      }
      if (supports(config, 'object_refs')) {
        renderObjectPicker(section, card);
      }
      if (supports(config, 'dynamic_refs')) {
        renderDynamicReferenceList(section, card);
      }

      target.appendChild(card);
    }

    function appendPreviewText(target, className, value) {
      var text = String(value || '').trim();
      if (!text) {
        return;
      }

      target.appendChild(createElement('p', className, text));
    }

    function renderPreviewSection(section, index, target) {
      var config = gestureConfig(section.type || firstGesture());
      var article = createElement('article', 'iss-veranstaltung-content-editor__preview-section');
      var meta = createElement(
        'span',
        'iss-veranstaltung-content-editor__preview-type',
        (config.label || section.type || firstGesture()) + ' ' + String(index + 1)
      );

      article.appendChild(meta);
      appendPreviewText(article, 'iss-veranstaltung-content-editor__preview-kicker', section.kicker);

      if (section.title) {
        article.appendChild(createElement('h4', '', section.title));
      }
      appendPreviewText(article, 'iss-veranstaltung-content-editor__preview-body', section.body);

      if (section.quote) {
        var quote = createElement('blockquote', '');
        quote.appendChild(createElement('p', '', section.quote));
        if (section.attribution) {
          quote.appendChild(createElement('cite', '', section.attribution));
        }
        article.appendChild(quote);
      }

      if (Array.isArray(section.items) && section.items.length) {
        var list = createElement('ul', 'iss-veranstaltung-content-editor__preview-items');
        section.items.forEach(function (item) {
          list.appendChild(createElement('li', '', item));
        });
        article.appendChild(list);
      }

      if (Array.isArray(section.media_refs) && section.media_refs.length) {
        var media = createElement('div', 'iss-veranstaltung-content-editor__preview-media');
        section.media_refs.forEach(function (reference) {
          var item = createElement('figure', '');
          hydrateMediaReference(reference, refreshPreview);
          var thumbnail = mediaDisplayThumbnail(reference);
          if (thumbnail) {
            var image = document.createElement('img');
            image.src = thumbnail;
            image.alt = '';
            item.appendChild(image);
          }
          if (reference.label) {
            item.appendChild(createElement('figcaption', '', reference.label));
          }
          media.appendChild(item);
        });
        article.appendChild(media);
      }

      if (Array.isArray(section.object_refs) && section.object_refs.length) {
        var refs = createElement('div', 'iss-veranstaltung-content-editor__preview-refs');
        section.object_refs.forEach(function (reference) {
          var item = createElement('figure', '');
          if (reference.thumbnail) {
            var image = document.createElement('img');
            image.src = reference.thumbnail;
            image.alt = '';
            item.appendChild(image);
          }
          item.appendChild(createElement('figcaption', '', compactText(reference.label || reference.id, 140)));
          refs.appendChild(item);
        });
        article.appendChild(refs);
      }

      if (Array.isArray(section.dynamic_refs) && section.dynamic_refs.length) {
        var dynamicRefs = createElement('div', 'iss-veranstaltung-content-editor__preview-dynamic-refs');
        section.dynamic_refs.forEach(function (reference) {
          var preview = dynamicPreviews[reference.key] || {};
          var item = createElement('p', '', preview.value || reference.key);
          if (reference.label) {
            item.setAttribute('data-label', reference.label);
          }
          dynamicRefs.appendChild(item);
        });
        article.appendChild(dynamicRefs);
      }

      target.appendChild(article);
    }

    function renderPreview(target, sections) {
      var panel = createElement('aside', 'iss-veranstaltung-content-editor__preview');
      panel.appendChild(createElement('h3', '', 'Vorschau'));

      if (!sections.length) {
        panel.appendChild(createElement('p', 'iss-veranstaltung-content-editor__empty', 'Noch keine gespeicherte Struktur.'));
        target.appendChild(panel);
        return;
      }

      sections.forEach(function (section, index) {
        renderPreviewSection(section, index, panel);
      });
      target.appendChild(panel);
    }

    function render() {
      clear(root);
      var layout = createElement('div', 'iss-veranstaltung-content-editor__layout');
      var body = createElement('div', 'iss-veranstaltung-content-editor__body');
      var editorPane = createElement('div', 'iss-veranstaltung-content-editor__edit');
      previewPane = createElement('div', 'iss-veranstaltung-content-editor__preview-wrap');
      var stage = createElement('div', 'iss-veranstaltung-content-editor__stage');
      var toolbar = createElement('div', 'iss-veranstaltung-content-editor__toolbar');
      var addDefault = createElement('button', 'button button-primary', 'Abschnitt hinzufuegen');
      var sections = currentSections();

      addDefault.type = 'button';
      addDefault.addEventListener('click', function () {
        addSection(firstGesture());
      });

      renderPalette(toolbar);
      toolbar.appendChild(addDefault);
      editorPane.appendChild(toolbar);

      if (!state.sections.length) {
        stage.appendChild(createElement('p', 'iss-veranstaltung-content-editor__empty', 'Noch keine Struktur. Eine Geste waehlen oder einen Abschnitt hinzufuegen.'));
      } else {
        state.sections.forEach(function (section, index) {
          renderSection(section, index, stage);
        });
      }

      editorPane.appendChild(stage);
      renderPreview(previewPane, sections);
      body.appendChild(editorPane);
      body.appendChild(previewPane);
      layout.appendChild(body);
      root.appendChild(layout);
      updateField();
    }

    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.iss-veranstaltung-content-editor').forEach(initEditor);
  });
}());
