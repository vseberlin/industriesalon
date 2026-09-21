/* Persistent landing shell. Document state, fields and saves remain in admin.js. */
(function () {
  function node(tag, cls, text) {
    var el = document.createElement(tag); el.className = cls || '';
    if (text) { el.textContent = text; } return el;
  }
  function button(text, action, cls) {
    var el = node('button', cls || 'button', text); el.type = 'button';
    el.addEventListener('click', action); return el;
  }
  window.issEditorialWorkspace = function (mount, options) {
    var root = node('section', 'iss-editorial-studio');
    root.setAttribute('aria-label', 'Seite gestalten');
    var head = node('div', 'iss-editorial-studio__head');
    var title = node('strong', '', 'Seite gestalten');
    var tools = node('div', 'iss-editorial-studio__tools');
    var tabs = node('nav', 'iss-editorial-studio__views');
    tabs.setAttribute('aria-label', 'Arbeitsbereich');
    var grid = node('div', 'iss-editorial-studio__grid');
    var outline = node('nav', 'iss-editorial-studio__outline'); outline.setAttribute('aria-label', 'Abschnitte');
    var stage = node('div', 'iss-editorial-stage');
    var palette = node('div', 'iss-editorial-palette'); palette.hidden = true;
    var trash = node('details', 'iss-editorial-studio__trash');
    var trashLabel = node('summary', '', 'Papierkorb');
    var trashBody = node('div'); trash.append(trashLabel, trashBody);
    var preview = node('div', 'iss-editorial-studio__preview');
    var inspector = node('section', 'iss-editorial-studio__inspector'); inspector.setAttribute('aria-label', 'Abschnitt bearbeiten');
    var navigation = node('div', 'iss-editorial-studio__navigation');
    var previous = button('↑ Vorheriger', function () { options.select(selected - 1); });
    var next = button('Nächster ↓', function () { options.select(selected + 1); });
    var heading = node('h3');
    var inspectorTabs = node('nav', 'iss-editorial-studio__inspector-tabs'); inspectorTabs.setAttribute('aria-label', 'Abschnitt-Einstellungen');
    var body = node('div', 'iss-editorial-studio__fields');
    var note = node('p', 'iss-editorial-studio__notice'); note.hidden = true; note.setAttribute('role', 'status');
    var remove = button('In Papierkorb', function () { options.remove(selected); }, 'button-link-delete');
    var selected = 0;
    var activeTab = 'content';
    function setView(view) {
      root.dataset.view = view;
      Array.from(tabs.children).forEach(function (el) { el.setAttribute('aria-pressed', String(el.dataset.view === view)); });
    }
    [['outline', 'Abschnitte'], ['preview', 'Vorschau'], ['inspector', 'Bearbeiten']].forEach(function (choice) {
      var tab = button(choice[1], function () { options.afterEditing(function () { setView(choice[0]); }); }); tab.dataset.view = choice[0]; tabs.appendChild(tab);
    });
    var expand = button('Arbeitsfläche vergrößern', function () {
      var full = root.classList.toggle('iss-editorial-studio--expanded');
      expand.textContent = full ? 'Arbeitsfläche verkleinern' : 'Arbeitsfläche vergrößern'; expand.setAttribute('aria-pressed', String(full));
    });
    expand.setAttribute('aria-pressed', 'false');
    var publish = button('Zum Speichern / Veröffentlichen', function () {
      options.afterEditing(function () {
        root.classList.remove('iss-editorial-studio--expanded'); expand.textContent = 'Arbeitsfläche vergrößern'; expand.setAttribute('aria-pressed', 'false');
        var native = document.getElementById('publish');
        if (native) { native.scrollIntoView({ block: 'center' }); native.focus(); }
      });
    });
    var more = node('details', 'iss-editorial-studio__more'); more.appendChild(node('summary', '', 'Weitere Werkzeuge'));
    more.appendChild(button('Bisherige Abschnittsansicht', function () { options.afterEditing(options.legacy); }));
    var history = mount.closest('.iss-editorial-admin').querySelector('a[href*="revision.php"]');
    if (history) { var link = history.cloneNode(true); link.textContent = 'Verlauf'; more.appendChild(link); }
    head.append(title, tools, expand, publish, more);
    outline.append(button('+ Abschnitt hinzufügen', function () { options.insert(); }), palette, stage, trash);
    navigation.append(previous, next); inspector.append(navigation, heading, inspectorTabs, note, body, remove);
    grid.append(outline, preview, inspector); root.append(head, tabs, grid); mount.appendChild(root); setView('preview');
    var search = node('input'); search.type = 'search'; search.placeholder = 'Abschnitt suchen'; search.setAttribute('aria-label', 'Abschnitt suchen');
    var results = node('div');
    palette.append(search, results, button('Schließen', function () { palette.hidden = true; }));
    var insertAt = null;
    function filterPalette() {
      results.replaceChildren();
      var first = null;
      var lastGroup = '';
      Object.keys(options.sections).filter(function (type) { return !options.hidden(type); }).sort(function (a, b) {
        return (options.sections[a].group || 'Text').localeCompare(options.sections[b].group || 'Text');
      }).forEach(function (type) {
        var definition = options.sections[type];
        if ((definition.label + ' ' + definition.description).toLowerCase().indexOf(search.value.toLowerCase()) === -1) { return; }
        var group = definition.group || 'Text';
        if (group !== lastGroup) { results.appendChild(node('strong', '', group)); lastGroup = group; }
        var choice = button(definition.label, function () { options.add(type, insertAt); palette.hidden = true; setView('inspector'); }, 'iss-editorial-gesture');
        choice.dataset.sectionType = type; choice.draggable = true;
        results.appendChild(choice); if (!first) { first = choice; }
      });
      if (first) { first.dataset.first = 'true'; }
      else { results.appendChild(node('p', '', 'Keine passenden Abschnitte.')); }
    }
    search.addEventListener('input', filterPalette);
    search.addEventListener('keydown', function (event) {
      var first = results.querySelector('button');
      if (first && ['Enter', 'ArrowDown'].indexOf(event.key) !== -1) { event.preventDefault(); if (event.key === 'Enter') { first.click(); } else { first.focus(); } }
      if (event.key === 'Escape') { palette.hidden = true; }
    });
    stage.addEventListener('keydown', function (event) {
      if (!event.target.matches('.iss-editorial-outline__select') || ['ArrowUp', 'ArrowDown'].indexOf(event.key) === -1) { return; }
      event.preventDefault(); options.select(selected + (event.key === 'ArrowUp' ? -1 : 1));
      var active = stage.querySelector('[aria-current="true"]'); if (active) { active.focus(); }
    });
    function showInspectorTab(key) {
      activeTab = key;
      Array.from(inspectorTabs.children).forEach(function (tab) { tab.setAttribute('aria-pressed', String(tab.dataset.panel === key)); });
      Array.from(body.children).forEach(function (panel) { panel.hidden = panel.dataset.inspectorTab !== key; });
    }
    return {
      root: root, body: body, previewMount: preview, tools: tools, palette: palette, stage: stage, trashBody: trashBody,
      showView: setView,
      message: function (text) { note.textContent = text || ''; note.hidden = !text; },
      editing: function (active) { body.inert = active; remove.disabled = active; },
      insert: function (index) { insertAt = index; search.value = ''; filterPalette(); palette.hidden = false; setView('outline'); search.focus(); },
      organizeFields: function () {
        inspectorTabs.replaceChildren();
        var groups = {};
        Array.from(body.children).forEach(function (panel) {
          var group = panel.classList.contains('iss-editorial-panel--display') ? 'display' : panel.classList.contains('iss-editorial-panel--links') || panel.classList.contains('iss-editorial-panel--sources') ? 'links' : panel.classList.contains('iss-editorial-panel--media') || panel.classList.contains('iss-editorial-panel--archive') ? 'media' : 'content';
          panel.dataset.inspectorTab = group; groups[group] = true;
        });
        [['content', 'Inhalt'], ['display', 'Darstellung'], ['links', 'Links'], ['media', 'Medien']].forEach(function (choice) {
          if (!groups[choice[0]]) { return; }
          var tab = button(choice[1], function () { showInspectorTab(choice[0]); }); tab.dataset.panel = choice[0]; inspectorTabs.appendChild(tab);
        });
        showInspectorTab(groups[activeTab] ? activeTab : 'content');
      },
      outline: function (sections, index, deletedCount) {
        selected = index;
        var focusIndex = stage.contains(document.activeElement) && document.activeElement.closest('[data-section-index]');
        var focusHandle = focusIndex && document.activeElement.classList.contains('iss-editorial-card__drag-handle');
        focusIndex = focusIndex ? Number(focusIndex.dataset.sectionIndex) : -1;
        stage.replaceChildren(); stage.dataset.sectionCount = String(sections.length);
        sections.forEach(function (section, i) {
          var definition = options.sections[section.type] || {};
          var row = node('div', 'iss-editorial-card iss-editorial-outline'); row.dataset.sectionIndex = String(i); row.draggable = true;
          var select = button('', function () { options.select(i); }, 'iss-editorial-outline__select'); select.setAttribute('aria-current', String(i === index));
          var sketch = options.sketch(section); if (sketch) { select.appendChild(sketch); }
          var icon = node('span', 'dashicons dashicons-' + (definition.icon || 'editor-paragraph')); icon.setAttribute('aria-hidden', 'true');
          var kind = node('small', '', definition.label || section.type); kind.prepend(icon);
          var label = node('span', 'iss-editorial-outline__label'); label.append(kind, node('strong', '', section.title || 'Ohne Titel'), node('small', '', options.treatmentLabel(section)));
          select.appendChild(label);
          var handle = button('', function () { handle.focus(); }, 'button-link iss-editorial-card__drag-handle'); handle.draggable = true; handle.setAttribute('aria-label', 'Abschnitt „' + (section.title || definition.label) + '“ mit Pfeiltasten verschieben');
          row.append(select, handle); stage.appendChild(row);
        });
        if (focusIndex >= 0) { var restore = stage.querySelector('[data-section-index="' + focusIndex + '"] ' + (focusHandle ? '.iss-editorial-card__drag-handle' : '.iss-editorial-outline__select')); if (restore) { restore.focus(); } }
        previous.disabled = index <= 0; next.disabled = index >= sections.length - 1;
        remove.hidden = !sections.length;
        heading.textContent = sections[index] ? (sections[index].title || (options.sections[sections[index].type] || {}).label || 'Abschnitt') : 'Abschnitt hinzufügen';
        trashLabel.textContent = 'Papierkorb (' + deletedCount + ')';
      }
    };
  };
})();
