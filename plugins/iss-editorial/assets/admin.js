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

  function isSafeRichTextHref(href) {
    var value = String(href || '').trim();
    if (!value) {
      return false;
    }
    if (/^(#|\/|\?|\.{1,2}\/)/.test(value)) {
      return true;
    }
    if (/^[a-z][a-z0-9+.-]*:/i.test(value)) {
      return /^(https?:|mailto:|tel:)/i.test(value);
    }

    return true;
  }

  function appendSanitizedRichNode(node, target) {
    var element;
    var tag;
    var href;

    if (node.nodeType === Node.TEXT_NODE) {
      target.appendChild(document.createTextNode(node.textContent || ''));
      return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
      return;
    }

    tag = String(node.nodeName || '').toLowerCase();
    if (tag === 'b') {
      tag = 'strong';
    } else if (tag === 'i') {
      tag = 'em';
    } else if (tag === 'div') {
      tag = 'p';
    }

    if (['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li'].indexOf(tag) === -1) {
      Array.prototype.forEach.call(node.childNodes, function (child) {
        appendSanitizedRichNode(child, target);
      });
      return;
    }

    element = document.createElement(tag);
    if (tag === 'a') {
      href = node.getAttribute('href') || '';
      if (!isSafeRichTextHref(href)) {
        Array.prototype.forEach.call(node.childNodes, function (child) {
          appendSanitizedRichNode(child, target);
        });
        return;
      }
      element.setAttribute('href', href.trim());
    }

    if (tag !== 'br') {
      Array.prototype.forEach.call(node.childNodes, function (child) {
        appendSanitizedRichNode(child, element);
      });
    }
    target.appendChild(element);
  }

  function sanitizeRichHtml(value) {
    var template = document.createElement('template');
    var target = document.createElement('div');
    template.innerHTML = String(value || '');
    Array.prototype.forEach.call(template.content.childNodes, function (child) {
      appendSanitizedRichNode(child, target);
    });
    if (!(target.textContent || '').trim()) {
      return '';
    }

    return target.innerHTML;
  }

  function escapeText(value) {
    var element = document.createElement('span');
    element.textContent = String(value || '');

    return element.innerHTML;
  }

  function plainTextToRichHtml(value) {
    if (!String(value || '').trim()) {
      return '';
    }

    return String(value || '').split(/\n{2,}/).map(function (paragraph) {
      return '<p>' + paragraph.split(/\n/).map(escapeText).join('<br>') + '</p>';
    }).join('');
  }

  function richTextSummary(value) {
    var element = document.createElement('div');
    element.innerHTML = sanitizeRichHtml(value);

    return (element.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function sectionTone(type) {
    var tones = {
      leitfrage: '#7f77dd',
      quellenauszug: '#d85a30',
      objektfokus: '#1d9e75',
      bildstrecke: '#888780',
      image_wall: '#b98250',
      vollbild: '#185fa5',
      massstab: '#ba7517',
      projekt_rail: '#255f63',
      fliesstext: '#5f5e5a',
      kapitel: '#1a1a2e',
      zitat: '#d4537e',
      aside: '#3c3489',
      schluss: '#a32d2d'
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
      thumbnail: thumbnail,
      width: attachment.width ? String(attachment.width) : '',
      height: attachment.height ? String(attachment.height) : ''
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
    var skins = Array.isArray(config.skins) ? config.skins.filter(function (skin) {
      return skin && skin.slug;
    }) : [];
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

    function isEditorFieldVisible(type, fieldName) {
      if (fieldName === 'anchor') {
        return false;
      }

      return supports(type, fieldName);
    }

    function usesRichBodyEditor(type) {
      return format === 'projekt' && ['kapitel', 'fliesstext', 'schluss'].indexOf(type) !== -1;
    }

    function updateField() {
      field.value = JSON.stringify(documentState);
    }

    function currentSkin() {
      var skin = String(documentState.skin || 'standard');
      var exists = skins.some(function (item) {
        return item.slug === skin;
      });

      return exists ? skin : 'standard';
    }

    function setStatus(message) {
      if (status) {
        status.textContent = message || '';
      }
    }

    function currentEnabled() {
      if (enabledField) {
        return !!enabledField.checked;
      }

      return !!config.enabled;
    }

    function sectionSummary(section) {
      var parts = [];
      if (section.kicker) {
        parts.push(String(section.kicker).replace(/\s+/g, ' ').slice(0, 60));
      }
      if (section.body) {
        parts.push(richTextSummary(section.body).slice(0, 140));
      }
      if ((section.facts || []).length) {
        parts.push(String((section.facts || []).length) + ' Fakt(en)');
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
      if ((section.links || []).length) {
        parts.push(String((section.links || []).length) + ' Link(s)');
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
        setStatus((config.strings && config.strings.savedPermanent) || 'Struktur gespeichert.');
        window.alert((config.strings && config.strings.savedPermanentNotice) || 'Struktur gespeichert. Die sichtbaren Ausstellungsabschnitte sind damit aktualisiert. Den WordPress-Button "Aktualisieren" nur verwenden, wenn Titel, Status, Slug oder andere WordPress-Felder geändert wurden.');
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
        anchor: '',
        kicker: '',
        title: '',
        body: '',
        object_refs: [],
        media_refs: [],
        links: []
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
      var heading = createElement('div', 'iss-editorial-stage__title', 'Komposition');
      var tools = createElement('div', 'iss-editorial-stage__tools');
      var save = createElement('button', 'button iss-editorial-save', (config.strings && config.strings.savePermanent) || 'Speichern');
      save.type = 'button';
      save.addEventListener('click', saveDocument);
      head.appendChild(heading);
      if (skins.length > 1) {
        tools.appendChild(renderSkinControl());
      }
      tools.appendChild(save);
      head.appendChild(tools);
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

    function renderSkinControl() {
      var wrapper = createElement('label', 'iss-editorial-skin-control');
      var label = createElement('span', '', 'Darstellung');
      var select = document.createElement('select');
      select.value = currentSkin();

      skins.forEach(function (skin) {
        var option = document.createElement('option');
        option.value = skin.slug;
        option.textContent = skin.label || skin.slug;
        option.selected = skin.slug === currentSkin();
        select.appendChild(option);
      });

      select.addEventListener('change', function () {
        documentState.skin = select.value || 'standard';
        updateField();
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(label);
      wrapper.appendChild(select);

      return wrapper;
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
        item.appendChild(label);
        item.appendChild(remove);
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

    function renderMediaTray(section, target, rerender) {
      var isFullViewport = (section.type || '') === 'vollbild';
      clear(target);
      if (isFullViewport) {
        target.appendChild(createElement(
          'p',
          'description iss-editorial-media-rule',
          'Vollbild verwendet genau ein 16:9-Bild. Andere Formate werden vollflächig beschnitten.'
        ));
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
          preview.textContent = 'Bild';
        }

        controls.appendChild(createTextInput('Bildunterschrift', reference.label || '', function (value) {
          reference.label = value;
          render();
          scheduleAutosave();
        }));
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
      var kickerField = createTextInput('Kicker', section.kicker || '', function (value) {
        section.kicker = value;
        render();
        scheduleAutosave();
      });
      var titleField = createTextInput('Titel', section.title || '', function (value) {
        section.title = value;
        render();
        scheduleAutosave();
      });
      var bodyField = usesRichBodyEditor(type) ? createRichTextInput('Text', section.body || '', function (value) {
        section.body = value;
        render();
        scheduleAutosave();
      }) : createTextarea('Text', section.body || '', function (value) {
        section.body = value;
        render();
        scheduleAutosave();
      });
      body.appendChild(kickerField);
      body.appendChild(titleField);
      if (isEditorFieldVisible(type, 'anchor')) {
        body.appendChild(createTextInput('Anker', section.anchor || '', function (value) {
          section.anchor = value;
          render();
          scheduleAutosave();
        }));
      }
      if (!supports(type, 'no_body')) {
        body.appendChild(bodyField);
      }

      if (supports(type, 'facts')) {
        renderFactEditor(section, body);
      }

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

      if (supports(type, 'orientation')) {
        renderOrientationControl(section, body);
      }

      if (supports(type, 'links')) {
        renderLinkEditor(section, body);
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
            render();
            scheduleAutosave();
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

    function renderOrientationControl(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--orientation');
      var options = createElement('div', 'iss-editorial-segmented');
      var choices = [
        { value: 'media-left', label: 'Text rechts' },
        { value: 'media-right', label: 'Text links' }
      ];
      var current = section.orientation === 'media-right' ? 'media-right' : 'media-left';

      choices.forEach(function (choice) {
        var label = createElement('label', 'iss-editorial-segmented__option');
        var input = document.createElement('input');
        var text = createElement('span', '', choice.label);
        input.type = 'radio';
        input.name = 'iss-editorial-orientation-' + String(documentState.sections.indexOf(section));
        input.value = choice.value;
        input.checked = current === choice.value;
        input.addEventListener('change', function () {
          if (input.checked) {
            section.orientation = choice.value;
            render();
            scheduleAutosave();
          }
        });
        label.appendChild(input);
        label.appendChild(text);
        options.appendChild(label);
      });

      wrapper.appendChild(createElement('span', '', 'Textposition'));
      wrapper.appendChild(options);
      body.appendChild(wrapper);
    }

    function renderLinkEditor(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--links');
      var rows = createElement('div', 'iss-editorial-link-rows');
      var add = createElement('button', 'button', 'Link hinzufügen');

      function rerenderRows() {
        clear(rows);
        section.links = Array.isArray(section.links) ? section.links : [];
        section.links.forEach(function (link, index) {
          var row = createElement('div', 'iss-editorial-link-row');
          var label = createTextInput('Beschriftung', link.label || '', function (value) {
            link.label = value;
            render();
            scheduleAutosave();
          });
          var url = createTextInput('URL', link.url || '', function (value) {
            link.url = value;
            render();
            scheduleAutosave();
          });
          var remove = createElement('button', 'button button-link-delete', 'Entfernen');
          remove.type = 'button';
          remove.addEventListener('click', function () {
            section.links.splice(index, 1);
            rerenderRows();
            render();
            scheduleAutosave();
          });
          row.appendChild(label);
          row.appendChild(url);
          row.appendChild(remove);
          rows.appendChild(row);
        });
        if (!section.links.length) {
          rows.appendChild(createElement('p', 'description', 'Noch keine Links hinzugefügt.'));
        }
      }

      add.type = 'button';
      add.addEventListener('click', function () {
        section.links = Array.isArray(section.links) ? section.links : [];
        section.links.push({ label: '', url: '' });
        rerenderRows();
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(createElement('span', '', 'Links'));
      wrapper.appendChild(rows);
      wrapper.appendChild(add);
      body.appendChild(wrapper);
      rerenderRows();
    }

    function renderFactEditor(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--facts');
      var rows = createElement('div', 'iss-editorial-fact-rows');
      var add = createElement('button', 'button', 'Fakt hinzufügen');

      function rerenderRows() {
        clear(rows);
        section.facts = Array.isArray(section.facts) ? section.facts : [];
        section.facts.forEach(function (fact, index) {
          var row = createElement('div', 'iss-editorial-fact-row');
          var value = createTextInput('Wert', fact.value || '', function (nextValue) {
            fact.value = nextValue;
            render();
            scheduleAutosave();
          });
          var label = createTextarea('Beschreibung', fact.label || '', function (nextValue) {
            fact.label = nextValue;
            render();
            scheduleAutosave();
          }, 3);
          var remove = createElement('button', 'button button-link-delete', 'Entfernen');
          remove.type = 'button';
          remove.addEventListener('click', function () {
            section.facts.splice(index, 1);
            rerenderRows();
            render();
            scheduleAutosave();
          });
          row.appendChild(value);
          row.appendChild(label);
          row.appendChild(remove);
          rows.appendChild(row);
        });
        if (!section.facts.length) {
          rows.appendChild(createElement('p', 'description', 'Noch keine Fakten hinzugefügt.'));
        }
      }

      add.type = 'button';
      add.addEventListener('click', function () {
        section.facts = Array.isArray(section.facts) ? section.facts : [];
        section.facts.push({ value: '', label: '' });
        rerenderRows();
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(createElement('span', '', 'Fakten'));
      wrapper.appendChild(rows);
      wrapper.appendChild(add);
      body.appendChild(wrapper);
      rerenderRows();
    }

    function renderMediaPicker(section, body) {
      var refs = createElement('div', 'iss-editorial-field iss-editorial-field--media');
      var tray = createElement('div', 'iss-editorial-media-tray');
      var isFullViewport = (section.type || '') === 'vollbild';
      var pickerButton = createElement('button', 'button', isFullViewport ? 'Bild auswählen' : 'Bilder auswählen');

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
          title: isFullViewport ? 'Bild auswählen' : 'Bilder auswählen',
          button: { text: isFullViewport ? 'Bild übernehmen' : 'Bilder übernehmen' },
          multiple: !isFullViewport,
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
          if (isFullViewport) {
            section.media_refs = section.media_refs.slice(0, 1);
          }
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

    function createTextarea(label, value, onChange, rows) {
      var wrapper = createElement('label', 'iss-editorial-field');
      var input = document.createElement('textarea');
      input.className = 'widefat';
      input.rows = rows || 7;
      input.value = value;
      input.addEventListener('input', function () { onChange(input.value); });
      wrapper.appendChild(createElement('span', '', label));
      wrapper.appendChild(input);
      return wrapper;
    }

    function createRichTextInput(label, value, onChange) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--rich-text');
      var toolbar = createElement('div', 'iss-editorial-rich-toolbar');
      var editor = createElement('div', 'iss-editorial-rich-editor');
      var commands = [
        { label: 'P', command: 'formatBlock', value: 'p' },
        { label: 'B', command: 'bold' },
        { label: 'I', command: 'italic' },
        { label: 'Link', command: 'createLink' },
        { label: 'Liste', command: 'insertUnorderedList' },
        { label: '1.', command: 'insertOrderedList' }
      ];

      function commit() {
        var nextValue = sanitizeRichHtml(editor.innerHTML);
        onChange(nextValue);
      }

      function replaceEditorHtml() {
        var cleaned = sanitizeRichHtml(editor.innerHTML);
        if (editor.innerHTML !== cleaned) {
          editor.innerHTML = cleaned;
        }
        onChange(cleaned);
      }

      commands.forEach(function (item) {
        var button = createElement('button', 'button iss-editorial-rich-toolbar__button', item.label);
        button.type = 'button';
        button.addEventListener('click', function (event) {
          var href;
          event.preventDefault();
          editor.focus();
          if (item.command === 'createLink') {
            href = window.prompt('Link URL');
            if (href === null) {
              return;
            }
            if (!isSafeRichTextHref(href)) {
              return;
            }
            document.execCommand('createLink', false, href.trim());
          } else {
            document.execCommand(item.command, false, item.value || null);
          }
          commit();
        });
        toolbar.appendChild(button);
      });

      editor.contentEditable = 'true';
      editor.setAttribute('role', 'textbox');
      editor.setAttribute('aria-label', label);
      editor.setAttribute('data-placeholder', 'Text eingeben');
      editor.innerHTML = /<[a-z][\s\S]*>/i.test(String(value || ''))
        ? sanitizeRichHtml(value)
        : plainTextToRichHtml(value);
      editor.addEventListener('input', commit);
      editor.addEventListener('blur', replaceEditorHtml);
      editor.addEventListener('paste', function (event) {
        var clipboard = event.clipboardData || window.clipboardData;
        var html;
        if (!clipboard) {
          return;
        }
        event.preventDefault();
        html = clipboard.getData('text/html');
        if (html) {
          document.execCommand('insertHTML', false, sanitizeRichHtml(html));
        } else {
          document.execCommand('insertHTML', false, plainTextToRichHtml(clipboard.getData('text/plain')));
        }
        commit();
      });

      wrapper.appendChild(createElement('span', '', label));
      wrapper.appendChild(toolbar);
      wrapper.appendChild(editor);
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
