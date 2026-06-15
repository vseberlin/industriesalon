(function () {
  const editor = window.issRelationsEditor;
  if (!editor) return;

  const el = editor.components.el;
  const Button = editor.components.Button;
  const SelectControl = editor.components.SelectControl;
  const TextControl = editor.components.TextControl;
  const PLACE_POST_TYPE = editor.PLACE_POST_TYPE;

  function parsePlaceIds(value) {
    if (typeof value !== 'string') return [];

    const seen = {};
    return value
      .split(/[\s,]+/)
      .map(function (part) {
        return parseInt(part, 10);
      })
      .filter(function (id) {
        if (!id || seen[id]) return false;
        seen[id] = true;
        return true;
      });
  }

  function getPlaceLabel(post) {
    const title = editor.stripTags(post && post.title && post.title.rendered ? post.title.rendered : '');
    return title !== '' ? title + ' (#' + post.id + ')' : '#' + post.id;
  }

  function stringifyPlaceIds(ids) {
    return ids.join(',');
  }

  function buildPlaceOptions(placePosts, selectedIds) {
    var seen = {};
    var options = [];

    (Array.isArray(placePosts) ? placePosts : []).forEach(function (post) {
      var value = String(post.id || '');
      if (!value || seen[value]) return;

      seen[value] = true;
      options.push({
        label: getPlaceLabel(post),
        value: value,
      });
    });

    selectedIds.forEach(function (id) {
      var value = String(id);
      if (!value || seen[value]) return;

      seen[value] = true;
      options.push({
        label: '#' + value + ' (nicht gefunden)',
        value: value,
      });
    });

    return options;
  }

  function getAddPlaceOptions(placeOptions, selectedIds) {
    var selectedLookup = {};

    selectedIds.forEach(function (id) {
      selectedLookup[String(id)] = true;
    });

    return [{ label: 'Ort auswählen', value: '' }].concat(
      placeOptions.filter(function (option) {
        return !selectedLookup[String(option.value || '')];
      })
    );
  }

  function getRowPlaceOptions(placeOptions, selectedIds, currentId) {
    var selectedLookup = {};

    selectedIds.forEach(function (id) {
      if (id !== currentId) {
        selectedLookup[String(id)] = true;
      }
    });

    return placeOptions.filter(function (option) {
      var value = String(option.value || '');
      return value === String(currentId) || !selectedLookup[value];
    });
  }

  function updateSelectedPlaceIds(setAttributes, selectedIds) {
    setAttributes({
      source: 'manual',
      placeIds: stringifyPlaceIds(selectedIds),
    });
  }

  function renderManualPlaceRow(placeOptions, selectedIds, setAttributes, placeId, index) {
    if (!SelectControl) {
      return null;
    }

    return el(
      'div',
      { key: String(placeId) + '-' + index },
      el(SelectControl, {
        label: 'Ort ' + String(index + 1),
        value: String(placeId),
        options: getRowPlaceOptions(placeOptions, selectedIds, placeId),
        onChange: function (value) {
          var nextId = parseInt(value, 10);
          if (!nextId) return;

          var nextIds = selectedIds.slice();
          nextIds[index] = nextId;
          nextIds = nextIds.filter(function (id, position) {
            return nextIds.indexOf(id) === position;
          });
          updateSelectedPlaceIds(setAttributes, nextIds);
        },
      }),
      Button
        ? el(
            'div',
            null,
            el(
              Button,
              {
                variant: 'secondary',
                disabled: index === 0,
                onClick: function () {
                  if (index === 0) return;

                  var nextIds = selectedIds.slice();
                  var current = nextIds[index - 1];
                  nextIds[index - 1] = nextIds[index];
                  nextIds[index] = current;
                  updateSelectedPlaceIds(setAttributes, nextIds);
                },
              },
              'Weiter nach oben'
            ),
            ' ',
            el(
              Button,
              {
                variant: 'secondary',
                disabled: index >= selectedIds.length - 1,
                onClick: function () {
                  if (index >= selectedIds.length - 1) return;

                  var nextIds = selectedIds.slice();
                  var current = nextIds[index + 1];
                  nextIds[index + 1] = nextIds[index];
                  nextIds[index] = current;
                  updateSelectedPlaceIds(setAttributes, nextIds);
                },
              },
              'Weiter nach unten'
            ),
            ' ',
            el(
              Button,
              {
                variant: 'tertiary',
                onClick: function () {
                  updateSelectedPlaceIds(
                    setAttributes,
                    selectedIds.filter(function (_id, position) {
                      return position !== index;
                    })
                  );
                },
              },
              'Entfernen'
            )
          )
        : null
    );
  }

  function renderManualPlaceControl(attrs, setAttributes, placeOptions, selectedIds, config) {
    if (String(editor.getRelatedBlockSource(attrs, config || {})) !== 'manual') return null;

    if (!SelectControl) {
      return TextControl
        ? el(TextControl, {
            label: 'Orts-IDs',
            help: 'Kommagetrennte register_place IDs, z.B. 12921,12865',
            value: attrs.placeIds || '',
            onChange: function (value) {
              setAttributes({ source: 'manual', placeIds: value });
            },
          })
        : null;
    }

    var addOptions = getAddPlaceOptions(placeOptions, selectedIds);
    var addControlOptions = [];

    if (!placeOptions.length) {
      addControlOptions = [{ label: 'Keine veröffentlichten Orte verfügbar', value: '' }];
    } else if (addOptions.length > 1) {
      addControlOptions = addOptions;
    } else {
      addControlOptions = [{ label: 'Alle veröffentlichten Orte sind bereits ausgewählt', value: '' }];
    }

    return el(
      'div',
      null,
      el(
        'p',
        { className: 'components-base-control__help' },
        'Wählt veröffentlichte Orte aus. Die Reihenfolge bleibt erhalten und steuert beim Kartenblock Marker und Liste.'
      ),
      selectedIds.map(function (placeId, index) {
        return renderManualPlaceRow(placeOptions, selectedIds, setAttributes, placeId, index);
      }),
      el(SelectControl, {
        label: 'Ort hinzufügen',
        value: '',
        options: addControlOptions,
        disabled: addOptions.length <= 1,
        onChange: function (value) {
          var nextId = parseInt(value, 10);
          if (!nextId) return;

          updateSelectedPlaceIds(setAttributes, selectedIds.concat([nextId]));
        },
      }),
      !selectedIds.length
        ? el(
            'p',
            { className: 'components-base-control__help' },
            'Noch keine Orte ausgewählt.'
          )
        : null
    );
  }

  window.issRelationsEditor.sourceControls = {
    PLACE_POST_TYPE: PLACE_POST_TYPE,
    parsePlaceIds: parsePlaceIds,
    buildPlaceOptions: buildPlaceOptions,
    renderManualPlaceControl: renderManualPlaceControl,
  };
})();
