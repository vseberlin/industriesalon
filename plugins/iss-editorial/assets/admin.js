(function () {
  var config = window.issEditorialAdmin || {};
  var editorUi = window.issEditorialUi || {};

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

  function currentRichTextLink(editor) {
    var selection = window.getSelection ? window.getSelection() : null;
    var node;
    if (!selection || selection.rangeCount < 1) {
      return null;
    }

    node = selection.anchorNode;
    if (node && node.nodeType === Node.TEXT_NODE) {
      node = node.parentElement;
    }
    if (!node || !editor.contains(node)) {
      return null;
    }

    return node.closest ? node.closest('a') : null;
  }

    function sectionTone(type) {
      var tones = {
        bildbuehne: '#185fa5',
        leitfrage: '#7f77dd',
        objektfokus: '#1d9e75',
        vollbild: '#185fa5',
        massstab: '#ba7517',
        publication_rail: '#255f63',
      intro: '#b94436',
      longread_chapter: '#1a1a2e',
      longread_quote: '#8a3b59',
      timeline_item: '#3a6c8f',
        photoalbum: '#426d54',
        galerie: '#426d54',
        statement: '#a32d2d',
        gateway: '#255f63',
        feature: '#426d54',
        dynamic_slot: '#5f5e5a',
        fliesstext: '#5f5e5a',
        kapitel: '#1a1a2e',
        zitat: '#d4537e',
        material: '#6b5b35',
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
    var activeType = Object.keys(sections).filter(function (type) {
      return !isSectionHidden(type);
    })[0] || 'kapitel';
    var modal = null;
    var routeConfig = config.routeStations && config.routeStations.enabled && format === 'fuehrung'
      ? config.routeStations
      : null;
    var routeFields = container.querySelector('.iss-editorial-route-fields');
    var routeRelations = routeConfig && Array.isArray(routeConfig.relations)
      ? routeConfig.relations.map(normalizeRouteRelation)
      : [];

    function sectionConfig(type) {
      return sections[type] || { label: type, supports: [] };
    }

    function isSectionHidden(type) {
      return !!sectionConfig(type).ui_hidden;
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
      if (format === 'projekt' && ['kapitel', 'fliesstext', 'schluss'].indexOf(type) !== -1) {
        return true;
      }

      if (format === 'fuehrung' && ['bildbuehne', 'intro', 'kapitel', 'leitfrage', 'material', 'schluss'].indexOf(type) !== -1) {
        return true;
      }

      if (format === 'landing' && ['statement', 'fliesstext', 'gateway', 'feature'].indexOf(type) !== -1) {
        return true;
      }

      return format === 'publication'
        && ['intro', 'source', 'longread_chapter', 'longread_quote', 'timeline_item'].indexOf(type) !== -1;
    }

    function usePageLinkSelector() {
      return format === 'landing' && pageChoices.length > 0;
    }

    function normalizedPathFromUrl(url) {
      var parsed;
      if (!url) {
        return '';
      }

      try {
        parsed = new URL(String(url), window.location.origin);
      } catch (error) {
        return '';
      }

      return parsed.pathname.replace(/\/?$/, '/');
    }

    function pageChoiceForLink(link) {
      var pageId = parseInt(link.page_id || '0', 10) || 0;
      var path = normalizedPathFromUrl(link.url || '');
      var match = pageChoices.filter(function (page) {
        return parseInt(page.id || '0', 10) === pageId;
      })[0];

      if (match) {
        return match;
      }

      return pageChoices.filter(function (page) {
        return normalizedPathFromUrl(page.url || page.path || '') === path || page.path === path;
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
      var wrapper = createElement('label', 'iss-editorial-field');
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

      if (current && !target.page_id) {
        applyPageChoiceToLink(target, current);
      }

      select.addEventListener('change', function () {
        var selected = pageChoices.filter(function (page) {
          return String(page.id || '') === select.value;
        })[0] || null;
        applyPageChoiceToLink(target, selected);
        if (typeof afterChange === 'function') {
          afterChange();
        }
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(createElement('span', '', 'Seite'));
      wrapper.appendChild(select);

      return wrapper;
    }

    function updateField() {
      field.value = JSON.stringify(documentState);
    }

    function normalizeRouteRelation(relation) {
      relation = relation && typeof relation === 'object' ? relation : {};

      return {
        place_id: parseInt(relation.place_id || '0', 10) || 0,
        role: relation.role || 'related',
        weight: parseInt(relation.weight || '0', 10) || 0,
        label: relation.label || '',
        route_title: relation.route_title || '',
        route_teaser: relation.route_teaser || '',
        station_object_id: parseInt(relation.station_object_id || '0', 10) || 0,
        station_story_id: parseInt(relation.station_story_id || '0', 10) || 0
      };
    }

    function routePlaces() {
      return routeConfig && Array.isArray(routeConfig.places) ? routeConfig.places : [];
    }

    function routeStationRows() {
      return routeRelations.filter(function (relation) {
        return relation.role === 'stop';
      }).sort(function (left, right) {
        if (left.weight === right.weight) {
          return left.place_id - right.place_id;
        }

        return left.weight - right.weight;
      });
    }

    function routeNonStationRows() {
      return routeRelations.filter(function (relation) {
        return relation.role !== 'stop';
      });
    }

    function renumberRouteStations(stations) {
      stations.forEach(function (station, index) {
        station.role = 'stop';
        station.weight = index + 1;
      });
      routeRelations = routeNonStationRows().concat(stations);
    }

    function buildRouteRelationsPayload() {
      var stations = routeStationRows();
      renumberRouteStations(stations);

      return routeRelations.map(function (relation) {
        return normalizeRouteRelation(relation);
      });
    }

    function appendHiddenRelationField(target, index, key, value) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'iss_relations[' + String(index) + '][' + key + ']';
      input.value = String(value || '');
      target.appendChild(input);
    }

    function syncRouteHiddenFields() {
      if (!routeFields || !routeConfig) {
        return;
      }

      clear(routeFields);
      buildRouteRelationsPayload().forEach(function (relation, index) {
        ['place_id', 'role', 'weight', 'label', 'route_title', 'route_teaser', 'station_object_id', 'station_story_id'].forEach(function (key) {
          appendHiddenRelationField(routeFields, index, key, relation[key]);
        });
      });
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
      if ((section.media_refs || []).length) {
        parts.push(String((section.media_refs || []).length) + ' Medien');
      }
      if ((section.links || []).length) {
        parts.push(String((section.links || []).length) + ' Link(s)');
      }

      return parts.join(' · ');
    }

    function scheduleAutosave() {
      updateField();
      window.clearTimeout(autosaveTimer);
      autosaveTimer = window.setTimeout(function () {
        setStatus((config.strings && config.strings.pendingUpdate) || 'Aenderungen werden mit WordPress-Aktualisieren gespeichert.');
      }, 1200);
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
      openEditor(insertAt);
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
      closeModal();
      render();
      scheduleAutosave();
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

    function renderPalette(target) {
      var list = createElement('div', 'iss-editorial-palette');
      Object.keys(sections).forEach(function (type) {
        if (isSectionHidden(type)) {
          return;
        }

        var button = createElement('button', 'iss-editorial-gesture' + (type === activeType ? ' active' : ''));
        var dot = createElement('span', 'iss-editorial-gesture__dot');
        var body = createElement('span', 'iss-editorial-gesture__body');
        button.type = 'button';
        button.draggable = true;
        button.setAttribute('data-section-type', type);
        button.setAttribute('aria-label', 'Abschnitt hinzufügen: ' + (sectionConfig(type).label || type));
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
      if (isSectionHidden(type)) {
        return;
      }

      var card = createElement('article', 'iss-editorial-card iss-editorial-card--' + type);
      var marker = createElement('span', 'iss-editorial-card__marker');
      var meta = createElement('div', 'iss-editorial-card__meta');
      var actions = createElement('div', 'iss-editorial-card__actions');
      var handle = createElement('button', 'button-link iss-editorial-card__drag-handle');
      var handleText = createElement('span', 'screen-reader-text', 'Abschnitt ziehen oder mit Pfeiltasten verschieben');
      var edit = createElement('button', 'button button-primary', 'Bearbeiten');
      var remove = createElement('button', 'button button-link-delete', 'In Papierkorb');

      card.draggable = true;
      card.setAttribute('data-section-index', String(index));
      marker.style.backgroundColor = sectionTone(type);
      meta.appendChild(createElement('span', 'iss-editorial-card__type', sectionConfig(type).label || type));
      meta.appendChild(createElement('h3', '', section.title || 'Ohne Titel'));
      meta.appendChild(createElement('p', '', sectionSummary(section) || 'Noch kein Inhalt.'));
      if ((section.media_refs || []).length) {
        renderMediaThumbs(section, meta);
      }

      [handle, edit, remove].forEach(function (button) {
        button.type = 'button';
      });
      handle.draggable = true;
      handle.setAttribute('aria-label', 'Abschnitt "' + (section.title || sectionConfig(type).label || type) + '" verschieben');
      handle.appendChild(handleText);
      edit.addEventListener('click', function () { openEditor(index); });
      remove.addEventListener('click', function () { removeSection(index); });
      actions.appendChild(handle);
      actions.appendChild(edit);
      actions.appendChild(remove);

      card.appendChild(marker);
      card.appendChild(meta);
      card.appendChild(actions);
      target.appendChild(card);
    }

    function renderDeletedSectionCard(section, index, target) {
      var type = section.type || 'kapitel';
      var card = createElement('article', 'iss-editorial-card iss-editorial-card--deleted iss-editorial-card--' + type);
      var marker = createElement('span', 'iss-editorial-card__marker');
      var meta = createElement('div', 'iss-editorial-card__meta');
      var actions = createElement('div', 'iss-editorial-card__actions');
      var restore = createElement('button', 'button button-secondary', 'Wiederherstellen');
      var purge = createElement('button', 'button button-link-delete', 'Endgültig löschen');

      marker.style.backgroundColor = sectionTone(type);
      meta.appendChild(createElement('span', 'iss-editorial-card__type', 'Papierkorb · ' + (sectionConfig(type).label || type)));
      meta.appendChild(createElement('h3', '', section.title || 'Ohne Titel'));
      meta.appendChild(createElement('p', '', sectionSummary(section) || 'Noch kein Inhalt.'));
      if (section.deleted_at) {
        meta.appendChild(createElement('p', 'iss-editorial-card__trash-note', 'Gelöscht: ' + String(section.deleted_at).replace('T', ' ').replace(/\..+$/, '')));
      }
      if ((section.media_refs || []).length) {
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

    function renderStage(target) {
      var stage = createElement('div', 'iss-editorial-stage');
      var head = createElement('div', 'iss-editorial-stage__head');
      var heading = createElement('div', 'iss-editorial-stage__title', 'Komposition');
      var tools = createElement('div', 'iss-editorial-stage__tools');
      stage.setAttribute('data-section-count', String(documentState.sections.length));
      head.appendChild(heading);
      if (skins.length > 1) {
        tools.appendChild(renderSkinControl());
      }
      if (format === 'projekt') {
        tools.appendChild(renderRailFeatureControl());
      }
      head.appendChild(tools);
      stage.appendChild(head);

      if (!documentState.sections.some(function (section) {
        return section && !isSectionHidden(section.type || 'kapitel');
      })) {
        stage.appendChild(createElement('p', 'iss-editorial-empty', 'Noch keine Abschnitte. Links einen Abschnitt wählen.'));
      } else {
        documentState.sections.forEach(function (section, index) {
          renderSectionCard(section, index, stage);
        });
      }

      target.appendChild(stage);
    }

    function bindSectionDragDrop(layout) {
      if (!window.issEditorialDnd || !window.issEditorialDnd.bindSectionCanvas) {
        return;
      }

      window.issEditorialDnd.bindSectionCanvas({
        palette: layout.querySelector('.iss-editorial-palette'),
        stage: layout.querySelector('.iss-editorial-stage'),
        onInsert: function (type, index) {
          activeType = type;
          addSection(type, index);
        },
        onReorder: reorderSection,
        onKeyboardMove: moveSection
      });
    }

    function renderDeletedSections(target) {
      var items = deletedSections();
      var panel;
      var head;

      if (!items.length) {
        return;
      }

      panel = createElement('div', 'iss-editorial-trash-panel');
      head = createElement('div', 'iss-editorial-trash-panel__head');
      head.appendChild(createElement('h3', '', 'Gelöschte Abschnitte'));
      head.appendChild(createElement('p', '', 'Diese Abschnitte werden öffentlich nicht gerendert und bleiben nach Aktualisieren wiederherstellbar.'));
      panel.appendChild(head);

      items.forEach(function (section, index) {
        renderDeletedSectionCard(section, index, panel);
      });

      target.appendChild(panel);
    }

    function relationChoiceStrings() {
      return (window.issRelationsAdmin && window.issRelationsAdmin.strings) || {};
    }

    function relationChoiceConfig() {
      return window.issRelationsAdmin || {};
    }

    function relationChoiceString(key, fallback) {
      return relationChoiceStrings()[key] || fallback;
    }

    function setSelectOptions(select, items, selectedId, emptyLabel) {
      var placeholder = document.createElement('option');
      var fragment = document.createDocumentFragment();
      placeholder.value = '';
      placeholder.textContent = emptyLabel;
      fragment.appendChild(placeholder);

      (items || []).forEach(function (item) {
        var option = document.createElement('option');
        option.value = String(item.id || '');
        option.textContent = item.title || '';
        option.selected = String(item.id || '') === String(selectedId || '');
        fragment.appendChild(option);
      });

      select.innerHTML = '';
      select.appendChild(fragment);
    }

    function fetchRouteStationChoices(kind, placeId, selectedId) {
      var relations = relationChoiceConfig();
      var action = kind === 'object' ? 'iss_relations_station_objects' : 'iss_relations_station_stories';
      var dataKey = kind === 'object' ? 'objects' : 'stories';
      var url;

      if (!relations.ajaxUrl || !relations.nonce || !placeId) {
        return Promise.resolve([]);
      }

      url = new URL(relations.ajaxUrl, window.location.origin);
      url.searchParams.set('action', action);
      url.searchParams.set('nonce', relations.nonce);
      url.searchParams.set('place_id', String(placeId));
      if (selectedId) {
        url.searchParams.set('selected_id', String(selectedId));
      }

      return window.fetch(url.toString(), {
        credentials: 'same-origin'
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('choice request failed');
        }
        return response.json();
      }).then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error('invalid choice payload');
        }

        return Array.isArray(payload.data[dataKey]) ? payload.data[dataKey] : [];
      });
    }

    function createRoutePlaceSelect(station, rerender) {
      var field = createElement('label', 'iss-editorial-field');
      var select = document.createElement('select');
      var currentPlaceId = station.place_id || 0;
      var hasCurrent = false;

      select.className = 'widefat';
      select.appendChild(new Option('Ort wählen', ''));
      routePlaces().forEach(function (place) {
        var option = new Option(place.title || ('Ort #' + String(place.id || '')), String(place.id || ''));
        option.selected = String(place.id || '') === String(currentPlaceId || '');
        if (option.selected) {
          hasCurrent = true;
        }
        select.appendChild(option);
      });
      if (currentPlaceId && !hasCurrent) {
        select.appendChild(new Option('Ort #' + String(currentPlaceId), String(currentPlaceId), true, true));
      }
      select.addEventListener('change', function () {
        station.place_id = parseInt(select.value || '0', 10) || 0;
        station.station_object_id = 0;
        station.station_story_id = 0;
        syncRouteHiddenFields();
        rerender();
      });

      field.appendChild(createElement('span', '', 'Ort'));
      field.appendChild(select);

      return field;
    }

    function createRouteChoiceSelect(kind, station) {
      var field = createElement('label', 'iss-editorial-field');
      var select = document.createElement('select');
      var key = kind === 'object' ? 'station_object_id' : 'station_story_id';
      var selectedId = station[key] || 0;
      var placeholder = kind === 'object'
        ? relationChoiceString('objectPlaceholder', 'Objekt wählen')
        : relationChoiceString('storyPlaceholder', 'Beitrag wählen');
      var loading = kind === 'object'
        ? relationChoiceString('objectLoading', 'Objekte werden geladen ...')
        : relationChoiceString('storyLoading', 'Beiträge werden geladen ...');
      var none = kind === 'object'
        ? relationChoiceString('objectNone', 'Keine verknüpften Objekte für diesen Ort')
        : relationChoiceString('storyNone', 'Keine verknüpften Beiträge für diesen Ort');
      var error = kind === 'object'
        ? relationChoiceString('objectError', 'Objekte konnten nicht geladen werden')
        : relationChoiceString('storyError', 'Beiträge konnten nicht geladen werden');

      select.className = 'widefat';
      setSelectOptions(select, [], selectedId, station.place_id ? loading : placeholder);
      select.addEventListener('change', function () {
        station[key] = parseInt(select.value || '0', 10) || 0;
        syncRouteHiddenFields();
      });

      if (station.place_id) {
        fetchRouteStationChoices(kind, station.place_id, selectedId).then(function (items) {
          setSelectOptions(select, items, selectedId, items.length ? placeholder : none);
        }).catch(function () {
          setSelectOptions(select, [], selectedId, error);
        });
      }

      field.appendChild(createElement('span', '', kind === 'object' ? 'Objekt' : 'Beitrag'));
      field.appendChild(select);

      return field;
    }

    function renderRouteStationPanel(target) {
      if (!routeConfig) {
        return;
      }

      var panel = createElement('section', 'iss-editorial-route-panel');
      var head = createElement('div', 'iss-editorial-route-panel__head');
      var rows = createElement('div', 'iss-editorial-route-rows');
      var add = createElement('button', 'button', 'Station hinzufügen');
      var stations = routeStationRows();

      function rerenderPanel() {
        render();
      }

      head.appendChild(createElement('div', 'iss-editorial-stage__title', 'Route / Stationen'));
      head.appendChild(createElement('p', 'description', 'Stationen bleiben als Verknüpfte Orte gespeichert. Die Reihenfolge entspricht der Route.'));
      panel.appendChild(head);

      if (!stations.length) {
        rows.appendChild(createElement('p', 'iss-editorial-empty', 'Noch keine Stationen. Eine Station verbindet die Führung mit einem Ort.'));
      }

      stations.forEach(function (station, index) {
        var row = createElement('article', 'iss-editorial-route-row');
        var position = createElement('div', 'iss-editorial-route-row__position', String(index + 1));
        var fields = createElement('div', 'iss-editorial-route-row__fields');
        var tools = createElement('div', 'iss-editorial-route-row__tools');
        var up = createElement('button', 'button', 'Hoch');
        var down = createElement('button', 'button', 'Runter');
        var remove = createElement('button', 'button button-link-delete', 'Entfernen');

        fields.appendChild(createRoutePlaceSelect(station, rerenderPanel));
        fields.appendChild(createTextInput('Stations-Titel', station.route_title || '', function (value) {
          station.route_title = value;
          syncRouteHiddenFields();
        }));
        fields.appendChild(createTextarea('Stations-Teaser', station.route_teaser || '', function (value) {
          station.route_teaser = value;
          syncRouteHiddenFields();
        }, 3));
        fields.appendChild(createRouteChoiceSelect('object', station));
        fields.appendChild(createRouteChoiceSelect('story', station));

        [up, down, remove].forEach(function (button) {
          button.type = 'button';
        });
        up.disabled = index === 0;
        down.disabled = index >= stations.length - 1;
        up.addEventListener('click', function () {
          var current = stations.splice(index, 1)[0];
          stations.splice(index - 1, 0, current);
          renumberRouteStations(stations);
          syncRouteHiddenFields();
          rerenderPanel();
        });
        down.addEventListener('click', function () {
          var current = stations.splice(index, 1)[0];
          stations.splice(index + 1, 0, current);
          renumberRouteStations(stations);
          syncRouteHiddenFields();
          rerenderPanel();
        });
        remove.addEventListener('click', function () {
          stations.splice(index, 1);
          renumberRouteStations(stations);
          syncRouteHiddenFields();
          rerenderPanel();
        });

        tools.appendChild(up);
        tools.appendChild(down);
        tools.appendChild(remove);
        row.appendChild(position);
        row.appendChild(fields);
        row.appendChild(tools);
        rows.appendChild(row);
      });

      add.type = 'button';
      add.addEventListener('click', function () {
        stations.push(normalizeRouteRelation({
          role: 'stop',
          weight: stations.length + 1
        }));
        renumberRouteStations(stations);
        syncRouteHiddenFields();
        rerenderPanel();
      });

      panel.appendChild(rows);
      panel.appendChild(add);
      target.appendChild(panel);
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

    function renderRailFeatureControl() {
      var rail = currentRailFeature();
      var wrapper = createElement('div', 'iss-editorial-skin-control iss-editorial-rail-feature-control');
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
      document.body.classList.remove('iss-editorial-modal-open');
    }

    function createEditorModal(section, callbacks) {
      var options = callbacks || {};
      var configData = sectionConfig(section.type);

      if (editorUi.createModal) {
        return editorUi.createModal({
          kicker: 'Abschnitt bearbeiten',
          title: configData.label || section.type || 'Abschnitt',
          closeLabel: 'Schließen',
          doneLabel: 'Übernehmen',
          onClose: options.onClose,
          onDone: options.onDone
        });
      }

      var root = createElement('div', 'iss-editorial-modal');
      var dialog = createElement('div', 'iss-editorial-modal__dialog');
      var head = createElement('div', 'iss-editorial-modal__head');
      var body = createElement('div', 'iss-editorial-modal__body');
      var foot = createElement('div', 'iss-editorial-modal__foot');
      var footLeft = createElement('div', 'iss-editorial-modal__foot-left');
      var footTools = createElement('div', 'iss-editorial-modal__foot-tools');
      var title = createElement('h2', '', configData.label || section.type || 'Abschnitt');
      var close = createElement('button', 'button', 'Schließen');
      var done = createElement('button', 'button button-primary', 'Übernehmen');

      close.type = 'button';
      done.type = 'button';
      close.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof options.onClose === 'function') {
          options.onClose();
        }
      });
      done.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof options.onDone === 'function') {
          options.onDone();
        }
      });

      head.appendChild(title);
      head.appendChild(close);
      footTools.appendChild(done);
      foot.appendChild(footLeft);
      foot.appendChild(footTools);
      dialog.appendChild(head);
      dialog.appendChild(body);
      dialog.appendChild(foot);
      root.appendChild(dialog);

      return {
        root: root,
        body: body,
        footLeft: footLeft,
        footTools: footTools,
        doneButton: done
      };
    }

    function createEditorPanel(name, label, icon, count) {
      if (editorUi.createPanel) {
        return editorUi.createPanel({
          name: name,
          label: label,
          icon: icon || name,
          count: count
        });
      }

      var root = createElement('section', 'iss-editorial-panel iss-editorial-panel--' + name);
      var head = createElement('div', 'iss-editorial-panel__head');
      var title = createElement('div', 'iss-editorial-panel__title');
      var marker = createElement('span', 'iss-editorial-panel__icon');
      var body = createElement('div', 'iss-editorial-panel__body');
      var countNode = null;

      marker.setAttribute('aria-hidden', 'true');
      title.appendChild(marker);
      title.appendChild(createElement('span', 'iss-editorial-panel__label', label));
      head.appendChild(title);
      if (typeof count === 'number') {
        countNode = createElement('span', 'iss-editorial-panel__count', String(count));
        head.appendChild(countNode);
      }
      root.appendChild(head);
      root.appendChild(body);

      return {
        root: root,
        body: body,
        setCount: function (value) {
          if (!countNode) {
            countNode = createElement('span', 'iss-editorial-panel__count');
            head.appendChild(countNode);
          }
          countNode.textContent = String(value);
        }
      };
    }

    function appendPanelIfUsed(target, panel) {
      if (panel && panel.body && panel.body.children.length) {
        target.appendChild(panel.root);
      }
    }

    function collectionCount(section, key) {
      return Array.isArray(section[key]) ? section[key].length : 0;
    }

    function openEditor(index) {
      var section = documentState.sections[index];
      if (!section) {
        return;
      }

      closeModal();
      var shell = createEditorModal(section, {
        onClose: closeModal,
        onDone: function () {
          closeModal();
          render();
          scheduleAutosave();
        }
      });
      var remove = createElement('button', 'button-link-delete iss-editorial-modal__delete', 'In Papierkorb');

      remove.type = 'button';
      remove.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        removeSection(index);
        closeModal();
      });

      shell.footLeft.appendChild(remove);
      renderSectionFields(section, shell.body);
      modal = shell.root;
      document.body.classList.add('iss-editorial-modal-open');
      document.body.appendChild(modal);
    }

    function renderSectionFields(section, body) {
      var type = section.type || 'kapitel';
      var contentPanel = createEditorPanel('content', 'Inhalt', 'content');
      var displayPanel = createEditorPanel('display', 'Darstellung', 'display');
      var archivePanel = createEditorPanel('archive', 'Archiv', 'archive', collectionCount(section, 'object_refs'));
      var mediaPanel = createEditorPanel('media', 'Bilder', 'media', collectionCount(section, 'media_refs'));
      var albumPanel = createEditorPanel('album', 'Album', 'album', collectionCount(section, 'sheets'));
      var factPanel = createEditorPanel('facts', 'Fakten', 'facts', collectionCount(section, 'facts'));
      var itemPanel = createEditorPanel('items', 'Ziele', 'items', collectionCount(section, 'items'));
      var linkPanel = createEditorPanel('links', 'Links', 'links', collectionCount(section, 'links'));
      var kickerField = createTextInput('Vorspann', section.kicker || '', function (value) {
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
      contentPanel.body.appendChild(kickerField);
      contentPanel.body.appendChild(titleField);
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
        renderFactEditor(section, factPanel.body);
      }

      if (supports(type, 'treatment')) {
        renderTreatmentControl(section, displayPanel.body, function () {
          clear(body);
          renderSectionFields(section, body);
        });
      }

      if (supports(type, 'items')) {
        renderGatewayItemEditor(section, itemPanel.body);
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
        renderObjectPicker(section, archivePanel.body);
      }

      if (supports(type, 'media_refs')) {
        renderMediaPicker(section, mediaPanel.body);
      }

      if (supports(type, 'album_source') || supports(type, 'sheets')) {
        renderAlbumEditor(section, albumPanel.body);
      }

      if (supports(type, 'orientation')) {
        renderOrientationControl(section, displayPanel.body);
      }

      if (supports(type, 'links')) {
        renderLinkEditor(section, linkPanel.body);
      }

      appendPanelIfUsed(body, contentPanel);
      appendPanelIfUsed(body, displayPanel);
      appendPanelIfUsed(body, archivePanel);
      appendPanelIfUsed(body, mediaPanel);
      appendPanelIfUsed(body, albumPanel);
      appendPanelIfUsed(body, factPanel);
      appendPanelIfUsed(body, itemPanel);
      appendPanelIfUsed(body, linkPanel);
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
        return item && item.slug;
      });
    }

    function defaultTreatment(type) {
      var choices = treatmentChoices(type);

      return choices.length ? choices[0].slug : '';
    }

    function treatmentLabel(type, treatment) {
      var choices = treatmentChoices(type);
      var match = choices.filter(function (item) {
        return item.slug === treatment;
      })[0];

      return match ? match.label : treatment;
    }

    function normalizeTreatmentValue(type, treatment) {
      if (type === 'statement' && treatment === 'statement.callout') {
        return 'statement.leitfrage';
      }
      if (type === 'feature' && treatment === 'feature.microblocks') {
        return 'feature.image-overlay';
      }

      return treatment || '';
    }

    function renderTreatmentControl(section, body, onChange) {
      var type = section.type || 'kapitel';
      var choices = treatmentChoices(type);
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--treatment');
      var select = document.createElement('select');

      if (!choices.length) {
        return;
      }

      section.treatment = normalizeTreatmentValue(type, section.treatment);
      if (!section.treatment) {
        section.treatment = choices[0].slug;
      }

      choices.forEach(function (choice) {
        var option = document.createElement('option');
        option.value = choice.slug;
        option.textContent = choice.label || choice.slug;
        option.selected = choice.slug === section.treatment;
        select.appendChild(option);
      });

      select.addEventListener('change', function () {
        section.treatment = select.value || choices[0].slug;
        if (typeof onChange === 'function') {
          onChange();
        }
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(createElement('span', '', 'Darstellung'));
      wrapper.appendChild(select);
      body.appendChild(wrapper);
    }

    function slotKeyChoices(type) {
      if (type !== 'dynamic_slot') {
        return [];
      }

      return [
        { value: 'front-projects', label: 'Projekt-Notizen' },
        { value: 'front-timeline', label: 'Termine' },
        { value: 'front-visit-info', label: 'Besuchsinfo' },
        { value: 'front-newsletter', label: 'Newsletter' }
      ];
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
        render();
        scheduleAutosave();
      });

      wrapper.appendChild(createElement('span', '', 'Slot'));
      wrapper.appendChild(select);
      body.appendChild(wrapper);
    }

    function renderGalleryLayoutControl(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--gallery-layout');
      var options = createElement('div', 'iss-editorial-segmented');
      var choices = [
        { value: 'grid', label: 'Raster' },
        { value: 'sequence', label: 'Strecke' },
        { value: 'wall', label: 'Bilderwand' }
      ];
      var current = ['grid', 'sequence', 'wall'].indexOf(section.gallery_layout) !== -1 ? section.gallery_layout : 'grid';

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

    function renderGatewayItemEditor(section, body) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--gateway-items');
      var rows = createElement('div', 'iss-editorial-gateway-item-rows');
      var add = createElement('button', 'button', 'Ziel hinzufügen');

      function rerenderRows() {
        clear(rows);
        section.items = Array.isArray(section.items) ? section.items : [];
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
          fields.appendChild(createTextarea('Text', item.text || '', function (value) {
            item.text = value;
            render();
            scheduleAutosave();
          }, 3));
          fields.appendChild(usePageLinkSelector()
            ? createPageLinkSelect(item, rerenderRows)
            : createTextInput('URL', item.url || '', function (value) {
              item.url = value;
              render();
              scheduleAutosave();
            }));

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
          rows.appendChild(createElement('p', 'description', 'Noch keine Ziele hinzugefügt.'));
        }
      }

      add.type = 'button';
      add.addEventListener('click', function () {
        section.items = Array.isArray(section.items) ? section.items : [];
        section.items.push({ label: '', text: '', url: '', page_id: '', media_refs: [] });
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

    function renderAlbumEditor(section, body) {
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

    function renderMediaPicker(section, body) {
      var refs = createElement('div', 'iss-editorial-field iss-editorial-field--media');
      var tray = createElement('div', 'iss-editorial-media-tray');
      var isFullViewport = (section.type || '') === 'vollbild';
      var isMaterial = (section.type || '') === 'material';
      var noun = isMaterial ? 'Medien/Dateien' : (isFullViewport ? 'Bild' : 'Bilder');
      var actions = createElement('div', 'iss-editorial-media-actions');
      var setPickerButton = createElement('button', 'button button-primary', 'Aus Set auswählen');
      var mediaLibraryButton = createElement('button', 'button', 'Medien suchen');

      function rerenderTray() {
        renderMediaTray(section, tray, rerenderTray);
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
          return reference.id;
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
          library: isMaterial ? {} : { type: 'image' }
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
          mode: isFullViewport ? 'single' : 'multiple',
          mediaType: isMaterial ? '' : 'image',
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

    function createCheckbox(label, checked, onChange) {
      var wrapper = createElement('label', 'iss-editorial-check');
      var input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = !!checked;
      input.addEventListener('change', function () { onChange(input.checked); });
      wrapper.appendChild(input);
      wrapper.appendChild(createElement('span', '', label));
      return wrapper;
    }

    function createRichTextInput(label, value, onChange) {
      var wrapper = createElement('div', 'iss-editorial-field iss-editorial-field--rich-text');
      var toolbar = createElement('div', 'iss-editorial-rich-toolbar');
      var editor = createElement('div', 'iss-editorial-rich-editor');
      var activeLink = null;
      var commands = [
        { label: 'P', command: 'formatBlock', value: 'p' },
        { label: 'B', command: 'bold' },
        { label: 'I', command: 'italic' },
        { label: 'Link', command: 'createLink' },
        { label: 'Liste', command: 'insertUnorderedList', icon: 'dashicons-editor-ul' },
        { label: 'Nummerierte Liste', command: 'insertOrderedList', icon: 'dashicons-editor-ol' }
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

      function unwrapLink(link) {
        while (link.firstChild) {
          link.parentNode.insertBefore(link.firstChild, link);
        }
        link.remove();
      }

      function rememberActiveLink() {
        var link = currentRichTextLink(editor);
        if (link) {
          activeLink = link;
        }
      }

      commands.forEach(function (item) {
        var button = createElement('button', 'button iss-editorial-rich-toolbar__button', item.icon ? '' : item.label);
        button.type = 'button';
        button.title = item.label;
        button.setAttribute('aria-label', item.label);
        if (item.icon) {
          button.classList.add('iss-editorial-rich-toolbar__button--icon');
          button.appendChild(createElement('span', 'dashicons ' + item.icon));
        }
        button.addEventListener('click', function (event) {
          var href;
          event.preventDefault();
          editor.focus();
          if (item.command === 'createLink') {
            var link = currentRichTextLink(editor) || (activeLink && editor.contains(activeLink) ? activeLink : null);
            href = window.prompt(
              link ? 'Link URL (leer lassen zum Entfernen)' : 'Link URL',
              link ? link.getAttribute('href') || '' : ''
            );
            if (href === null) {
              return;
            }
            if (link && href.trim() === '') {
              unwrapLink(link);
              activeLink = null;
              commit();
              return;
            }
            if (!isSafeRichTextHref(href)) {
              return;
            }
            if (link) {
              link.setAttribute('href', href.trim());
            } else {
              document.execCommand('createLink', false, href.trim());
            }
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
      editor.addEventListener('click', rememberActiveLink);
      editor.addEventListener('keyup', rememberActiveLink);
      editor.addEventListener('mouseup', rememberActiveLink);
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
      var main = createElement('div', 'iss-editorial-main');
      clear(root);
      documentState.sections = Array.isArray(documentState.sections) ? documentState.sections : [];
      documentState.deleted_sections = deletedSections();
      renderPalette(layout);
      renderStage(main);
      renderDeletedSections(main);
      renderRouteStationPanel(main);
      layout.appendChild(main);
      root.appendChild(layout);
      bindSectionDragDrop(layout);
      updateField();
      syncRouteHiddenFields();
    }

    function savePreviewDocument() {
      var params = new window.URLSearchParams();
      var saveUrl = config.ajaxUrl || window.ajaxurl || '';

      if (!saveUrl || !config.previewNonce || !config.postId || !format) {
        setStatus((config.strings && config.strings.previewError) || 'Die Vorschau konnte nicht vorbereitet werden.');
        return Promise.reject(new Error('missing preview config'));
      }

      updateField();
      params.append('action', 'iss_editorial_save_preview_document');
      params.append('nonce', config.previewNonce);
      params.append('post_id', String(config.postId));
      params.append('format', format);
      params.append('document', field.value || JSON.stringify(documentState));

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

          return payload.data && payload.data.previewUrl ? payload.data.previewUrl : config.previewUrl;
        });
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
        setPreviewButtonBusy(button, true);
        setStatus((config.strings && config.strings.previewSaving) || 'Vorschau wird vorbereitet ...');
        savePreviewDocument().then(function (previewUrl) {
          window.open(previewUrl || fallbackUrl || config.previewUrl, '_blank', 'noopener');
          setStatus((config.strings && config.strings.previewReady) || 'Vorschau wurde geoeffnet.');
        }).catch(function (error) {
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
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('.iss-editorial-admin'), initEditor);
  });
}());
