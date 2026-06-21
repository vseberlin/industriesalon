(function () {
  var config = window.issEditorialAdmin || {};

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

  function sectionTone(type) {
    var tones = {
      leitfrage: '#7f77dd',
      quellenauszug: '#d85a30',
      objektfokus: '#1d9e75',
      bildstrecke: '#888780',
      massstab: '#ba7517',
      kapitel: '#1a1a2e',
      zitat: '#d4537e'
    };

    return tones[type] || '#1a1a2e';
  }

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
      thumbnail: thumbnail
    };
  }

  function initEditor(container) {
    var root = container.querySelector('.iss-editorial-root');
    var field = container.querySelector('.iss-editorial-document-field');
    var enabledField = container.querySelector('.iss-editorial-enabled-field');
    var status = container.querySelector('.iss-editorial-autosave-status');
    if (!root || !field) {
      return;
    }

    var format = container.getAttribute('data-format') || '';
    var postId = parseInt(container.getAttribute('data-post-id') || config.postId || '0', 10);
    var sections = parseJson(root.getAttribute('data-sections'), {});
    var documentState = parseJson(root.getAttribute('data-document'), {
      schema_version: 1,
      skin: 'standard',
      variant: 'standard',
      sections: []
    });
    var autosaveTimer = null;
    var activeType = Object.keys(sections)[0] || 'kapitel';
    var modal = null;

    function sectionConfig(type) {
      return sections[type] || { label: type, supports: [] };
    }

    function supports(type, fieldName) {
      var supported = sectionConfig(type).supports || [];
      return supported.indexOf(fieldName) !== -1;
    }

    function updateField() {
      field.value = JSON.stringify(documentState);
    }

    function setStatus(message) {
      if (status) {
        status.textContent = message || '';
      }
    }

    function currentEnabled() {
      return enabledField ? !!enabledField.checked : false;
    }

    function sectionSummary(section) {
      var parts = [];
      if (section.body) {
        parts.push(String(section.body).replace(/\s+/g, ' ').slice(0, 140));
      }
      if (section.quote) {
        parts.push('Zitat: ' + String(section.quote).replace(/\s+/g, ' ').slice(0, 110));
      }
      if ((section.object_refs || []).length) {
        parts.push(String((section.object_refs || []).length) + ' Archivobjekt(e)');
      }
      if ((section.media_refs || []).length) {
        parts.push(String((section.media_refs || []).length) + ' Medien');
      }

      return parts.join(' · ');
    }

    function saveDocument() {
      updateField();
      if (!config.restRoot || !config.nonce || !postId || !format) {
        return;
      }

      setStatus((config.strings && config.strings.savingPermanent) || 'JSON-Komposition wird gespeichert...');
      window.fetch(config.restRoot.replace(/\/$/, '') + '/document/' + encodeURIComponent(String(postId)) + '/' + encodeURIComponent(format) + '/save', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        },
        body: JSON.stringify({ document: documentState, enabled: currentEnabled() })
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('save failed');
        }
        return response.json();
      }).then(function (response) {
        if (response && response.document) {
          documentState = response.document;
          render();
        }
        setStatus((config.strings && config.strings.savedPermanent) || 'JSON-Komposition gespeichert.');
      }).catch(function () {
        setStatus((config.strings && config.strings.error) || 'Speichern fehlgeschlagen.');
      });
    }

    function scheduleAutosave() {
      updateField();
      window.clearTimeout(autosaveTimer);
      autosaveTimer = window.setTimeout(function () {
        if (!config.restRoot || !config.nonce || !postId || !format) {
          return;
        }

        setStatus((config.strings && config.strings.saving) || 'Automatische Sicherung...');
        window.fetch(config.restRoot.replace(/\/$/, '') + '/document/' + encodeURIComponent(String(postId)) + '/' + encodeURIComponent(format) + '/autosave', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce
          },
          body: JSON.stringify({ document: documentState })
        }).then(function (response) {
          if (!response.ok) {
            throw new Error('autosave failed');
          }
          setStatus((config.strings && config.strings.saved) || 'Automatisch gesichert.');
        }).catch(function () {
          setStatus((config.strings && config.strings.error) || 'Automatische Sicherung fehlgeschlagen.');
        });
      }, 1200);
    }

    function addSection(type) {
      documentState.sections.push({
        type: type,
        title: '',
        body: '',
        object_refs: [],
        media_refs: []
      });
      render();
      openEditor(documentState.sections.length - 1);
      scheduleAutosave();
    }

    function moveSection(index, direction) {
      var next = index + direction;
      if (next < 0 || next >= documentState.sections.length) {
        return;
      }
      var item = documentState.sections.splice(index, 1)[0];
      documentState.sections.splice(next, 0, item);
      render();
      scheduleAutosave();
    }

    function removeSection(index) {
      documentState.sections.splice(index, 1);
      closeModal();
      render();
      scheduleAutosave();
    }

    function renderPalette(target) {
      var list = createElement('div', 'iss-editorial-palette');
      Object.keys(sections).forEach(function (type) {
        var button = createElement('button', 'iss-editorial-gesture' + (type === activeType ? ' active' : ''));
        var dot = createElement('span', 'iss-editorial-gesture__dot');
        var body = createElement('span', 'iss-editorial-gesture__body');
        button.type = 'button';
        dot.style.backgroundColor = sectionTone(type);
        body.appendChild(createElement('strong', '', sectionConfig(type).label || type));
        body.appendChild(createElement('span', '', sectionConfig(type).description || type));
        button.appendChild(dot);
        button.appendChild(body);
        button.addEventListener('click', function () {
          activeType = type;
          addSection(type);
        });
        list.appendChild(button);
      });
      target.appendChild(list);
    }

    function renderSectionCard(section, index, target) {
      var type = section.type || 'kapitel';
      var card = createElement('article', 'iss-editorial-card iss-editorial-card--' + type);
      var marker = createElement('span', 'iss-editorial-card__marker');
      var meta = createElement('div', 'iss-editorial-card__meta');
      var actions = createElement('div', 'iss-editorial-card__actions');
      var edit = createElement('button', 'button button-primary', 'Bearbeiten');
      var up = createElement('button', 'button', 'Hoch');
      var down = createElement('button', 'button', 'Runter');

      marker.style.backgroundColor = sectionTone(type);
      meta.appendChild(createElement('span', 'iss-editorial-card__type', sectionConfig(type).label || type));
      meta.appendChild(createElement('h3', '', section.title || 'Ohne Titel'));
      meta.appendChild(createElement('p', '', sectionSummary(section) || 'Noch kein Inhalt.'));
      if ((section.media_refs || []).length) {
        renderMediaThumbs(section, meta);
      }

      [edit, up, down].forEach(function (button) {
        button.type = 'button';
      });
      edit.addEventListener('click', function () { openEditor(index); });
      up.disabled = index === 0;
      down.disabled = index >= documentState.sections.length - 1;
      up.addEventListener('click', function () { moveSection(index, -1); });
      down.addEventListener('click', function () { moveSection(index, 1); });
      actions.appendChild(edit);
      actions.appendChild(up);
      actions.appendChild(down);

      card.appendChild(marker);
      card.appendChild(meta);
      card.appendChild(actions);
      target.appendChild(card);
    }

    function renderStage(target) {
      var stage = createElement('div', 'iss-editorial-stage');
      var head = createElement('div', 'iss-editorial-stage__head');
      var save = createElement('button', 'button button-primary', (config.strings && config.strings.savePermanent) || 'JSON speichern');
      save.type = 'button';
      save.addEventListener('click', saveDocument);
      head.appendChild(createElement('div', '', 'Komposition'));
      head.appendChild(save);
      stage.appendChild(head);

      if (!documentState.sections.length) {
        stage.appendChild(createElement('p', 'iss-editorial-empty', 'Noch keine Abschnitte. Links eine Geste wählen.'));
      } else {
        documentState.sections.forEach(function (section, index) {
          renderSectionCard(section, index, stage);
        });
      }

      target.appendChild(stage);
    }

    function renderReferenceTray(section, target, rerender) {
      clear(target);
      (section.object_refs || []).forEach(function (reference, index) {
        var item = createElement('button', 'button iss-editorial-ref', reference.label || 'Archivobjekt #' + String(reference.id || ''));
        item.type = 'button';
        item.addEventListener('click', function () {
          section.object_refs.splice(index, 1);
          rerender();
          scheduleAutosave();
        });
        target.appendChild(item);
      });

      if (!(section.object_refs || []).length) {
        target.appendChild(createElement('p', 'description', 'Noch kein Archivobjekt ausgewählt.'));
      }
    }

    function renderMediaThumbs(section, target) {
      var strip = createElement('div', 'iss-editorial-media-strip');
      (section.media_refs || []).slice(0, 6).forEach(function (reference) {
        var item = createElement('span', 'iss-editorial-media-thumb');
        if (reference.thumbnail) {
          var image = document.createElement('img');
          image.src = reference.thumbnail;
          image.alt = '';
          item.appendChild(image);
        } else {
          item.textContent = reference.label || 'Bild';
        }
        strip.appendChild(item);
      });
      target.appendChild(strip);
    }

    function renderMediaTray(section, target, rerender) {
      clear(target);
      (section.media_refs || []).forEach(function (reference, index) {
        var item = createElement('article', 'iss-editorial-media-item');
        var preview = createElement('div', 'iss-editorial-media-item__preview');
        var controls = createElement('div', 'iss-editorial-media-item__controls');
        var remove = createElement('button', 'button button-link-delete', 'Entfernen');
        if (reference.thumbnail) {
          var image = document.createElement('img');
          image.src = reference.thumbnail;
          image.alt = '';
          preview.appendChild(image);
        } else {
          preview.textContent = 'Bild';
        }

        controls.appendChild(createTextInput('Bildunterschrift', reference.label || '', function (value) {
          reference.label = value;
          render();
          scheduleAutosave();
        }));

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
        target.appendChild(createElement('p', 'description', 'Noch keine Bilder ausgewählt.'));
      }
    }

    function closeModal() {
      if (modal) {
        modal.remove();
        modal = null;
      }
    }

    function openEditor(index) {
      var section = documentState.sections[index];
      if (!section) {
        return;
      }

      closeModal();
      modal = createElement('div', 'iss-editorial-modal');
      var dialog = createElement('div', 'iss-editorial-modal__dialog');
      var head = createElement('div', 'iss-editorial-modal__head');
      var body = createElement('div', 'iss-editorial-modal__body');
      var foot = createElement('div', 'iss-editorial-modal__foot');
      var title = createElement('h2', '', sectionConfig(section.type).label || section.type || 'Abschnitt');
      var close = createElement('button', 'button', 'Schließen');
      var remove = createElement('button', 'button button-link-delete', 'Abschnitt entfernen');
      var done = createElement('button', 'button button-primary', 'Übernehmen');

      close.type = 'button';
      close.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeModal();
      });
      remove.type = 'button';
      remove.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        removeSection(index);
      });
      done.type = 'button';
      done.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeModal();
        render();
        scheduleAutosave();
      });

      head.appendChild(title);
      head.appendChild(close);
      renderSectionFields(section, body);
      foot.appendChild(remove);
      foot.appendChild(done);
      dialog.appendChild(head);
      dialog.appendChild(body);
      dialog.appendChild(foot);
      modal.appendChild(dialog);
      document.body.appendChild(modal);
    }

    function renderSectionFields(section, body) {
      var type = section.type || 'kapitel';
      var titleField = createTextInput('Titel', section.title || '', function (value) {
        section.title = value;
        render();
        scheduleAutosave();
      });
      var bodyField = createTextarea('Text', section.body || '', function (value) {
        section.body = value;
        render();
        scheduleAutosave();
      });
      body.appendChild(titleField);
      body.appendChild(bodyField);

      if (supports(type, 'quote')) {
        body.appendChild(createTextarea('Zitat', section.quote || '', function (value) {
          section.quote = value;
          render();
          scheduleAutosave();
        }));
        body.appendChild(createTextInput('Zuordnung', section.attribution || '', function (value) {
          section.attribution = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'object_refs')) {
        renderObjectPicker(section, body);
      }

      if (supports(type, 'media_refs')) {
        renderMediaPicker(section, body);
      }
    }

    function renderObjectPicker(section, body) {
      var refs = createElement('div', 'iss-editorial-field iss-editorial-field--refs');
      var tray = createElement('div', 'iss-editorial-ref-tray');
      var pickerMount = createElement('div', 'iss-editorial-picker');
      var pickerButton = createElement('button', 'button', 'Archivobjekte auswählen');

      function rerenderTray() {
        renderReferenceTray(section, tray, rerenderTray);
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
          contextPostId: postId,
          initialSelection: (section.object_refs || []).map(function (reference) { return reference.id; }),
          onConfirm: function (items) {
            section.object_refs = (items || []).map(referenceFromArchiveItem).filter(function (reference) {
              return reference.id;
            });
            rerenderTray();
            updateField();
          }
        });
      });

      refs.appendChild(createElement('span', '', 'Archivobjekte'));
      refs.appendChild(tray);
      refs.appendChild(pickerButton);
      refs.appendChild(pickerMount);
      body.appendChild(refs);
      rerenderTray();
    }

    function renderMediaPicker(section, body) {
      var refs = createElement('div', 'iss-editorial-field iss-editorial-field--media');
      var tray = createElement('div', 'iss-editorial-media-tray');
      var pickerButton = createElement('button', 'button', 'Bilder auswählen');

      function rerenderTray() {
        renderMediaTray(section, tray, rerenderTray);
      }

      pickerButton.type = 'button';
      pickerButton.addEventListener('click', function () {
        if (!window.wp || !wp.media) {
          tray.textContent = 'Medienauswahl ist nicht geladen.';
          return;
        }

        var existing = {};
        (section.media_refs || []).forEach(function (reference) {
          if (reference.id) {
            existing[String(reference.id)] = reference;
          }
        });

        var frame = wp.media({
          title: 'Bilder auswählen',
          button: { text: 'Bilder übernehmen' },
          multiple: true,
          library: { type: 'image' }
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
          section.media_refs = frame.state().get('selection').map(function (attachment) {
            var reference = referenceFromMediaAttachment(attachment.toJSON());
            if (existing[reference.id] && existing[reference.id].label) {
              reference.label = existing[reference.id].label;
            }
            return reference;
          }).filter(function (reference) {
            return reference.id;
          });
          rerenderTray();
          render();
          scheduleAutosave();
        });

        frame.open();
      });

      refs.appendChild(createElement('span', '', 'Bilder'));
      refs.appendChild(tray);
      refs.appendChild(pickerButton);
      body.appendChild(refs);
      rerenderTray();
    }

    function createTextInput(label, value, onChange) {
      var wrapper = createElement('label', 'iss-editorial-field');
      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'widefat';
      input.value = value;
      input.addEventListener('input', function () { onChange(input.value); });
      wrapper.appendChild(createElement('span', '', label));
      wrapper.appendChild(input);
      return wrapper;
    }

    function createTextarea(label, value, onChange) {
      var wrapper = createElement('label', 'iss-editorial-field');
      var input = document.createElement('textarea');
      input.className = 'widefat';
      input.rows = 7;
      input.value = value;
      input.addEventListener('input', function () { onChange(input.value); });
      wrapper.appendChild(createElement('span', '', label));
      wrapper.appendChild(input);
      return wrapper;
    }

    function render() {
      var layout = createElement('div', 'iss-editorial-layout');
      clear(root);
      documentState.sections = Array.isArray(documentState.sections) ? documentState.sections : [];
      renderPalette(layout);
      renderStage(layout);
      root.appendChild(layout);
      updateField();
    }

    if (enabledField) {
      enabledField.addEventListener('change', function () {
        updateField();
        scheduleAutosave();
      });
    }

    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('.iss-editorial-admin'), initEditor);
  });
}());
