(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const useEffect = window.wp.element.useEffect;
  const useState = window.wp.element.useState;
  const select = window.wp.data && window.wp.data.select;
  const useSelect = window.wp.data && window.wp.data.useSelect;
  const InspectorControls = window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components.PanelBody;
  const Placeholder = window.wp.components.Placeholder;
  const SelectControl = window.wp.components.SelectControl;
  const TextControl = window.wp.components.TextControl;
  const Button = window.wp.components.Button;
  const Notice = window.wp.components.Notice;
  const ServerSideRender = window.wp.serverSideRender;
  const apiFetch = window.wp.apiFetch;
  const setSelector = window.issArchiveSetSelector || {};

  function intValue(value) {
    const parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function currentEditorPostId() {
    if (!select) {
      return 0;
    }

    const editor = select('core/editor');
    if (!editor || !editor.getCurrentPostId) {
      return 0;
    }

    return intValue(editor.getCurrentPostId());
  }

  function setPath(setId, contextPostId) {
    let path = '/iss-archive/v1/sets/' + String(setId);
    if (contextPostId > 0) {
      path += '?contextPostId=' + encodeURIComponent(String(contextPostId));
    }

    return path;
  }

  function cleanLabel(value, fallback) {
    if (setSelector.cleanLabel) {
      return setSelector.cleanLabel(value, fallback);
    }

    const text = String(value || '').replace(/\s+/g, ' ').trim();
    return text || fallback || '';
  }

  function setOptionLabel(set) {
    if (setSelector.setOptionLabel) {
      return setSelector.setOptionLabel(set);
    }

    if (!set || !set.id) {
      return '';
    }

    const count = intValue(set.memberCount || 0);
    const title = cleanLabel(set.title, 'Archivset #' + String(set.id));
    return count > 0 ? title + ' (' + String(count) + ')' : title;
  }

  function memberOptionLabel(member) {
    if (!member || member.memberKind !== 'object') {
      return '';
    }

    const label = cleanLabel(member.pageLabel, '');
    const title = cleanLabel(member.title || member.objectTitle || member.memberTitle, 'Archivobjekt #' + String(member.objectPostId || member.id || ''));
    return label !== '' ? label + ' - ' + title : title;
  }

  function stopPreviewNavigation(event) {
    if (event.target && event.target.closest && event.target.closest('a')) {
      event.preventDefault();
    }
  }

  window.wp.blocks.registerBlockType('iss-wf-import/archive-object', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};
      const selectedSetId = intValue(attrs.setId || 0);
      const memberPosition = intValue(attrs.memberPosition || -1);
      const contextPostId = intValue(attrs.contextPostId || 0);
      const subscribedEditorPostId = useSelect
        ? useSelect(function (wpSelect) {
            const editor = wpSelect('core/editor');
            if (!editor || !editor.getCurrentPostId) {
              return 0;
            }

            return intValue(editor.getCurrentPostId());
          }, [])
        : 0;
      const editorPostId = subscribedEditorPostId > 0 ? subscribedEditorPostId : currentEditorPostId();
      const effectiveContextPostId = contextPostId > 0 ? contextPostId : editorPostId;
      const [sets, setSets] = useState([]);
      const [setsLoaded, setSetsLoaded] = useState(false);
      const [setMembers, setSetMembers] = useState([]);
      const [archiveSetError, setArchiveSetError] = useState('');
      const [archiveSetNotice, setArchiveSetNotice] = useState('');
      const [setSearch, setSetSearch] = useState('');
      const [setSearchResults, setSetSearchResults] = useState([]);
      const [setSearchBusy, setSetSearchBusy] = useState(false);
      const [refreshKey, setRefreshKey] = useState(0);
      const effectiveSetId = selectedSetId > 0
        ? selectedSetId
        : intValue((sets[0] && sets[0].id) || 0);

      useEffect(function () {
        if (!setSelector.getAttachedSets || effectiveContextPostId <= 0) {
          setSets([]);
          setSetsLoaded(false);
          return undefined;
        }

        let active = true;
        setSelector.getAttachedSets(effectiveContextPostId).then(function (response) {
          if (!active) {
            return;
          }

          const items = response && Array.isArray(response.items) ? response.items : [];
          setSets(items);
          setSetsLoaded(true);
          setArchiveSetError('');

          if (selectedSetId <= 0 && items.length === 1 && items[0] && items[0].id) {
            setAttributes({ setId: intValue(items[0].id) });
          }
        }).catch(function () {
          if (!active) {
            return;
          }

          setSets([]);
          setSetsLoaded(true);
          setArchiveSetError('Verbundene Archivsets konnten nicht geladen werden.');
        });

        return function () {
          active = false;
        };
      }, [effectiveContextPostId, refreshKey]);

      useEffect(function () {
        if (!effectiveContextPostId || !window.addEventListener) {
          return undefined;
        }

        const onAttachedSetsChanged = function (event) {
          const detail = event && event.detail ? event.detail : {};
          if (intValue(detail.postId || 0) !== effectiveContextPostId) {
            return;
          }

          setSets(Array.isArray(detail.sets) ? detail.sets : []);
          setSetsLoaded(true);
          setRefreshKey(function (value) {
            return value + 1;
          });
        };

        window.addEventListener('iss-archive-sets:attached-changed', onAttachedSetsChanged);
        return function () {
          window.removeEventListener('iss-archive-sets:attached-changed', onAttachedSetsChanged);
        };
      }, [effectiveContextPostId]);

      useEffect(function () {
        if (selectedSetId <= 0 || !setsLoaded || !Array.isArray(sets)) {
          return;
        }

        const selectedStillAttached = sets.some(function (set) {
          return intValue(set && set.id) === selectedSetId;
        });
        if (!selectedStillAttached) {
          setAttributes({
            setId: 0,
            memberPosition: -1,
          });
        }
      }, [selectedSetId, sets]);

      useEffect(function () {
        if (effectiveSetId <= 0) {
          setSetMembers([]);
          return undefined;
        }

        let active = true;
        const setRequest = setSelector.loadSet
          ? setSelector.loadSet(effectiveSetId, { contextPostId: effectiveContextPostId })
          : apiFetch({ path: setPath(effectiveSetId, effectiveContextPostId) });

        if (!setRequest || !setRequest.then) {
          setSetMembers([]);
          return undefined;
        }

        setRequest.then(function (response) {
          if (!active) {
            return;
          }

          const item = response && response.item ? response.item : {};
          const members = Array.isArray(item.members)
            ? item.members.filter(function (member) {
                return member && member.memberKind === 'object';
              })
            : [];
          setSetMembers(members);
          setArchiveSetError('');
        }).catch(function () {
          if (!active) {
            return;
          }

          setSetMembers([]);
          setArchiveSetError('Objekte der Auswahl konnten nicht geladen werden.');
        });

        return function () {
          active = false;
        };
      }, [effectiveSetId, effectiveContextPostId]);

      function runSetSearch() {
        if (!setSelector.searchSets || effectiveContextPostId <= 0) {
          setArchiveSetError('Beitrag zuerst speichern, dann kann eine Archivauswahl verbunden werden.');
          return;
        }

        setSetSearchBusy(true);
        setArchiveSetError('');
        setArchiveSetNotice('');
        setSelector.searchSets({
          search: setSearch,
          perPage: 12,
          contextPostId: effectiveContextPostId,
        }).then(function (response) {
          const items = response && Array.isArray(response.items) ? response.items : [];
          setSetSearchResults(items.filter(function (set) {
            return set && set.status === 'active';
          }));
          setSetSearchBusy(false);
        }).catch(function () {
          setSetSearchResults([]);
          setSetSearchBusy(false);
          setArchiveSetError('Archivauswahlen konnten nicht gesucht werden.');
        });
      }

      function attachSetToPost(setId) {
        const id = intValue(setId);
        if (!setSelector.attachSet || effectiveContextPostId <= 0 || id <= 0) {
          setArchiveSetError('Archivauswahl konnte nicht verbunden werden.');
          return;
        }

        setArchiveSetError('');
        setArchiveSetNotice('');
        setSelector.attachSet(effectiveContextPostId, id).then(function (response) {
          const items = response && Array.isArray(response.items) ? response.items : [];
          setSets(items);
          setSetsLoaded(true);
          setSetSearchResults([]);
          setSetSearch('');
          setAttributes({
            setId: id,
            memberPosition: -1,
            contextPostId: effectiveContextPostId,
          });
          if (setSelector.notifyAttachedSetsChanged) {
            setSelector.notifyAttachedSetsChanged(effectiveContextPostId, items);
          }
          setArchiveSetNotice('Archivauswahl wurde mit diesem Beitrag verbunden.');
        }).catch(function () {
          setArchiveSetError('Archivauswahl konnte nicht verbunden werden.');
        });
      }

      const setOptions = [{ label: 'Verbundene Auswahl dieses Beitrags', value: '0' }]
        .concat((Array.isArray(sets) ? sets : []).map(function (set) {
          return {
            label: setOptionLabel(set),
            value: String(set.id || 0),
          };
        }).filter(function (option) {
          return option.label !== '' && option.value !== '0';
        }));
      const memberOptions = [{ label: 'Objekt auswählen', value: '-1' }]
        .concat((Array.isArray(setMembers) ? setMembers : []).map(function (member) {
          return {
            label: memberOptionLabel(member),
            value: String(intValue(member.position || 0)),
          };
        }).filter(function (option) {
          return option.label !== '';
        }));
      const hasSelectedMemberOption = memberOptions.some(function (option) {
        return option.value === String(memberPosition);
      });
      if (memberPosition >= 0 && !hasSelectedMemberOption) {
        memberOptions.push({
          label: 'Position ' + String(memberPosition + 1),
          value: String(memberPosition),
        });
      }

      const controls = InspectorControls && PanelBody
        ? el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: 'Archivauswahl', initialOpen: true },
              archiveSetError && Notice
                ? el(Notice, { status: 'warning', isDismissible: false }, archiveSetError)
                : null,
              archiveSetNotice && Notice
                ? el(Notice, { status: 'success', isDismissible: true, onRemove: function () { setArchiveSetNotice(''); } }, archiveSetNotice)
                : null,
              SelectControl
                ? el(SelectControl, {
                    label: 'Auswahl',
                    value: String(selectedSetId),
                    options: setOptions,
                    onChange: function (value) {
                      setAttributes({
                        setId: intValue(value),
                        memberPosition: -1,
                      });
                    },
                  })
                : null,
              SelectControl
                ? el(SelectControl, {
                    label: 'Objekt',
                    value: String(memberPosition),
                    options: memberOptions,
                    onChange: function (value) {
                      setAttributes({ memberPosition: intValue(value) });
                    },
                    help: effectiveSetId > 0
                      ? 'Objekte kommen aus der gewaehlten Archivauswahl.'
                      : 'Verbinde zuerst eine Archivauswahl ueber den Archive Picker.'
                  })
                : null,
              TextControl
                ? el(TextControl, {
                    label: 'Archivauswahl verbinden',
                    value: setSearch,
                    placeholder: 'Titel suchen',
                    onChange: setSetSearch,
                    help: 'Sucht vorhandene Archivsets und verbindet die Auswahl mit diesem Beitrag.',
                  })
                : null,
              Button
                ? el(Button, {
                    variant: 'secondary',
                    disabled: setSearchBusy || effectiveContextPostId <= 0,
                    onClick: runSetSearch,
                  }, setSearchBusy ? 'Sucht...' : 'Archivauswahl suchen')
                : null,
              setSearchResults.length > 0
                ? el(
                    'div',
                    { className: 'iss-archive-object-editor-set-results' },
                    setSearchResults.map(function (set) {
                      const alreadyAttached = sets.some(function (attachedSet) {
                        return intValue(attachedSet && attachedSet.id) === intValue(set.id);
                      });

                      return el(
                        'div',
                        {
                          className: 'iss-archive-object-editor-set-result',
                          key: 'set-' + String(set.id),
                        },
                        el('span', null, setOptionLabel(set)),
                        Button
                          ? el(Button, {
                              variant: alreadyAttached ? 'tertiary' : 'secondary',
                              disabled: alreadyAttached,
                              onClick: function () {
                                attachSetToPost(set.id);
                              },
                            }, alreadyAttached ? 'Verbunden' : 'Verbinden')
                          : null
                      );
                    })
                  )
                : null
            )
          )
        : null;

      const preview = ServerSideRender
        ? el(
            'div',
            {
              className: 'iss-archive-object-editor-preview',
              onClick: stopPreviewNavigation,
            },
            el(ServerSideRender, {
              block: 'iss-wf-import/archive-object',
              attributes: Object.assign({}, attrs, { variant: attrs.variant || 'featured' }),
            })
          )
        : Placeholder
          ? el(Placeholder, {
              label: 'ISS Archivobjekt',
              instructions: 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.',
            })
          : el('p', null, 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.');

      return el(
        Fragment,
        null,
        controls,
        preview
      );
    },
    save: function () {
      return null;
    },
  });
})();
