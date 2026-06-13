(function () {
  var picker = window.issArchiveSetsPicker;
  if (!picker) {
    return;
  }

  var config = picker.config || {};
  var t = picker.t;
  var createElement = picker.createElement;
  var clear = picker.clear;
  var request = picker.request;
  var makeButton = picker.makeButton;
  var setMetaLine = picker.setMetaLine;
  var notifyAttachedSetsChanged = picker.notifyAttachedSetsChanged;

  function setNotice(container, message, isError) {
    var notice = container.querySelector('.iss-archivsets-notice');
    if (!notice) {
      notice = createElement('p', 'iss-archivsets-notice');
      container.prepend(notice);
    }
    notice.textContent = message || '';
    notice.classList.toggle('iss-archivsets-notice--error', !!isError);
    notice.hidden = !message;
  }

  function appendSetCard(container, set, actions) {
    var card = createElement('article', 'iss-archivsets-set-card');
    var body = createElement('div', 'iss-archivsets-set-card__body');
    var title = createElement('h4', '', set.title || 'Archivset #' + String(set.id));
    var meta = createElement('p', 'description', setMetaLine(set));
    body.appendChild(title);
    body.appendChild(meta);
    if (set.summary) {
      body.appendChild(createElement('p', 'iss-archivsets-set-card__summary', set.summary));
    }
    card.appendChild(body);

    var actionWrap = createElement('div', 'iss-archivsets-set-card__actions');
    actions.forEach(function (action) {
      if (action.href) {
        var link = createElement('a', action.className || 'button', action.label);
        link.href = action.href;
        actionWrap.appendChild(link);
        return;
      }
      actionWrap.appendChild(makeButton(action.label, action.className || 'button', action.onClick));
    });
    card.appendChild(actionWrap);
    container.appendChild(card);
  }

  function parseSourceRefs(raw) {
    if (!raw.trim()) {
      return [];
    }
    try {
      var decoded = JSON.parse(raw);
      return Array.isArray(decoded) ? decoded : [];
    } catch (error) {
      throw new Error('Source refs muessen gueltiges JSON sein.');
    }
  }

  function selectedArchiveObjectIds(objects) {
    var seen = {};

    return (objects || []).map(function (selected) {
      return parseInt(selected && selected.id ? selected.id : selected, 10);
    }).filter(function (id) {
      if (!id || id <= 0 || seen[id]) {
        return false;
      }

      seen[id] = true;
      return true;
    });
  }

  function addObjectsToSet(setId, objects, args) {
    var objectIds = selectedArchiveObjectIds(objects);

    if (!setId || setId <= 0) {
      return Promise.reject(new Error('Waehle zuerst eine Archivauswahl.'));
    }

    if (!objectIds.length) {
      return Promise.reject(new Error('Waehle zuerst mindestens ein Archivobjekt aus.'));
    }

    return objectIds.reduce(function (sequence, objectId) {
      return sequence.then(function () {
        return picker.addObjectToSet(setId, objectId, args || {});
      });
    }, Promise.resolve());
  }

  function initMetabox(root) {
    var postId = parseInt(root.getAttribute('data-post-id') || config.postId || '0', 10);
    if (!postId || !config.restRoot) {
      root.innerHTML = '<p class="description">Beitrag zuerst speichern, dann kann eine Archivauswahl verbunden werden.</p>';
      return;
    }

    var currentSet = null;
    var attachedSets = [];
    var selectedSetId = 0;
    var metaboxObjectPicker = null;

    root.innerHTML =
      '<div class="iss-archivsets-panel">' +
        '<div class="iss-archivsets-notice" hidden></div>' +
        '<section class="iss-archivsets-section iss-archivsets-buckets">' +
          '<div class="iss-archivsets-section__head">' +
            '<h4>Archivauswahl</h4>' +
            '<button type="button" class="button button-primary iss-archivsets-create-attached">' + t('create', 'Create') + '</button>' +
          '</div>' +
          '<p class="description">Verbinde eine gespeicherte Archivauswahl mit diesem Beitrag. Die Auswahl bleibt der Materialkorb; sichtbar wird Material erst durch eingefuegte Objekt-Bloecke im Text.</p>' +
          '<div class="iss-archivsets-attached"></div>' +
          '<div class="iss-archivsets-inline-form">' +
            '<input type="search" class="regular-text iss-archivsets-search-field" placeholder="Bestehende Archivauswahl suchen">' +
            '<button type="button" class="button iss-archivsets-search">' + t('search', 'Search') + '</button>' +
          '</div>' +
          '<div class="iss-archivsets-search-results"></div>' +
        '</section>' +
        '<section class="iss-archivsets-section iss-archivsets-metabox-members">' +
          '<div class="iss-archivsets-section__head">' +
            '<h4>Objekte in der aktiven Auswahl</h4>' +
            '<button type="button" class="button iss-archivsets-save-members">' + t('save', 'Save') + '</button>' +
          '</div>' +
          '<div class="iss-archivsets-metabox-current description"></div>' +
          '<div class="iss-archivsets-members__list"></div>' +
        '</section>' +
        '<section class="iss-archivsets-section iss-archivsets-picker">' +
          '<div class="iss-archivsets-section__head"><h4>Archivobjekte zur aktiven Auswahl hinzufuegen</h4></div>' +
          '<div class="iss-archivsets-picker__mount"></div>' +
        '</section>' +
      '</div>';

    var attached = root.querySelector('.iss-archivsets-attached');
    var currentLine = root.querySelector('.iss-archivsets-metabox-current');
    var memberList = root.querySelector('.iss-archivsets-members__list');
    var results = root.querySelector('.iss-archivsets-search-results');
    var searchField = root.querySelector('.iss-archivsets-search-field');
    var pickerMount = root.querySelector('.iss-archivsets-picker__mount');

    function getMemberPath(setId, suffix) {
      return '/sets/' + setId + suffix + '?contextPostId=' + encodeURIComponent(String(postId));
    }

    function loadAttached() {
      attached.textContent = t('loading', 'Loading...');
      return picker.getAttachedSets(postId).then(function (payload) {
        clear(attached);
        attachedSets = payload.items || [];
        notifyAttachedSetsChanged(postId, attachedSets);
        if (!attachedSets.length) {
          currentSet = null;
          selectedSetId = 0;
          attached.appendChild(createElement('p', 'description', t('noSets', 'No Archivsets attached.')));
          renderCurrentSet();
          return;
        }

        if (!selectedSetId) {
          selectedSetId = attachedSets[0].id;
        }

        if (!attachedSets.some(function (set) {
          return set.id === selectedSetId;
        })) {
          selectedSetId = attachedSets[0].id;
        }

        attachedSets.forEach(function (set) {
          var workbenchUrl = set.editUrl || (config.workbenchUrl ? config.workbenchUrl + '&set_id=' + encodeURIComponent(String(set.id)) : '');
          appendSetCard(attached, set, [
            {
              label: set.id === selectedSetId ? 'Aktive Auswahl' : 'Auswahl verwenden',
              className: set.id === selectedSetId ? 'button button-primary' : 'button',
              onClick: function () {
                selectedSetId = set.id;
                loadAttached();
              }
            },
            {
              label: 'In Workbench oeffnen',
              href: workbenchUrl,
              className: 'button'
            },
            {
              label: t('detach', 'Detach'),
              className: 'button-link-delete',
              onClick: function () {
                picker.detachSet(postId, set.id).then(loadAttached).catch(function (error) {
                  setNotice(root, error.message, true);
                });
              }
            }
          ]);
        });

        return loadCurrentSet();
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function loadCurrentSet() {
      if (!selectedSetId) {
        currentSet = null;
        renderCurrentSet();
        return Promise.resolve();
      }

      return picker.loadSet(selectedSetId, { contextPostId: postId }).then(function (setPayload) {
        currentSet = setPayload.item || null;
        renderCurrentSet();
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function renderCurrentSet() {
      clear(memberList);
      if (!currentSet) {
        currentLine.textContent = 'Keine aktive Archivauswahl. Erstelle eine neue Auswahl oder verbinde eine bestehende.';
        memberList.appendChild(createElement('p', 'description', 'Noch keine Auswahl aktiv.'));
        metaboxObjectPicker = null;
        pickerMount.innerHTML = '<p class="description">Waehle zuerst eine Archivauswahl.</p>';
        return;
      }

      currentLine.textContent = (currentSet.title || 'Archivset #' + String(currentSet.id)) + ' - ' + setMetaLine(currentSet);
      (currentSet.members || []).forEach(function (member) {
        memberList.appendChild(createMetaboxMemberRow(member));
      });
      if (!(currentSet.members || []).length) {
        memberList.appendChild(createElement('p', 'description', 'Noch keine Archivobjekte ausgewaehlt.'));
      }
      renderMetaboxObjectPicker();
    }

    function createMetaboxMemberRow(member) {
      var row = createElement('article', 'iss-archivsets-member');
      row.setAttribute('data-member-id', String(member.id));

      var top = createElement('div', 'iss-archivsets-member__top');
      var label = member.objectTitle || member.memberTitle || 'Objekt #' + String(member.objectPostId || member.id);
      top.appendChild(createElement('strong', '', label));
      var controls = createElement('div', 'iss-archivsets-member__controls');
      controls.appendChild(makeButton('In Beitrag einfuegen', 'button button-primary iss-archivsets-member-insert', function () {
        var adapter = window.issArchiveEditorAdapter || window.issArchiveSetsPicker || {};
        var objectId = parseInt(member.objectPostId || member.object_post_id || '0', 10);
        var inserted = '';

        if (objectId > 0 && adapter.insertArchiveObject) {
          inserted = adapter.insertArchiveObject(objectId, {
            contextPostId: postId,
            setId: currentSet && currentSet.id ? currentSet.id : 0,
            memberPosition: parseInt(member.position || '0', 10)
          });
        }

        if (inserted) {
          setNotice(root, 'Archivobjekt wurde als Block in den Beitrag eingefuegt.', false);
          return;
        }

        setNotice(root, 'Archivobjekt konnte nicht in den Beitrag eingefuegt werden.', true);
      }));
      controls.appendChild(makeButton('Up', 'button iss-archivsets-member-up', function () {
        var previous = row.previousElementSibling;
        if (previous) {
          row.parentNode.insertBefore(row, previous);
        }
      }));
      controls.appendChild(makeButton('Down', 'button iss-archivsets-member-down', function () {
        var next = row.nextElementSibling;
        if (next) {
          row.parentNode.insertBefore(next, row);
        }
      }));
      controls.appendChild(makeButton(t('remove', 'Remove'), 'button-link-delete', function () {
        if (!currentSet) {
          return;
        }
        request(getMemberPath(currentSet.id, '/members/' + member.id), { method: 'DELETE' }).then(function () {
          return picker.loadSet(currentSet.id, { contextPostId: postId });
        }).then(function (payload) {
          currentSet = payload.item || currentSet;
          renderCurrentSet();
          return loadAttached();
        }).catch(function (error) {
          setNotice(root, error.message, true);
        });
      }));
      top.appendChild(controls);
      row.appendChild(top);

      var meta = [member.sourceObjectId, member.sourceUrl].filter(Boolean).join(' - ');
      if (meta) {
        row.appendChild(createElement('p', 'description', meta));
      }

      var grid = createElement('div', 'iss-archivsets-member__fields');
      grid.appendChild(buildTextField('Seite', 'widefat iss-archivsets-member-page', member.pageLabel || ''));
      grid.appendChild(buildTextField('Anzeigetitel', 'widefat iss-archivsets-member-title', member.displayTitle || ''));
      grid.appendChild(buildTextarea('Beschriftung', 'widefat iss-archivsets-member-caption', member.caption || '', 3));
      row.appendChild(grid);

      if (member.editUrl || member.permalink) {
        var links = createElement('p', 'description');
        if (member.editUrl) {
          var editLink = createElement('a', '', 'Objekt bearbeiten');
          editLink.href = member.editUrl;
          editLink.target = '_blank';
          editLink.rel = 'noopener noreferrer';
          links.appendChild(editLink);
        }
        if (member.permalink) {
          if (member.editUrl) {
            links.appendChild(document.createTextNode(' - '));
          }
          var publicLink = createElement('a', '', 'Oeffnen');
          publicLink.href = member.permalink;
          publicLink.target = '_blank';
          publicLink.rel = 'noopener noreferrer';
          links.appendChild(publicLink);
        }
        row.appendChild(links);
      }

      return row;
    }

    function createAttachedSet() {
      var title = config.postTitle
        ? 'Archivauswahl: ' + config.postTitle
        : '';

      return picker.createSet({
        title: title,
        contextPostId: postId,
        attachToContext: true
      }).then(function (payload) {
        currentSet = payload.item || null;
        selectedSetId = currentSet && currentSet.id ? currentSet.id : 0;
        return loadAttached().then(function () {
          return currentSet;
        });
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function saveCurrentMembers() {
      if (!currentSet || !currentSet.id) {
        setNotice(root, 'Noch keine gespeicherte Auswahl vorhanden.', true);
        return;
      }

      var rows = Array.prototype.slice.call(memberList.querySelectorAll('.iss-archivsets-member'));
      var members = rows.map(function (row, index) {
        return {
          id: parseInt(row.getAttribute('data-member-id') || '0', 10),
          position: index,
          pageLabel: row.querySelector('.iss-archivsets-member-page').value || '',
          displayTitle: row.querySelector('.iss-archivsets-member-title').value || '',
          caption: row.querySelector('.iss-archivsets-member-caption').value || ''
        };
      });

      request(getMemberPath(currentSet.id, '/members/reorder'), {
        method: 'POST',
        body: { members: members }
      }).then(function (payload) {
        currentSet = payload.item || currentSet;
        renderCurrentSet();
        return loadAttached();
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function searchSets() {
      results.textContent = t('loading', 'Loading...');
      picker.searchSets({
        search: searchField.value || '',
        perPage: 12,
        contextPostId: postId
      }).then(function (payload) {
        clear(results);
        var items = payload.items || [];
        if (!items.length) {
          results.appendChild(createElement('p', 'description', t('noResults', 'No results.')));
          return;
        }

        items.forEach(function (set) {
          var workbenchUrl = set.editUrl || (config.workbenchUrl ? config.workbenchUrl + '&set_id=' + encodeURIComponent(String(set.id)) : '');
          appendSetCard(results, set, [
            {
              label: t('attach', 'Attach'),
              className: 'button',
              onClick: function () {
                picker.attachSet(postId, set.id).then(function () {
                  selectedSetId = set.id;
                  clear(results);
                  searchField.value = '';
                  loadAttached();
                }).catch(function (error) {
                  setNotice(root, error.message, true);
                });
              }
            },
            {
              label: 'In Workbench oeffnen',
              href: workbenchUrl,
              className: 'button'
            }
          ]);
        });
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function addObjectsToCurrentSet(objects) {
      if (!currentSet || !currentSet.id) {
        setNotice(root, 'Waehle zuerst eine Archivauswahl.', true);
        return Promise.reject(new Error('Waehle zuerst eine Archivauswahl.'));
      }

      return addObjectsToSet(currentSet.id, objects, { contextPostId: postId }).then(function (memberPayload) {
        currentSet = memberPayload && memberPayload.item ? memberPayload.item : currentSet;
        renderCurrentSet();
        setNotice(root, 'Archivobjekte wurden zur aktiven Auswahl hinzugefuegt.', false);
        return loadAttached();
      }).catch(function (error) {
        setNotice(root, error.message, true);
        throw error;
      });
    }

    function renderMetaboxObjectPicker() {
      if (!currentSet || !currentSet.id) {
        return;
      }

      if (!picker.createObjectPicker) {
        pickerMount.innerHTML = '<p class="description">Archivobjekt-Auswahl ist nicht geladen.</p>';
        return;
      }

      metaboxObjectPicker = picker.createObjectPicker(pickerMount, {
        mode: 'multiple',
        perPage: 24,
        contextPostId: postId,
        onConfirm: function (objects) {
          return addObjectsToCurrentSet(objects).then(function () {
            if (metaboxObjectPicker && metaboxObjectPicker.clearSelection) {
              metaboxObjectPicker.clearSelection();
            }
          });
        }
      });
    }

    root.querySelector('.iss-archivsets-create-attached').addEventListener('click', createAttachedSet);
    root.querySelector('.iss-archivsets-search').addEventListener('click', searchSets);
    searchField.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchSets();
      }
    });
    root.querySelector('.iss-archivsets-save-members').addEventListener('click', saveCurrentMembers);

    loadAttached();
  }

  function buildTextField(labelText, className, value) {
    var label = createElement('label', 'iss-archivsets-field');
    label.appendChild(createElement('span', '', labelText));
    var input = createElement('input', className);
    input.type = 'text';
    input.value = value || '';
    label.appendChild(input);
    return label;
  }

  function buildTextarea(labelText, className, value, rows) {
    var label = createElement('label', 'iss-archivsets-field');
    label.appendChild(createElement('span', '', labelText));
    var textarea = createElement('textarea', className);
    textarea.rows = rows || 4;
    textarea.value = value || '';
    label.appendChild(textarea);
    return label;
  }

  function initWorkbench(root) {
    if (!config.restRoot) {
      return;
    }

    var selectedId = parseInt(new URLSearchParams(window.location.search).get('set_id') || '0', 10);
    root.innerHTML =
      '<div class="iss-archivsets-notice" hidden></div>' +
      '<div class="iss-archivsets-workbench__layout">' +
        '<aside class="iss-archivsets-workbench__sidebar">' +
          '<div class="iss-archivsets-sidebar-actions">' +
            '<input type="search" class="regular-text iss-archivsets-list-search" placeholder="Archivsets suchen">' +
            '<button type="button" class="button iss-archivsets-list-search-button">' + t('search', 'Search') + '</button>' +
            '<button type="button" class="button button-primary iss-archivsets-new-set">' + t('create', 'Create') + '</button>' +
          '</div>' +
          '<div class="iss-archivsets-list"></div>' +
        '</aside>' +
        '<main class="iss-archivsets-workbench__main">' +
          '<div class="iss-archivsets-editor"></div>' +
        '</main>' +
      '</div>';

    var list = root.querySelector('.iss-archivsets-list');
    var editor = root.querySelector('.iss-archivsets-editor');
    var listSearch = root.querySelector('.iss-archivsets-list-search');

    function loadList() {
      var query = encodeURIComponent(listSearch.value || '');
      list.textContent = t('loading', 'Loading...');
      request('/sets?search=' + query + '&perPage=50').then(function (payload) {
        clear(list);
        var items = payload.items || [];
        if (!items.length) {
          list.appendChild(createElement('p', 'description', t('noResults', 'No results.')));
          return;
        }

        if (!selectedId) {
          selectedId = items[0].id;
        }

        items.forEach(function (set) {
          var button = makeButton(set.title || 'Archivset #' + String(set.id), 'iss-archivsets-list__item', function () {
            selectedId = set.id;
            loadSet();
            loadList();
          });
          button.classList.toggle('is-active', selectedId === set.id);
          button.appendChild(createElement('span', '', setMetaLine(set)));
          list.appendChild(button);
        });

        if (selectedId) {
          loadSet();
        }
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function loadSet() {
      if (!selectedId) {
        editor.appendChild(createElement('p', 'description', 'Archivset auswaehlen oder neu anlegen.'));
        return;
      }

      editor.textContent = t('loading', 'Loading...');
      picker.loadSet(selectedId).then(function (payload) {
        renderEditor(payload.item);
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function renderEditor(set) {
      clear(editor);
      if (!set) {
        editor.appendChild(createElement('p', 'description', 'Archivset nicht gefunden.'));
        return;
      }

      var header = createElement('div', 'iss-archivsets-editor__header');
      header.appendChild(createElement('h2', '', set.title || 'Archivset #' + String(set.id)));
      header.appendChild(createElement('p', 'description', setMetaLine(set)));
      editor.appendChild(header);

      var form = createElement('section', 'iss-archivsets-section');
      form.appendChild(buildTextField('Titel', 'widefat iss-archivsets-field-title', set.title || ''));
      form.appendChild(buildTextarea('Zusammenfassung', 'widefat iss-archivsets-field-summary', set.summary || '', 3));

      var statusLabel = createElement('label', 'iss-archivsets-field');
      statusLabel.appendChild(createElement('span', '', 'Status'));
      var statusSelect = createElement('select', 'iss-archivsets-field-status');
      Object.keys(config.statusLabels || { active: 'Aktiv', draft: 'Entwurf', archived: 'Archiviert' }).forEach(function (status) {
        statusSelect.appendChild(new Option(config.statusLabels[status] || status, status));
      });
      statusSelect.value = set.status || 'active';
      statusLabel.appendChild(statusSelect);
      form.appendChild(statusLabel);

      var sourceGrid = createElement('div', 'iss-archivsets-form-grid');
      sourceGrid.appendChild(buildTextField('Quellsystem', 'widefat iss-archivsets-field-source-system', set.sourceSystem || ''));
      sourceGrid.appendChild(buildTextField('Quell-ID', 'widefat iss-archivsets-field-source-id', set.sourceId || ''));
      sourceGrid.appendChild(buildTextField('Quell-URL', 'widefat iss-archivsets-field-source-url', set.sourceUrl || ''));
      form.appendChild(sourceGrid);
      form.appendChild(buildTextarea('Source refs JSON', 'widefat iss-archivsets-field-source-refs', JSON.stringify(set.sourceRefs || [], null, 2), 5));

      if (set.legacyCollectionPostId) {
        var legacy = createElement('p', 'description');
        legacy.textContent = 'Legacy Archivsammlung #' + String(set.legacyCollectionPostId);
        if (set.legacyCollectionEditUrl) {
          var legacyLink = createElement('a', '', ' bearbeiten');
          legacyLink.href = set.legacyCollectionEditUrl;
          legacy.appendChild(legacyLink);
        }
        form.appendChild(legacy);
      }

      var formActions = createElement('div', 'iss-archivsets-actions');
      formActions.appendChild(makeButton(t('save', 'Save'), 'button button-primary', function () {
        saveSet(set.id);
      }));
      formActions.appendChild(makeButton(t('deleteSet', 'Delete set'), 'button-link-delete', function () {
        if (!window.confirm(t('deleteSet', 'Delete set') + '?')) {
          return;
        }
        request('/sets/' + set.id, { method: 'DELETE' }).then(function () {
          selectedId = 0;
          clear(editor);
          loadList();
        }).catch(function (error) {
          setNotice(root, error.message, true);
        });
      }));
      form.appendChild(formActions);
      editor.appendChild(form);

      renderMembers(set);
      renderObjectPicker(set);
    }

    function saveSet(setId) {
      var sourceRefs;
      try {
        sourceRefs = parseSourceRefs(editor.querySelector('.iss-archivsets-field-source-refs').value || '');
      } catch (error) {
        setNotice(root, error.message, true);
        return;
      }

      request('/sets/' + setId, {
        method: 'PUT',
        body: {
          title: editor.querySelector('.iss-archivsets-field-title').value || '',
          summary: editor.querySelector('.iss-archivsets-field-summary').value || '',
          status: editor.querySelector('.iss-archivsets-field-status').value || 'active',
          sourceSystem: editor.querySelector('.iss-archivsets-field-source-system').value || '',
          sourceId: editor.querySelector('.iss-archivsets-field-source-id').value || '',
          sourceUrl: editor.querySelector('.iss-archivsets-field-source-url').value || '',
          sourceRefs: sourceRefs
        }
      }).then(function (payload) {
        renderEditor(payload.item);
        loadList();
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function renderMembers(set) {
      var section = createElement('section', 'iss-archivsets-section iss-archivsets-members');
      var title = createElement('div', 'iss-archivsets-section__head');
      title.appendChild(createElement('h3', '', 'Mitglieder'));
      title.appendChild(makeButton(t('save', 'Save'), 'button', function () {
        saveMembers(set.id);
      }));
      section.appendChild(title);

      var listElement = createElement('div', 'iss-archivsets-members__list');
      (set.members || []).forEach(function (member) {
        listElement.appendChild(createMemberRow(member));
      });
      if (!(set.members || []).length) {
        listElement.appendChild(createElement('p', 'description', 'Noch keine Archivobjekte ausgewaehlt.'));
      }
      section.appendChild(listElement);
      editor.appendChild(section);
    }

    function createMemberRow(member) {
      var row = createElement('article', 'iss-archivsets-member');
      row.setAttribute('data-member-id', String(member.id));

      var top = createElement('div', 'iss-archivsets-member__top');
      var label = member.objectTitle || member.legacyCollectionTitle || member.memberTitle || 'Mitglied #' + String(member.id);
      top.appendChild(createElement('strong', '', label));
      var controls = createElement('div', 'iss-archivsets-member__controls');
      controls.appendChild(makeButton('Up', 'button iss-archivsets-member-up', function () {
        var previous = row.previousElementSibling;
        if (previous) {
          row.parentNode.insertBefore(row, previous);
        }
      }));
      controls.appendChild(makeButton('Down', 'button iss-archivsets-member-down', function () {
        var next = row.nextElementSibling;
        if (next) {
          row.parentNode.insertBefore(next, row);
        }
      }));
      controls.appendChild(makeButton(t('remove', 'Remove'), 'button-link-delete', function () {
        request('/sets/' + selectedId + '/members/' + member.id, { method: 'DELETE' }).then(loadSet).catch(function (error) {
          setNotice(root, error.message, true);
        });
      }));
      top.appendChild(controls);
      row.appendChild(top);

      var meta = [];
      if (member.sourceObjectId) {
        meta.push(member.sourceObjectId);
      }
      if (member.sourceUrl) {
        meta.push(member.sourceUrl);
      }
      if (meta.length) {
        row.appendChild(createElement('p', 'description', meta.join(' - ')));
      }

      var grid = createElement('div', 'iss-archivsets-member__fields');
      grid.appendChild(buildTextField('Seite', 'widefat iss-archivsets-member-page', member.pageLabel || ''));
      grid.appendChild(buildTextField('Anzeigetitel', 'widefat iss-archivsets-member-title', member.displayTitle || ''));
      grid.appendChild(buildTextarea('Beschriftung', 'widefat iss-archivsets-member-caption', member.caption || '', 3));
      row.appendChild(grid);

      if (member.editUrl || member.permalink) {
        var links = createElement('p', 'description');
        if (member.editUrl) {
          var editLink = createElement('a', '', 'Objekt bearbeiten');
          editLink.href = member.editUrl;
          links.appendChild(editLink);
        }
        if (member.permalink) {
          if (member.editUrl) {
            links.appendChild(document.createTextNode(' - '));
          }
          var publicLink = createElement('a', '', 'Oeffnen');
          publicLink.href = member.permalink;
          links.appendChild(publicLink);
        }
        row.appendChild(links);
      }

      return row;
    }

    function saveMembers(setId) {
      var rows = Array.prototype.slice.call(editor.querySelectorAll('.iss-archivsets-member'));
      var members = rows.map(function (row, index) {
        return {
          id: parseInt(row.getAttribute('data-member-id') || '0', 10),
          position: index,
          pageLabel: row.querySelector('.iss-archivsets-member-page').value || '',
          displayTitle: row.querySelector('.iss-archivsets-member-title').value || '',
          caption: row.querySelector('.iss-archivsets-member-caption').value || ''
        };
      });

      request('/sets/' + setId + '/members/reorder', {
        method: 'POST',
        body: { members: members }
      }).then(function (payload) {
        renderEditor(payload.item);
        loadList();
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    }

    function renderObjectPicker(set) {
      var section = createElement('section', 'iss-archivsets-section iss-archivsets-picker');
      var head = createElement('div', 'iss-archivsets-section__head');
      var mount = createElement('div', 'iss-archivsets-picker__mount');
      head.appendChild(createElement('h3', '', 'Archivobjekte'));
      section.appendChild(head);
      section.appendChild(mount);
      editor.appendChild(section);

      if (!picker.createObjectPicker) {
        mount.appendChild(createElement('p', 'description', 'Archivobjekt-Auswahl ist nicht geladen.'));
        return;
      }

      picker.createObjectPicker(mount, {
        mode: 'multiple',
        perPage: 24,
        onConfirm: function (objects) {
          return addObjectsToSet(set.id, objects).then(function (payload) {
            if (payload && payload.item) {
              renderEditor(payload.item);
            } else {
              loadSet();
            }
            setNotice(root, 'Archivobjekte wurden zum Archivset hinzugefuegt.', false);
            loadList();
          }).catch(function (error) {
            setNotice(root, error.message, true);
            throw error;
          });
        }
      });
    }

    root.querySelector('.iss-archivsets-list-search-button').addEventListener('click', loadList);
    listSearch.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        loadList();
      }
    });
    root.querySelector('.iss-archivsets-new-set').addEventListener('click', function () {
      picker.createSet({ title: 'Neues Archivset' }).then(function (payload) {
        selectedId = payload.item.id;
        loadList();
      }).catch(function (error) {
        setNotice(root, error.message, true);
      });
    });

    loadList();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var metabox = document.getElementById('iss-archivsets-metabox');
    if (metabox) {
      initMetabox(metabox);
    }

    var workbench = document.getElementById('iss-archivsets-workbench');
    if (workbench) {
      initWorkbench(workbench);
    }
  });
})();
