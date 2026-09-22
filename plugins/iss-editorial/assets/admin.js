(function () {
  var config = window.issEditorialAdmin || {};
  var editorUi = window.issEditorialUi || {};
  var richEditorCounter = 0;
  var treatmentSketch = editorUi.treatmentSketch;
  var createTextInput = editorUi.createTextInput;
  var createNumberInput = editorUi.createNumberInput;
  var createSelect = editorUi.createSelect;
  var createTextarea = editorUi.createTextarea;
  var createCheckbox = editorUi.createCheckbox;


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

  function initEditor(container) {
    var root = container.querySelector('.iss-editorial-root');
    var field = container.querySelector('.iss-editorial-document-field');
    var enabledField = container.querySelector('.iss-editorial-enabled-field');
    var previewButton = container.querySelector('.iss-editorial-preview-button');
    var nativePreviewButton = document.getElementById('post-preview');
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
      features: {},
      sections: []
    });
    var skins = Array.isArray(config.skins) ? config.skins.filter(function (skin) {
      return skin && skin.slug;
    }) : [];
    var pageChoices = Array.isArray(config.pageChoices) ? config.pageChoices.filter(function (page) {
      return page && page.id && page.url;
    }) : [];
    var autosaveTimer = null;
    var richEditorIds = [];
    var saveChain = Promise.resolve();
    var draftToken = config.draftToken || '';
    var draftTokenField = container.querySelector('.iss-editorial-draft-token-field');
    var identityFields = { title: document.getElementById('title'), excerpt: document.getElementById('excerpt') };
    var lastSavedValue = field.value;
    var activeSectionIndex = null;
    var submitting = false;
    var recoveryPending = !!config.recovery;
    var retry = createElement('button', 'button button-secondary', 'Erneut sichern');
    retry.type = 'button';
    retry.hidden = true;
    status.insertAdjacentElement('afterend', retry);
    retry.addEventListener('click', function () { queueSave().catch(function () {}); });
    var livePreview = null;
    var studio = null;
    var studioSection = null;
    var canvasEdit = null;

    function afterCanvasEdit(action) {
      if (livePreview && studio) { livePreview.afterEditing(action); } else { action(); }
    }

    function selectWorkspace(index, fromPreview) {
      if (!documentState.sections[index]) { return; }
      studioSection = documentState.sections[index];
      activeSectionIndex = index;
      disposeRichEditors(studio.body);
      clear(studio.body);
      renderSectionFields(studioSection, studio.body);
      studio.organizeFields();
      studio.body.scrollTop = 0;
      renderWorkspaceOutline();
      if (livePreview) { livePreview.selectSection(index, !fromPreview, studioSection); }
    }

    function renderWorkspaceOutline() {
      studio.outline(documentState.sections, activeSectionIndex || 0, deletedSections().length);
      clear(studio.trashBody);
      deletedSections().forEach(function (section, index) { renderDeletedSectionCard(section, index, studio.trashBody); });
    }

    function receiveCanvasField(data, snapshot) {
      if (!snapshot || !Number.isInteger(data.section) || typeof data.field !== 'string' || ['title', 'kicker', 'body', 'lead'].indexOf(data.field) === -1) { return { accepted: false }; }
      var section = snapshot.refs[data.section];
      var index = documentState.sections.indexOf(section);
      if (!section || index < 0 || typeof data.session !== 'string') { return { accepted: false }; }
      if (data.type === 'iss-preview-field-start') {
        if (canvasEdit || section.type === 'dynamic_slot' || JSON.stringify(section) !== JSON.stringify(snapshot.values[data.section])) { return { accepted: false, message: 'Die Vorschau wird aktualisiert. Bitte danach erneut bearbeiten.' }; }
        var profile = ['title', 'kicker'].indexOf(data.field) !== -1 ? 'plain' : textProfile(section.type, data.field);
        if (!profile || (data.field === 'lead' && !supports(section.type, 'lead'))) { return { accepted: false }; }
        canvasEdit = { section: section, field: data.field, original: section[data.field] || '', session: data.session, sequence: 0 };
        selectWorkspace(index, true);
        studio.editing(true);
        studio.message('Text wird direkt in der Vorschau bearbeitet. Fertig oder Escape beendet die Eingabe.');
        return { accepted: true, value: canvasEdit.original, profile: profile };
      }
      if (!canvasEdit || canvasEdit.section !== section || canvasEdit.field !== data.field || canvasEdit.session !== data.session || !Number.isInteger(data.sequence) || data.sequence <= canvasEdit.sequence) { return { accepted: false }; }
      canvasEdit.sequence = data.sequence;
      if (data.type === 'iss-preview-field-change') {
        if (typeof data.value !== 'string') { return { accepted: false }; }
        section[data.field] = ['title', 'kicker'].indexOf(data.field) !== -1 ? data.value : window.issEditorialRichText.sanitize(data.value, textProfile(section.type, data.field));
      } else if (data.type === 'iss-preview-field-end') {
        if (data.cancel) { section[data.field] = canvasEdit.original; }
        canvasEdit = null;
        studio.editing(false);
        studio.message('');
        selectWorkspace(index, true);
      } else { return { accepted: false }; }
      renderWorkspaceOutline(); updateField(); scheduleAutosave();
      return { accepted: true };
    }

    function renderWorkspace() {
      if (!studio) {
        clear(root);
        studio = window.issEditorialWorkspace(root, {
          sections: sections, hidden: isSectionHidden, status: status, retry: retry, label: config.formatLabel,
          select: function (index) { afterCanvasEdit(function () { selectWorkspace(index); }); },
          remove: function (index) { afterCanvasEdit(function () { removeSection(index); }); },
          insert: function () { afterCanvasEdit(function () { studio.insert(documentState.sections.length); }); },
          add: function (type, index) { afterCanvasEdit(function () { addSection(type, index); }); },
          afterEditing: afterCanvasEdit,
          sketch: function (section) { var choice = treatmentChoices(section.type).find(function (item) { return item.slug === (section.treatment || defaultTreatment(section.type)); }); return treatmentSketch(choice || sectionConfig(section.type)); },
          treatmentLabel: function (section) { var choice = treatmentChoices(section.type).find(function (item) { return item.slug === (section.treatment || defaultTreatment(section.type)); }); return choice ? choice.label : ''; }
        });
        if (skins.length > 1) { studio.tools.appendChild(renderSkinControl()); }
        if (format === 'projekt') { studio.ownerBody.appendChild(renderRailFeatureControl()); }
        renderRouteStationPanel(studio.ownerBody);
        renderTextUpgrade(studio.menu);
        livePreview = window.issEditorialLivePreview(studio, 0, function () { afterCanvasEdit(function () { queueSave().catch(function () {}); }); }, function (index, snapshot) {
          var section = snapshot && snapshot.refs[index];
          var current = documentState.sections.indexOf(section);
          if (current >= 0) { afterCanvasEdit(function () { selectWorkspace(current, true); }); }
        }, {
          field: receiveCanvasField,
          form: function (index, snapshot, message) {
            var current = snapshot ? documentState.sections.indexOf(snapshot.refs[index]) : -1;
            if (current >= 0) { afterCanvasEdit(function () { selectWorkspace(current, true); studio.showView('inspector'); studio.message(message || ''); var input = studio.body.querySelector('input, textarea'); if (input) { input.focus(); } }); }
          },
          insert: function (index, snapshot) { afterCanvasEdit(function () { var before = snapshot.refs[index]; var current = before ? documentState.sections.indexOf(before) : documentState.sections.length; if (current >= 0) { studio.insert(current); } }); }
        });
        if (window.issEditorialDnd) {
          window.issEditorialDnd.bindSectionCanvas({ palette: studio.palette, stage: studio.stage,
            onInsert: function (type, index) { afterCanvasEdit(function () { addSection(type, index); }); },
            onReorder: function (from, to) { afterCanvasEdit(function () { reorderSection(from, to); }); },
            onKeyboardMove: function (index, direction) { afterCanvasEdit(function () { moveSection(index, direction); }); }
          });
        }
        if (!recoveryPending) { window.setTimeout(function () { queueSave().catch(function () {}); }, 0); }
      }
      var selected = documentState.sections.indexOf(studioSection);
      if (selected < 0) { studioSection = null; selected = Math.min(activeSectionIndex || 0, documentState.sections.length - 1); }
      activeSectionIndex = selected;
      if (!studioSection && selected >= 0) { selectWorkspace(selected); }
      if (selected < 0) {
        disposeRichEditors(studio.body); clear(studio.body);
        studio.body.appendChild(createElement('p', '', 'Abschnitte einzeln hinzufügen oder mit einer Vorlage beginnen.'));
        (config.starters || []).filter(function (starter) { return starter.sections.every(function (section) { return !isSectionHidden(section.type); }); }).forEach(function (starter) {
          var button = createElement('button', 'button iss-editorial-starter', starter.label);
          button.type = 'button';
          starter.sections.forEach(function (section) { button.appendChild(treatmentSketch(sectionConfig(section.type))); });
          button.addEventListener('click', function () {
            if (documentState.sections.length) { return; }
            documentState.sections = starter.sections.map(function (section) { return Object.assign(createSection(section.type), JSON.parse(JSON.stringify(section))); });
            activeSectionIndex = 0; render(); scheduleAutosave();
          });
          studio.body.appendChild(button);
        });
      }
      renderWorkspaceOutline();
      studio.body.querySelectorAll('.iss-editorial-opening-note').forEach(function (note) { note.textContent = openingNote(studioSection); note.hidden = !note.textContent; });
      if (livePreview) { livePreview.selectSection(selected, false, documentState.sections[selected]); }
      updateField();
    }
    var routeConfig = config.routeStations && config.routeStations.enabled && format === 'fuehrung'
      ? config.routeStations
      : null;
    var routeFields = container.querySelector('.iss-editorial-route-fields');
    var routeStationEditor = null;

    function sectionConfig(type) {
      return sections[type] || { label: type, supports: [] };
    }

    function isSectionHidden(type) {
      return !!sectionConfig(type).ui_hidden || Object.keys(config.sectionContexts || {}).some(function (key) {
        var allowed = config.sectionContexts[key][documentState[key]];
        return Array.isArray(allowed) && allowed.indexOf(type) === -1;
      });
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

    function textProfile(type, fieldName) {
      return documentState.schema_version >= 2 ? (sectionConfig(type).rich_text || {})[fieldName] || '' : '';
    }

    function usesRichBodyEditor(type) {
      return !supports(type, 'no_body');
    }

    function usePageLinkSelector() {
      return pageChoices.length > 0;
    }

    function normalizedLinkUrl(url) {
      var parsed;
      if (!url) {
        return '';
      }

      try {
        parsed = new URL(String(url), window.location.origin);
      } catch (error) {
        return '';
      }

      return parsed.href;
    }

    function pageChoiceForLink(link) {
      var pageId = parseInt(link.page_id || '0', 10) || 0;
      var url = normalizedLinkUrl(link.url || '');
      var match = pageChoices.filter(function (page) {
        return parseInt(page.id || '0', 10) === pageId;
      })[0];

      if (match) {
        return match;
      }

      return pageChoices.filter(function (page) {
        return url && normalizedLinkUrl(page.url || '') === url;
      })[0] || null;
    }

    function applyPageChoiceToLink(target, page) {
      if (!page) {
        target.page_id = '';
        target.url = '';
        return;
      }

      target.page_id = String(page.id || '');
      target.url = page.url || '';
      if (!target.label) {
        target.label = page.title || '';
      }
    }

    function createPageLinkSelect(target, afterChange) {
      var wrapper = createElement('div', 'iss-editorial-field');
      var pageLabel = createElement('label', 'iss-editorial-field');
      var select = document.createElement('select');
      var current = pageChoiceForLink(target);
      var placeholder = document.createElement('option');

      placeholder.value = '';
      placeholder.textContent = 'Seite wählen';
      select.appendChild(placeholder);

      pageChoices.forEach(function (page) {
        var option = document.createElement('option');
        option.value = String(page.id || '');
        option.textContent = page.title || page.path || page.url;
        option.selected = current && String(current.id) === String(page.id);
        select.appendChild(option);
      });

      select.addEventListener('change', function () {
        var selected = pageChoices.filter(function (page) {
          return String(page.id || '') === select.value;
        })[0] || null;
        applyPageChoiceToLink(target, selected);
        url.querySelector('input').value = target.url;
        if (typeof afterChange === 'function') {
          afterChange();
        }
        render();
        scheduleAutosave();
      });

      pageLabel.appendChild(createElement('span', '', 'Seite'));
      pageLabel.appendChild(select);
      wrapper.appendChild(pageLabel);
      var url = createTextInput('Link-Adresse', target.url || '', function (value) {
        target.url = value;
        target.page_id = '';
        select.value = '';
        scheduleAutosave();
      });
      wrapper.appendChild(url);

      return wrapper;
    }

    function updateField() {
      Object.keys(config.documentBindings || {}).forEach(function (key) {
        var input = document.querySelector(config.documentBindings[key]);
        if (input && input.value) { documentState[key] = input.value; }
      });
      field.value = JSON.stringify(documentState);
    }

    function currentSaveValue() {
      return JSON.stringify([field.value, identityFields.title ? identityFields.title.value : '', identityFields.excerpt ? identityFields.excerpt.value : '', enabledField ? enabledField.value : '1']);
    }

    function currentSkin() {
      var skin = String(documentState.skin || 'standard');
      var exists = skins.some(function (item) {
        return item.slug === skin;
      });

      return exists ? skin : 'standard';
    }

    function currentRailFeature() {
      var features = documentState.features && typeof documentState.features === 'object' ? documentState.features : {};
      var rail = features.rail && typeof features.rail === 'object' ? features.rail : {};
      var hasEnabled = Object.prototype.hasOwnProperty.call(rail, 'enabled');

      return {
        enabled: hasEnabled ? !!rail.enabled : false,
        placement: rail.placement || (currentSkin() === 'dossier' ? 'horizontal' : 'right'),
        mode: rail.mode || 'contextual',
        treatment: rail.treatment || 'quiet'
      };
    }

    function setRailFeature(nextRail) {
      documentState.features = documentState.features && typeof documentState.features === 'object' ? documentState.features : {};
      documentState.features.rail = Object.assign({}, currentRailFeature(), nextRail || {});
      updateField();
      render();
      scheduleAutosave();
    }

    function setStatus(message) {
      if (status) {
        status.textContent = message || '';
      }
    }

    function sectionSummary(section) {
      var parts = [];
      if (section.kicker) {
        parts.push(String(section.kicker).replace(/\s+/g, ' ').slice(0, 60));
      }
      if (section.body) {
        parts.push(richTextSummary(section.body).slice(0, 140));
      }
      if (section.year) {
        parts.push('Jahr: ' + String(section.year).replace(/\s+/g, ' ').slice(0, 24));
      }
      if (section.start_year || section.end_year) {
        parts.push('Zeitraum: ' + [section.start_year || '', section.end_year || 'heute'].join('–'));
      }
      if (section.era_key) {
        parts.push('Epoche: ' + section.era_key);
      }
      if (section.function_key) {
        parts.push('Funktion: ' + section.function_key);
      }
      if (section.media_layout) {
        parts.push(mediaLayoutLabel(section.media_layout, section));
      }
      if (section.gallery_layout) {
        parts.push('Galerie: ' + galleryLayoutLabel(section.gallery_layout));
      }
      if (section.quote_treatment) {
        parts.push(section.quote_treatment === 'source' ? 'Quellenauszug' : 'Zitat');
      }
      if (section.section_treatment) {
        parts.push(section.section_treatment === 'aside' ? 'Ausstellungsentscheidung' : 'Kapitel');
      }
      if (section.treatment) {
        parts.push(treatmentLabel(section.type || '', section.treatment));
      }
      if (section.slot_key) {
        parts.push(slotKeyLabel(section.slot_key));
      }
      if ((section.items || []).length) {
        parts.push(String((section.items || []).length) + ' Ziel(e)');
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
      if (sectionMediaRefsForDisplay(section).length) {
        parts.push(String(sectionMediaRefsForDisplay(section).length) + ((section.type || '') === 'material' ? ' Datei(en)' : ' Medien'));
      }
      if ((section.links || []).length) {
        parts.push(String((section.links || []).length) + ' Link(s)');
      }

      return parts.join(' · ');
    }

    function scheduleAutosave() {
      updateField();
      if (recoveryPending || submitting) { return; }
      window.clearTimeout(autosaveTimer);
      setStatus('Änderungen werden gesichert …');
      if (livePreview) { livePreview.stale('Änderungen werden gesichert …'); }
      autosaveTimer = window.setTimeout(function () {
        queueSave().catch(function () {});
      }, config.livePreview ? 350 : 1200);
    }

    function deletedSections() {
      documentState.deleted_sections = Array.isArray(documentState.deleted_sections) ? documentState.deleted_sections : [];

      return documentState.deleted_sections;
    }

    function focusSectionHandle(index) {
      window.setTimeout(function () {
        var handle = root.querySelector('.iss-editorial-card[data-section-index="' + String(index) + '"] .iss-editorial-card__drag-handle');
        if (handle) {
          handle.focus();
        }
      }, 0);
    }

    function createSection(type) {
      return {
        type: type,
        anchor: '',
        kicker: '',
        title: '',
        body: '',
        lead: supports(type, 'lead') ? '' : undefined,
        object_refs: [],
        media_refs: [],
        links: [],
        items: supports(type, 'items') ? [] : undefined,
        slot_key: supports(type, 'slot_key') ? defaultSlotKey(type) : undefined,
        treatment: defaultTreatment(type)
      };
    }

    function addSection(type, index) {
      var insertAt;
      if (isSectionHidden(type)) {
        return;
      }

      insertAt = Number.isFinite(index) ? index : documentState.sections.length;
      insertAt = Math.max(0, Math.min(documentState.sections.length, insertAt));
      documentState.sections.splice(insertAt, 0, createSection(type));
      render();
      afterCanvasEdit(function () { selectWorkspace(insertAt); studio.showView('inspector'); });
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
      focusSectionHandle(next);
      scheduleAutosave();
    }

    function reorderSection(fromIndex, dropIndex) {
      var item;
      var nextIndex;
      fromIndex = parseInt(fromIndex, 10);
      dropIndex = parseInt(dropIndex, 10);
      if (!Number.isFinite(fromIndex) || !Number.isFinite(dropIndex)) {
        return;
      }
      if (fromIndex < 0 || fromIndex >= documentState.sections.length) {
        return;
      }

      nextIndex = Math.max(0, Math.min(documentState.sections.length, dropIndex));
      if (nextIndex > fromIndex) {
        nextIndex -= 1;
      }
      if (nextIndex === fromIndex) {
        return;
      }

      item = documentState.sections.splice(fromIndex, 1)[0];
      documentState.sections.splice(nextIndex, 0, item);
      render();
      scheduleAutosave();
    }

    function removeSection(index) {
      var removed = documentState.sections.splice(index, 1)[0];
      if (removed && typeof removed === 'object') {
        removed.deleted_at = new Date().toISOString();
        removed.original_index = index;
        deletedSections().unshift(removed);
      }
      render();
      scheduleAutosave();
      if (studio && removed) { studio.removed(removed.title || treatmentLabel(removed.type, removed.treatment) || sectionConfig(removed.type).label, function () { var deletedIndex = deletedSections().indexOf(removed); if (deletedIndex >= 0) { restoreDeletedSection(deletedIndex); } }); }
    }

    function restoreDeletedSection(index) {
      var removed = deletedSections().splice(index, 1)[0];
      var targetIndex;
      if (!removed || typeof removed !== 'object') {
        return;
      }

      targetIndex = parseInt(removed.original_index || '0', 10);
      delete removed.deleted_at;
      delete removed.original_index;
      targetIndex = Math.max(0, Math.min(documentState.sections.length, targetIndex || 0));
      documentState.sections.splice(targetIndex, 0, removed);
      render();
      scheduleAutosave();
    }

    function purgeDeletedSection(index) {
      deletedSections().splice(index, 1);
      render();
      scheduleAutosave();
    }

    function renderDeletedSectionCard(section, index, target) {
      var type = section.type || 'kapitel';
      var card = createElement('article', 'iss-editorial-card iss-editorial-card--deleted iss-editorial-card--' + type);
      var marker = createElement('span', 'iss-editorial-card__marker');
      var meta = createElement('div', 'iss-editorial-card__meta');
      var actions = createElement('div', 'iss-editorial-card__actions');
      var restore = createElement('button', 'button button-secondary', 'Wiederherstellen');
      var purge = createElement('button', 'button button-link-delete', 'Endgültig löschen');

      marker.dataset.tone = sectionConfig(type).tone || 'text';
      meta.appendChild(createElement('span', 'iss-editorial-card__type', 'Papierkorb · ' + (sectionConfig(type).label || type)));
      meta.appendChild(createElement('h3', '', section.title || 'Ohne Titel'));
      meta.appendChild(createElement('p', '', sectionSummary(section) || 'Noch kein Inhalt.'));
      if (section.deleted_at) {
        meta.appendChild(createElement('p', 'iss-editorial-card__trash-note', 'Gelöscht: ' + String(section.deleted_at).replace('T', ' ').replace(/\..+$/, '')));
      }
      if (sectionMediaRefsForDisplay(section).length) {
        renderMediaThumbs(section, meta);
      }

      restore.type = 'button';
      restore.addEventListener('click', function () { restoreDeletedSection(index); });
      actions.appendChild(restore);

      if (config.canPurgeDeletedSections) {
        purge.type = 'button';
        purge.addEventListener('click', function () { purgeDeletedSection(index); });
        actions.appendChild(purge);
      }

      card.appendChild(marker);
      card.appendChild(meta);
      card.appendChild(actions);
      target.appendChild(card);
    }

    function renderTextUpgrade(target) {
      if (documentState.schema_version === 1 && (config.supportedVersions || []).indexOf(2) !== -1) {
        var upgrade = createElement('button', 'button', 'Textfarben aktivieren');
        upgrade.type = 'button';
        upgrade.addEventListener('click', function () {
          ['sections', 'deleted_sections'].forEach(function (key) {
            (documentState[key] || []).forEach(function (section) {
              if ((sectionConfig(section.type).rich_text || {}).items) {
                (section.items || []).forEach(function (item) { item.text = escapeText(item.text || '').replace(/\n/g, '<br>'); });
              }
            });
          });
          documentState.schema_version = (config.supportedVersions || []).indexOf(3) !== -1 ? 3 : 2;
          if (studio) { studio.destroy(); disposeRichEditors(studio.body); livePreview.destroy(); studio = null; livePreview = null; studioSection = null; }
          render(); scheduleAutosave();
        });
        target.appendChild(upgrade);
      }
    }

    function renderRouteStationPanel(target) {
      var mount;
      if (!routeConfig || !window.issRelationsRouteStations || !window.issRelationsRouteStations.create) {
        return;
      }

      if (!routeStationEditor) {
        routeStationEditor = window.issRelationsRouteStations.create({
          config: routeConfig,
          postId: postId,
          fields: routeFields,
          setStatus: setStatus
        });
      }

      mount = createElement('div', 'iss-editorial-route-station-mount');
      target.appendChild(mount);
      routeStationEditor.render(mount);
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
        afterCanvasEdit(function () {
          documentState.skin = select.value || 'standard';
          updateField(); render(); scheduleAutosave();
        });
      });

      wrapper.appendChild(label);
      wrapper.appendChild(select);

      return wrapper;
    }

    function renderRailFeatureControl() {
      var rail = currentRailFeature();
      var wrapper = createElement('div', 'iss-editorial-rail-feature-control');
      var enabled = document.createElement('input');
      var enabledLabel = createElement('label', '');
      var placement = document.createElement('select');
      var treatment = document.createElement('select');

      enabled.type = 'checkbox';
      enabled.checked = !!rail.enabled;
      enabled.addEventListener('change', function () {
        setRailFeature({ enabled: !!enabled.checked });
      });

      enabledLabel.appendChild(enabled);
      enabledLabel.appendChild(document.createTextNode(' Rail'));

      [
        { value: 'left', label: 'Links' },
        { value: 'right', label: 'Rechts' },
        { value: 'top', label: 'Oben' },
        { value: 'bottom', label: 'Unten' },
        { value: 'horizontal', label: 'Horizontal' }
      ].forEach(function (item) {
        var option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.label;
        option.selected = item.value === rail.placement;
        placement.appendChild(option);
      });
      placement.disabled = !rail.enabled;
      placement.setAttribute('aria-label', 'Position der Lesenavigation');
      placement.addEventListener('change', function () {
        setRailFeature({ placement: placement.value || 'right', enabled: true });
      });

      [
        { value: 'quiet', label: 'Ruhig' },
        { value: 'card', label: 'Karte' },
        { value: 'line', label: 'Linie' },
        { value: 'sticky', label: 'Sticky' },
        { value: 'overlay', label: 'Overlay' }
      ].forEach(function (item) {
        var option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.label;
        option.selected = item.value === rail.treatment;
        treatment.appendChild(option);
      });
      treatment.disabled = !rail.enabled;
      treatment.setAttribute('aria-label', 'Darstellung der Lesenavigation');
      treatment.addEventListener('change', function () {
        setRailFeature({ treatment: treatment.value || 'quiet', enabled: true });
      });

      wrapper.appendChild(enabledLabel);
      wrapper.appendChild(placement);
      wrapper.appendChild(treatment);

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
      sectionMediaRefsForDisplay(section).slice(0, 6).forEach(function (reference) {
        var item = createElement('span', 'iss-editorial-media-thumb');
        if (reference.thumbnail) {
          var image = document.createElement('img');
          image.src = reference.thumbnail;
          image.alt = '';
          item.appendChild(image);
        } else {
          item.textContent = reference.label || ((section.type || '') === 'material' ? 'Datei' : 'Bild');
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

    function mediaReferenceIsImage(reference) {
      return String(reference && reference.mime ? reference.mime : '').indexOf('image/') === 0;
    }

    function sectionUsesViewportGallery(section) {
      return (section.type || '') === 'vollbild' || ((section.type || '') === 'galerie' && (section.gallery_layout || '') === 'viewport');
    }

    function sectionMediaRefsForDisplay(section) {
      var refs = section && Array.isArray(section.media_refs) ? section.media_refs : [];
      if ((section.type || '') !== 'material') {
        return refs;
      }

      return refs.filter(function (reference) {
        return !mediaReferenceIsImage(reference);
      });
    }

    function renderMediaTray(section, target, rerender) {
      var isFullViewport = sectionUsesViewportGallery(section);
      var isMaterial = (section.type || '') === 'material';
      clear(target);
      if (isFullViewport) {
        target.appendChild(createElement(
          'p',
          'description iss-editorial-media-rule',
          'Vollbild verwendet genau ein 16:9-Bild. Andere Formate werden vollflächig beschnitten.'
        ));
      }
      if (isMaterial) {
        target.appendChild(createElement(
          'p',
          'description iss-editorial-media-rule',
          'Material verwendet Dateien. Bilder bitte über Galerie einsetzen.'
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
          preview.textContent = isMaterial ? 'Datei' : 'Bild';
        }

        controls.appendChild(createTextInput(isMaterial ? 'Dateiname / Label' : 'Bildunterschrift', reference.label || '', function (value) {
          reference.label = value;
          render();
          scheduleAutosave();
        }));
        if (isMaterial && mediaReferenceIsImage(reference)) {
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

    function disposeRichEditors(within) {
      richEditorIds = richEditorIds.filter(function (id) {
        var input = document.getElementById(id);
        if (within && input && !within.contains(input)) { return true; }
        if (window.wp && window.wp.editor) { window.wp.editor.remove(id); }
        return false;
      });
    }

    function createEditorPanel(name, label, icon, count) {
      return editorUi.createPanel({ name: name, label: label, icon: icon || name, count: count, plain: !!studio, hideHeading: !!studio && ['content', 'display', 'links'].indexOf(name) !== -1 });
    }

    function appendPanelIfUsed(target, panel) {
      if (panel && panel.body && panel.body.children.length) {
        target.appendChild(panel.root);
      }
    }

    function collectionCount(section, key) {
      return Array.isArray(section[key]) ? section[key].length : 0;
    }

    function renderSectionFields(section, body) {
      var type = section.type || 'kapitel';
      var contentPanel = createEditorPanel('content', 'Inhalt', 'content');
      var displayPanel = createEditorPanel('display', 'Darstellung', 'display');
      var archivePanel = createEditorPanel('archive', 'Archiv', 'archive', collectionCount(section, 'object_refs'));
      var mediaPanel = createEditorPanel('media', type === 'material' ? 'Dateien' : 'Bilder', 'media', sectionMediaRefsForDisplay(section).length);
      var albumPanel = createEditorPanel('album', 'Album', 'album', collectionCount(section, 'sheets'));
      var factPanel = createEditorPanel('facts', 'Fakten', 'facts', collectionCount(section, 'facts'));
      var itemPanel = createEditorPanel('items', type === 'text_bild_reihe' ? 'Bild-Text-Paare' : 'Ziele', 'items', collectionCount(section, 'items'));
      var linkPanel = createEditorPanel('links', 'Links', 'links', collectionCount(section, 'links'));
      var sourcePanel = createEditorPanel('sources', 'Quellen', 'links', collectionCount(section, 'source_refs'));
      var kickerField = createTextInput('Vorspann', section.kicker || '', function (value) {
        section.kicker = value;
        render();
        scheduleAutosave();
      });
      var titleField = (studio ? createTextarea : createTextInput)('Titel', section.title || '', function (value) {
        section.title = value;
        render();
        scheduleAutosave();
      });
      if (studio) {
        titleField.classList.add('iss-editorial-field--title');
        var titleInput = titleField.querySelector('textarea'); titleInput.rows = 2;
        var grow = createElement('span', 'iss-editorial-title-input');
        var mirror = createElement('span'); mirror.setAttribute('aria-hidden', 'true');
        function mirrorTitle() { mirror.textContent = titleInput.value + ' '; }
        titleInput.addEventListener('input', mirrorTitle); mirrorTitle();
        titleField.appendChild(grow); grow.append(titleInput, mirror);
      }
      var bodyField = usesRichBodyEditor(type) ? createRichTextInput('Text', section.body || '', function (value) {
        section.body = value;
        render();
        scheduleAutosave();
      }, textProfile(type, 'body')) : createTextarea('Text', section.body || '', function (value) {
        section.body = value;
        render();
        scheduleAutosave();
      });
      if (config.isFrontPage && documentState.schema_version >= 3) { var opening = createElement('p', 'iss-editorial-opening-note', openingNote(section)); opening.hidden = !opening.textContent; contentPanel.body.appendChild(opening); }
      contentPanel.body.appendChild(titleField);
      var optional = studio ? createElement('details', 'iss-editorial-studio__optional') : contentPanel.body;
      if (studio) { optional.appendChild(createElement('summary', '', 'Vorspann & Einleitung (optional)')); contentPanel.body.appendChild(optional); }
      optional.appendChild(kickerField);
      if (supports(type, 'lead')) {
        optional.appendChild(createRichTextInput('Einleitung', section.lead || '', function (value) {
          section.lead = value;
          render();
          scheduleAutosave();
        }, textProfile(type, 'lead')));
      }
      if (isEditorFieldVisible(type, 'anchor')) {
        contentPanel.body.appendChild(createTextInput('Anker', section.anchor || '', function (value) {
          section.anchor = value;
          render();
          scheduleAutosave();
        }));
      }
      if (!supports(type, 'no_body')) {
        contentPanel.body.appendChild(bodyField);
      }

      if (supports(type, 'facts')) {
        renderFactEditor(section, factPanel.body, factPanel.setCount);
      }

      if (supports(type, 'treatment') && !supports(type, 'slot_key')) {
        renderTreatmentControl(section, displayPanel.body);
      }

      if (supports(type, 'items')) {
        if (sectionConfig(type).items_kind === 'text') {
          itemPanel.body.appendChild(createTextarea('Hinweise (ein Eintrag pro Zeile)', (section.items || []).join('\n'), function (value) {
            section.items = value.split(/\r?\n/).filter(function (line) { return line.trim(); });
            itemPanel.setCount(section.items.length);
            render();
            scheduleAutosave();
          }));
        } else {
          renderGatewayItemEditor(section, itemPanel.body, itemPanel.setCount);
        }
      }

      if (supports(type, 'slot_key')) {
        renderSlotKeyControl(section, displayPanel.body);
      }

      if (supports(type, 'year')) {
        contentPanel.body.appendChild(createTextInput('Jahr', section.year || '', function (value) {
          section.year = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'start_year')) {
        contentPanel.body.appendChild(createNumberInput('Beginn', section.start_year || '', 1500, 2100, function (value) {
          section.start_year = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'end_year')) {
        contentPanel.body.appendChild(createNumberInput('Ende (leer = offen)', section.end_year || '', 1500, 2100, function (value) {
          section.end_year = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'era_key')) {
        contentPanel.body.appendChild(createSelect('Historische Epoche', section.era_key || '', [
          { value: '', label: 'Bitte wählen' },
          { value: 'kaiserzeit', label: 'Kaiserzeit' },
          { value: 'weimar', label: 'Weimarer Republik' },
          { value: 'ns-zeit', label: 'NS-Zeit' },
          { value: 'nachkriegszeit', label: 'Nachkriegszeit' },
          { value: 'ddr', label: 'DDR' },
          { value: 'nach-1990', label: 'Nach 1990' }
        ], function (value) {
          section.era_key = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'function_key')) {
        contentPanel.body.appendChild(createSelect('Funktion', section.function_key || '', [
          { value: '', label: 'Bitte wählen' },
          { value: 'industrial', label: 'Industrie / Produktion' },
          { value: 'commercial', label: 'Gewerbe / Handel' },
          { value: 'culture', label: 'Kultur' },
          { value: 'education', label: 'Bildung / Forschung' },
          { value: 'community', label: 'Gemeinwohl / Soziales' },
          { value: 'residential', label: 'Wohnen' },
          { value: 'mixed', label: 'Mischnutzung' },
          { value: 'vacant', label: 'Leerstand' },
          { value: 'infrastructure', label: 'Infrastruktur' }
        ], function (value) {
          section.function_key = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'is_current')) {
        contentPanel.body.appendChild(createCheckbox('Aktuelle Phase', section.is_current, function (checked) {
          section.is_current = checked;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'source_confidence')) {
        sourcePanel.body.appendChild(createSelect('Quellentyp', section.source_confidence || 'unknown', [
          { value: 'unknown', label: 'Unbekannt / nicht bewertet' },
          { value: 'oral', label: 'Mündliche Quelle' },
          { value: 'archive', label: 'Archivquelle' },
          { value: 'publication', label: 'Publikation' },
          { value: 'url', label: 'Webquelle' }
        ], function (value) {
          section.source_confidence = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'source_summary')) {
        sourcePanel.body.appendChild(createTextarea('Quellenhinweis', section.source_summary || '', function (value) {
          section.source_summary = value;
          render();
          scheduleAutosave();
        }, 4));
      }

      if (supports(type, 'source_refs')) {
        renderSourceRefEditor(section, sourcePanel.body, sourcePanel.setCount);
      }

      if (supports(type, 'media_layout')) {
        renderMediaLayoutControl(section, displayPanel.body);
      }

      if (supports(type, 'gallery_layout')) {
        renderGalleryLayoutControl(section, displayPanel.body);
      }

      if (supports(type, 'rail_options')) {
        renderRailEditor(section, displayPanel.body);
      }

      if (supports(type, 'quote')) {
        contentPanel.body.appendChild(createTextarea('Zitat', section.quote || '', function (value) {
          section.quote = value;
          render();
          scheduleAutosave();
        }));
        contentPanel.body.appendChild(createTextInput('Zuordnung', section.attribution || '', function (value) {
          section.attribution = value;
          render();
          scheduleAutosave();
        }));
      }

      if (supports(type, 'quote_treatment')) {
        renderQuoteTreatmentControl(section, displayPanel.body);
      }

      if (supports(type, 'section_treatment')) {
        renderSectionTreatmentControl(section, displayPanel.body);
      }

      if (supports(type, 'object_refs')) {
        renderObjectPicker(section, archivePanel.body, archivePanel.setCount);
      }

      if (supports(type, 'media_refs')) {
        renderMediaPicker(section, mediaPanel.body, mediaPanel.setCount);
      }

      if (supports(type, 'album_source') || supports(type, 'sheets')) {
        renderAlbumEditor(section, albumPanel.body, albumPanel.setCount);
      }

      if (supports(type, 'orientation')) {
        renderOrientationControl(section, displayPanel.body);
      }

      if (supports(type, 'links')) {
        renderLinkEditor(section, linkPanel.body, linkPanel.setCount);
      }
      if (supports(type, 'dynamic_refs') && Array.isArray(section.dynamic_refs)) {
        section.dynamic_refs.forEach(function (reference) {
          var row = createElement('div', 'iss-editorial-field');
          row.appendChild(createElement('p', 'description', (reference.label || reference.key) + ' · Zentral gepflegte Information'));
          var preview = (config.dynamicPreviews || {})[reference.key];
          if (preview) { row.appendChild(createElement('p', '', preview.value || 'Kein aktueller Wert aus Steuerung.')); }
          var remove = createElement('button', 'button button-link-delete', 'Verweis entfernen');
          remove.type = 'button';
          remove.addEventListener('click', function () {
            section.dynamic_refs.splice(section.dynamic_refs.indexOf(reference), 1);
            row.remove();
            render();
            scheduleAutosave();
          });
          row.appendChild(remove);
          sourcePanel.body.appendChild(row);
        });
      }

      appendPanelIfUsed(body, contentPanel);
      appendPanelIfUsed(body, displayPanel);
      appendPanelIfUsed(body, archivePanel);
      appendPanelIfUsed(body, mediaPanel);
      appendPanelIfUsed(body, albumPanel);
      appendPanelIfUsed(body, factPanel);
      appendPanelIfUsed(body, itemPanel);
      appendPanelIfUsed(body, linkPanel);
      appendPanelIfUsed(body, sourcePanel);
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

    function renderMediaLayoutControl(section, body) {
      var choices = mediaLayoutChoices(section);
      var current;
      if (!choices.length) {
        return;
      }

      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--media-layout');
      var options = createElement('div', 'iss-editorial-segmented');
      current = choices.filter(function (choice) {
        return choice.value === section.media_layout;
      })[0] ? section.media_layout : choices[0].value;

      choices.forEach(function (choice) {
        var label = createElement('label', 'iss-editorial-segmented__option');
        var input = document.createElement('input');
        var text = createElement('span', '', choice.label);
        input.type = 'radio';
        input.name = 'iss-editorial-media-layout-' + String(documentState.sections.indexOf(section));
        input.value = choice.value;
        input.checked = current === choice.value;
        input.addEventListener('change', function () {
          if (input.checked) {
            section.media_layout = choice.value;
            render();
            scheduleAutosave();
          }
        });
        label.appendChild(input);
        label.appendChild(text);
        options.appendChild(label);
      });

      wrapper.appendChild(createElement('span', '', 'Bildposition'));
      wrapper.appendChild(options);
      body.appendChild(wrapper);
    }

    function mediaLayoutChoices(section) {
      if (format === 'landing' && (section.type || '') === 'feature') {
        if ((section.treatment || defaultTreatment('feature')) !== 'feature.media-text') {
          return [];
        }

        return [
          { value: '50-50', label: 'Ausgewogen' },
          { value: '40-60', label: 'Text kompakt' },
          { value: '60-40', label: 'Text breit' }
        ];
      }

      return [
        { value: 'inline', label: 'Im Text' },
        { value: 'aside-right', label: 'Rechts daneben' }
      ];
    }

    function mediaLayoutLabel(layout, section) {
      var choices = mediaLayoutChoices(section || {});
      var match = choices.filter(function (choice) {
        return choice.value === layout;
      })[0];

      return match ? 'Bild: ' + match.label : 'Bild: ' + layout;
    }

    function galleryLayoutLabel(layout) {
      if (layout === 'sequence') {
        return 'Strecke';
      }
      if (layout === 'wall') {
        return 'Bilderwand';
      }

      return 'Raster';
    }

    function treatmentChoices(type) {
      return (sectionConfig(type).treatments || []).filter(function (item) {
        return item && item.slug && (item.min_version || 1) <= documentState.schema_version && (item.role !== 'opening' || config.isFrontPage);
      });
    }

    function defaultTreatment(type) {
      var choices = treatmentChoices(type);

      return sectionConfig(type).default_treatment || (choices.length ? choices[0].slug : '');
    }

    function treatmentLabel(type, treatment) {
      var choices = treatmentChoices(type);
      var match = choices.filter(function (item) {
        return item.slug === treatment;
      })[0];

      return match ? match.label : treatment;
    }

    function openingNote(selectedSection) {
      if (selectedSection && selectedSection.treatment !== 'feature.opening') { return ''; }
      if (!config.isFrontPage || documentState.schema_version < 3) { return ''; }
      var opening = selectedSection ? documentState.sections.indexOf(selectedSection) : documentState.sections.findIndex(function (section) { return section.treatment === 'feature.opening'; });
      if (opening === -1) { return 'Vorlagenauftakt aktiv. Für einen eigenen Auftakt im ersten hervorgehobenen Inhalt „Seitenauftakt“ wählen.'; }
      var section = documentState.sections[opening];
      if (opening !== 0 || currentSkin() !== 'frontpage' || !(section.title || '').trim() || !(section.media_refs || []).length) {
        return 'Seitenauftakt prüfen: erster Abschnitt, Startseiten-Stil, Titel und Bild erforderlich. Die Vorschau bleibt bei der letzten gültigen Fassung.';
      }
      return 'Seitenauftakt: Dieser erste Abschnitt ersetzt den Auftakt der Vorlage. Titel und Bild sind erforderlich.';
    }

    function renderTreatmentControl(section, body) {
      var choices = treatmentChoices(section.type || 'kapitel');
      if (!choices.length) { return; }
      var wrapper = createElement('fieldset', 'iss-editorial-field iss-editorial-field--treatment');
      wrapper.appendChild(createElement('legend', '', 'Darstellung wählen'));
      var grid = createElement('div', 'iss-editorial-treatment-grid');
      var current = section.treatment || choices[0].slug;
      choices.forEach(function (choice) {
        var label = createElement('label', 'iss-editorial-treatment-choice');
        var input = document.createElement('input'); input.type = 'radio';
        input.name = 'iss-editorial-treatment-' + String(documentState.sections.indexOf(section));
        input.value = choice.slug; input.checked = choice.slug === current;
        input.addEventListener('change', function () {
          if (!input.checked) { return; }
          section.treatment = choice.slug;
          render(); scheduleAutosave();
        });
        label.appendChild(input); label.appendChild(treatmentSketch(choice));
        label.appendChild(createElement('strong', '', choice.label || choice.slug));
        if (choice.hint) { label.appendChild(createElement('span', '', choice.hint)); }
        grid.appendChild(label);
      });
      wrapper.appendChild(grid); body.appendChild(wrapper);
    }

    function slotKeyChoices(type) {
      if (type !== 'dynamic_slot') {
        return [];
      }

      var slots = sectionConfig(type).slots || {};
      return Object.keys(slots).map(function (key) {
        return { value: key, label: slots[key].label, treatment: slots[key].treatment };
      });
    }

    function defaultSlotKey(type) {
      var choices = slotKeyChoices(type);

      return choices.length ? choices[0].value : '';
    }

    function slotKeyLabel(slotKey) {
      var choices = slotKeyChoices('dynamic_slot');
      var match = choices.filter(function (item) {
        return item.value === slotKey;
      })[0];

      return match ? match.label : slotKey;
    }

    function renderSlotKeyControl(section, body) {
      var type = section.type || '';
      var choices = slotKeyChoices(type);
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--slot-key');
      var select = document.createElement('select');

      if (!choices.length) {
        return;
      }

      if (!section.slot_key) {
        section.slot_key = choices[0].value;
      }

      choices.forEach(function (choice) {
        var option = document.createElement('option');
        option.value = choice.value;
        option.textContent = choice.label;
        option.selected = choice.value === section.slot_key;
        select.appendChild(option);
      });

      select.addEventListener('change', function () {
        section.slot_key = select.value || choices[0].value;
        var selected = choices.filter(function (choice) { return choice.value === section.slot_key; })[0];
        if (selected) { section.treatment = selected.treatment; }
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(createElement('span', '', 'Automatische Inhalte'));
      wrapper.appendChild(select);
      body.appendChild(wrapper);
    }

    function renderGalleryLayoutControl(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--gallery-layout');
      var options = createElement('div', 'iss-editorial-segmented');
      var choices = [
        { value: 'grid', label: 'Raster' },
        { value: 'sequence', label: 'Strecke' },
        { value: 'wall', label: 'Bilderwand' },
        { value: 'viewport', label: 'Vollbild' }
      ];
      var current = ['grid', 'sequence', 'wall', 'viewport'].indexOf(section.gallery_layout) !== -1 ? section.gallery_layout : 'grid';

      choices.forEach(function (choice) {
        var label = createElement('label', 'iss-editorial-segmented__option');
        var input = document.createElement('input');
        var text = createElement('span', '', choice.label);
        input.type = 'radio';
        input.name = 'iss-editorial-gallery-layout-' + String(documentState.sections.indexOf(section));
        input.value = choice.value;
        input.checked = current === choice.value;
        input.addEventListener('change', function () {
          if (input.checked) {
            section.gallery_layout = choice.value;
            render();
            scheduleAutosave();
          }
        });
        label.appendChild(input);
        label.appendChild(text);
        options.appendChild(label);
      });

      wrapper.appendChild(createElement('span', '', 'Galerie-Layout'));
      wrapper.appendChild(options);
      body.appendChild(wrapper);
    }

    function renderQuoteTreatmentControl(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--quote-treatment');
      var options = createElement('div', 'iss-editorial-segmented');
      var choices = [
        { value: 'pull', label: 'Zitat' },
        { value: 'source', label: 'Quellenauszug' }
      ];
      var current = section.quote_treatment === 'source' ? 'source' : 'pull';

      choices.forEach(function (choice) {
        var label = createElement('label', 'iss-editorial-segmented__option');
        var input = document.createElement('input');
        var text = createElement('span', '', choice.label);
        input.type = 'radio';
        input.name = 'iss-editorial-quote-treatment-' + String(documentState.sections.indexOf(section));
        input.value = choice.value;
        input.checked = current === choice.value;
        input.addEventListener('change', function () {
          if (input.checked) {
            section.quote_treatment = choice.value;
            render();
            scheduleAutosave();
          }
        });
        label.appendChild(input);
        label.appendChild(text);
        options.appendChild(label);
      });

      wrapper.appendChild(createElement('span', '', 'Zitat-Typ'));
      wrapper.appendChild(options);
      body.appendChild(wrapper);
    }

    function renderSectionTreatmentControl(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--section-treatment');
      var options = createElement('div', 'iss-editorial-segmented');
      var choices = [
        { value: 'standard', label: 'Kapitel' },
        { value: 'aside', label: 'Ausstellungsentscheidung' }
      ];
      var current = section.section_treatment === 'aside' ? 'aside' : 'standard';

      choices.forEach(function (choice) {
        var label = createElement('label', 'iss-editorial-segmented__option');
        var input = document.createElement('input');
        var text = createElement('span', '', choice.label);
        input.type = 'radio';
        input.name = 'iss-editorial-section-treatment-' + String(documentState.sections.indexOf(section));
        input.value = choice.value;
        input.checked = current === choice.value;
        input.addEventListener('change', function () {
          if (input.checked) {
            section.section_treatment = choice.value;
            render();
            scheduleAutosave();
          }
        });
        label.appendChild(input);
        label.appendChild(text);
        options.appendChild(label);
      });

      wrapper.appendChild(createElement('span', '', 'Kapitel-Typ'));
      wrapper.appendChild(options);
      body.appendChild(wrapper);
    }

    function renderLinkEditor(section, body, setCount) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--links');
      var rows = createElement('div', 'iss-editorial-link-rows');
      var add = createElement('button', 'button', 'Link hinzufügen');

      function rerenderRows() {
        clear(rows);
        section.links = Array.isArray(section.links) ? section.links : [];
        setCount(section.links.length);
        section.links.forEach(function (link, index) {
          var row = createElement('div', 'iss-editorial-link-row');
          var label = createTextInput('Beschriftung', link.label || '', function (value) {
            link.label = value;
            render();
            scheduleAutosave();
          });
          var url = usePageLinkSelector()
            ? createPageLinkSelect(link, rerenderRows)
            : createTextInput('URL', link.url || '', function (value) {
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
        section.links.push({ label: '', url: '', page_id: '' });
        rerenderRows();
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(rows);
      wrapper.appendChild(add);
      body.appendChild(wrapper);
      rerenderRows();
    }

    function renderSourceRefEditor(section, body, setCount) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--links');
      var rows = createElement('div', 'iss-editorial-link-rows');
      var add = createElement('button', 'button', 'Quelle hinzufügen');

      function rerenderRows() {
        clear(rows);
        section.source_refs = Array.isArray(section.source_refs) ? section.source_refs : [];
        setCount(section.source_refs.length);
        section.source_refs.forEach(function (link, index) {
          var row = createElement('div', 'iss-editorial-link-row');
          var label = createTextInput('Bezeichnung', link.label || '', function (value) {
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
            section.source_refs.splice(index, 1);
            rerenderRows();
            render();
            scheduleAutosave();
          });
          row.appendChild(label);
          row.appendChild(url);
          row.appendChild(remove);
          rows.appendChild(row);
        });
        if (!section.source_refs.length) {
          rows.appendChild(createElement('p', 'description', 'Noch keine Quellen hinzugefügt.'));
        }
      }

      add.type = 'button';
      add.addEventListener('click', function () {
        section.source_refs = Array.isArray(section.source_refs) ? section.source_refs : [];
        section.source_refs.push({ label: '', url: '' });
        rerenderRows();
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(rows);
      wrapper.appendChild(add);
      body.appendChild(wrapper);
      rerenderRows();
    }

    function renderFactEditor(section, body, setCount) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--facts');
      var rows = createElement('div', 'iss-editorial-fact-rows');
      var add = createElement('button', 'button', 'Fakt hinzufügen');

      function rerenderRows() {
        clear(rows);
        section.facts = Array.isArray(section.facts) ? section.facts : [];
        setCount(section.facts.length);
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

      wrapper.appendChild(rows);
      wrapper.appendChild(add);
      body.appendChild(wrapper);
      rerenderRows();
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

    function renderGatewayItemEditor(section, body, setCount) {
      var isTextImageRow = ['text_bild_reihe', 'map_img'].indexOf(section.type || '') !== -1;
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--gateway-items');
      var rows = createElement('div', 'iss-editorial-gateway-item-rows');
      var add = createElement('button', 'button', isTextImageRow ? 'Bild-Text-Paar hinzufügen' : 'Ziel hinzufügen');

      function rerenderRows() {
        disposeRichEditors(rows);
        clear(rows);
        section.items = Array.isArray(section.items) ? section.items : [];
        setCount(section.items.length);
        section.items.forEach(function (item, index) {
          var row = createElement('div', 'iss-editorial-gateway-item-row');
          var fields = createElement('div', 'iss-editorial-gateway-item-row__fields');
          var media = createElement('div', 'iss-editorial-gateway-item-row__media');
          var tray = createElement('div', 'iss-editorial-media-tray');
          var mediaButton = createElement('button', 'button', 'Bild wählen');
          var remove = createElement('button', 'button button-link-delete', 'Entfernen');

          item.media_refs = Array.isArray(item.media_refs) ? item.media_refs : [];
          fields.appendChild(createTextInput('Beschriftung', item.label || '', function (value) {
            item.label = value;
            render();
            scheduleAutosave();
          }));
          var itemTextInput = textProfile(section.type, 'items') ? createRichTextInput : createTextarea;
          fields.appendChild(itemTextInput('Text', item.text || '', function (value) {
            item.text = value;
            render();
            scheduleAutosave();
          }, textProfile(section.type, 'items') || 3));
          if (!isTextImageRow) {
            fields.appendChild(usePageLinkSelector()
              ? createPageLinkSelect(item, rerenderRows)
              : createTextInput('URL', item.url || '', function (value) {
                item.url = value;
                render();
                scheduleAutosave();
              }));
          }

          function rerenderMedia() {
            renderGatewayItemMedia(item, tray, rerenderMedia);
          }

          mediaButton.type = 'button';
          mediaButton.addEventListener('click', function () {
            openGatewayItemMediaLibrary(item, tray, rerenderMedia);
          });

          remove.type = 'button';
          remove.addEventListener('click', function () {
            section.items.splice(index, 1);
            rerenderRows();
            render();
            scheduleAutosave();
          });

          media.appendChild(createElement('span', '', 'Bild'));
          media.appendChild(tray);
          media.appendChild(mediaButton);
          row.appendChild(fields);
          row.appendChild(media);
          row.appendChild(remove);
          rows.appendChild(row);
          rerenderMedia();
        });
        if (!section.items.length) {
          rows.appendChild(createElement('p', 'description', isTextImageRow ? 'Noch keine Bild-Text-Paare hinzugefügt.' : 'Noch keine Ziele hinzugefügt.'));
        }
      }

      add.type = 'button';
      add.addEventListener('click', function () {
        section.items = Array.isArray(section.items) ? section.items : [];
        section.items.push(isTextImageRow
          ? { label: '', text: '', media_refs: [] }
          : { label: '', text: '', url: '', page_id: '', media_refs: [] });
        rerenderRows();
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(rows);
      wrapper.appendChild(add);
      body.appendChild(wrapper);
      rerenderRows();
    }

    function renderRailEditor(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--rail');
      var options = section.rail_options && typeof section.rail_options === 'object' ? section.rail_options : {};
      var variantField = createElement('label', 'iss-editorial-field');
      var variant = document.createElement('select');

      section.rail_options = {
        show_nav: Object.prototype.hasOwnProperty.call(options, 'show_nav') ? !!options.show_nav : true,
        show_summary: Object.prototype.hasOwnProperty.call(options, 'show_summary') ? !!options.show_summary : true,
        show_related: Object.prototype.hasOwnProperty.call(options, 'show_related') ? !!options.show_related : true,
        variant: options.variant || 'detailed'
      };

      [
        { value: 'detailed', label: 'Detailliert' },
        { value: 'compact', label: 'Kompakt' }
      ].forEach(function (item) {
        var option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.label;
        option.selected = item.value === section.rail_options.variant;
        variant.appendChild(option);
      });
      variant.addEventListener('change', function () {
        section.rail_options.variant = variant.value || 'detailed';
        render();
        scheduleAutosave();
      });

      variantField.appendChild(createElement('span', '', 'Darstellung'));
      variantField.appendChild(variant);

      wrapper.appendChild(createElement('span', '', 'Rail-Inhalt'));
      wrapper.appendChild(createCheckbox('Navigation anzeigen', section.rail_options.show_nav, function (checked) {
        section.rail_options.show_nav = checked;
        render();
        scheduleAutosave();
      }));
      wrapper.appendChild(createCheckbox('Rahmendaten anzeigen', section.rail_options.show_summary, function (checked) {
        section.rail_options.show_summary = checked;
        render();
        scheduleAutosave();
      }));
      wrapper.appendChild(createCheckbox('Weiterlesen anzeigen', section.rail_options.show_related, function (checked) {
        section.rail_options.show_related = checked;
        render();
        scheduleAutosave();
      }));
      wrapper.appendChild(variantField);
      body.appendChild(wrapper);
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
        fetchJson(url).then(function (payload) {
          var items = Array.isArray(payload && payload.items) ? payload.items : [];
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
      var isMaterial = (section.type || '') === 'material';
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
          return reference.id && (!isMaterial || !mediaReferenceIsImage(reference));
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
          library: isMaterial ? { type: 'application' } : { type: 'image' }
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
          mediaType: isMaterial ? 'file' : 'image',
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

    function createRichTextInput(label, value, onChange, profile) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--rich-text');
      var input = document.createElement('textarea');
      var textLabel = createElement('label', '', label);
      var id = 'iss-editorial-text-' + (++richEditorCounter);
      input.id = id;
      input.className = 'widefat';
      input.rows = 8;
      input.value = profile || /<[a-z][\s\S]*>/i.test(String(value || '')) ? value : plainTextToRichHtml(value);
      textLabel.htmlFor = id;
      wrapper.appendChild(textLabel);
      wrapper.appendChild(input);
      input.addEventListener('input', function () { onChange(input.value); });
      function startEditor() {
        if (!input.isConnected || !window.wp || !window.wp.editor) { return; }
        richEditorIds.push(id);
        window.wp.editor.initialize(id, {
          mediaButtons: false,
          quicktags: false,
          tinymce: profile && window.issEditorialRichText ? window.issEditorialRichText.configure({ profile: profile, wrapper: wrapper, palette: config.textPalette, onChange: onChange, onClose: scheduleAutosave }) : {
            wpautop: true,
            menubar: false,
            statusbar: false,
            height: 230,
            toolbar1: 'undo redo | bold italic | bullist numlist | link unlink | removeformat',
            toolbar2: '',
            setup: function (editor) {
              editor.on('input change undo redo', function () { onChange(editor.getContent()); });
              editor.on('keydown', function (event) {
                if (event.key === 'Escape') {
                  event.preventDefault();
                  // TinyMCE must finish handling the key before its selection is destroyed.
                  window.setTimeout(scheduleAutosave, 0);
                }
              });
            }
          }
        });
      }
      if (profile && window.issEditorialRichText.hasUnsupportedMarkup(input.value, profile)) {
        input.readOnly = true;
        var warning = createElement('p', 'description', 'Dieser Text enthält ältere Formatierung. Sie bleibt erhalten. Vor der Bearbeitung bitte prüfen; Vereinfachen entfernt nicht unterstützte Gestaltung.');
        var simplify = createElement('button', 'button', 'Formatierung vereinfachen');
        simplify.type = 'button';
        simplify.addEventListener('click', function () {
          input.value = window.issEditorialRichText.sanitize(input.value, profile);
          input.readOnly = false;
          onChange(input.value);
          warning.remove(); simplify.remove(); startEditor();
        });
        wrapper.appendChild(warning); wrapper.appendChild(simplify);
      } else {
        window.setTimeout(startEditor, 0);
      }
      return wrapper;
    }

    function render() {
      documentState.sections = Array.isArray(documentState.sections) ? documentState.sections : [];
      documentState.deleted_sections = deletedSections();
      if (recoveryPending) { updateField(); return; }
      if (!window.issEditorialWorkspace || !window.issEditorialLivePreview) {
        setStatus('Die Arbeitsfläche konnte nicht geladen werden. Bitte die Seite neu laden.');
        return;
      }
      renderWorkspace();
    }

    function saveRouteStationsIfDirty() {
      if (!routeStationEditor || !routeStationEditor.saveIfDirty) {
        return Promise.resolve();
      }

      return routeStationEditor.saveIfDirty();
    }

    function appendRoutePreviewArgs(previewUrl) {
      if (!routeStationEditor || !routeStationEditor.getPreviewArgs) {
        return previewUrl;
      }

      var args = routeStationEditor.getPreviewArgs() || {};
      var keys = Object.keys(args);
      var next;
      if (!keys.length) {
        return previewUrl;
      }

      next = new URL(previewUrl, window.location.origin);
      keys.forEach(function (key) {
        next.searchParams.set(key, String(args[key]));
      });

      return next.toString();
    }

    function savePreviewDocument(intent) {
      var params = new window.URLSearchParams();
      var saveUrl = config.ajaxUrl || window.ajaxurl || '';

      if (!saveUrl || !config.previewNonce || !config.postId || !format) {
        setStatus((config.strings && config.strings.previewError) || 'Die Vorschau konnte nicht vorbereitet werden.');
        return Promise.reject(new Error('missing preview config'));
      }

      updateField();
      var snapshot = currentSaveValue();
      var previewSections = { refs: documentState.sections.slice(), values: JSON.parse(JSON.stringify(documentState.sections)), labels: documentState.sections.map(function (section) { return sectionConfig(section.type).label + ' · ' + treatmentLabel(section.type, section.treatment || defaultTreatment(section.type)); }) };
      params.append('action', 'iss_editorial_save_preview_document');
      params.append('nonce', config.previewNonce);
      params.append('post_id', String(config.postId));
      params.append('format', format);
      params.append('document', field.value || JSON.stringify(documentState));
      params.append('base', config.baseToken || '');
      params.append('draft_token', draftToken);
      params.append('enabled', enabledField ? enabledField.value : '1');
      params.append('intent', intent || 'autosave');
      Object.keys(identityFields).forEach(function (key) {
        if (identityFields[key]) { params.append(key, identityFields[key].value); }
      });

      return window.fetch(saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: params.toString()
      }).then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload || !payload.success) {
            throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'preview failed');
          }

          draftToken = payload.data.draftToken || '';
          if (draftTokenField) { draftTokenField.value = draftToken; }
          lastSavedValue = snapshot;
          retry.hidden = true;
          if (payload.data.validationMessage) {
            setStatus('Entwurf gesichert. Vor dem Veröffentlichen prüfen: ' + payload.data.validationMessage);
            if (studio) { studio.message('Entwurf gesichert. Bitte prüfen: ' + payload.data.validationMessage); }
            if (livePreview) { livePreview.stale('Bitte prüfen: ' + payload.data.validationMessage); livePreview.reportSave('Entwurf gesichert. Bitte prüfen: ' + payload.data.validationMessage); }
            if (intent === 'preview' || intent === 'submit') { throw new Error(payload.data.validationMessage); }
            return '';
          }
          if (livePreview) { livePreview.reportSave('Entwurf gesichert · unveröffentlicht'); }
          if (studio && !canvasEdit) { studio.message(''); }
          if (livePreview && currentSaveValue() === snapshot && payload.data.previewUrl) { livePreview.update(payload.data.previewUrl, draftToken, previewSections); }
          setStatus(currentSaveValue() === snapshot ? 'Entwurf gesichert · unveröffentlicht' : 'Weitere Änderungen werden gesichert …');
          return payload.data && payload.data.previewUrl ? payload.data.previewUrl : config.previewUrl;
        });
      });
    }

    function queueSave(intent) {
      window.clearTimeout(autosaveTimer);
      if (recoveryPending || config.validationError) {
        return Promise.reject(new Error(config.validationError || 'Bitte zuerst den vorhandenen Entwurf prüfen.'));
      }
      saveChain = saveChain.catch(function () {}).then(function () {
        return savePreviewDocument(intent);
      });
      return saveChain.catch(function (error) {
        setStatus(error.message || 'Nicht gesichert. Bitte erneut versuchen.');
        if (studio) { studio.message('Nicht gesichert: ' + error.message); }
        retry.hidden = false;
        if (livePreview) { livePreview.stale('Vorschau nicht aktualisiert: ' + error.message); livePreview.reportSave('Nicht gesichert: ' + error.message); }
        throw error;
      });
    }

    function setPreviewButtonBusy(button, isBusy) {
      if (!button) {
        return;
      }

      if ('disabled' in button) {
        button.disabled = !!isBusy;
      }
      button.classList.toggle('disabled', !!isBusy);
      button.setAttribute('aria-disabled', isBusy ? 'true' : 'false');
    }

    function previewUrlFromButton(button) {
      if (!button) {
        return config.previewUrl;
      }

      return button.getAttribute('href') || button.getAttribute('data-preview-url') || config.previewUrl;
    }

    function bindPreviewSave(button) {
      if (!button || button.getAttribute('data-iss-editorial-preview-bound') === '1') {
        return;
      }

      button.setAttribute('data-iss-editorial-preview-bound', '1');
      button.addEventListener('click', function (event) {
        var fallbackUrl = previewUrlFromButton(button);
        event.preventDefault();
        if (recoveryPending || config.validationError) {
          setStatus(config.validationError || 'Bitte zuerst den vorhandenen Entwurf prüfen.');
          return;
        }
        var previewWindow = window.open('', '_blank');
        if (previewWindow) { previewWindow.opener = null; }
        setPreviewButtonBusy(button, true);
        setStatus((config.strings && config.strings.previewSaving) || 'Vorschau wird vorbereitet ...');
        saveRouteStationsIfDirty().then(function () { return queueSave('preview'); }).then(function (previewUrl) {
          var url = appendRoutePreviewArgs(previewUrl || fallbackUrl || config.previewUrl);
          if (previewWindow) {
            previewWindow.location.href = url;
          } else {
            var link = createElement('a', '', 'Vorschau öffnen');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
            status.appendChild(document.createTextNode(' '));
            status.appendChild(link);
          }
        }).catch(function (error) {
          if (previewWindow) { previewWindow.close(); }
          setStatus(error && error.message ? error.message : ((config.strings && config.strings.previewError) || 'Die Vorschau konnte nicht vorbereitet werden.'));
        }).finally(function () {
          setPreviewButtonBusy(button, false);
        });
      });
    }

    if (enabledField) {
      enabledField.addEventListener('change', function () {
        updateField();
        scheduleAutosave();
      });
    }

    bindPreviewSave(previewButton);
    bindPreviewSave(nativePreviewButton);

    render();
    lastSavedValue = currentSaveValue();
    setStatus(config.validationError || 'Gespeicherte Fassung geladen.');
    if (config.validationError) { root.inert = true; }

    Object.keys(identityFields).forEach(function (key) {
      if (identityFields[key]) { identityFields[key].addEventListener('input', scheduleAutosave); }
    });

    Object.keys(config.documentBindings || {}).forEach(function (key) {
      var input = document.querySelector(config.documentBindings[key]);
      if (input) {
        input.addEventListener('change', function () {
          updateField();
          render();
          scheduleAutosave();
        });
      }
    });

    if (recoveryPending) {
      root.inert = true;
      root.hidden = true;
      previewButton.disabled = true;
      setStatus('Bitte zuerst eine Fassung zum Weiterarbeiten wählen.');
      var recovery = container.querySelector('.iss-editorial-recovery');
      var restore = createElement('button', 'button button-primary', 'Entwurf weiterbearbeiten');
      var discard = createElement('button', 'button', 'Entwurf verwerfen');
      var stale = config.recovery.base !== config.baseToken;
      recovery.appendChild(createElement('strong', '', 'Unveröffentlichter Entwurf vorhanden'));
      recovery.appendChild(createElement('p', '', stale
        ? 'Seit Ihrem Entwurf wurde die gespeicherte Seite geändert. Sie können am Entwurf weiterarbeiten und die Änderungen prüfen oder ihn verwerfen und die gespeicherte Seite öffnen.'
        : 'Ihr Entwurf vom ' + config.recovery.modified + ' ist automatisch gesichert. Arbeiten Sie daran weiter oder verwerfen Sie ihn, um die gespeicherte Seite zu öffnen.'));
      restore.type = discard.type = 'button';
      restore.addEventListener('click', function () {
        documentState = JSON.parse(JSON.stringify(config.recovery.document));
        Object.keys(identityFields).forEach(function (key) {
          if (identityFields[key]) { identityFields[key].value = config.recovery[key] || ''; }
        });
        Object.keys(config.documentBindings || {}).forEach(function (key) {
          var input = document.querySelector(config.documentBindings[key]);
          if (input && documentState[key]) { input.value = documentState[key]; }
        });
        recoveryPending = false;
        root.inert = false;
        root.hidden = false;
        previewButton.disabled = false;
        clear(recovery);
        render();
        var firstEdit = root.querySelector('.iss-editorial-outline__select');
        if (firstEdit) { firstEdit.focus(); }
        scheduleAutosave();
      });
      discard.addEventListener('click', function () {
        recoveryPending = false;
        queueSave('discard').then(function () {
          root.inert = false;
          root.hidden = false;
          previewButton.disabled = false;
          clear(recovery);
          render();
          setStatus('Gespeicherte Fassung geladen.');
        }).catch(function () { recoveryPending = true; });
      });
      recovery.appendChild(restore);
      recovery.appendChild(discard);
    }

    var form = container.closest('form');
    if (form) {
      form.addEventListener('submit', function (event) {
        if (submitting) { return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        var submitter = event.submitter;
        afterCanvasEdit(function () { queueSave('submit').then(function () {
          submitting = true;
          form.requestSubmit(submitter || undefined);
        }).catch(function () {}); });
      }, true);
    }
    window.addEventListener('beforeunload', function (event) {
      if (!submitting && currentSaveValue() !== lastSavedValue) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('.iss-editorial-admin'), initEditor);
  });
}());
