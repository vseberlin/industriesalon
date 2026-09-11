(function () {
  var config = window.issContentEditorialSets || {};
  var apiFetch = window.wp && window.wp.apiFetch ? window.wp.apiFetch : null;
  var root = document.getElementById('iss-editorial-sets-workbench');

  if (!root || !apiFetch) {
    return;
  }

  apiFetch.use(apiFetch.createNonceMiddleware(config.nonce || ''));

  var modalNode = null;
  var releaseModalFocus = null;
  var lastPreviewId = 0;

  var state = {
    sets: [],
    setsPage: 1, setsTotal: 0, setSearch: '',
    itemsPage: 1, itemsTotal: 0,
    targets: [], target: null, targetSearch: '', targetPage: 1, targetPages: 1,
    sectionType: '', editUrl: '',
    items: [],
    selectedSetId: parseInt(config.setId, 10) || 0,
    selectedItems: {},
    status: '',
    modalItem: null,
    busy: false,
    notice: '',
    uploadOpened: false
  };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (typeof text === 'string') {
      node.textContent = text;
    }
    return node;
  }

  function clear(node) {
    while (node.firstChild) {
      node.removeChild(node.firstChild);
    }
  }

  function path(route) {
    return '/iss-content/v1' + route;
  }

  function t(key, fallback) {
    return (config.strings && config.strings[key]) || fallback || key;
  }

  function mapLabel(mapName, key, fallback) {
    var map = config.strings && config.strings[mapName] ? config.strings[mapName] : {};
    return map[key] || fallback || key || '';
  }

  function statusLabel(status) {
    return mapLabel('statusLabels', status, status);
  }

  function kindLabel(kind) {
    return mapLabel('kindLabels', kind, kind || 'file');
  }

  function storageLabel(storageState) {
    return mapLabel('storageLabels', storageState, storageState || '-');
  }

  function selectedIds() {
    return Object.keys(state.selectedItems).filter(function (id) {
      return state.selectedItems[id];
    }).map(function (id) {
      return parseInt(id, 10);
    }).filter(Boolean);
  }

  function promotionTarget() {
    return state.target;
  }

  function setNotice(message) {
    state.notice = message || '';
    render();
  }

  function request(options) {
    state.busy = true;
    render();
    return apiFetch(options).catch(function (error) {
      setNotice((error && error.message) || (config.strings && config.strings.error) || 'Request failed.');
      throw error;
    }).finally(function () {
      state.busy = false;
      render();
    });
  }

  function loadSets(append) {
    if (append !== true) { state.setsPage = 1; }
    var query = '?page=' + state.setsPage + '&search=' + encodeURIComponent(state.setSearch);
    return request({ path: path('/editorial-sets' + query) }).then(function (response) {
      state.sets = append === true ? state.sets.concat(response.items || []) : response.items || [];
      state.setsTotal = response.total || 0;
      if (!state.selectedSetId && state.sets.length) { state.selectedSetId = state.sets[0].id; }
      return loadItems();
    });
  }

  function loadItems() {
    var parts = ['setId=' + state.selectedSetId, 'page=' + state.itemsPage];
    if (state.status) { parts.push('status=' + encodeURIComponent(state.status)); }
    return request({ path: path('/editorial-set-items?' + parts.join('&')) }).then(function (response) {
      state.items = response.items || [];
      state.itemsTotal = response.total || 0;
      state.selectedItems = {};
      render();
    });
  }

  function loadTargets(initial) {
    var query = '?search=' + encodeURIComponent(state.targetSearch) + '&page=' + state.targetPage;
    if (initial && config.contextId) { query = '?id=' + config.contextId; }
    return request({path: path('/editorial-set-targets' + query)}).then(function (response) {
      state.targets = response.items || [];
      state.targetPages = response.totalPages || 1;
      if (initial && config.contextId) { state.target = state.targets[0] || null; }
      render();
    });
  }

  function pager(page, pages, onPage) {
    var bar = el('div', 'tablenav');
    ['Zurück', 'Weiter'].forEach(function (label, index) {
      var button = el('button', 'button', label);
      button.type = 'button';
      button.disabled = state.busy || (index ? page >= pages : page <= 1);
      button.addEventListener('click', function () { onPage(page + (index ? 1 : -1)); });
      bar.appendChild(button);
    });
    bar.appendChild(el('span', 'description', ' Seite ' + page + ' / ' + Math.max(1, pages)));
    return bar;
  }

  function renderDestination() {
    var panel = el('div', 'iss-editorial-sets-filters');
    var label = el('label', '', 'Ziel für das Material ');
    var search = el('input');
    search.type = 'search'; search.value = state.targetSearch; search.setAttribute('aria-label', 'Inhalte suchen');
    var run = el('button', 'button', 'Inhalte suchen'); run.type = 'button';
    run.addEventListener('click', function () { state.targetSearch = search.value; state.targetPage = 1; loadTargets(); });
    panel.appendChild(search); panel.appendChild(run);
    var select = el('select'); select.setAttribute('aria-label', 'Ziel für das Material');
    select.appendChild(el('option', '', 'Ziel auswählen'));
    select.firstChild.value = '';
    var choices = state.targets.slice();
    if (state.target && !choices.some(function (item) { return item.id === state.target.id; })) { choices.unshift(state.target); }
    choices.forEach(function (item) {
      var option = el('option', '', item.title + ' (' + (item.typeLabel || item.type) + ')');
      option.value = String(item.id); option.selected = !!state.target && item.id === state.target.id;
      select.appendChild(option);
    });
    select.value = state.target ? String(state.target.id) : '';
    select.addEventListener('change', function () {
      state.target = choices.find(function (item) { return String(item.id) === select.value; }) || null;
      state.sectionType = ''; state.editUrl = ''; render();
    });
    label.appendChild(select); panel.appendChild(label);
    panel.appendChild(pager(state.targetPage, state.targetPages, function (page) { state.targetPage = page; loadTargets(); }));
    if (state.target) {
      var section = el('select'); section.setAttribute('aria-label', 'Zielabschnitt');
      [{value:'', label:'Fotos in Galerie, Dokumente in Dateien'}, {value:'galerie', label:'Galerie'}, {value:'material', label:'Dokumente & Downloads'}].forEach(function (choice) {
        if (choice.value && state.target.sections.indexOf(choice.value) < 0) { return; }
        var option = el('option', '', choice.label); option.value = choice.value; option.selected = state.sectionType === choice.value; section.appendChild(option);
      });
      section.addEventListener('change', function () { state.sectionType = section.value; });
      panel.appendChild(section);
    }
    return panel;
  }

  function createSet() {
    var title = window.prompt(t('setName', 'Set name'));
    if (!title) {
      return;
    }
    request({
      path: path('/editorial-sets'),
      method: 'POST',
      data: {
        title: title,
        setRole: 'intake',
        status: 'working',
        contextType: config.contextType || '',
        contextId: config.contextId || 0
      }
    }).then(function (response) {
      if (response && response.item) {
        state.selectedSetId = response.item.id;
      }
      loadSets();
    });
  }

  function attachSetToContext(setId) {
    if (!config.contextType || !config.contextId || !setId) {
      return;
    }

    request({
      path: path('/editorial-sets/' + setId + '/attach'),
      method: 'POST',
      data: {
        contextType: config.contextType,
        contextId: config.contextId,
        linkRole: 'source_material'
      }
    }).then(loadSets);
  }

  function addMedia() {
    if (!window.wp || !window.wp.media) {
      return;
    }
    if (!state.selectedSetId) {
      setNotice(t('setMissingForUpload', 'Create or select a Set before uploading.'));
      return;
    }
    var frame = window.wp.media({
      title: t('addMedia', 'Add media to Set'),
      multiple: true,
      button: { text: t('addToSet', 'Add to Set') }
    });
    frame.on('select', function () {
      var attachments = frame.state().get('selection').toJSON();
      var chain = Promise.resolve();
      attachments.forEach(function (attachment) {
        chain = chain.then(function () {
          return request({
            path: path('/editorial-set-items'),
            method: 'POST',
            data: {
              setId: state.selectedSetId || 0,
              kind: 'wp_media',
              source: 'wp-media',
              sourceId: String(attachment.id || ''),
              label: attachment.caption || attachment.title || '',
              status: 'pending',
              origin: 'manual_upload'
            }
          });
        });
      });
      chain.then(loadItems);
    });
    frame.open();
  }

  function uploadRawFiles() {
    if (!state.selectedSetId) {
      setNotice(t('setMissingForUpload', 'Create or select a Set before uploading.'));
      return;
    }

    var input = el('input');
    input.type = 'file';
    input.multiple = true;
    input.hidden = true;
    input.addEventListener('change', function () {
      if (!input.files || !input.files.length) {
        input.remove();
        return;
      }

      var form = new window.FormData();
      Array.prototype.forEach.call(input.files, function (file) {
        form.append('files[]', file);
      });
      form.append('setId', String(state.selectedSetId));
      if (config.contextType && config.contextId) {
        form.append('contextType', config.contextType);
        form.append('contextId', String(config.contextId));
      }

      input.remove();
      request({
        path: path('/editorial-set-items/upload'),
        method: 'POST',
        body: form
      }).then(loadItems);
    });

    root.appendChild(input);
    input.click();
  }

  function batch(action) {
    var ids = selectedIds();
    if (!ids.length) {
      setNotice(t('noSelection', 'No items selected.'));
      return;
    }
    request({
      path: path('/editorial-set-items/batch'),
      method: 'POST',
      data: {
        action: action,
        itemIds: ids,
        setId: state.selectedSetId
      }
    }).then(function (response) { state.notice = response.message || ('Geändert: ' + response.updated); return loadItems(); });
  }

  function moveSelected() {
    var ids = selectedIds();
    if (!ids.length) {
      setNotice(t('noSelection', 'No items selected.'));
      return;
    }

    var target = window.prompt(t('moveToSet', 'Move to Set ID'));
    var targetId = parseInt(target || '', 10);
    if (!targetId) {
      return;
    }

    request({
      path: path('/editorial-set-items/batch'),
      method: 'POST',
      data: {
        action: 'move',
        itemIds: ids,
        setId: targetId
      }
    }).then(loadItems);
  }

  function saveModalItem() {
    var item = state.modalItem;
    if (!item) {
      return;
    }
    var label = modalNode.querySelector('.iss-editorial-sets-modal__label');
    var status = modalNode.querySelector('.iss-editorial-sets-modal__status');
    var notes = modalNode.querySelector('.iss-editorial-sets-modal__notes');
    var rightsValue = Object.assign({}, item.rights || {});
    var provenanceValue = Object.assign({}, item.provenance || {});
    rightsValue.attribution = modalNode.querySelector('.iss-editorial-sets-modal__credit').value.trim();
    rightsValue.license = modalNode.querySelector('.iss-editorial-sets-modal__license').value;
    rightsValue.consent = modalNode.querySelector('.iss-editorial-sets-modal__consent').checked ? '1' : '';
    provenanceValue.source_note = modalNode.querySelector('.iss-editorial-sets-modal__source-note').value.trim();

    state.modalItem = Object.assign({}, item, { label: label.value, status: status.value, notes: notes.value, rights: rightsValue, provenance: provenanceValue });
    request({
      path: path('/editorial-set-items/' + item.id),
      method: 'POST',
      data: {
        label: label ? label.value : item.label,
        status: status ? status.value : item.status,
        notes: notes ? notes.value : item.notes,
        rights: rightsValue,
        provenance: provenanceValue,
        decayAt: item.decayAt || '',
        retain: item.retain,
        retainReason: item.retainReason || ''
      }
    }).then(function (response) {
      state.modalItem = null;
      state.notice = response && response.item ? 'Eintrag gespeichert.' : '';
      return loadItems();
    });
  }

  function promoteSelected() {
    var ids = selectedIds();
    var target = promotionTarget();
    if (!ids.length || !target || !target.type || !target.id) {
      setNotice(t('promotionTargetMissing', 'Select approved items from a Set attached to a target post.'));
      return;
    }
    request({
      path: path('/editorial-set-items/promote'),
      method: 'POST',
      data: {
        itemIds: ids,
        targetType: target.type,
        targetId: target.id, sectionType: state.sectionType, base: target.base, draftToken: target.draftToken
      }
    }).then(function (response) {
      state.editUrl = response.editUrl || '';
      setNotice(response && response.message ? response.message : t('promotionComplete', 'Draft prepared.'));
      if (response.prepared) { state.target = null; }
      loadItems();
    });
  }

  function renderToolbar() {
    var toolbar = el('div', 'iss-editorial-sets-toolbar');
    var create = el('button', 'button button-primary', t('newSet', 'New Set'));
    create.type = 'button';
    create.addEventListener('click', createSet);
    toolbar.appendChild(create);

    var upload = el('button', 'button button-primary', t('uploadFiles', 'Upload files to Set'));
    upload.type = 'button';
    upload.disabled = !state.selectedSetId || state.busy;
    upload.addEventListener('click', uploadRawFiles);
    toolbar.appendChild(upload);

    var add = el('button', 'button', t('addMedia', 'Add media'));
    add.type = 'button';
    add.disabled = !state.selectedSetId || state.busy;
    add.addEventListener('click', addMedia);
    toolbar.appendChild(add);

    var selectAll = el('button', 'button', t('selectAll', 'Select all'));
    selectAll.type = 'button';
    selectAll.disabled = !state.items.length || state.busy;
    selectAll.addEventListener('click', function () {
      state.items.forEach(function (item) {
        state.selectedItems[item.id] = true;
      });
      render();
    });
    toolbar.appendChild(selectAll);

    ['approve', 'reject', 'review', 'retain', 'stale', 'restore'].forEach(function (action) {
      var button = el('button', 'button', t(action, action.replace(/^\w/, function (match) { return match.toUpperCase(); })));
      button.type = 'button';
      button.disabled = !selectedIds().length || state.busy;
      button.addEventListener('click', function () {
        batch(action);
      });
      toolbar.appendChild(button);
    });

    var archive = el('button', 'button', t('archiveCandidate', 'Archive candidate'));
    archive.type = 'button';
    archive.disabled = !selectedIds().length || state.busy;
    archive.addEventListener('click', function () {
      batch('archive_candidate');
    });
    toolbar.appendChild(archive);

    var move = el('button', 'button', t('move', 'Move'));
    move.type = 'button';
    move.disabled = !selectedIds().length || state.busy;
    move.addEventListener('click', moveSelected);
    toolbar.appendChild(move);

    var promote = el('button', 'button button-secondary', t('promote', 'Promote'));
    promote.type = 'button';
    promote.disabled = !selectedIds().length || !promotionTarget() || state.busy;
    promote.addEventListener('click', promoteSelected);
    toolbar.appendChild(promote);

    return toolbar;
  }

  function renderFilters() {
    var filters = el('div', 'iss-editorial-sets-filters');
    var search = el('input'); search.type = 'search'; search.value = state.setSearch; search.setAttribute('aria-label', 'Sets suchen');
    var run = el('button', 'button', 'Sets suchen'); run.type = 'button';
    run.addEventListener('click', function () { state.setSearch = search.value; state.setsPage = 1; loadSets(); });
    filters.appendChild(search); filters.appendChild(run);
    var setSelect = el('select'); setSelect.setAttribute('aria-label', 'Material-Set');
    var uncategorized = el('option', '', t('uncategorized', 'Uncategorized'));
    uncategorized.value = '0';
    setSelect.appendChild(uncategorized);
    state.sets.forEach(function (set) {
      var option = el('option', '', set.title || set.setKey || String(set.id));
      option.value = String(set.id);
      option.selected = set.id === state.selectedSetId;
      setSelect.appendChild(option);
    });
    setSelect.addEventListener('change', function () {
      state.selectedSetId = parseInt(setSelect.value, 10) || 0;
      state.itemsPage = 1; loadItems();
    });
    filters.appendChild(setSelect);
    if (state.sets.length < state.setsTotal) {
      var more = el('button', 'button', 'Weitere Sets laden'); more.type = 'button';
      more.addEventListener('click', function () { state.setsPage++; loadSets(true); }); filters.appendChild(more);
    }

    if (config.contextType && config.contextId && state.selectedSetId) {
      var attach = el('button', 'button', t('attachHere', 'Attach here'));
      attach.type = 'button';
      attach.addEventListener('click', function () {
        attachSetToContext(state.selectedSetId);
      });
      filters.appendChild(attach);
    }

    var statusSelect = el('select');
    var all = el('option', '', t('allStatuses', 'All statuses'));
    all.value = '';
    statusSelect.appendChild(all);
    (config.statuses || []).forEach(function (status) {
      var option = el('option', '', statusLabel(status));
      option.value = status;
      option.selected = status === state.status;
      statusSelect.appendChild(option);
    });
    statusSelect.addEventListener('change', function () {
      state.status = statusSelect.value; state.itemsPage = 1;
      loadItems();
    });
    filters.appendChild(statusSelect);

    return filters;
  }

  function renderGrid() {
    var grid = el('div', 'iss-editorial-sets-grid');
    if (!state.items.length) {
      grid.appendChild(el('p', 'iss-editorial-sets-empty', t('noItems', 'No items in this view.')));
      return grid;
    }

    state.items.forEach(function (item) {
      var card = el('article', 'iss-editorial-sets-card');
      var preview = el('button', 'iss-editorial-sets-card__preview');
      preview.type = 'button';
      preview.dataset.itemId = String(item.id);
      var thumb = item.preview && item.preview.thumbnail ? item.preview.thumbnail : '';
      if (thumb) {
        var img = el('img');
        img.src = thumb;
        img.alt = item.label || (item.preview && item.preview.title) || '';
        preview.appendChild(img);
      } else {
        preview.appendChild(el('span', 'iss-editorial-sets-card__icon', kindLabel(item.kind)));
      }
      preview.addEventListener('click', function () {
        lastPreviewId = item.id;
        state.modalItem = item;
        render();
      });
      card.appendChild(preview);

      var meta = el('div', 'iss-editorial-sets-card__meta');
      var checkLabel = el('label', 'iss-editorial-sets-card__check');
      var check = el('input');
      check.type = 'checkbox';
      check.checked = !!state.selectedItems[item.id];
      check.addEventListener('change', function () {
        state.selectedItems[item.id] = check.checked;
        render();
      });
      checkLabel.appendChild(check);
      checkLabel.appendChild(el('span', '', statusLabel(item.status || 'pending')));
      meta.appendChild(checkLabel);
      meta.appendChild(el('strong', '', item.label || (item.preview && item.preview.title) || t('untitled', 'Untitled item')));
      meta.appendChild(el('span', 'description', [
        item.setTitle || t('uncategorized', 'Uncategorized'),
        storageLabel(item.storageState)
      ].filter(Boolean).join(' - ')));
      card.appendChild(meta);
      grid.appendChild(card);
    });

    return grid;
  }

  function renderModal() {
    if (!state.modalItem) {
      return null;
    }
    var item = state.modalItem;
    var overlay = el('div', 'iss-editorial-sets-modal');
    var dialog = el('div', 'iss-editorial-sets-modal__dialog');
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-label', item.label || 'Material prüfen');
    var head = el('div', 'iss-editorial-sets-modal__head');
    head.appendChild(el('h2', '', item.label || (item.preview && item.preview.title) || t('item', 'Item')));
    var close = el('button', 'button', t('close', 'Close'));
    close.type = 'button';
    close.addEventListener('click', function () {
      state.modalItem = null;
      render();
    });
    head.appendChild(close);
    dialog.appendChild(head);

    var body = el('div', 'iss-editorial-sets-modal__body');
    if (item.preview && item.preview.url) {
      var link = el('a', 'iss-editorial-sets-modal__media');
      link.href = item.preview.url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      if (item.preview.thumbnail) {
        var image = el('img');
        image.src = item.preview.thumbnail;
        image.alt = item.label || item.preview.title || '';
        link.appendChild(image);
      } else {
        link.textContent = 'Datei öffnen / herunterladen';
      }
      body.appendChild(link);
    }
    var facts = el('dl', 'iss-editorial-sets-facts');
    [
      [t('status', 'Status'), statusLabel(item.status)],
      [t('source', 'Source'), item.source + ':' + item.sourceId],
      [t('origin', 'Origin'), item.origin],
      [t('storageState', 'Storage'), storageLabel(item.storageState)],
      [t('uploaded', 'Uploaded'), item.preview && item.preview.uploadedAt],
      [t('filename', 'Filename'), item.preview && item.preview.filename],
      [t('mime', 'MIME'), item.preview && item.preview.mime],
      [t('decay', 'Decay'), item.decayAt || '']
    ].forEach(function (row) {
      facts.appendChild(el('dt', '', row[0]));
      facts.appendChild(el('dd', '', row[1] || '-'));
    });
    body.appendChild(facts);
    var form = el('div', 'iss-editorial-sets-modal__form');
    var labelWrap = el('label', 'iss-editorial-sets-field');
    labelWrap.appendChild(el('span', '', t('label', 'Label')));
    var labelInput = el('input', 'iss-editorial-sets-modal__label');
    labelInput.type = 'text';
    labelInput.value = item.label || '';
    labelWrap.appendChild(labelInput);
    form.appendChild(labelWrap);

    var statusWrap = el('label', 'iss-editorial-sets-field');
    statusWrap.appendChild(el('span', '', t('status', 'Status')));
    var statusSelect = el('select', 'iss-editorial-sets-modal__status');
    (config.statuses || []).filter(function (name) { return name !== 'promoted' || item.status === 'promoted'; }).forEach(function (statusName) {
      var option = el('option', '', statusLabel(statusName));
      option.value = statusName;
      option.selected = statusName === item.status;
      statusSelect.appendChild(option);
    });
    statusWrap.appendChild(statusSelect);
    form.appendChild(statusWrap);

    var notesWrap = el('label', 'iss-editorial-sets-field');
    notesWrap.appendChild(el('span', '', t('notes', 'Notes')));
    var notesInput = el('textarea', 'iss-editorial-sets-modal__notes');
    notesInput.rows = 4;
    notesInput.value = item.notes || '';
    notesWrap.appendChild(notesInput);
    form.appendChild(notesWrap);

    [['credit', 'Bildnachweis / Urheber', (item.rights || {}).attribution || ''], ['source-note', 'Herkunft / erhalten von', (item.provenance || {}).source_note || '']].forEach(function (field) {
      var wrap = el('label', 'iss-editorial-sets-field'); wrap.appendChild(el('span', '', field[1]));
      var input = el('input', 'iss-editorial-sets-modal__' + field[0]); input.value = field[2]; wrap.appendChild(input); form.appendChild(wrap);
    });
    var licenseWrap = el('label', 'iss-editorial-sets-field'); licenseWrap.appendChild(el('span', '', 'Nutzungsrechte'));
    var license = el('select', 'iss-editorial-sets-modal__license');
    var currentLicense = (item.rights || {}).license || '';
    var licenses = ['', 'all-rights-reserved', 'cc-by-4.0', 'cc-by-sa-4.0', 'cc-by-nc-4.0', 'cc0-1.0'];
    if (currentLicense && licenses.indexOf(currentLicense) < 0) { licenses.push(currentLicense); }
    licenses.forEach(function (value) { var option = el('option', '', value === '' ? 'Bitte klären' : value === 'all-rights-reserved' ? 'Alle Rechte vorbehalten / individuelle Erlaubnis' : value.toUpperCase()); option.value = value; option.selected = value === currentLicense; license.appendChild(option); });
    licenseWrap.appendChild(license); form.appendChild(licenseWrap);
    var consentWrap = el('label', 'iss-editorial-sets-field');
    var consent = el('input', 'iss-editorial-sets-modal__consent'); consent.type = 'checkbox'; consent.checked = String((item.rights || {}).consent || '') === '1';
    consentWrap.appendChild(consent); consentWrap.appendChild(el('span', '', 'Veröffentlichung ist erlaubt; Rechte und Einwilligungen sind geklärt.')); form.appendChild(consentWrap);
    (item.uses || []).forEach(function (use) { var link = el('a', '', 'Bereits verwendet: ' + use.title); link.href = use.editUrl; form.appendChild(link); });

    var save = el('button', 'button button-primary', t('saveItem', 'Save item'));
    save.type = 'button';
    save.disabled = state.busy;
    save.addEventListener('click', saveModalItem);
    form.appendChild(save);
    body.appendChild(form);
    dialog.appendChild(body);
    overlay.appendChild(dialog);
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        state.modalItem = null;
        render();
      }
    });
    return overlay;
  }

  function render() {
    var hadModal = !!modalNode;
    if (releaseModalFocus) { releaseModalFocus(); releaseModalFocus = null; }
    if (modalNode) { modalNode.remove(); modalNode = null; }
    clear(root);
    root.appendChild(renderToolbar());
    root.appendChild(renderFilters());
    root.appendChild(renderDestination());
    if (state.notice) {
      root.appendChild(el('div', 'notice notice-info inline iss-editorial-sets-notice', state.notice));
    }
    if (state.busy) {
      root.appendChild(el('p', 'description', t('loading', 'Loading...')));
    }
    if (state.editUrl) { var editorLink = el('a', 'button button-primary', 'Entwurf im Editor öffnen'); editorLink.href = state.editUrl; root.appendChild(editorLink); }
    root.appendChild(renderGrid());
    root.appendChild(pager(state.itemsPage, Math.ceil(state.itemsTotal / 60), function (page) { state.itemsPage = page; loadItems(); }));
    var modal = renderModal();
    var opener = root.querySelector('[data-item-id="' + lastPreviewId + '"]');
    if ((modal || hadModal) && opener) { opener.focus(); }
    if (modal) {
      modalNode = modal; document.body.appendChild(modal);
      if (window.issEditorialUi) {
        releaseModalFocus = window.issEditorialUi.manageModalFocus(modal.querySelector('[role="dialog"]'), function () { state.modalItem = null; render(); });
      }
    }
  }

  render();
  loadTargets(true).then(function () { return loadSets(); }).then(function () {
    if (config.upload && !state.uploadOpened && state.selectedSetId) {
      state.uploadOpened = true;
      uploadRawFiles();
    }
  });
}());
