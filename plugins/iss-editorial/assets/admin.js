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
    element.innerHTML = window.issEditorialRichText.sanitize(value, 'basic');

    return (element.textContent || '').replace(/\s+/g, ' ').trim();
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
    var documentState = parseJson(field.value, {
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
    var previewTimer = null;
    var previewIdle = true;
    var previewData = null;
    var richEditorIds = [];
    var saveChain = Promise.resolve();
    var draftToken = config.draftToken || '';
    var draftTokenField = container.querySelector('.iss-editorial-draft-token-field');
    var identityFields = { title: document.getElementById('title'), excerpt: document.getElementById('excerpt') };
    var lastSavedValue = field.value;
    var lastSavedStatus = 'Gespeicherte Fassung geladen.';
    var activeSectionIndex = null;
    var submitting = false;
    var recoveryPending = !!config.recovery;
    var sessionChanged = false;
    var sessionMessage = 'Die Anmeldung hat sich geändert. Bitte die Seite neu öffnen. Nicht gesicherte Änderungen bleiben bis dahin in diesem Fenster erhalten.';
    var retry = createElement('button', 'button button-secondary', 'Erneut sichern');
    retry.type = 'button';
    retry.hidden = true;
    status.insertAdjacentElement('afterend', retry);
    retry.addEventListener('click', function () { queueSave('retry').catch(function () {}); });
    var livePreview = null;
    var studio = null;
    var pickers = window.issEditorialPickers.create({config: config, postId: postId, sections: sections, sectionEditor: sectionEditor, presentationValue: presentationValue, render: render, scheduleAutosave: scheduleAutosave, collectionCount: collectionCount, createElement: createElement, clear: clear, getStudio: function () { return studio; }});
    var renderObjectPicker = pickers.renderObjectPicker;
    var renderMediaPicker = pickers.renderMediaPicker;
    var renderAlbumEditor = pickers.renderAlbumEditor;
    var sectionMediaRefsForDisplay = pickers.sectionMediaRefsForDisplay;
    var renderGatewayItemMedia = pickers.renderGatewayItemMedia;
    var openGatewayItemMediaLibrary = pickers.openGatewayItemMediaLibrary;
    var studioSection = null;
    var canvasEdit = null;

    function afterCanvasEdit(action) {
      if (livePreview && studio) { livePreview.afterEditing(action); } else { action(); }
    }

    function selectWorkspace(index, fromPreview, openInspector) {
      if (!documentState.sections[index]) { return; }
      var navigating = studio.root.querySelector('.iss-editorial-studio__navigation').contains(document.activeElement);
      studioSection = documentState.sections[index];
      activeSectionIndex = index;
      disposeRichEditors(studio.body);
      clear(studio.body);
      renderSectionFields(studioSection, studio.body);
      studio.organizeFields(studioSection.type);
      studio.body.scrollTop = 0;
      studio.inspector.scrollTop = 0;
      renderWorkspaceOutline();
      if (openInspector !== false) { studio.showView('inspector'); }
      if (livePreview) { livePreview.selectSection(index, !fromPreview, studioSection); }
      if (openInspector !== false && !fromPreview && !navigating) {
        var firstField = studio.body.querySelector('input, textarea, select');
        if (firstField) { firstField.focus(); }
      }
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
        if (canvasEdit || sectionEditor(section.type).preview_editable === false || JSON.stringify(section) !== JSON.stringify(snapshot.values[data.section])) { return { accepted: false, message: 'Die Vorschau wird aktualisiert. Bitte danach erneut bearbeiten.' }; }
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
      if (data.type === 'iss-preview-field-end') { window.setTimeout(finishPreviewEdit, 0); }
      return { accepted: true };
    }

    function renderWorkspace() {
      if (!studio) {
        clear(root);
        studio = window.issEditorialWorkspace(root, {
          sections: sections, hidden: isSectionHidden, status: status, retry: retry, label: config.formatLabel,
          orderNotice: config.orderNotice, format: format, publishing: config.publishing,
          select: function (index) { afterCanvasEdit(function () { selectWorkspace(index); }); },
          remove: function (index) { afterCanvasEdit(function () { removeSection(index); }); },
          insert: function () { afterCanvasEdit(function () { studio.insert(documentState.sections.length); }); },
          add: function (type, index) { afterCanvasEdit(function () { addSection(type, index); }); },
          afterEditing: afterCanvasEdit,
          finish: function () { return saveRouteStationsIfDirty().then(function () { return queueSave('finish'); }).then(function (url) { return !!url && currentSaveValue() === lastSavedValue; }); },
          sketch: function (section) { var choice = treatmentChoices(section.type).find(function (item) { return item.slug === resolvedTreatment(section); }); return treatmentSketch(choice || sectionConfig(section.type)); },
          treatmentLabel: function (section) { var choice = treatmentChoices(section.type).find(function (item) { return item.slug === resolvedTreatment(section); }); return choice ? choice.label : ''; }
        });
        if (skins.length > 1) { studio.ownerBody.appendChild(renderSkinControl()); }
        if (config.railControl) { studio.ownerBody.appendChild(renderRailFeatureControl()); }
        if (Object.keys(config.identityPalettes || {}).length) { studio.ownerBody.appendChild(renderIdentityControl()); }
        renderRouteStationPanel(studio.ownerBody);
        renderTextUpgrade(studio.menu);
        livePreview = window.issEditorialLivePreview(studio, 0, function () { afterCanvasEdit(function () { queueSave('preview').catch(function () {}); }); }, function (index, snapshot) {
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
        if (!recoveryPending && !config.validationError) {
          window.setTimeout(function () {
            if (currentSaveValue() !== lastSavedValue) { return; }
            previewData = { url: config.previewUrl, token: 'saved:' + config.baseToken, value: lastSavedValue, sections: previewSections() };
            refreshLivePreview();
          }, 0);
        }
      }
      Array.prototype.forEach.call(studio.ownerBody.children, function (control) { if (control.refreshIdentity) { control.refreshIdentity(); } });
      var selected = documentState.sections.indexOf(studioSection);
      if (selected < 0) { studioSection = null; selected = Math.min(activeSectionIndex || 0, documentState.sections.length - 1); }
      activeSectionIndex = selected;
      if (!studioSection && selected >= 0) { selectWorkspace(selected, false, false); }
      if (selected < 0) {
        studio.showView('inspector');
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
    var routeConfig = config.routeStations && config.routeStations.enabled
      ? config.routeStations
      : null;
    var routeFields = container.querySelector('.iss-editorial-route-fields');
    var routeStationEditor = null;

    function sectionConfig(type) {
      return sections[type] || { label: type, supports: [] };
    }

    function sectionEditor(type) {
      return sectionConfig(type).editor || {};
    }

    function isSectionHidden(type) {
      var definition = sectionConfig(type);
      var limit = Number(definition.max_instances || 0);
      return !!definition.ui_hidden || (limit > 0 && documentState.sections.filter(function (section) { return section.type === type; }).length >= limit) || Object.keys(config.sectionContexts || {}).some(function (key) {
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

      return (sectionEditor(type).hidden_fields || []).indexOf(fieldName) === -1 && supports(type, fieldName);
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
        if (input) { documentState[key] = input.value; }
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
        placement: rail.placement || ((config.railPlacementBySkin || {})[currentSkin()] || 'right'),
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

    function scheduleAutosave() {
      updateField();
      if (recoveryPending || submitting || sessionChanged) { return; }
      window.clearTimeout(autosaveTimer);
      window.clearTimeout(previewTimer);
      previewIdle = false;
      setStatus('Änderungen werden gesichert …');
      if (livePreview) { livePreview.stale('Änderungen werden gesichert …'); }
      autosaveTimer = window.setTimeout(function () {
        queueSave('autosave').catch(function () {});
      }, 1200);
      previewTimer = window.setTimeout(function () { previewIdle = true; refreshLivePreview(); }, 2000);
    }

    function previewSections() {
      return { refs: documentState.sections.slice(), values: JSON.parse(JSON.stringify(documentState.sections)), labels: documentState.sections.map(function (section) { return sectionConfig(section.type).label + ' · ' + treatmentLabel(section.type, resolvedTreatment(section)); }) };
    }

    function refreshLivePreview() {
      if (livePreview && previewIdle && previewData && previewData.url && !canvasEdit && !recoveryPending && !submitting && !sessionChanged && currentSaveValue() === previewData.value) {
        livePreview.update(previewData.url, previewData.token, previewData.sections);
      }
    }

    function finishPreviewEdit() {
      if (recoveryPending || submitting || sessionChanged || canvasEdit) { return; }
      window.clearTimeout(previewTimer);
      previewIdle = true;
      queueSave('autosave').then(refreshLivePreview).catch(function () {});
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
      var removed = deletedSections()[index];
      var targetIndex;
      if (!removed || typeof removed !== 'object') {
        return;
      }

      var before = currentSaveValue();
      var candidate = JSON.parse(JSON.stringify(documentState));
      var restored = candidate.deleted_sections.splice(index, 1)[0];
      targetIndex = parseInt(restored.original_index || '0', 10);
      delete restored.deleted_at;
      delete restored.original_index;
      targetIndex = Math.max(0, Math.min(documentState.sections.length, targetIndex || 0));
      candidate.sections.splice(targetIndex, 0, restored);
      // Ask the existing validator before an obsolete slot/type can be normalized by controls.
      saveChain = queueSave('validate', candidate).then(function (validated) {
        if (sessionChanged || currentSaveValue() !== before || deletedSections()[index] !== removed) {
          setStatus('Der Inhalt wurde inzwischen geändert. Bitte erneut wiederherstellen.');
          return;
        }
        // Registered aliases and media previews now belong to the active schema.
        restored = validated.sections[targetIndex];
        deletedSections().splice(index, 1);
        documentState.sections.splice(targetIndex, 0, restored);
        render(); scheduleAutosave();
      }).catch(function (error) {
        setStatus('Wiederherstellen nicht möglich: ' + error.message + ' Der Abschnitt bleibt im Papierkorb.');
      });
    }

    function purgeDeletedSection(index) {
      deletedSections().splice(index, 1);
      render();
      scheduleAutosave();
    }

    function renderDeletedSectionCard(section, index, target) {
      var type = section.type || '';
      var card = createElement('article', 'iss-editorial-card iss-editorial-card--deleted iss-editorial-card--' + type);
      var marker = createElement('span', 'iss-editorial-card__marker');
      var meta = createElement('div', 'iss-editorial-card__meta');
      var actions = createElement('div', 'iss-editorial-card__actions');
      var restore = createElement('button', 'button button-secondary', 'Wiederherstellen');
      var purge = createElement('button', 'button button-link-delete', 'Endgültig löschen');

      marker.dataset.tone = sectionConfig(type).tone || 'text';
      meta.appendChild(createElement('span', 'iss-editorial-card__type', 'Papierkorb · ' + (sectionConfig(type).label || type)));
      meta.appendChild(createElement('h3', '', section.title || 'Ohne Titel'));
      meta.appendChild(createElement('p', '', typeof section.body === 'string' ? richTextSummary(section.body).slice(0, 140) : 'Gespeicherter Abschnitt'));
      if (section.deleted_at) {
        meta.appendChild(createElement('p', 'iss-editorial-card__trash-note', 'Gelöscht: ' + String(section.deleted_at).replace('T', ' ').replace(/\..+$/, '')));
      }
      // Do not interpret media or other fields from a retired section schema.

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
              if ((sectionConfig(section.type).rich_text || {}).items && Array.isArray(section.items)) {
                section.items.forEach(function (item) { if (item && typeof item === 'object') { item.text = escapeText(item.text || '').replace(/\n/g, '<br>'); } });
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
          if (studioSection) { selectWorkspace(documentState.sections.indexOf(studioSection), true, false); }
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
        placement.disabled = treatment.disabled = !enabled.checked;
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
      var type = section.type || '';
      var contentPanel = createEditorPanel('content', 'Inhalt', 'content');
      var displayPanel = createEditorPanel('display', 'Darstellung', 'display');
      var archivePanel = createEditorPanel('archive', 'Archiv', 'archive', collectionCount(section, 'object_refs'));
      var mediaPanel = createEditorPanel('media', sectionEditor(type).media_kind === 'files' ? 'Dateien' : 'Bilder', 'media', sectionMediaRefsForDisplay(section).length);
      var albumPanel = createEditorPanel('album', 'Album', 'album', collectionCount(section, 'sheets'));
      var factPanel = createEditorPanel('facts', 'Fakten', 'facts', collectionCount(section, 'facts'));
      var itemPanel = createEditorPanel('items', sectionConfig(type).items_label || 'Ziele', 'items', collectionCount(section, 'items'));
      var linkPanel = createEditorPanel('links', 'Links', 'links', collectionCount(section, 'links'));
      var sourcePanel = createEditorPanel('sources', 'Quellen', 'links', collectionCount(section, 'source_refs') + collectionCount(section, 'dynamic_refs'));
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
      var titleContext = sectionEditor(type).title_context;
      if (titleContext) {
        var preceding = documentState.sections.slice(0, documentState.sections.indexOf(section)).reverse().find(function (item) { return item.type === titleContext.preceding_type; });
        titleField.querySelector('input, textarea').placeholder = titleContext.placeholder || '';
        contentPanel.body.appendChild(createElement('p', 'description', preceding
          ? titleContext.hint.replace('{title}', preceding.title || titleContext.fallback_title)
          : titleContext.missing_hint));
      }
      if (sectionEditor(type).title_from_post) {
        titleField.querySelector('input, textarea').placeholder = identityFields.title ? identityFields.title.value : '';
      }
      if (sectionEditor(type).title_hint) {
        contentPanel.body.appendChild(createElement('p', 'description', sectionEditor(type).title_hint));
      }
      if (sectionEditor(type).route_stations && routeConfig) {
        var editRoute = createElement('button', 'button', 'Route / Stationen bearbeiten'); editRoute.type = 'button';
        editRoute.addEventListener('click', function () { afterCanvasEdit(function () { studio.showView('settings'); var input = studio.ownerBody.querySelector('.iss-editorial-route-station-mount select'); if (input) { input.focus(); } }); });
        contentPanel.body.appendChild(editRoute);
      }
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

      ['era_key', 'function_key'].forEach(function (field) {
        var vocabulary = (sectionConfig(type).vocabularies || {})[field];
        if (!supports(type, field) || !vocabulary) { return; }
        contentPanel.body.appendChild(createSelect(vocabulary.label, section[field] || '', [
          { value: '', label: 'Bitte wählen' }
        ].concat(vocabulary.choices || []), function (value) {
          section[field] = value;
          render();
          scheduleAutosave();
        }));
      });

      if (supports(type, 'is_current')) {
        contentPanel.body.appendChild(createCheckbox('Aktuelle Phase', section.is_current, function (checked) {
          section.is_current = checked;
          render();
          scheduleAutosave();
        }));
      }

      if (isEditorFieldVisible(type, 'contribution_question')) {
        contentPanel.body.appendChild(createTextInput('Eigene Forschungsfrage (optional, max. 240 Zeichen)', section.contribution_question || '', function (value) {
          section.contribution_question = value;
          scheduleAutosave();
        }));
        contentPanel.body.appendChild(createElement('p', 'description', 'Leer lassen für die automatische Frage zur Zeitspanne dieser Epoche. Eigener Text ersetzt sie. Alle Beiträge gehen in das gemeinsame Set des Ortes; Beiträge unter Material & Beiträge öffnen.'));
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

      if (supports(type, 'image_fit')) {
        displayPanel.body.appendChild(createSelect('Bilddarstellung', section.image_fit || 'cover', [
          { value: 'cover', label: 'Foto im Ausschnitt' }, { value: 'contain', label: 'Ganzes Bild / Logo / Plakat' }
        ], function (value) { section.image_fit = value; render(); scheduleAutosave(); }));
      }

      if (supports(type, 'surface') && section.treatment !== 'feature.opening') {
        displayPanel.body.appendChild(createSelect('Fläche', section.surface || 'default', [
          { value: 'default', label: 'Papier' }, { value: 'soft', label: 'Leise abgesetzt' },
          { value: 'dark', label: 'Dunkel' }, { value: 'accent', label: 'Akzentfarbe' }
        ], function (value) { section.surface = value; render(); scheduleAutosave(); }));
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
      })[0] ? section.media_layout : sectionConfig(section.type).media_layout.default;

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
      var layout = sectionConfig(section.type).media_layout || {};
      var treatments = layout.treatments || [];
      if (treatments.length && treatments.indexOf(resolvedTreatment(section)) === -1) { return []; }
      return layout.choices || [];
    }

    function treatmentChoices(type) {
      var context = (sectionConfig(type).treatment_contexts || {})[currentSkin()] || {};
      return (sectionConfig(type).treatments || []).filter(function (item) {
        return item && item.slug && (!context.allowed || context.allowed.indexOf(item.slug) !== -1) && (item.min_version || 1) <= documentState.schema_version && (item.role !== 'opening' || config.openingSkin);
      });
    }

    function defaultTreatment(type) {
      var choices = treatmentChoices(type);

      var context = (sectionConfig(type).treatment_contexts || {})[currentSkin()] || {};
      return context.default || sectionConfig(type).default_treatment || (choices.length ? choices[0].slug : '');
    }

    function resolvedTreatment(section) {
      var definition = sectionConfig(section.type);
      var choices = definition.treatments || [];
      var explicit = (definition.treatment_aliases || {})[section.treatment] || section.treatment;
      if (explicit) {
        return choices.some(function (choice) { return choice.slug === explicit; }) ? explicit : defaultTreatment(section.type);
      }
      var legacy = section[definition.legacy_treatment_field];
      var context = (definition.treatment_contexts || {})[currentSkin()] || {};
      if (legacy !== undefined) {
        if ((context.legacy || {})[legacy]) { return context.legacy[legacy]; }
        var choice = choices.find(function (item) { return item.legacy_value === legacy; });
        if (choice) { return choice.slug; }
      }
      return defaultTreatment(section.type);
    }

    function treatmentLabel(type, treatment) {
      var choices = treatmentChoices(type);
      var match = choices.filter(function (item) {
        return item.slug === treatment;
      })[0];

      return match ? match.label : treatment;
    }

    function presentationValue(section, fieldName) {
      var definition = sectionConfig(section.type);
      var treatment = resolvedTreatment(section);
      var choice = (definition.treatments || []).find(function (item) { return item.slug === treatment; });
      return definition.legacy_treatment_field === fieldName && choice && choice.legacy_value ? choice.legacy_value : section[fieldName];
    }

    function openingNote(selectedSection) {
      if (selectedSection && selectedSection.treatment !== 'feature.opening') { return ''; }
      if (!config.openingSkin || documentState.schema_version < 3) { return ''; }
      var opening = selectedSection ? documentState.sections.indexOf(selectedSection) : documentState.sections.findIndex(function (section) { return section.treatment === 'feature.opening'; });
      if (opening === -1) { return 'Vorlagenauftakt aktiv. Für einen eigenen Auftakt im ersten hervorgehobenen Inhalt „Seitenauftakt“ wählen.'; }
      var section = documentState.sections[opening];
      if (opening !== 0 || currentSkin() !== config.openingSkin || !(section.title || '').trim() || !(section.media_refs || []).length) {
        return 'Seitenauftakt prüfen: erster Abschnitt, passender Seitenstil (Startseite oder Bühne), Titel und Bild erforderlich. Die Vorschau bleibt bei der letzten gültigen Fassung.';
      }
      return 'Seitenauftakt: Dieser erste Abschnitt ersetzt den Auftakt der Vorlage. Titel und Bild sind erforderlich.';
    }

    function renderTreatmentControl(section, body) {
      var choices = treatmentChoices(section.type || '');
      if (!choices.length) { return; }
      var wrapper = createElement('fieldset', 'iss-editorial-field iss-editorial-field--treatment');
      wrapper.appendChild(createElement('legend', '', 'Darstellung wählen'));
      var grid = createElement('div', 'iss-editorial-treatment-grid');
      var current = resolvedTreatment(section);
      if (section.treatment && !choices.some(function (choice) { return choice.slug === section.treatment; })) {
        wrapper.appendChild(createElement('p', 'description', 'Die gespeicherte Darstellung bleibt erhalten. Für diesen Kontext stehen die folgenden Varianten zur Wahl.'));
      }
      choices.forEach(function (choice) {
        var label = createElement('label', 'iss-editorial-treatment-choice');
        var input = document.createElement('input'); input.type = 'radio';
        input.name = 'iss-editorial-treatment-' + String(documentState.sections.indexOf(section));
        input.value = choice.slug; input.checked = choice.slug === current;
        input.addEventListener('change', function () {
          if (!input.checked) { return; }
          section.treatment = choice.slug;
          var legacyField = sectionConfig(section.type).legacy_treatment_field;
          if (legacyField && choice.legacy_value) { section[legacyField] = choice.legacy_value; }
          if (studioSection === section) {
            selectWorkspace(documentState.sections.indexOf(section), true, false);
            var selectedChoice = studio.body.querySelector('.iss-editorial-treatment-choice input:checked');
            if (selectedChoice) { selectedChoice.focus(); }
          }
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

    function renderSlotKeyControl(section, body) {
      var type = section.type || '';
      var choices = slotKeyChoices(type);
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--slot-key');
      var select = document.createElement('select');
      var label = createElement('label', '', 'Automatische Inhalte');
      var hint = createElement('p', 'description');

      if (!choices.length) {
        return;
      }

      if (!section.slot_key) {
        section.slot_key = choices[0].value;
      }
      select.id = 'iss-editorial-slot-' + documentState.sections.indexOf(section);
      label.htmlFor = select.id;
      hint.id = select.id + '-description';
      function updateHint() {
        var slot = choices.find(function (choice) { return choice.value === section.slot_key; });
        var treatment = slot && treatmentChoices(type).find(function (choice) { return choice.slug === slot.treatment; });
        hint.textContent = treatment ? treatment.hint || '' : '';
        hint.hidden = !hint.textContent;
        if (hint.textContent) { select.setAttribute('aria-describedby', hint.id); }
        else { select.removeAttribute('aria-describedby'); }
      }
      updateHint();

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
        updateHint();
        render();
        scheduleAutosave();
      });

      wrapper.append(label, select, hint);
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

    function renderIdentityControl() {
      var wrapper = createElement('fieldset', 'iss-editorial-field');
      wrapper.appendChild(createElement('legend', '', 'Projektidentität'));
      wrapper.appendChild(createElement('p', 'description', 'Logo und Farbraum gestalten den Auftritt in der Darstellung „Bühne“.'));
      var paletteControl = createSelect('Farbraum', identity().palette || 'neutral', Object.keys(config.identityPalettes).map(function (key) {
        return { value: key, label: config.identityPalettes[key] };
      }), function (palette) {
        afterCanvasEdit(function () {
          documentState.features.identity = Object.assign({}, identity(), { palette: palette });
          render(); scheduleAutosave();
        });
      });
      var select = paletteControl.querySelector('select');
      var tray = createElement('div', 'iss-editorial-media-tray');
      var button = createElement('button', 'button', 'Projektlogo wählen');
      button.type = 'button';
      function identity() { return documentState.features.identity || {}; }
      function refresh() {
        select.value = identity().palette || 'neutral';
        // Render a fresh view so recovery and starter changes cannot retain an old document reference.
        var view = { media_refs: (identity().media_refs || []).slice() };
        renderGatewayItemMedia(view, tray, function () {
          documentState.features.identity = Object.assign({}, identity(), { media_refs: view.media_refs });
          refresh();
        });
      }
      button.addEventListener('click', function () {
        afterCanvasEdit(function () {
          var view = { media_refs: (identity().media_refs || []).slice() };
          openGatewayItemMediaLibrary(view, tray, function () {
            documentState.features.identity = Object.assign({}, identity(), { media_refs: view.media_refs });
            refresh();
          });
        });
      });
      wrapper.append(paletteControl, tray, button);
      wrapper.refreshIdentity = refresh;
      refresh();
      return wrapper;
    }



    function renderGatewayItemEditor(section, body, setCount) {
      var isTextImageRow = sectionConfig(section.type).items_kind === 'text_image';
      var treatment = (sectionConfig(section.type).treatments || []).find(function (choice) { return choice.slug === section.treatment; });
      var itemFields = isTextImageRow && treatment ? treatment.item_fields || [] : [];
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
          if (itemFields.length) {
            var extra = createElement('details', 'iss-editorial-field');
            extra.appendChild(createElement('summary', '', 'Vorspann und Details'));
            if (itemFields.indexOf('eyebrow') !== -1) {
              extra.appendChild(createTextInput('Vorspann / Zeitraum', item.eyebrow || '', function (value) {
                item.eyebrow = value; render(); scheduleAutosave();
              }));
            }
            if (itemFields.indexOf('group') !== -1) {
              extra.appendChild(createTextInput('Filtergruppe (optional)', item.group || '', function (value) {
                item.group = value; render(); scheduleAutosave();
              }));
            }
            if (itemFields.indexOf('detail') !== -1) {
              extra.appendChild(createTextarea('Mehr erfahren', item.detail || '', function (value) {
                item.detail = value; render(); scheduleAutosave();
              }, 4));
            }
            fields.appendChild(extra);
          }
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
          if (studio) { studio.help.hint(mediaButton, 'media'); }
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
          tinymce: window.issEditorialRichText.configure({ profile: profile || 'legacy', wrapper: wrapper, palette: config.textPalette, onChange: onChange, onClose: finishPreviewEdit, onBlur: finishPreviewEdit })
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

    function savePreviewDocument(intent, candidate) {
      var params = new window.URLSearchParams();
      var saveUrl = config.ajaxUrl || window.ajaxurl || '';

      if (!saveUrl || !config.previewNonce || !config.postId || !format) {
        setStatus((config.strings && config.strings.previewError) || 'Die Vorschau konnte nicht vorbereitet werden.');
        return Promise.reject(new Error('missing preview config'));
      }

      updateField();
      var snapshot = currentSaveValue();
      var savedSections = previewSections();
      params.append('action', 'iss_editorial_save_preview_document');
      params.append('nonce', config.previewNonce);
      params.append('post_id', String(config.postId));
      params.append('format', format);
      params.append('document', candidate ? JSON.stringify(candidate) : field.value || JSON.stringify(documentState));
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
          if (intent === 'validate') { return payload.data.document; }

          draftToken = payload.data.draftToken || '';
          if (draftTokenField) { draftTokenField.value = draftToken; }
          lastSavedValue = snapshot;
          retry.hidden = true;
          if (payload.data.validationMessage) {
            previewData = null;
            lastSavedStatus = 'Entwurf gesichert. Vor dem Veröffentlichen prüfen: ' + payload.data.validationMessage;
            setStatus(lastSavedStatus);
            if (studio) { studio.message('Entwurf gesichert. Bitte prüfen: ' + payload.data.validationMessage); }
            if (livePreview) { livePreview.fallback(config.previewUrl, 'saved:' + config.baseToken, 'Bitte prüfen: ' + payload.data.validationMessage); livePreview.reportSave('Entwurf gesichert. Bitte prüfen: ' + payload.data.validationMessage); }
            if (intent === 'preview' || intent === 'submit') {
              var validationError = new Error(payload.data.validationMessage);
              validationError.draftSaved = true;
              throw validationError;
            }
            return '';
          }
          if (livePreview) { livePreview.reportSave('Entwurf gesichert · unveröffentlicht'); }
          if (studio && !canvasEdit) { studio.message(''); }
          previewData = { url: intent === 'discard' ? config.previewUrl : payload.data.previewUrl, token: intent === 'discard' ? 'saved:' + config.baseToken : draftToken, value: snapshot, sections: savedSections };
          if (['preview', 'retry', 'finish', 'discard'].indexOf(intent) !== -1) { previewIdle = true; }
          if (intent !== 'submit') { refreshLivePreview(); }
          lastSavedStatus = intent === 'discard' ? 'Gespeicherte Fassung geladen.' : 'Entwurf gesichert · unveröffentlicht';
          setStatus(currentSaveValue() === snapshot ? lastSavedStatus : 'Weitere Änderungen werden gesichert …');
          return payload.data && payload.data.previewUrl ? payload.data.previewUrl : config.previewUrl;
        });
      });
    }

    function queueSave(intent, candidate) {
      if (sessionChanged) { return Promise.reject(new Error(sessionMessage)); }
      if (intent !== 'validate') { window.clearTimeout(autosaveTimer); }
      if (recoveryPending || config.validationError) {
        return Promise.reject(new Error(config.validationError || 'Bitte zuerst den vorhandenen Entwurf prüfen.'));
      }
      saveChain = saveChain.catch(function () {}).then(function () {
        if (sessionChanged) { throw new Error(sessionMessage); }
        updateField();
        if ((!intent || intent === 'autosave') && currentSaveValue() === lastSavedValue) {
          if (retry.hidden) { setStatus(lastSavedStatus); }
          refreshLivePreview();
          return previewData && previewData.value === lastSavedValue ? previewData.url : '';
        }
        return savePreviewDocument(intent, candidate);
      });
      return saveChain.catch(function (error) {
        if (intent === 'validate' || error.draftSaved) { throw error; }
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

    if (window.wp && window.wp.hooks) {
      window.wp.hooks.addAction('heartbeat.send', 'iss-editorial/session', function (data) {
        data['iss-editorial-user'] = config.userId;
      });
      window.wp.hooks.addAction('heartbeat.tick', 'iss-editorial/session', function (data) {
        if (data['iss-editorial-session-changed']) {
          if (sessionChanged) { return; }
          sessionChanged = true;
          window.clearTimeout(autosaveTimer);
          window.clearTimeout(previewTimer);
          var oldDialog = document.getElementById('post-lock-dialog');
          if (oldDialog) { window.jQuery(oldDialog).hide(); }
          var notice = createElement('p', 'notice notice-warning', sessionMessage + ' ');
          notice.setAttribute('role', 'alert');
          var reopen = createElement('a', '', 'Seite neu öffnen');
          reopen.href = window.location.href;
          notice.appendChild(reopen);
          container.insertBefore(notice, container.firstChild);
          setStatus(sessionMessage);
          notice.tabIndex = -1;
          notice.focus();
          notice.scrollIntoView({ block: 'center' });
          return;
        }
        var lock = data['wp-refresh-post-lock'];
        var dialog = document.getElementById('post-lock-dialog');
        if (!lock || !dialog || sessionChanged) { return; }
        if (lock.new_lock && dialog.getClientRects().length) {
          // Core updates the lock field but leaves an already-open takeover dialog visible.
          window.jQuery(dialog).hide();
          if (!recoveryPending) { queueSave('check').catch(function () {}); }
        }
      });
    }

    Object.keys(identityFields).forEach(function (key) {
      if (identityFields[key]) {
        identityFields[key].addEventListener('input', scheduleAutosave);
        identityFields[key].addEventListener('blur', finishPreviewEdit);
      }
    });
    container.addEventListener('focusout', function (event) {
      if (event.target.matches('input, textarea, select')) { finishPreviewEdit(); }
    });

    Object.keys(config.documentBindings || {}).forEach(function (key) {
      var input = document.querySelector(config.documentBindings[key]);
      if (input) {
        input.addEventListener('input', scheduleAutosave);
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
          if (input) { input.value = documentState[key] || ''; }
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
        }).catch(function (error) {
          setStatus(error.message || 'Speichern nicht möglich. Bitte die Angaben prüfen.');
          var target = recoveryPending ? container.querySelector('.iss-editorial-recovery') : status;
          target.tabIndex = -1;
          target.focus();
          target.scrollIntoView({ block: 'center' });
        }); });
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
