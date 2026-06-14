(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const useEffect = window.wp.element.useEffect;
  const useState = window.wp.element.useState;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const MediaUpload = window.wp.blockEditor && window.wp.blockEditor.MediaUpload;
  const MediaUploadCheck = window.wp.blockEditor && window.wp.blockEditor.MediaUploadCheck;
  const Button = window.wp.components && window.wp.components.Button;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;
  const RangeControl = window.wp.components && window.wp.components.RangeControl;

  const COLUMN_OPTIONS = [
    { label: '2 Spalten', value: '2' },
    { label: '3 Spalten', value: '3' },
    { label: '4 Spalten', value: '4' },
    { label: '5 Spalten', value: '5' },
    { label: '6 Spalten', value: '6' },
  ];
  const MODE_OPTIONS = [
    { label: 'Komposition', value: 'compose' },
    { label: 'Text & Links', value: 'annotate' },
  ];

  function stripTags(value) {
    return String(value || '').replace(/<[^>]*>/g, '').trim();
  }

  function normalizeItems(items) {
    if (!Array.isArray(items)) return [];

    var maxColSpan = arguments.length > 1 ? parseInt(arguments[1], 10) || 4 : 4;
    var maxRowSpan = arguments.length > 2 ? parseInt(arguments[2], 10) || 6 : 6;

    maxColSpan = Math.max(1, Math.min(6, maxColSpan));
    maxRowSpan = Math.max(1, Math.min(6, maxRowSpan));

    return items.map(function (item) {
      var source = item && typeof item === 'object' ? item : {};
      var span = source.span === 'wide' || source.span === 'tall' ? source.span : 'normal';
      var colSpan = parseInt(source.colSpan, 10);
      var rowSpan = parseInt(source.rowSpan, 10);

      if (!colSpan || colSpan < 1) {
        colSpan = span === 'wide' ? 2 : 1;
      }
      if (!rowSpan || rowSpan < 1) {
        rowSpan = span === 'tall' ? 2 : 1;
      }

      return {
        id: source.id ? parseInt(source.id, 10) || 0 : 0,
        url: typeof source.url === 'string' ? source.url : '',
        alt: typeof source.alt === 'string' ? source.alt : '',
        kicker: typeof source.kicker === 'string' ? source.kicker : '',
        title: typeof source.title === 'string' ? source.title : '',
        text: typeof source.text === 'string' ? source.text : '',
        caption: typeof source.caption === 'string' ? source.caption : '',
        captionHtml: typeof source.captionHtml === 'string' ? source.captionHtml : '',
        className: typeof source.className === 'string' ? source.className : '',
        captionClassName: typeof source.captionClassName === 'string' ? source.captionClassName : '',
        linkUrl: typeof source.linkUrl === 'string' ? source.linkUrl : '',
        colSpan: Math.max(1, Math.min(maxColSpan, colSpan || 1)),
        rowSpan: Math.max(1, Math.min(maxRowSpan, rowSpan || 1)),
      };
    });
  }

  function normalizeColumns(value) {
    var columns = parseInt(value, 10);
    if (!columns || columns < 2) return 4;
    return Math.max(2, Math.min(6, columns));
  }

  function normalizeRowHeight(value) {
    var rowHeight = parseInt(value, 10);
    if (!rowHeight || rowHeight < 80) return 118;
    return Math.max(80, Math.min(260, rowHeight));
  }

  function hasOverlay(item) {
    return !!(item && (item.kicker || item.title || item.text || item.caption || item.captionHtml));
  }

  function usesStructuredCaption(item) {
    return !!(
      item &&
      (
        item.kicker ||
        item.title ||
        item.text ||
        item.captionHtml ||
        (item.captionClassName && item.captionClassName.trim())
      )
    );
  }

  function cellClass(item) {
    var options = arguments.length > 1 ? arguments[1] : {};
    var classes = ['wp-block-image', 'iss-dense-image-wall__cell'];

    if (item && item.colSpan >= 2) classes.push('iss-dense-image-wall__cell--wide');
    if (item && item.rowSpan >= 2) classes.push('iss-dense-image-wall__cell--tall');
    if (!options.legacy && hasOverlay(item)) classes.push('iss-dense-image-wall__cell--captioned');
    if (item && typeof item.className === 'string' && item.className.trim()) {
      classes.push(item.className.trim());
    }

    return classes.join(' ');
  }

  function cellStyle(item) {
    var style = {};
    var colSpan = item && item.colSpan ? parseInt(item.colSpan, 10) : 1;
    var rowSpan = item && item.rowSpan ? parseInt(item.rowSpan, 10) : 1;

    colSpan = Math.max(1, Math.min(6, colSpan || 1));
    rowSpan = Math.max(1, Math.min(6, rowSpan || 1));

    if (colSpan > 1) style.gridColumn = 'span ' + String(colSpan);
    if (rowSpan > 1) style.gridRow = 'span ' + String(rowSpan);

    return style;
  }

  function captionClass(item) {
    var classes = ['iss-dense-image-wall__caption'];
    if (item && typeof item.captionClassName === 'string' && item.captionClassName.trim()) {
      classes.push(item.captionClassName.trim());
    }
    return classes.join(' ');
  }

  function captionNode(item) {
    var options = arguments.length > 1 ? arguments[1] : {};
    if (!item) return null;

    if (options.legacy && item.caption) {
      return el('figcaption', null, item.caption);
    }

    if (item.captionHtml) {
      return el('figcaption', {
        className: captionClass(item),
        dangerouslySetInnerHTML: { __html: item.captionHtml },
      });
    }

    if (item.kicker || item.title || item.text) {
      return el(
        'figcaption',
        { className: captionClass(item) },
        item.kicker ? el('p', { className: 'iss-dense-image-wall__kicker' }, item.kicker) : null,
        item.title ? el('p', { className: 'iss-dense-image-wall__title' }, item.title) : null,
        item.text ? el('p', { className: 'iss-dense-image-wall__text' }, item.text) : null
      );
    }

    if (item.caption) {
      return el('figcaption', { className: captionClass(item) }, item.caption);
    }

    return null;
  }

  function makeDraft(index, item) {
    return {
      index: index,
      kicker: item && item.kicker ? item.kicker : '',
      title: item && item.title ? item.title : '',
      text: item && item.text ? item.text : '',
      linkUrl: item && item.linkUrl ? item.linkUrl : '',
      dirty: false,
    };
  }

  function edit(props) {
    var attrs = props.attributes || {};
    var setAttributes = props.setAttributes || function () {};
    var columns = normalizeColumns(attrs.columns);
    var rowHeight = normalizeRowHeight(attrs.rowHeight);
    var items = normalizeItems(attrs.items, columns, 6);
    var editMode = attrs.editMode === 'annotate' ? 'annotate' : 'compose';
    var isAnnotating = editMode === 'annotate';
    var isComposing = !isAnnotating;
    var activeState = useState ? useState(items.length ? 0 : -1) : [items.length ? 0 : -1, function () {}];
    var activeIndex = activeState[0];
    var setActiveIndex = activeState[1];
    var currentIndex = items.length ? Math.max(0, Math.min(activeIndex, items.length - 1)) : -1;
    var currentItem = currentIndex >= 0 ? items[currentIndex] : null;
    var draftState = useState ? useState(makeDraft(currentIndex, currentItem)) : [makeDraft(currentIndex, currentItem), function () {}];
    var draft = draftState[0];
    var setDraft = draftState[1];
    var draftValues = draft && draft.index === currentIndex ? draft : makeDraft(currentIndex, currentItem);
    var controls = null;

    if (useEffect) {
      useEffect(function () {
        setDraft(function (previousDraft) {
          var nextDraft = makeDraft(currentIndex, currentItem);

          if (previousDraft && previousDraft.dirty && previousDraft.index === currentIndex) {
            return previousDraft;
          }
          if (
            previousDraft &&
            previousDraft.index === nextDraft.index &&
            previousDraft.kicker === nextDraft.kicker &&
            previousDraft.title === nextDraft.title &&
            previousDraft.text === nextDraft.text &&
            previousDraft.linkUrl === nextDraft.linkUrl &&
            previousDraft.dirty === nextDraft.dirty
          ) {
            return previousDraft;
          }

          return nextDraft;
        });
      }, [
        currentIndex,
        currentItem ? currentItem.kicker || '' : '',
        currentItem ? currentItem.title || '' : '',
        currentItem ? currentItem.text || '' : '',
        currentItem ? currentItem.linkUrl || '' : '',
      ]);
    }

    function updateItems(nextItems, nextColumns) {
      setAttributes({ items: normalizeItems(nextItems, nextColumns || columns, 6) });
    }

    function patchItem(index, patch) {
      var nextItems = items.slice();
      nextItems[index] = Object.assign({}, nextItems[index] || {}, patch || {});
      updateItems(nextItems, columns);
    }

    function setDraftField(field, value) {
      setDraft(function (previousDraft) {
        var nextDraft = previousDraft && previousDraft.index === currentIndex
          ? previousDraft
          : makeDraft(currentIndex, currentItem);
        var patch = { index: currentIndex, dirty: true };
        patch[field] = value;
        return Object.assign({}, nextDraft, patch);
      });
    }

    function commitDraft(sourceDraft) {
      var nextDraft = sourceDraft || draft;
      var targetIndex = nextDraft && typeof nextDraft.index === 'number' ? nextDraft.index : -1;
      var targetItem = targetIndex >= 0 ? items[targetIndex] : null;

      if (!nextDraft || !nextDraft.dirty || !targetItem) return;

      if (
        (targetItem.kicker || '') === (nextDraft.kicker || '') &&
        (targetItem.title || '') === (nextDraft.title || '') &&
        (targetItem.text || '') === (nextDraft.text || '') &&
        (targetItem.linkUrl || '') === (nextDraft.linkUrl || '')
      ) {
        setDraft(Object.assign({}, nextDraft, { dirty: false }));
        return;
      }

      patchItem(targetIndex, {
        kicker: nextDraft.kicker || '',
        title: nextDraft.title || '',
        text: nextDraft.text || '',
        linkUrl: nextDraft.linkUrl || '',
      });
      setDraft(Object.assign({}, nextDraft, { dirty: false }));
    }

    function selectItem(index) {
      if (index !== currentIndex) commitDraft();
      setActiveIndex(index);
    }

    function removeItem(index) {
      var nextLength = Math.max(0, items.length - 1);
      updateItems(items.filter(function (_item, position) {
        return position !== index;
      }), columns);
      setActiveIndex(nextLength ? Math.max(0, Math.min(index, nextLength - 1)) : -1);
    }

    function moveItem(index, direction) {
      var target = index + direction;
      if (target < 0 || target >= items.length) return;

      var nextItems = items.slice();
      var current = nextItems[target];
      nextItems[target] = nextItems[index];
      nextItems[index] = current;
      updateItems(nextItems, columns);
      setActiveIndex(target);
    }

    function addItem() {
      updateItems(items.concat([{
        id: 0,
        url: '',
        alt: '',
        kicker: '',
        title: '',
        text: '',
        caption: '',
        linkUrl: '',
        colSpan: 1,
        rowSpan: 1,
      }]), columns);
      setActiveIndex(items.length);
    }

    function itemLabel(item, index) {
      var sizeLabel = String(item.colSpan || 1) + 'x' + String(item.rowSpan || 1);
      var text = item.caption || item.alt || '';
      if (text.length > 42) text = text.slice(0, 39) + '...';
      return String(index + 1) + '. Bild - ' + sizeLabel + (text ? ' - ' + text : '');
    }

    if (InspectorControls && PanelBody) {
      controls = el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Dense Image Wall', initialOpen: true },
          SelectControl
            ? el(SelectControl, {
                label: 'Arbeitsmodus',
                value: editMode,
                options: MODE_OPTIONS,
                help: isAnnotating
                  ? 'Komposition ist gesperrt. Text und Links werden über Änderungen übernehmen geschrieben.'
                  : 'Bilder, Reihenfolge und Zellgrößen bearbeiten. Text und Links bleiben ausgeblendet.',
                onChange: function (value) {
                  if (editMode === 'annotate' && value !== 'annotate') commitDraft();
                  setAttributes({ editMode: value === 'annotate' ? 'annotate' : 'compose' });
                },
              })
            : null,
          isComposing && SelectControl
            ? el(SelectControl, {
                label: 'Raster',
                value: String(columns),
                options: COLUMN_OPTIONS,
                onChange: function (value) {
                  var nextColumns = normalizeColumns(value);
                  setAttributes({
                    columns: nextColumns,
                    items: normalizeItems(items, nextColumns, 6),
                  });
                },
              })
            : null,
          isComposing && RangeControl
            ? el(RangeControl, {
                label: 'Grundhöhe je Reihe',
                min: 80,
                max: 260,
                step: 10,
                value: rowHeight,
                help: 'Steuert die Grundhöhe der Wand. Größere Werte ergeben eine höhere Gesamtfläche.',
                onChange: function (value) {
                  setAttributes({ rowHeight: normalizeRowHeight(value) });
                },
              })
            : null,
          el(
            'p',
            { className: 'components-base-control__help' },
            isAnnotating
              ? 'Bildauswahl, Reihenfolge und Zellgrößen bleiben in diesem Modus unverändert.'
              : 'Asymmetrie entsteht aus Reihenfolge, Breite, Höhe und dem gewählten Grundraster.'
          )
        )
      );
    }

    return el(
      Fragment,
      null,
      controls,
      el(
        'div',
        { className: 'components-placeholder wp-block-iss-dense-image-wall-editor' },
        el('strong', null, 'Dense Image Wall'),
        el(
          'p',
          null,
          isAnnotating
            ? 'Text und Links bearbeiten, ohne die Komposition zu verändern.'
            : 'Bilder, Reihenfolge und Zellgrößen frei komponieren.'
        ),
        el(
          'div',
          {
            style: {
              display: 'grid',
              gap: '0.85rem',
              width: '100%',
              gridTemplateColumns: 'repeat(' + String(columns) + ', minmax(0, 1fr))',
              gridAutoRows: String(rowHeight) + 'px',
            },
          },
          items.length
            ? items.map(function (item, index) {
                return el(
                  'div',
                  {
                    key: 'dense-image-wall-item-' + String(index),
                    style: {
                      display: 'grid',
                      gridTemplateColumns: isComposing ? '3.5rem minmax(0, 1fr) auto' : '3.5rem minmax(0, 1fr)',
                      alignItems: 'center',
                      gap: '0.75rem',
                      padding: '0.55rem 0.65rem',
                      border: index === currentIndex ? '1px solid #1e1e1e' : '1px solid rgba(30, 30, 30, 0.14)',
                      background: index === currentIndex ? '#fff' : 'rgba(255,255,255,0.72)',
                      cursor: 'pointer',
                    },
                    onClick: function () {
                      selectItem(index);
                    },
                  },
                  item.url
                    ? el('img', {
                        src: item.url,
                        alt: item.alt || '',
                        style: {
                          display: 'block',
                          width: '3.5rem',
                          height: '3.5rem',
                          objectFit: 'cover',
                          borderRadius: '4px',
                        },
                      })
                    : el(
                        'div',
                        {
                          style: {
                            display: 'grid',
                            placeItems: 'center',
                            width: '3.5rem',
                            height: '3.5rem',
                            borderRadius: '4px',
                            background: '#ddd2c2',
                            color: '#666',
                            fontSize: '10px',
                            textAlign: 'center',
                            lineHeight: '1.2',
                            padding: '0.25rem',
                          },
                        },
                        'Kein Bild'
                      ),
                  el(
                    'div',
                    { style: { minWidth: 0 } },
                    el(
                      'div',
                      {
                        style: {
                          fontWeight: '600',
                          fontSize: '12px',
                          lineHeight: '1.35',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        },
                      },
                      itemLabel(item, index)
                    ),
                    el(
                      'div',
                      {
                        style: {
                          fontSize: '11px',
                          color: '#666',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        },
                      },
                      item.url ? (item.linkUrl ? 'Mit Link' : 'Ohne Link') : 'Bild fehlt'
                    )
                  ),
                  isComposing && Button
                    ? el(
                        'div',
                        {
                          style: {
                            display: 'flex',
                            gap: '0.35rem',
                            flexWrap: 'wrap',
                            justifyContent: 'flex-end',
                          },
                          onClick: function (event) {
                            event.stopPropagation();
                          },
                        },
                        el(
                          Button,
                          {
                            variant: 'secondary',
                            disabled: index === 0,
                            onClick: function () {
                              moveItem(index, -1);
                            },
                          },
                          'Nach oben'
                        ),
                        el(
                          Button,
                          {
                            variant: 'secondary',
                            disabled: index >= items.length - 1,
                            onClick: function () {
                              moveItem(index, 1);
                            },
                          },
                          'Nach unten'
                        ),
                        el(
                          Button,
                          {
                            variant: 'tertiary',
                            onClick: function () {
                              removeItem(index);
                            },
                          },
                          'Entfernen'
                        )
                      )
                    : null
                );
              })
            : el('p', { className: 'components-base-control__help' }, 'Noch keine Bilder hinzugefügt.')
        ),
        currentItem
          ? el(
              'div',
              {
                style: {
                  width: '100%',
                  marginTop: '0.9rem',
                  padding: '0.9rem',
                  border: '1px solid rgba(30, 30, 30, 0.14)',
                  background: '#fff',
                },
              },
              el(
                'div',
                {
                  style: {
                    display: 'grid',
                    gridTemplateColumns: '5rem minmax(0, 1fr)',
                    gap: '0.9rem',
                    alignItems: 'start',
                    marginBottom: '0.9rem',
                  },
                },
                currentItem.url
                  ? el('img', {
                      src: currentItem.url,
                      alt: currentItem.alt || '',
                      style: {
                        display: 'block',
                        width: '5rem',
                        height: '5rem',
                        objectFit: 'cover',
                        borderRadius: '4px',
                      },
                    })
                  : el(
                      'div',
                      {
                        style: {
                          display: 'grid',
                          placeItems: 'center',
                          width: '5rem',
                          height: '5rem',
                          background: '#ddd2c2',
                          color: '#666',
                          fontSize: '11px',
                          textAlign: 'center',
                          padding: '0.25rem',
                        },
                      },
                      'Kein Bild'
                    ),
                el(
                  'div',
                  null,
                  el('strong', null, 'Eintrag ' + String(currentIndex + 1)),
                  el(
                    'p',
                    {
                      style: {
                        margin: '0.25rem 0 0',
                        fontSize: '12px',
                        color: '#666',
                      },
                    },
                    'Breite ' + String(currentItem.colSpan || 1) + ' | Höhe ' + String(currentItem.rowSpan || 1)
                  )
                )
              ),
              isAnnotating && TextControl
                ? el(TextControl, {
                    label: 'Kicker',
                    value: draftValues.kicker || '',
                    onChange: function (value) {
                      setDraftField('kicker', value);
                    },
                  })
                : null,
              isAnnotating && TextControl
                ? el(TextControl, {
                    label: 'Titel',
                    value: draftValues.title || '',
                    onChange: function (value) {
                      setDraftField('title', value);
                    },
                  })
                : null,
              isAnnotating && TextareaControl
                ? el(TextareaControl, {
                    label: 'Text',
                    value: draftValues.text || '',
                    onChange: function (value) {
                      setDraftField('text', value);
                    },
                  })
                : null,
              isAnnotating && TextControl
                ? el(TextControl, {
                    label: 'Link-URL',
                    value: draftValues.linkUrl || '',
                    onChange: function (value) {
                      setDraftField('linkUrl', value);
                    },
                  })
                : null,
              isAnnotating && Button
                ? el(
                    Button,
                    {
                      variant: draftValues.dirty ? 'primary' : 'secondary',
                      disabled: !draftValues.dirty,
                      onClick: function () {
                        commitDraft();
                      },
                    },
                    'Änderungen übernehmen'
                  )
                : null,
              isAnnotating && draftValues.dirty
                ? el('p', { className: 'components-base-control__help' }, 'Nicht übernommene Änderungen werden erst nach Klick auf den Button gespeichert.')
                : null,
              isComposing && SelectControl
                ? el(SelectControl, {
                    label: 'Breite',
                    value: String(currentItem.colSpan || 1),
                    options: Array.from({ length: columns }, function (_entry, position) {
                      var span = position + 1;
                      return {
                        label: span === 1 ? '1 Spalte' : String(span) + ' Spalten',
                        value: String(span),
                      };
                    }),
                    onChange: function (value) {
                      patchItem(currentIndex, { colSpan: parseInt(value, 10) || 1 });
                    },
                  })
                : null,
              isComposing && SelectControl
                ? el(SelectControl, {
                    label: 'Höhe',
                    value: String(currentItem.rowSpan || 1),
                    options: Array.from({ length: 6 }, function (_entry, position) {
                      var span = position + 1;
                      return {
                        label: span === 1 ? '1 Reihe' : String(span) + ' Reihen',
                        value: String(span),
                      };
                    }),
                    onChange: function (value) {
                      patchItem(currentIndex, { rowSpan: parseInt(value, 10) || 1 });
                    },
                  })
                : null,
              isComposing && MediaUpload && MediaUploadCheck && Button
                ? el(
                    MediaUploadCheck,
                    null,
                    el(MediaUpload, {
                      onSelect: function (media) {
                        var mediaTitle = '';
                        var mediaCaption = '';

                        if (media && media.title) {
                          if (typeof media.title === 'string') {
                            mediaTitle = media.title;
                          } else if (typeof media.title.raw === 'string') {
                            mediaTitle = media.title.raw;
                          } else if (typeof media.title.rendered === 'string') {
                            mediaTitle = media.title.rendered;
                          }
                        }

                        if (media && media.caption) {
                          if (typeof media.caption === 'string') {
                            mediaCaption = media.caption;
                          } else if (typeof media.caption.raw === 'string') {
                            mediaCaption = media.caption.raw;
                          } else if (typeof media.caption.rendered === 'string') {
                            mediaCaption = media.caption.rendered;
                          }
                        }

                        if (!mediaCaption && media && media.description) {
                          if (typeof media.description === 'string') {
                            mediaCaption = media.description;
                          } else if (typeof media.description.raw === 'string') {
                            mediaCaption = media.description.raw;
                          } else if (typeof media.description.rendered === 'string') {
                            mediaCaption = media.description.rendered;
                          }
                        }

                        patchItem(currentIndex, {
                          id: media && media.id ? media.id : 0,
                          url: media && media.url ? media.url : '',
                          alt: currentItem.alt || (media && media.alt ? media.alt : ''),
                          title: currentItem.title || stripTags(mediaTitle),
                          text: currentItem.text || stripTags(mediaCaption),
                        });
                      },
                      allowedTypes: ['image'],
                      value: currentItem.id || 0,
                      render: function (renderProps) {
                        return el(
                          Button,
                          {
                            variant: 'secondary',
                            onClick: renderProps.open,
                          },
                          currentItem.url ? 'Bild ersetzen' : 'Bild wählen'
                        );
                      },
                    })
                  )
                : null
            )
          : null,
        isComposing && Button
          ? el(
              Button,
              {
                variant: 'primary',
                onClick: addItem,
              },
              'Bild hinzufügen'
            )
          : null
      )
    );
  }

  function save(props) {
    var attrs = props.attributes || {};
    var columns = normalizeColumns(attrs.columns);
    var rowHeight = normalizeRowHeight(attrs.rowHeight);
    var items = normalizeItems(attrs.items, columns, 6).filter(function (item) {
      return item.url;
    });

    return el(
      'div',
      columns === 4 && rowHeight === 118
        ? { className: 'wp-block-group iss-dense-image-wall' }
        : {
            className: 'wp-block-group iss-dense-image-wall',
            style: {
              '--iss-dense-image-wall-columns': String(columns),
              '--iss-dense-image-wall-row-size': String(rowHeight) + 'px',
            },
          },
      items.map(function (item, index) {
        var legacyCaption = !usesStructuredCaption(item) && !!item.caption;
        var imageNode = el('img', {
          src: item.url,
          alt: item.alt || '',
        });
        var mediaNode = item.linkUrl ? el('a', { href: item.linkUrl }, imageNode) : imageNode;

        return el(
          'figure',
          {
            key: 'dense-image-wall-save-' + String(index),
            className: cellClass(item, { legacy: legacyCaption }),
            style: cellStyle(item),
          },
          mediaNode,
          captionNode(item, { legacy: legacyCaption })
        );
      })
    );
  }

  window.wp.blocks.registerBlockType('iss/dense-image-wall', {
    supports: {
      html: false,
      align: ['wide', 'full'],
    },
    attributes: {
      columns: {
        type: 'number',
        default: 4,
      },
      rowHeight: {
        type: 'number',
        default: 118,
      },
      editMode: {
        type: 'string',
        default: 'compose',
      },
      items: {
        type: 'array',
        default: [],
      },
    },
    edit: edit,
    save: save,
  });
}());
