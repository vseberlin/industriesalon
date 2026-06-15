(function () {
  const editor = window.issRelationsEditor;
  if (!editor) return;

  const el = editor.components.el;
  const BaseControl = editor.components.BaseControl;
  const CheckboxControl = editor.components.CheckboxControl;
  const SelectControl = editor.components.SelectControl;
  const ToggleControl = editor.components.ToggleControl;
  const POST_TYPE_OPTIONS = editor.POST_TYPE_OPTIONS;
  const optionSets = editor.optionSets;

  function getSelectedPostTypes(attrs, config) {
    if (config.fixedPostType) {
      return [config.fixedPostType];
    }

    var values = Array.isArray(attrs.postTypes) ? attrs.postTypes : [];

    if (!values.length && attrs.postType) {
      values = [attrs.postType];
    }

    if (!values.length) {
      values = ['post'];
    }

    values = values
      .map(function (value) {
        return String(value || '');
      })
      .filter(function (value, index, list) {
        return value && list.indexOf(value) === index && POST_TYPE_OPTIONS.some(function (option) {
          return option.value === value;
        });
      });

    return values.length ? values : ['post'];
  }

  function setSelectedPostTypes(nextValues, attrs, setAttributes, config) {
    var values = Array.isArray(nextValues) ? nextValues : [];
    var normalized = values
      .map(function (value) {
        return String(value || '');
      })
      .filter(function (value, index, list) {
        return value && list.indexOf(value) === index && POST_TYPE_OPTIONS.some(function (option) {
          return option.value === value;
        });
      });
    var fallback = config.fixedPostType || attrs.postType || 'post';

    if (!normalized.length) {
      normalized = [fallback];
    }

    setAttributes({
      postTypes: normalized,
      postType: normalized[0],
    });
  }

  function shouldShowColumns(attrs) {
    return ['strip', 'rail'].indexOf(attrs.layoutVariant || 'grid') === -1;
  }

  function renderPostTypeControl(attrs, setAttributes, config) {
    if (config.showPostTypeField === false) {
      return null;
    }

    if (!CheckboxControl || !BaseControl) {
      return el(SelectControl, {
        label: 'Inhaltstyp',
        value: attrs.postType || config.fixedPostType || 'post',
        options: POST_TYPE_OPTIONS,
        onChange: function (value) {
          setSelectedPostTypes([value], attrs, setAttributes, config);
        },
      });
    }

    var selected = getSelectedPostTypes(attrs, config);

    return el(
      BaseControl,
      {
        label: 'Inhaltstypen',
        help: 'Mehrfachauswahl mischt mehrere Inhaltstypen in einem gemeinsamen Feed oder Strip.',
      },
      el(
        'div',
        {
          style: {
            display: 'grid',
            gap: '4px',
            marginTop: '8px',
          },
        },
        POST_TYPE_OPTIONS.map(function (option) {
          return el(CheckboxControl, {
            key: 'post-type-' + option.value,
            label: option.label,
            checked: selected.indexOf(option.value) !== -1,
            onChange: function (checked) {
              var next = checked
                ? selected.concat([option.value])
                : selected.filter(function (value) {
                    return value !== option.value;
                  });

              if (!next.length) {
                next = [option.value];
              }

              setSelectedPostTypes(next, attrs, setAttributes, config);
            },
          });
        })
      )
    );
  }

  function getPreviewText(attrs, config) {
    var postTypes = getSelectedPostTypes(attrs, config);
    var perPage = attrs.perPage || 3;
    var source = attrs.source || 'current';
    var layout = attrs.layoutVariant || 'grid';
    var sortMode = attrs.sortMode || 'auto';
    var columns = attrs.columns || 3;
    var skin = attrs.skin || 'default';
    var parts = [
      String(perPage) + ' Einträge',
      'Typen: ' + postTypes.map(editor.getPostTypeLabel).join(', '),
      'Quelle: ' + editor.getSourceLabel(source),
      shouldShowColumns(attrs)
        ? 'Layout: ' + layout + ' / ' + String(columns) + ' Sp.'
        : 'Layout: ' + layout,
      'Sortierung: ' + sortMode
    ];

    if (skin !== 'default') {
      parts.push('Skin: ' + editor.getSkinLabel(skin));
    }

    if (attrs.showImage === false) {
      parts.push('ohne Bild');
    }

    return parts.join(' · ');
  }

  function renderPreviewItems(preview, options) {
    options = options || {};
    var items = preview && Array.isArray(preview.items) ? preview.items : [];
    var isStrip = preview && preview.layoutVariant === 'strip';
    var isSingleColumn = preview && (preview.layoutVariant === 'stack' || preview.layoutVariant === 'compact');

    if (!items.length) {
      return el('p', { className: 'components-base-control__help' }, 'Keine passenden Einträge für diese Auswahl gefunden.');
    }

    return el(
      'div',
      {
        style: {
          display: isStrip ? 'flex' : 'grid',
          gap: '12px',
          gridTemplateColumns: isStrip
            ? undefined
            : (isSingleColumn
              ? '1fr'
              : 'repeat(' + String(Math.max(1, Math.min(6, preview.columns || 3))) + ', minmax(0, 1fr))'),
          flexWrap: isStrip ? 'nowrap' : undefined,
          overflowX: isStrip ? 'auto' : 'visible',
          paddingBottom: isStrip ? '6px' : 0,
          width: '100%',
          marginTop: '12px',
        },
      },
      items.map(function (item) {
        return el(
          'article',
          {
            key: 'related-preview-' + String(item.id),
            style: {
              border: '1px solid rgba(30,30,30,0.14)',
              background: '#fff',
              flex: isStrip ? '0 0 min(19rem, 82vw)' : undefined,
              minWidth: 0,
              height: '100%',
            },
          },
          preview.showImage === false
            ? null
            : (item.thumbnail
              ? el('img', {
                  src: item.thumbnail,
                  alt: '',
                  style: {
                    display: 'block',
                    width: '100%',
                    height: preview.layoutVariant === 'compact' ? '5rem' : '7.5rem',
                    objectFit: 'cover',
                    borderBottom: '1px solid rgba(30,30,30,0.08)',
                  },
                })
              : el('div', {
                  style: {
                    display: 'block',
                    width: '100%',
                    height: preview.layoutVariant === 'compact' ? '5rem' : '7.5rem',
                    background: 'linear-gradient(180deg, rgba(30,30,30,0.05), rgba(255,255,255,0.95))',
                    borderBottom: '1px solid rgba(30,30,30,0.08)',
                  },
                })),
          el(
            'div',
            {
              style: {
                padding: '12px',
                display: 'grid',
                gap: '8px',
              },
            },
            item.kicker
              ? el(
                  'p',
                  {
                    style: {
                      margin: 0,
                      fontSize: '11px',
                      lineHeight: '1.4',
                      letterSpacing: '0.08em',
                      textTransform: 'uppercase',
                      color: 'rgba(30,30,30,0.65)',
                    },
                  },
                  item.kicker
                )
              : null,
            el(
              'h4',
              {
                style: {
                  margin: 0,
                  fontSize: '18px',
                  lineHeight: '1.25',
                },
              },
              item.title || 'Ohne Titel'
            ),
            item.detail
              ? el(
                  'p',
                  {
                    style: {
                      margin: 0,
                      fontSize: '12px',
                      lineHeight: '1.45',
                      fontWeight: '600',
                      color: 'rgba(30,30,30,0.72)',
                    },
                  },
                  item.detail
                )
              : null,
            item.meta
              ? el(
                  'p',
                  {
                    style: {
                      margin: 0,
                      fontSize: '11px',
                      lineHeight: '1.4',
                      fontWeight: '700',
                      color: 'rgba(30,30,30,0.62)',
                    },
                  },
                  item.meta
                )
              : null,
            options.canManageEditorialSignals
              ? editor.editorialSignalControls.renderCardControls(item, options)
              : null
          )
        );
      })
    );
  }

  function renderInspectorControls(attrs, setAttributes) {
    return [
      el(SelectControl, {
        key: 'layoutVariant',
        label: 'Kartenlayout',
        value: attrs.layoutVariant || 'grid',
        options: optionSets.RELATED_CARDS_LAYOUT_OPTIONS,
        onChange: function (value) {
          setAttributes({ layoutVariant: value || 'grid' });
        },
      }),
      el(SelectControl, {
        key: 'sortMode',
        label: 'Sortierung',
        value: attrs.sortMode || 'auto',
        options: optionSets.RELATED_CARDS_SORT_OPTIONS,
        onChange: function (value) {
          setAttributes({ sortMode: value || 'auto' });
        },
      }),
      shouldShowColumns(attrs)
        ? el(SelectControl, {
            key: 'columns',
            label: 'Spalten',
            value: String(attrs.columns || 3),
            options: optionSets.RELATED_CARDS_COLUMN_OPTIONS,
            onChange: function (value) {
              setAttributes({ columns: parseInt(value, 10) || 3 });
            },
          })
        : null,
      ToggleControl
        ? el(ToggleControl, {
            key: 'showImage',
            label: 'Bild zeigen',
            checked: attrs.showImage !== false,
            onChange: function (value) {
              setAttributes({ showImage: !!value });
            },
          })
        : null,
      el(SelectControl, {
        key: 'skin',
        label: 'Skin',
        value: attrs.skin || 'default',
        options: optionSets.RELATED_CARDS_SKIN_OPTIONS,
        onChange: function (value) {
          setAttributes({ skin: value || 'default' });
        },
      }),
    ];
  }

  window.issRelationsEditor.relatedCardsControls = {
    getSelectedPostTypes: getSelectedPostTypes,
    renderInspectorControls: renderInspectorControls,
    renderPostTypeControl: renderPostTypeControl,
    renderPreviewItems: renderPreviewItems,
    getPreviewText: getPreviewText,
  };
})();
