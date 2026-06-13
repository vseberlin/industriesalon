(function () {
  var archive = window.issArchiveSetsPicker || null;

  window.issContentEditorModalHandlers = window.issContentEditorModalHandlers || {};

  if (!archive || !archive.createObjectPicker) {
    return;
  }

  function getArchivePostId() {
    var config = archive.config || {};

    return parseInt(config.postId || '0', 10);
  }

  function notifyArchiveAttachedSetsChanged(postId) {
    if (!postId) {
      return Promise.resolve();
    }

    return archive.getAttachedSets(postId).then(function (payload) {
      archive.notifyAttachedSetsChanged(postId, payload.items || []);
    }).catch(function () {
      return null;
    });
  }

  function loadAttachedArchiveBucket() {
    var postId = getArchivePostId();
    if (!postId) {
      return Promise.resolve(null);
    }

    return archive.getAttachedSets(postId).then(function (payload) {
      var sets = (payload.items || []).filter(function (set) {
        return set && set.status === 'active' && !parseInt(set.legacyCollectionPostId || '0', 10);
      });

      if (!sets.length) {
        return null;
      }

      sets.sort(function (a, b) {
        return parseInt(a.linkPosition || '0', 10) - parseInt(b.linkPosition || '0', 10);
      });

      return archive.loadSet(sets[0].id, { contextPostId: postId }).then(function (setPayload) {
        return setPayload.item || null;
      });
    }).catch(function () {
      return null;
    });
  }

  function getSetMemberForObject(set, objectId) {
    var id = parseInt(objectId || '0', 10);
    var matches = (set && set.members ? set.members : []).filter(function (member) {
      return parseInt(member.objectPostId || '0', 10) === id;
    });

    if (!matches.length) {
      return null;
    }

    matches.sort(function (a, b) {
      return parseInt(b.position || '0', 10) - parseInt(a.position || '0', 10);
    });

    return matches[0];
  }

  function insertArchiveObject(objectId, modal, options) {
    var adapter = window.issArchiveEditorAdapter || archive;
    options = options || {};
    var inserted = adapter && adapter.insertArchiveObject
      ? adapter.insertArchiveObject(objectId, {
        contextPostId: getArchivePostId(),
        setId: options.setId || 0,
        memberPosition: options.memberPosition
      })
      : '';

    if (!inserted) {
      window.alert('Archivobjekt konnte nicht eingefuegt werden.');
      return;
    }

    modal.close();
  }

  function renderSetSearchResult(container, root, set, state) {
    var card = archive.createElement('article', 'iss-archive-editor-insert__set');
    var body = archive.createElement('div', '');
    body.appendChild(archive.createElement('strong', '', set.title || 'Archivset #' + String(set.id)));
    body.appendChild(archive.createElement('p', 'description', String(set.memberCount || 0) + ' Objekte'));
    card.appendChild(body);

    var button = archive.createElement('button', 'button', 'Auswahl verbinden');
    button.type = 'button';
    button.addEventListener('click', function () {
      button.disabled = true;
      button.textContent = 'Verbindet...';
      attachArchiveSet(root, set.id, state).catch(function (error) {
        button.disabled = false;
        button.textContent = 'Auswahl verbinden';
        container.prepend(archive.createElement('div', 'iss-archive-editor-insert__empty', error.message || 'Auswahl konnte nicht verbunden werden.'));
      });
    });
    card.appendChild(button);
    container.appendChild(card);
  }

  function searchArchiveSets(root, state) {
    var query = root.querySelector('.iss-archive-editor-insert__set-search').value || '';
    var results = root.querySelector('.iss-archive-editor-insert__set-results');
    results.textContent = 'Laedt...';

    archive.searchSets({
      search: query,
      perPage: 12,
      contextPostId: getArchivePostId()
    }).then(function (payload) {
      var sets = (payload.items || []).filter(function (set) {
        return set && set.status === 'active' && !parseInt(set.legacyCollectionPostId || '0', 10);
      });

      archive.clear(results);
      if (!sets.length) {
        results.appendChild(archive.createElement('div', 'iss-archive-editor-insert__empty', 'Keine passenden manuellen Archivauswahlen gefunden.'));
        return;
      }

      sets.forEach(function (set) {
        renderSetSearchResult(results, root, set, state);
      });
    }).catch(function (error) {
      archive.clear(results);
      results.appendChild(archive.createElement('div', 'iss-archive-editor-insert__empty', error.message || 'Archivauswahlen konnten nicht geladen werden.'));
    });
  }

  function setArchiveBucket(root, set, state) {
    var scope = root.querySelector('.iss-archive-editor-insert__scope');
    var bucketPanel = root.querySelector('.iss-archive-editor-insert__bucket');
    var activePanel = root.querySelector('.iss-archive-editor-insert__active');
    var pickerMount = root.querySelector('.iss-archive-editor-insert__picker');

    state.attachedSet = set || null;

    if (set) {
      scope.textContent = 'Auswahl: ' + (set.title || 'Archivset #' + String(set.id));
      bucketPanel.hidden = true;
      activePanel.hidden = false;
    } else {
      scope.textContent = 'Keine manuelle Archivauswahl verbunden. Verbinde eine Auswahl oder suche vorlaeufig im gesamten Archiv.';
      bucketPanel.hidden = false;
      activePanel.hidden = true;
    }

    archive.clear(pickerMount);
    state.objectPicker = archive.createObjectPicker(pickerMount, {
      mode: 'single',
      perPage: 24,
      contextPostId: getArchivePostId(),
      autoFocus: true,
      onConfirm: function (objects) {
        var object = objects[0] || null;
        if (!object || !object.id) {
          return;
        }

        if (state.attachedSet && state.addToAttachedSet) {
          archive.addObjectToSet(state.attachedSet.id, object.id, {
            contextPostId: getArchivePostId()
          }).then(function (payload) {
            var set = payload && payload.item ? payload.item : state.attachedSet;
            var member = getSetMemberForObject(set, object.id);
            state.attachedSet = set;
            insertArchiveObject(object.id, state.modal, member ? {
              setId: set.id,
              memberPosition: parseInt(member.position || '0', 10)
            } : {});
          }).catch(function (error) {
            pickerMount.prepend(archive.createElement('div', 'iss-archive-editor-insert__empty', error.message || 'Objekt konnte nicht hinzugefuegt werden.'));
          });
          return;
        }

        insertArchiveObject(object.id, state.modal);
      }
    });
  }

  function loadSetAndUse(root, setId, state) {
    return archive.loadSet(setId, { contextPostId: getArchivePostId() }).then(function (payload) {
      setArchiveBucket(root, payload.item || null, state);
    });
  }

  function attachArchiveSet(root, setId, state) {
    var postId = getArchivePostId();
    if (!postId || !setId) {
      return Promise.reject(new Error('Inhalt zuerst speichern, dann kann eine Auswahl verbunden werden.'));
    }

    return archive.attachSet(postId, setId).then(function () {
      return notifyArchiveAttachedSetsChanged(postId);
    }).then(function () {
      return loadSetAndUse(root, setId, state);
    });
  }

  function detachArchiveSet(root, state) {
    var postId = getArchivePostId();
    var set = state.attachedSet;
    if (!postId || !set || !set.id) {
      return Promise.reject(new Error('Keine verbundene Auswahl gefunden.'));
    }

    return archive.detachSet(postId, set.id).then(function () {
      return notifyArchiveAttachedSetsChanged(postId);
    }).then(function () {
      setArchiveBucket(root, null, state);
    });
  }

  function createArchiveSet(root, state) {
    var postId = getArchivePostId();
    var titleInput = document.getElementById('title');
    var title = 'Archivauswahl: ' + (titleInput ? titleInput.value : '');
    if (!postId) {
      return Promise.reject(new Error('Inhalt zuerst speichern, dann kann eine Auswahl angelegt werden.'));
    }

    return archive.createSet({
      title: title.trim() || 'Archivauswahl',
      contextPostId: postId,
      attachToContext: true
    }).then(function (payload) {
      var set = payload.item || null;
      if (!set || !set.id) {
        throw new Error('Archivauswahl konnte nicht angelegt werden.');
      }
      return notifyArchiveAttachedSetsChanged(postId).then(function () {
        return loadSetAndUse(root, set.id, state);
      });
    });
  }

  function renderArchiveInsertMarkup() {
    return '<div class="iss-archive-editor-insert">' +
      '<p class="description iss-archive-editor-insert__scope">Archivmaterial wird geladen.</p>' +
      '<div class="iss-archive-editor-insert__active" hidden>' +
        '<button type="button" class="button iss-archive-editor-insert__switch-set">Andere Auswahl verbinden</button>' +
        '<button type="button" class="button-link-delete iss-archive-editor-insert__detach-set">Auswahl trennen</button>' +
        '<label><input type="checkbox" class="iss-archive-editor-insert__add-to-set"> Eingefuegte Objekte zur Auswahl hinzufuegen</label>' +
      '</div>' +
      '<div class="iss-archive-editor-insert__bucket" hidden>' +
        '<div class="iss-archive-editor-insert__bucket-actions">' +
          '<button type="button" class="button button-primary iss-archive-editor-insert__create-set">Neue Auswahl anlegen</button>' +
          '<input type="search" class="regular-text iss-archive-editor-insert__set-search" placeholder="Bestehende Archivauswahl suchen">' +
          '<button type="button" class="button iss-archive-editor-insert__set-run">Auswahl suchen</button>' +
        '</div>' +
        '<div class="iss-archive-editor-insert__set-results"></div>' +
      '</div>' +
      '<div class="iss-archive-editor-insert__picker"></div>' +
    '</div>';
  }

  function bindArchiveInsert(root, modal) {
    var state = {
      attachedSet: null,
      addToAttachedSet: false,
      modal: modal,
      objectPicker: null
    };

    root.querySelector('.iss-archive-editor-insert__switch-set').addEventListener('click', function () {
      root.querySelector('.iss-archive-editor-insert__bucket').hidden = false;
      searchArchiveSets(root, state);
    });
    root.querySelector('.iss-archive-editor-insert__detach-set').addEventListener('click', function (event) {
      var button = event.currentTarget;
      button.disabled = true;
      button.textContent = 'Trennt...';
      detachArchiveSet(root, state).catch(function (error) {
        var pickerMount = root.querySelector('.iss-archive-editor-insert__picker');
        button.disabled = false;
        button.textContent = 'Auswahl trennen';
        pickerMount.prepend(archive.createElement('div', 'iss-archive-editor-insert__empty', error.message || 'Auswahl konnte nicht getrennt werden.'));
      });
    });
    root.querySelector('.iss-archive-editor-insert__create-set').addEventListener('click', function (event) {
      var button = event.currentTarget;
      button.disabled = true;
      button.textContent = 'Legt an...';
      createArchiveSet(root, state).catch(function (error) {
        var results = root.querySelector('.iss-archive-editor-insert__set-results');
        button.disabled = false;
        button.textContent = 'Neue Auswahl anlegen';
        archive.clear(results);
        results.appendChild(archive.createElement('div', 'iss-archive-editor-insert__empty', error.message || 'Archivauswahl konnte nicht angelegt werden.'));
      });
    });
    root.querySelector('.iss-archive-editor-insert__set-run').addEventListener('click', function () {
      searchArchiveSets(root, state);
    });
    root.querySelector('.iss-archive-editor-insert__set-search').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchArchiveSets(root, state);
      }
    });
    root.querySelector('.iss-archive-editor-insert__add-to-set').addEventListener('change', function (event) {
      state.addToAttachedSet = event.currentTarget.checked;
    });

    loadAttachedArchiveBucket().then(function (set) {
      state.addToAttachedSet = !!set;
      root.querySelector('.iss-archive-editor-insert__add-to-set').checked = !!set;
      setArchiveBucket(root, set, state);
    });
  }

  window.issContentEditorModalHandlers['iss-wf-import-archive-picker'] = {
    open: function (modal) {
      var body;
      body = modal.openContent('Archivmaterial hinzufügen', renderArchiveInsertMarkup(), {
        focusSelector: '.iss-archive-object-picker__search'
      });
      if (!body) {
        return;
      }

      bindArchiveInsert(body.querySelector('.iss-archive-editor-insert'), modal);
    }
  };
}());
