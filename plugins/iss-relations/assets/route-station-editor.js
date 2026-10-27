(function () {
  var FIELD_KEYS = [
    'place_id',
    'role',
    'weight',
    'label',
    'route_title',
    'route_teaser',
    'station_object_id',
    'station_story_id'
  ];

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
      station_story_id: parseInt(relation.station_story_id || '0', 10) || 0,
      is_draft: !!relation.is_draft
    };
  }

  function normalizeRelations(relations) {
    return Array.isArray(relations) ? relations.map(normalizeRouteRelation) : [];
  }

  function cleanPayloadRelation(relation) {
    var clean = {};
    FIELD_KEYS.forEach(function (key) {
      clean[key] = relation[key];
    });

    return clean;
  }

  function createTextInput(label, value, onChange) {
    var field = createElement('label', 'iss-editorial-field');
    var input = document.createElement('input');
    input.type = 'text';
    input.value = value || '';
    input.addEventListener('input', function () {
      onChange(input.value);
    });
    field.appendChild(createElement('span', '', label));
    field.appendChild(input);

    return field;
  }

  function createTextarea(label, value, onChange, rows) {
    var field = createElement('label', 'iss-editorial-field');
    var textarea = document.createElement('textarea');
    textarea.rows = rows || 4;
    textarea.value = value || '';
    textarea.addEventListener('input', function () {
      onChange(textarea.value);
    });
    field.appendChild(createElement('span', '', label));
    field.appendChild(textarea);

    return field;
  }

  function appendQueryArgs(url, args) {
    var next = new URL(url, window.location.origin);
    Object.keys(args || {}).forEach(function (key) {
      next.searchParams.set(key, String(args[key]));
    });

    return next.toString();
  }

  function createEditor(options) {
    var config = options && options.config ? options.config : {};
    var postId = parseInt((options && options.postId) || config.postId || '0', 10) || 0;
    var fields = options ? options.fields : null;
    var setStatus = options && typeof options.setStatus === 'function' ? options.setStatus : function () {};
    var locked = config.locked !== false;
    var canonical = normalizeRelations(config.canonical || config.relations);
    var relations = normalizeRelations(config.relations);
    var trash = normalizeRelations(config.trash);
    var baseHash = (config.draft && config.draft.base_hash) || config.canonicalHash || '';
    var draftToken = config.draftToken || '';
    var changeVersion = 0;
    var uncertain = null;
    var previewArgs = config.previewArgs || {};
    var dirty = false;
    var saving = null;
    var currentTarget = null;
    var failure = createElement('div', 'iss-relations-route-failure'); failure.setAttribute('role', 'alert'); failure.hidden = true;
    var failureText = createElement('p'); failure.appendChild(failureText);
    function fail(error) {
      failure.hidden = false; failureText.textContent = 'Route: ' + (error.message || String(error));
      setStatus(failureText.textContent);
      if (options.onFailure) { options.onFailure(error); }
    }
    function recoveryCopy() { return {relations: relations, trash: trash, baseHash:baseHash, draftToken:draftToken}; }
    var exportButton = createElement('button', 'button', 'Routenänderungen herunterladen'); exportButton.type = 'button';
    exportButton.addEventListener('click', function () {
      var url = window.URL.createObjectURL(new window.Blob([JSON.stringify(recoveryCopy(), null, 2)], {type:'application/json'}));
      var link = createElement('a'); link.href = url; link.download = 'route-' + postId + '-wiederherstellung.json'; link.click();
      window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 1000);
    }); failure.appendChild(exportButton);
    var compare = createElement('button', 'button', 'Routenfassungen vergleichen'); compare.type = 'button';
    compare.addEventListener('click', function () {
      window.issAdminRequest(routeDraftUrl(), {credentials:'same-origin',headers:{'X-WP-Nonce':config.nonce}}).then(function (body) {
        var panel = createElement('div');
        [['Meine Stationen', recoveryCopy()], ['Server', {canonical:body.canonical,relations:body.relations,trash:body.trash}]].forEach(function (entry) {
          panel.appendChild(createElement('p', '', entry[0]));
          var text = createElement('textarea', 'widefat'); text.readOnly = true; text.rows = 8; text.value = JSON.stringify(entry[1], null, 2); text.setAttribute('aria-label',entry[0]); panel.appendChild(text);
        });
        panel.appendChild(createElement('p', '', 'Wählen Sie bewusst: Ihre Stationen ersetzen beim nächsten Sichern den Server-Entwurf. Veröffentlicht wird nur über die eigene Routenaktion.'));
        [['Meine Stationen weiterbearbeiten',false],['Server-Entwurf übernehmen',true]].forEach(function (choice) {
          var button = createElement('button','button',choice[0]); button.type='button'; button.addEventListener('click',function () {
            baseHash=body.canonicalHash; draftToken=body.draftToken; uncertain=null;
            if (choice[1]) {applyServerPayload(body); baseHash=body.canonicalHash;} else {locked=false; markDirty();}
            panel.remove(); failure.hidden=true; rerender();
          }); panel.appendChild(button);
        });
        var cancel=createElement('button','button','Abbrechen'); cancel.type='button';cancel.addEventListener('click',function(){panel.remove();});panel.appendChild(cancel);
        failure.appendChild(panel);
      }).catch(fail);
    }); failure.appendChild(compare);

    function stringValue(key, fallback) {
      return (config.strings && config.strings[key]) || fallback;
    }

    function routePlaces() {
      return Array.isArray(config.places) ? config.places : [];
    }

    function stationRows(source) {
      return normalizeRelations(source || relations).filter(function (relation) {
        return relation.role === 'stop';
      }).sort(function (left, right) {
        if (left.weight === right.weight) {
          return left.place_id - right.place_id;
        }

        return left.weight - right.weight;
      });
    }

    function nonStationRows() {
      return relations.filter(function (relation) {
        return relation.role !== 'stop';
      });
    }

    function normalizedStations(stations) {
      return stations.map(function (station, index) {
        var normalized = normalizeRouteRelation(station);
        normalized.role = 'stop';
        normalized.weight = index + 1;

        return normalized;
      });
    }

    function buildRelationsPayload() {
      var stations = normalizedStations(stationRows()).filter(function (station) {
        return station.place_id > 0;
      });
      var stationPlaceIds = {};

      stations.forEach(function (station) {
        stationPlaceIds[String(station.place_id)] = true;
      });

      return stations.concat(nonStationRows().map(normalizeRouteRelation).filter(function (relation) {
        return !stationPlaceIds[String(relation.place_id || 0)];
      })).map(cleanPayloadRelation);
    }

    function trashPayload() {
      return stationRows(trash).map(cleanPayloadRelation);
    }

    function syncHiddenFields() {
      if (!fields) {
        return;
      }

      clear(fields);
    }

    function replaceStations(stations) {
      relations = nonStationRows().concat(normalizedStations(stations));
      syncHiddenFields();
    }

    function markDirty() {
      dirty = true; changeVersion++;
      syncHiddenFields();
      setStatus(stringValue('changed', 'Routen-Entwurf geändert.'));
    }

    function routeDraftUrl() {
      var restRoot = String(config.restRoot || '').replace(/\/$/, '');
      if (!restRoot || !postId || !config.nonce) {
        return '';
      }

      return restRoot + '/posts/' + String(postId) + '/route-draft';
    }

    function applyServerPayload(body) {
      locked = body.locked !== false;
      canonical = normalizeRelations(body.canonical || []);
      relations = normalizeRelations(body.relations || canonical);
      trash = normalizeRelations(body.trash || []);
      previewArgs = body.previewArgs || previewArgs || {};
      baseHash = body.draft && body.draft.base_hash || body.canonicalHash || baseHash;
      draftToken = body.draftToken || '';
      dirty = false;
      syncHiddenFields();
    }

    function requestDraft(action, extraPayload) {
      var url = routeDraftUrl();
      var payload = Object.assign({ action: action, baseHash:baseHash, draftToken:draftToken }, extraPayload || {});
      var version = changeVersion;
      if (!url) { var missing = new Error('Die Routenkonfiguration fehlt. Bitte Änderungen herunterladen und die Seite neu öffnen.'); fail(missing); return Promise.reject(missing); }
      function send(value) {
        return window.issAdminRequest(url, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce},body:JSON.stringify(value)}).catch(function (error) {
          if (['network_error','request_timeout','invalid_response'].indexOf(error.code)!==-1) { uncertain=value; }
          throw error;
        });
      }
      return (uncertain ? send(uncertain).then(function (body) { draftToken=body.draftToken || ''; baseHash=body.canonicalHash; uncertain=null; }) : Promise.resolve()).then(function () {
        payload.baseHash=baseHash; payload.draftToken=draftToken;
        return send(payload);
      }).then(function (body) {
        uncertain=null;
        if (version === changeVersion) { applyServerPayload(body); }
        else { draftToken=body.draftToken || ''; baseHash=body.canonicalHash; previewArgs=body.previewArgs || {}; }
        failure.hidden=true; if (options.onRecovered) { options.onRecovered(); }
        return body;
      }).catch(function (error) { fail(error); throw error; });
    }

    function unlockRoute() {
      setStatus(stringValue('saving', 'Routen-Entwurf wird gespeichert ...'));
      return requestDraft('unlock').then(function () {
        setStatus(stringValue('unlocked', 'Routen-Entwurf aktiv.'));
        rerender();
      });
    }

    function saveDraftIfDirty(force) {
      if (locked) {
        return Promise.resolve({ previewArgs: {} });
      }
      if (!dirty && !force) {
        return Promise.resolve({ previewArgs: previewArgs || {} });
      }
      if (saving) { return saving; }
      if (stationRows().some(function (station) { return !station.place_id; })) {
        var incomplete=new Error('Bitte für jede neue Station einen Ort wählen oder die unvollständige Station entfernen.'); fail(incomplete); return Promise.reject(incomplete);
      }

      setStatus(stringValue('saving', 'Routen-Entwurf wird gespeichert ...'));
      saving = requestDraft('save', {
        relations: buildRelationsPayload(),
        trash: trashPayload(),
        baseHash: baseHash
      }).then(function (body) {
        setStatus(dirty ? 'Weitere Routenänderungen sind noch nicht gesichert.' : stringValue('saved', 'Routen-Entwurf gespeichert.'));
        return {
          previewArgs: body.previewArgs || previewArgs || {}
        };
      }).finally(function () {
        saving = null;
      }).then(function (body) { return dirty ? saveDraftIfDirty() : body; });

      return saving;
    }

    function publishRoute() {
      var message = stringValue('publishConfirm', 'Route veröffentlichen und gelöschte Stationen aus der öffentlichen Route entfernen?');
      if (trash.length && !window.confirm(message)) {
        return;
      }

      saveDraftIfDirty(true).then(function () {
        setStatus(stringValue('saving', 'Routen-Entwurf wird gespeichert ...'));
        if (dirty) { throw new Error('Während der Speicherung wurden Stationen geändert. Bitte erneut speichern und veröffentlichen.'); }
        return requestDraft('publish');
      }).then(function () {
        setStatus(dirty ? 'Route veröffentlicht. Weitere lokale Änderungen sind noch nicht gesichert.' : stringValue('published', 'Route veröffentlicht, gesperrt und mit der Karte synchronisiert.'));
        rerender();
      }).catch(function (error) {
        fail(error);
      });
    }

    function discardDraft() {
      requestDraft('discard').then(function () {
        setStatus(stringValue('discarded', 'Routen-Entwurf verworfen.'));
        rerender();
      }).catch(function (error) {
        fail(error);
      });
    }

    function relationChoiceUrl(kind, station) {
      var url;
      var selectedId = kind === 'object' ? station.station_object_id : station.station_story_id;
      if (!config.ajaxUrl || !config.choiceNonce || !station.place_id) {
        return null;
      }

      url = new URL(config.ajaxUrl, window.location.origin);
      url.searchParams.set('action', kind === 'object' ? 'iss_relations_station_objects' : 'iss_relations_station_stories');
      url.searchParams.set('nonce', config.choiceNonce);
      url.searchParams.set('place_id', String(station.place_id));
      if (selectedId) {
        url.searchParams.set('selected_id', String(selectedId));
      }

      return url;
    }

    function setSelectOptions(select, items, selectedId, emptyLabel) {
      var fragment = document.createDocumentFragment();
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = emptyLabel;
      fragment.appendChild(placeholder);

      items.forEach(function (item) {
        var option = document.createElement('option');
        option.value = String(item.id || '');
        option.textContent = item.title || '';
        option.selected = String(item.id || '') === String(selectedId || '');
        fragment.appendChild(option);
      });

      select.innerHTML = '';
      select.appendChild(fragment);
    }

    function fetchStationChoices(kind, station) {
      var url = relationChoiceUrl(kind, station);
      var dataKey = kind === 'object' ? 'objects' : 'stories';

      if (!url) {
        return Promise.reject(new Error('Die Auswahl konnte nicht geladen werden: Die Routenkonfiguration fehlt.'));
      }

      return window.issAdminRequest(url.toString(), { credentials:'same-origin' }).then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error('invalid choice payload');
        }

        return Array.isArray(payload.data[dataKey]) ? payload.data[dataKey] : [];
      });
    }

    function createPlaceSelect(station, onChange) {
      var field = createElement('label', 'iss-editorial-field');
      var select = document.createElement('select');
      var currentPlaceId = station.place_id || 0;
      var hasCurrent = false;

      select.className = 'widefat';
      select.appendChild(new Option(stringValue('placePlaceholder', 'Ort wählen'), ''));
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
        station.is_draft = station.place_id <= 0;
        onChange(station, true);
        rerender();
      });

      field.appendChild(createElement('span', '', 'Ort'));
      field.appendChild(select);

      return field;
    }

    function createChoiceSelect(kind, station, onChange) {
      var field = createElement('label', 'iss-editorial-field');
      var select = document.createElement('select');
      var key = kind === 'object' ? 'station_object_id' : 'station_story_id';
      var selectedId = station[key] || 0;
      var placeholder = kind === 'object'
        ? stringValue('objectPlaceholder', 'Objekt wählen')
        : stringValue('storyPlaceholder', 'Beitrag wählen');
      var loading = kind === 'object'
        ? stringValue('objectLoading', 'Objekte werden geladen ...')
        : stringValue('storyLoading', 'Beiträge werden geladen ...');
      var none = kind === 'object'
        ? stringValue('objectNone', 'Keine verknüpften Objekte für diesen Ort')
        : stringValue('storyNone', 'Keine verknüpften Beiträge für diesen Ort');
      var error = kind === 'object'
        ? stringValue('objectError', 'Objekte konnten nicht geladen werden')
        : stringValue('storyError', 'Beiträge konnten nicht geladen werden');

      select.className = 'widefat';
      setSelectOptions(select, [], selectedId, station.place_id ? loading : placeholder);
      select.addEventListener('change', function () {
        station[key] = parseInt(select.value || '0', 10) || 0;
        onChange(station, false);
      });

      if (station.place_id) {
        fetchStationChoices(kind, station).then(function (items) {
          if (!select.isConnected) { return; }
          setSelectOptions(select, items, selectedId, items.length ? placeholder : none);
        }).catch(function (cause) {
          if (!select.isConnected) { return; }
          fail(cause);
          setSelectOptions(select, [], selectedId, error);
        });
      }

      field.appendChild(createElement('span', '', kind === 'object' ? 'Objekt' : 'Beitrag'));
      field.appendChild(select);

      return field;
    }

    function createSourceFields(station, onChange) {
      var details = document.createElement('details');
      var summary = document.createElement('summary');

      details.className = 'iss-relations-route-source';
      details.open = !!(station.station_object_id || station.station_story_id);
      summary.textContent = stringValue('sourceHeading', 'Optionale Quellen');

      details.appendChild(summary);
      details.appendChild(createElement(
        'p',
        'description',
        stringValue(
          'sourceDescription',
          'Objekt und Beitrag sind Quellen fuer spaetere Ausgaben. Die aktuelle Route und Atlas-Karte nutzen diese Felder nicht.'
        )
      ));
      details.appendChild(createChoiceSelect('object', station, onChange));
      details.appendChild(createChoiceSelect('story', station, onChange));

      return details;
    }

    function renderStationSummary(station, index, target) {
      var row = createElement('article', 'iss-relations-route-row iss-relations-route-row--locked');
      var title = station.route_title || station.label || ('Ort #' + String(station.place_id || ''));
      row.appendChild(createElement('div', 'iss-relations-route-row__position', String(index + 1)));
      row.appendChild(createElement('div', 'iss-relations-route-row__summary', title));
      target.appendChild(row);
    }

    function renderEditableStation(stations, station, index, target) {
      var row = createElement('article', 'iss-relations-route-row');
      var position = createElement('div', 'iss-relations-route-row__position', String(index + 1));
      var fieldsWrap = createElement('div', 'iss-relations-route-row__fields');
      var tools = createElement('div', 'iss-relations-route-row__tools');
      var up = createElement('button', 'button', 'Hoch');
      var down = createElement('button', 'button', 'Runter');
      var remove = createElement('button', 'button button-link-delete', 'Entfernen');
      var updateStation = function (updatedStation, shouldRerender) {
        stations[index] = normalizeRouteRelation(updatedStation);
        replaceStations(stations);
        markDirty();
        if (shouldRerender) {
          rerender();
        }
      };

      fieldsWrap.appendChild(createPlaceSelect(station, updateStation));
      fieldsWrap.appendChild(createTextInput('Stations-Titel', station.route_title || '', function (value) {
        station.route_title = value;
        updateStation(station, false);
      }));
      fieldsWrap.appendChild(createTextarea('Stations-Teaser', station.route_teaser || '', function (value) {
        station.route_teaser = value;
        updateStation(station, false);
      }, 3));
      fieldsWrap.appendChild(createSourceFields(station, updateStation));

      [up, down, remove].forEach(function (button) {
        button.type = 'button';
      });
      up.disabled = index === 0;
      down.disabled = index >= stations.length - 1;
      up.addEventListener('click', function () {
        var current = stations.splice(index, 1)[0];
        stations.splice(index - 1, 0, current);
        replaceStations(stations);
        markDirty();
        rerender();
      });
      down.addEventListener('click', function () {
        var current = stations.splice(index, 1)[0];
        stations.splice(index + 1, 0, current);
        replaceStations(stations);
        markDirty();
        rerender();
      });
      remove.addEventListener('click', function () {
        trash = [normalizeRouteRelation(station)].concat(trash);
        stations.splice(index, 1);
        replaceStations(stations);
        markDirty();
        rerender();
      });

      tools.appendChild(up);
      tools.appendChild(down);
      tools.appendChild(remove);
      row.appendChild(position);
      row.appendChild(fieldsWrap);
      row.appendChild(tools);
      target.appendChild(row);
    }

    function renderTrash(panel) {
      var trashRows = stationRows(trash);
      var box;
      var head;

      if (!trashRows.length) {
        return;
      }

      box = createElement('div', 'iss-relations-route-trash');
      head = createElement('div', 'iss-relations-route-trash__head');
      head.appendChild(createElement('h3', '', stringValue('trashHeading', 'Gelöschte Stationen im Entwurf')));
      head.appendChild(createElement('p', 'description', stringValue('trashDescription', 'Diese Stationen bleiben wiederherstellbar, solange der Entwurf nicht veröffentlicht wird.')));
      box.appendChild(head);

      trashRows.forEach(function (station, index) {
        var row = createElement('div', 'iss-relations-route-trash__row');
        var restore = createElement('button', 'button', stringValue('restoreStation', 'Wiederherstellen'));
        restore.type = 'button';
        restore.addEventListener('click', function () {
          var stations = stationRows();
          stations.push(station);
          trash.splice(index, 1);
          replaceStations(stations);
          markDirty();
          rerender();
        });
        row.appendChild(createElement('span', '', station.route_title || station.label || ('Ort #' + String(station.place_id || ''))));
        row.appendChild(restore);
        box.appendChild(row);
      });

      panel.appendChild(box);
    }

    function renderActions(panel) {
      var actions = createElement('div', 'iss-relations-route-actions');
      var save = createElement('button', 'button button-secondary', stringValue('saveDraft', 'Entwurf speichern'));
      var publish = createElement('button', 'button button-primary', stringValue('publish', 'Route veröffentlichen & sperren'));
      var discard = createElement('button', 'button button-link-delete', stringValue('discard', 'Entwurf verwerfen'));

      save.type = 'button';
      publish.type = 'button';
      discard.type = 'button';
      save.addEventListener('click', function () {
        saveDraftIfDirty(true).catch(function (error) {
          fail(error);
        });
      });
      publish.addEventListener('click', publishRoute);
      discard.addEventListener('click', discardDraft);

      actions.appendChild(save);
      actions.appendChild(publish);
      actions.appendChild(discard);
      panel.appendChild(actions);
    }

    function render(target) {
      var panel = createElement('section', 'iss-relations-route-panel' + (locked ? ' is-locked' : ' is-draft'));
      var head = createElement('div', 'iss-relations-route-panel__head');
      var rows = createElement('div', 'iss-relations-route-rows');
      var add = createElement('button', 'button', stringValue('addStation', 'Station hinzufügen'));
      var stations = stationRows();

      currentTarget = target;
      clear(target);
      head.appendChild(createElement('div', 'iss-editorial-stage__title', stringValue('heading', 'Route / Stationen')));
      head.appendChild(createElement('p', 'iss-relations-route-state', locked ? stringValue('lockedTitle', 'Route veröffentlicht und gesperrt') : stringValue('draftTitle', 'Routen-Entwurf aktiv')));
      head.appendChild(createElement('p', 'description', locked ? stringValue('lockedDescription', '') : stringValue('draftDescription', '')));
      panel.appendChild(head);

      if (!stations.length) {
        rows.appendChild(createElement('p', 'iss-editorial-empty', stringValue('empty', 'Noch keine Stationen. Eine Station verbindet die Führung mit einem Ort.')));
      }

      stations.forEach(function (station, index) {
        if (locked) {
          renderStationSummary(station, index, rows);
        } else {
          renderEditableStation(stations, station, index, rows);
        }
      });

      panel.appendChild(rows);

      if (locked) {
        var unlock = createElement('button', 'button button-secondary', stringValue('unlock', 'Route entsperren'));
        unlock.type = 'button';
        unlock.addEventListener('click', function () {
          unlockRoute().catch(function (error) {
            fail(error);
          });
        });
        panel.appendChild(unlock);
      } else {
        add.type = 'button';
        add.addEventListener('click', function () {
          stations.push(normalizeRouteRelation({
            role: 'stop',
            weight: stations.length + 1,
            is_draft: true
          }));
          replaceStations(stations);
          markDirty();
          rerender();
        });
        panel.appendChild(add);
        renderTrash(panel);
        renderActions(panel);
      }

      target.appendChild(failure);
      target.appendChild(panel);
      syncHiddenFields();
    }

    function rerender() {
      if (currentTarget) {
        render(currentTarget);
      }
    }

    window.addEventListener('beforeunload', function (event) { if (dirty || uncertain) { event.preventDefault(); event.returnValue=''; } });
    syncHiddenFields();

    return {
      render: render,
      isDirty: function () { return dirty || !!uncertain; },
      recoveryCopy: recoveryCopy,
      restoreCopy: function (copy) {
        if (!copy || !Array.isArray(copy.relations) || !Array.isArray(copy.trash)) { throw new Error('Die Routen-Sicherung ist unvollständig. Bitte die Datei prüfen.'); }
        relations=normalizeRelations(copy.relations); trash=normalizeRelations(copy.trash); locked=false; markDirty(); rerender();
      },
      refreshNonces: function (nonces) { config.nonce=nonces.rest; config.choiceNonce=nonces.choices; },
      saveIfDirty: saveDraftIfDirty,
      syncHiddenFields: syncHiddenFields,
      getPreviewArgs: function () {
        return locked ? {} : (previewArgs || {});
      },
      appendPreviewArgs: function (url) {
        return locked ? url : appendQueryArgs(url, previewArgs || {});
      }
    };
  }

  window.issRelationsRouteStations = {
    create: createEditor
  };
}());
