/* Shared editorial workspace. Document state, fields and saves remain in admin.js. */
(function () {
  function node(tag, cls, text) {
    var el = document.createElement(tag); el.className = cls || '';
    if (text) { el.textContent = text; } return el;
  }
  function button(text, action, cls) {
    var el = node('button', cls || 'iss-editorial-studio__button', text); el.type = 'button';
    el.addEventListener('click', action); return el;
  }
  function iconButton(label, icon, action) {
    var el = button('', action, 'iss-editorial-studio__button iss-editorial-studio__icon');
    el.setAttribute('aria-label', label); el.title = label;
    var glyph = node('span', 'dashicons dashicons-' + icon); glyph.setAttribute('aria-hidden', 'true'); el.appendChild(glyph); return el;
  }
  window.issEditorialWorkspace = function (mount, options) {
    var root = node('section', 'iss-editorial-studio');
    root.setAttribute('aria-label', 'Seite gestalten');
    document.body.classList.add('iss-editorial-workspace-screen');
    var head = node('div', 'iss-editorial-studio__head');
    var title = node('strong', 'iss-editorial-studio__breadcrumb', (options.label || 'Inhalt') + ' / ' + (document.getElementById('title').value || 'Neuer Inhalt'));
    var previewTools = node('div', 'iss-editorial-studio__preview-tools');
    var statusMount = node('div', 'iss-editorial-studio__status');
    var statusAnchor = document.createComment('editorial status');
    options.status.before(statusAnchor); statusMount.append(options.status, options.retry);
    var tools = node('div', 'iss-editorial-studio__tools');
    var tabs = node('nav', 'iss-editorial-studio__views');
    tabs.setAttribute('aria-label', 'Arbeitsbereich');
    var grid = node('div', 'iss-editorial-studio__grid');
    var sidebar = node('aside', 'iss-editorial-studio__sidebar'); sidebar.setAttribute('aria-label', 'Inhalt gestalten');
    var panelTabs = node('nav', 'iss-editorial-studio__panel-tabs'); panelTabs.setAttribute('aria-label', 'Inhaltswerkzeuge');
    var settings = node('section', 'iss-editorial-studio__settings'); settings.setAttribute('aria-label', 'Seiteneinstellungen');
    var outline = node('nav', 'iss-editorial-studio__outline'); outline.setAttribute('aria-label', 'Abschnitte');
    var stage = node('div', 'iss-editorial-stage');
    var palette = node('div', 'iss-editorial-palette'); palette.hidden = true;
    var trash = node('details', 'iss-editorial-studio__trash');
    var trashLabel = node('summary', '', 'Papierkorb');
    var trashBody = node('div'); trash.append(trashLabel, trashBody);
    var preview = node('div', 'iss-editorial-studio__preview');
    var inspector = node('section', 'iss-editorial-studio__inspector'); inspector.setAttribute('aria-label', 'Abschnitt bearbeiten');
    var navigation = node('div', 'iss-editorial-studio__navigation');
    var previous = iconButton('Vorherigen Abschnitt auswählen', 'arrow-up-alt2', function () { options.select(selected - 1); });
    var next = iconButton('Nächsten Abschnitt auswählen', 'arrow-down-alt2', function () { options.select(selected + 1); });
    var heading = node('h3');
    var guidance = node('p', 'iss-editorial-studio__guidance'); guidance.hidden = true;
    var position = node('select', 'iss-editorial-studio__position');
    position.setAttribute('aria-label', 'Abschnitt auswählen');
    position.addEventListener('change', function () { options.select(Number(position.value)); });
    var inspectorTabs = node('nav', 'iss-editorial-studio__inspector-tabs'); inspectorTabs.setAttribute('aria-label', 'Abschnitt-Einstellungen');
    var body = node('div', 'iss-editorial-studio__fields');
    var note = node('p', 'iss-editorial-studio__notice'); note.hidden = true; note.setAttribute('role', 'status');
    var remove = button('In Papierkorb', function () { options.remove(selected); }, 'iss-editorial-studio__button iss-editorial-studio__remove');
    var undo = node('div', 'iss-editorial-studio__undo'); undo.hidden = true;
    var undoText = node('span'); undoText.setAttribute('role', 'status');
    var restoreRemoved = null;
    var undoAction = button('Rückgängig', function () { options.afterEditing(function () { if (restoreRemoved) { restoreRemoved(); } undo.hidden = true; }); });
    undo.append(undoText, undoAction);
    var selected = 0;
    var help = window.issEditorialHelp(root, { preview: preview, tools: tools, sections: options.sections, format: options.format, publishing: options.publishing, afterEditing: options.afterEditing });
    var sectionHelp = help.button('arrange');
    var panelHead = node('div', 'iss-editorial-studio__panel-head'); panelHead.append(panelTabs, sectionHelp);
    var activeTab = 'content';
    var studioType = '';
    function contextualHelp() {
      var key = root.dataset.panel === 'inspector'
        ? (activeTab === 'content' ? 'section:' + studioType : ({display: 'display', links: 'links', media: 'media'}[activeTab]))
        : (root.dataset.panel === 'settings' ? 'page' : 'arrange');
      help.context(key, sectionHelp);
    }
    function setPanel(panel) {
      root.dataset.panel = panel;
      outline.hidden = panel !== 'outline';
      palette.hidden = panel !== 'insert';
      inspector.hidden = panel !== 'inspector';
      settings.hidden = panel !== 'settings';
      Array.from(panelTabs.children).forEach(function (el) { el.setAttribute('aria-pressed', String(el.dataset.panel === (panel === 'inspector' ? 'outline' : panel))); });
      contextualHelp();
    }
    function setView(view) {
      root.dataset.view = view;
      if (['outline', 'insert', 'inspector', 'settings'].indexOf(view) !== -1) { setPanel(view); }
      Array.from(tabs.children).forEach(function (el) { el.setAttribute('aria-pressed', String(el.dataset.view === (view === 'preview' ? 'preview' : 'controls'))); });
    }
    [['preview', 'Vorschau'], ['controls', 'Bearbeiten']].forEach(function (choice) {
      var tab = button(choice[1], function () { options.afterEditing(function () { setView(choice[0]); }); }, 'iss-editorial-studio__tab'); tab.dataset.view = choice[0]; tabs.appendChild(tab);
    });
    [['outline', 'Abschnitte'], ['insert', 'Hinzufügen'], ['settings', 'Seite']].forEach(function (choice) {
      var tab = button(choice[1], function () {
        options.afterEditing(function () { if (choice[0] === 'insert') { options.insert(); } else { setView(choice[0]); } });
      }, 'iss-editorial-studio__segment'); tab.dataset.panel = choice[0]; panelTabs.appendChild(tab);
    });
    var finish = button('Fertig', function () {
      options.afterEditing(function () {
        finish.disabled = true;
        options.finish().then(function (ready) {
          if (!ready || root.dataset.panel !== 'inspector') { return; }
          setPanel('outline'); setView('preview');
          var selectedRow = stage.querySelector('[aria-current="true"]');
          if (selectedRow && root.clientWidth > 760) { selectedRow.focus(); } else { tabs.querySelector('[data-view="preview"]').focus(); }
        }).catch(function () { /* The existing save path reports the error and retains the fields. */ }).finally(function () { finish.disabled = false; });
      });
    }, 'iss-editorial-studio__button iss-editorial-studio__primary');
    help.hint(finish, 'save', 'Sichert deinen aktuellen Arbeitsstand als privaten Entwurf und führt zur Übersicht zurück. Die veröffentlichte Seite wird dadurch noch nicht geändert.');
    var background = [];
    function setExpanded(full) {
      if (full && !background.length) {
        // Keep native controls out of the keyboard path while covered. WordPress
        // moves its link dialog from wpwrap to body when opening it; never
        // mark that dialog or its backdrop inert before the move.
        var branch = root;
        while (branch.parentElement && branch.id !== 'wpwrap') {
          Array.from(branch.parentElement.children).forEach(function (sibling) {
            if (sibling !== branch && !sibling.matches('#wpadminbar, #wp-link-wrap, #wp-link-backdrop')) { background.push([sibling, sibling.inert]); sibling.inert = true; }
          });
          branch = branch.parentElement;
        }
      } else if (!full) {
        background.forEach(function (item) { item[0].inert = item[1]; }); background = [];
      }
      root.classList.toggle('iss-editorial-studio--expanded', full);
      document.body.classList.toggle('iss-editorial-studio-open', full);
      var label = full ? 'Arbeitsfläche verkleinern' : 'Arbeitsfläche vergrößern';
      expand.setAttribute('aria-label', label); expand.setAttribute('aria-pressed', String(full));
      if (full) { expand.focus(); }
    }
    var expand = iconButton('Arbeitsfläche vergrößern', 'editor-expand', function () {
      setExpanded(!root.classList.contains('iss-editorial-studio--expanded'));
    });
    help.hint(expand, 'page', 'Vergrößert oder verkleinert die Arbeitsfläche. Deine Eingaben bleiben erhalten.');
    help.hint(previous, 'arrange', 'Wählt den vorherigen Abschnitt aus; die Reihenfolge bleibt gleich.');
    help.hint(next, 'arrange', 'Wählt den nächsten Abschnitt aus; die Reihenfolge bleibt gleich.');
    help.hint(remove, 'trash'); help.hint(undoAction, 'trash');
    Array.from(panelTabs.children).forEach(function (tab) { help.hint(tab, tab.dataset.panel === 'settings' ? 'page' : 'arrange'); });
    var more = node('details', 'iss-editorial-studio__more'); var moreLabel = node('summary', 'iss-editorial-studio__button iss-editorial-studio__icon', '⋯'); moreLabel.setAttribute('aria-label', 'Weitere Werkzeuge'); moreLabel.title = 'Weitere Werkzeuge'; more.appendChild(moreLabel);
    var menu = node('div', 'iss-editorial-studio__menu'); more.appendChild(menu);
    menu.appendChild(button('Angaben & Beziehungen …', function () { options.afterEditing(function () { setExpanded(false); var native = document.getElementById('poststuff'); if (native) { native.scrollIntoView({ block: 'start' }); } }); }));
    var history = mount.closest('.iss-editorial-admin').querySelector('a[href*="revision.php"]');
    if (history) { var link = history.cloneNode(true); link.textContent = 'Verlauf'; menu.appendChild(link); }
    menu.appendChild(tools);
    head.append(title, previewTools, statusMount, expand, more);
    var ownerBody = node('div', 'iss-editorial-studio__owner-controls');
    outline.appendChild(button('+ Abschnitt hinzufügen', function () { options.insert(); }));
    if (options.orderNotice) { outline.appendChild(node('p', 'iss-editorial-studio__guidance', options.orderNotice)); }
    outline.append(stage, trash);
    settings.append(node('h3', '', 'Seiteneinstellungen'), ownerBody, button('Angaben & Beziehungen …', function () {
      options.afterEditing(function () { setExpanded(false); var native = document.getElementById('poststuff'); if (native) { native.scrollIntoView({ block: 'start' }); } });
    }));
    navigation.setAttribute('role', 'group'); navigation.setAttribute('aria-label', 'Abschnitt-Navigation');
    navigation.append(position, previous, next, finish); inspector.append(navigation, heading, guidance, inspectorTabs, note, body, remove);
    sidebar.append(panelHead, outline, palette, inspector, settings, undo);
    var saveGuidance = node('p', 'iss-editorial-studio__save-guidance', help.saveNotice);
    grid.append(preview, sidebar); root.append(head, saveGuidance, tabs, grid); mount.appendChild(root); setPanel('outline'); setView('preview'); setExpanded(true);
    var search = node('input'); search.type = 'search'; search.placeholder = 'Abschnitt suchen'; search.setAttribute('aria-label', 'Abschnitt suchen');
    var results = node('div', 'iss-editorial-palette__results');
    palette.append(search, results, button('Schließen', function () { setView('outline'); panelTabs.children[0].focus(); }));
    var insertAt = null;
    function filterPalette() {
      results.replaceChildren();
      var first = null;
      var lastGroup = '';
      Object.keys(options.sections).filter(function (type) { return !options.hidden(type); }).sort(function (a, b) {
        return (options.sections[a].group || 'Text').localeCompare(options.sections[b].group || 'Text')
          || Number(!!options.sections[a].portable) - Number(!!options.sections[b].portable);
      }).forEach(function (type) {
        var definition = options.sections[type];
        if ((definition.label + ' ' + definition.description).toLowerCase().indexOf(search.value.toLowerCase()) === -1) { return; }
        var group = definition.group || 'Text';
        if (group !== lastGroup) { results.appendChild(node('strong', '', group)); lastGroup = group; }
        var choice = button('', function () { options.add(type, insertAt); }, 'iss-editorial-gesture');
        var icon = node('span', 'dashicons dashicons-' + (definition.icon || 'editor-paragraph')); icon.setAttribute('aria-hidden', 'true');
        var label = node('span', '', definition.label);
        choice.append(icon, label); help.hint(choice, 'section:' + type);
        // Shared gestures stay compact; domain-specific choices explain their
        // requirements without relying on a mouse-only title tooltip.
        if (!definition.portable && definition.description) {
          choice.classList.add('iss-editorial-gesture--explained');
          var description = node('small', 'iss-editorial-gesture__description', definition.description);
          label.id = 'iss-editorial-choice-' + type;
          description.id = label.id + '-description';
          choice.setAttribute('aria-labelledby', label.id);
          choice.setAttribute('aria-describedby', description.id);
          choice.appendChild(description);
        }
        choice.dataset.sectionType = type;
        results.appendChild(choice); if (!first) { first = choice; }
      });
      if (first) { first.dataset.first = 'true'; }
      else { results.appendChild(node('p', '', 'Keine passenden Abschnitte.')); }
    }
    search.addEventListener('input', filterPalette);
    search.addEventListener('keydown', function (event) {
      var first = results.querySelector('button');
      if (first && ['Enter', 'ArrowDown'].indexOf(event.key) !== -1) { event.preventDefault(); if (event.key === 'Enter') { first.click(); } else { first.focus(); } }
    });
    palette.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); setView('outline'); panelTabs.children[0].focus(); }
    });
    stage.addEventListener('keydown', function (event) {
      if (!event.target.matches('.iss-editorial-outline__select') || ['ArrowUp', 'ArrowDown'].indexOf(event.key) === -1) { return; }
      event.preventDefault(); options.select(selected + (event.key === 'ArrowUp' ? -1 : 1));
    });
    function showInspectorTab(key) {
      activeTab = key;
      contextualHelp();
      Array.from(inspectorTabs.children).forEach(function (tab) { tab.setAttribute('aria-pressed', String(tab.dataset.panel === key)); });
      Array.from(body.children).forEach(function (panel) { panel.hidden = panel.dataset.inspectorTab !== key; });
    }
    return {
      root: root, help: help, body: body, inspector: inspector, menu: menu, ownerBody: ownerBody, previewMount: preview, previewTools: previewTools, previewStatus: statusMount, tools: tools, palette: palette, stage: stage, trashBody: trashBody,
      showView: setView,
      destroy: function () { help.destroy(); document.body.classList.remove('iss-editorial-workspace-screen'); setExpanded(false); statusAnchor.replaceWith(options.status, options.retry); },
      removed: function (label, restore) { restoreRemoved = restore; undoText.textContent = '„' + label + '“ im Papierkorb.'; undo.hidden = false; },
      message: function (text) { note.textContent = text || ''; note.hidden = !text; },
      editing: function (active) { body.inert = active; remove.disabled = active; },
      insert: function (index) { insertAt = index; search.value = ''; filterPalette(); setView('insert'); search.focus(); },
      organizeFields: function (type) {
        studioType = type;
        inspectorTabs.replaceChildren();
        var groups = {};
        Array.from(body.children).forEach(function (panel) {
          var group = panel.classList.contains('iss-editorial-panel--display') ? 'display' : panel.classList.contains('iss-editorial-panel--links') || panel.classList.contains('iss-editorial-panel--sources') ? 'links' : panel.classList.contains('iss-editorial-panel--media') || panel.classList.contains('iss-editorial-panel--archive') ? 'media' : 'content';
          panel.dataset.inspectorTab = group; groups[group] = true;
        });
        [['content', 'Inhalt'], ['display', 'Darstellung'], ['links', 'Links'], ['media', 'Medien']].forEach(function (choice) {
          if (!groups[choice[0]]) { return; }
          var tab = button(choice[1], function () { showInspectorTab(choice[0]); }, 'iss-editorial-studio__tab'); tab.dataset.panel = choice[0]; help.hint(tab, { content: 'text', display: 'display', links: 'links', media: 'media' }[choice[0]]); inspectorTabs.appendChild(tab);
        });
        showInspectorTab(groups[activeTab] ? activeTab : 'content');
      },
      outline: function (sections, index, deletedCount) {
        selected = index;
        var focusIndex = stage.contains(document.activeElement) && document.activeElement.closest('[data-section-index]');
        var focusHandle = focusIndex && document.activeElement.classList.contains('iss-editorial-card__drag-handle');
        focusIndex = focusIndex ? Number(focusIndex.dataset.sectionIndex) : -1;
        stage.replaceChildren(); stage.dataset.sectionCount = String(sections.length);
        position.replaceChildren();
        sections.forEach(function (section, i) {
          var definition = options.sections[section.type] || {};
          var row = node('div', 'iss-editorial-card iss-editorial-outline'); row.dataset.sectionIndex = String(i); row.draggable = true;
          var select = button('', function () { options.select(i); }, 'iss-editorial-outline__select'); select.setAttribute('aria-current', String(i === index));
          var sketch = options.sketch(section); if (sketch) { select.appendChild(sketch); }
          var icon = node('span', 'dashicons dashicons-' + (definition.icon || 'editor-paragraph')); icon.setAttribute('aria-hidden', 'true');
          var treatment = options.treatmentLabel(section);
          var kind = node('small', '', (definition.label || section.type) + (treatment ? ' · ' + treatment : '')); kind.title = kind.textContent; kind.prepend(icon);
          var label = node('span', 'iss-editorial-outline__label');
          var sectionTitle = node('strong', '', section.title || treatment || definition.label || 'Abschnitt');
          var choice = node('option', '', 'Abschnitt ' + (i + 1) + ' von ' + sections.length + ' · ' + sectionTitle.textContent);
          choice.value = String(i); position.appendChild(choice);
          select.title = sectionTitle.textContent + ' · ' + kind.textContent;
          label.append(sectionTitle, kind);
          select.appendChild(label);
          var handle = button('', function () { handle.focus(); }, 'iss-editorial-card__drag-handle'); handle.draggable = true; handle.setAttribute('aria-label', 'Abschnitt „' + (section.title || definition.label) + '“ mit Pfeiltasten verschieben');
          help.hint(handle, 'arrange', 'Ziehen oder am fokussierten Griff die Pfeiltasten verwenden, um diesen Abschnitt zu verschieben.');
          row.append(select, handle); stage.appendChild(row);
        });
        if (focusIndex >= 0) { var restore = stage.querySelector('[data-section-index="' + focusIndex + '"] ' + (focusHandle ? '.iss-editorial-card__drag-handle' : '.iss-editorial-outline__select')); if (restore) { restore.focus(); } }
        previous.disabled = index <= 0; next.disabled = index >= sections.length - 1;
        remove.hidden = !sections.length;
        if (!sections.length) { position.appendChild(node('option', '', 'Keine Abschnitte')); }
        position.disabled = !sections.length; position.value = sections.length ? String(index) : 'Keine Abschnitte';
        position.title = position.selectedOptions[0] ? position.selectedOptions[0].textContent : '';
        heading.textContent = sections[index] ? ((options.sections[sections[index].type] || {}).label || 'Abschnitt') : 'Abschnitt hinzufügen';
        guidance.textContent = sections[index] ? ((options.sections[sections[index].type] || {}).description || '') : '';
        guidance.hidden = !guidance.textContent;
        studioType = sections[index] ? sections[index].type : ''; contextualHelp();
        trashLabel.textContent = 'Papierkorb (' + deletedCount + ')';
        if (!palette.hidden) { filterPalette(); }
      }
    };
  };
})();
