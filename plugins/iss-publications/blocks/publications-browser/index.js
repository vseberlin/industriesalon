(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const InspectorControls = window.wp.blockEditor.InspectorControls;
  const InnerBlocks = window.wp.blockEditor.InnerBlocks;
  const RichText = window.wp.blockEditor.RichText;
  const PanelBody = window.wp.components.PanelBody;
  const TextControl = window.wp.components.TextControl;

  const FORMAT_KEYS = ['standard', 'timeline', 'longread', 'photoalbum'];
  const DISCOVERY_LINKS = ['Archiv', 'Führungen', 'Sammlungen', 'Ausstellungen', 'Veranstaltungen'];

  const BROWSER_TEMPLATE = [
    [
      'iss/publications-browser-sidebar',
      {},
    ],
    [
      'iss/publications-browser-results',
      {},
      [
        [
          'iss/publications-browser-format',
          {
            format: 'standard',
          },
        ],
        [
          'iss/publications-browser-format',
          {
            format: 'timeline',
          },
        ],
        [
          'iss/publications-browser-format',
          {
            format: 'longread',
          },
        ],
        [
          'iss/publications-browser-format',
          {
            format: 'photoalbum',
          },
        ],
      ],
    ],
  ];

  const RESULTS_TEMPLATE = BROWSER_TEMPLATE[1][2];

  function objectValue(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  }

  function readCopy(attrs, key) {
    return String(objectValue(attrs.copy)[key] || '');
  }

  function readContextCopy(props, key) {
    const context = props && props.context ? props.context : {};
    return String(objectValue(context['issPublicationsBrowser/copy'])[key] || '');
  }

  function readContextNested(props, contextKey, itemKey, valueKey) {
    const context = props && props.context ? props.context : {};
    const item = objectValue(objectValue(context[contextKey])[itemKey]);
    return String(item[valueKey] || '');
  }

  function previewCount(props, key, count) {
    const label = readContextCopy(props, key);
    return String(count) + (label ? ' ' + label : '');
  }

  function writeCopy(attrs, setAttributes, key, value) {
    const copy = Object.assign({}, objectValue(attrs.copy));
    copy[key] = value;
    setAttributes({ copy: copy });
  }

  function readNested(attrs, group, itemKey, valueKey) {
    const items = objectValue(attrs[group]);
    const item = objectValue(items[itemKey]);
    return String(item[valueKey] || '');
  }

  function writeNested(attrs, setAttributes, group, itemKey, valueKey, value) {
    const items = Object.assign({}, objectValue(attrs[group]));
    const item = Object.assign({}, objectValue(items[itemKey]));
    item[valueKey] = value;
    items[itemKey] = item;
    setAttributes({ [group]: items });
  }

  function textControl(label, value, onChange) {
    return TextControl
      ? el(TextControl, {
          label: label,
          value: value,
          onChange: onChange,
        })
      : null;
  }

  function editableText(tagName, className, value, placeholder, onChange) {
    return RichText
      ? el(RichText, {
          tagName: tagName,
          className: className,
          value: value,
          placeholder: placeholder,
          allowedFormats: [],
          onChange: onChange,
        })
      : el(tagName, { className: className }, value || placeholder);
  }

  function renderControls(attrs, setAttributes) {
    if (!InspectorControls || !PanelBody) return null;

    return el(
      InspectorControls,
      null,
      el(
        PanelBody,
        { title: 'Operational labels', initialOpen: true },
        textControl('Aria label', readCopy(attrs, 'ariaLabel'), function (value) {
          writeCopy(attrs, setAttributes, 'ariaLabel', value);
        }),
        textControl('All topics label', readCopy(attrs, 'filterAllLabel'), function (value) {
          writeCopy(attrs, setAttributes, 'filterAllLabel', value);
        }),
        textControl('Empty result text', readCopy(attrs, 'emptyText'), function (value) {
          writeCopy(attrs, setAttributes, 'emptyText', value);
        }),
        textControl('Discovery title', readCopy(attrs, 'discoveryTitle'), function (value) {
          writeCopy(attrs, setAttributes, 'discoveryTitle', value);
        })
      ),
      el(
        PanelBody,
        { title: 'Count labels', initialOpen: false },
        textControl('Section count singular', readCopy(attrs, 'sectionCountSingular'), function (value) {
          writeCopy(attrs, setAttributes, 'sectionCountSingular', value);
        }),
        textControl('Section count plural', readCopy(attrs, 'sectionCountPlural'), function (value) {
          writeCopy(attrs, setAttributes, 'sectionCountPlural', value);
        })
      ),
      el(
        PanelBody,
        { title: 'Topic labels', initialOpen: false },
        textControl('Topic label', readNested(attrs, 'filterCopy', 'shared_topic', 'label'), function (value) {
          writeNested(attrs, setAttributes, 'filterCopy', 'shared_topic', 'label', value);
        })
      ),
      el(
        PanelBody,
        { title: 'Chapter labels', initialOpen: false },
        FORMAT_KEYS.map(function (key) {
          return textControl(key, readNested(attrs, 'formatCopy', key, 'label'), function (value) {
            writeNested(attrs, setAttributes, 'formatCopy', key, 'label', value);
          });
        })
      )
    );
  }

  function registerSidebarBlock() {
    if (window.wp.blocks.getBlockType('iss/publications-browser-sidebar')) return;

    window.wp.blocks.registerBlockType('iss/publications-browser-sidebar', {
      usesContext: ['issPublicationsBrowser/copy', 'issPublicationsBrowser/formatCopy'],
      attributes: {
        kicker: {
          type: 'string',
          default: '',
        },
        title: {
          type: 'string',
          default: '',
        },
      },
      edit: function (props) {
        const attrs = props.attributes || {};
        const setAttributes = props.setAttributes || function () {};

        return el(
          'aside',
          { className: (props.className || '') + ' iss-publications-browser__sidebar' },
          el(
            'div',
            { className: 'iss-publications-browser__sidebar-inner' },
            el(
              'div',
              { className: 'iss-publications-browser__sidebar-head' },
              editableText('p', 'iss-kicker iss-kicker--compact', attrs.kicker || '', 'Kicker', function (value) {
                setAttributes({ kicker: value });
              }),
              editableText('h2', '', attrs.title || '', 'Title', function (value) {
                setAttributes({ title: value });
              })
            ),
            el(
              'nav',
              { className: 'iss-publications-browser__chapters', 'aria-label': attrs.title || 'Publication formats' },
              FORMAT_KEYS.map(function (key, index) {
                return el(
                  'a',
                  {
                    className: 'iss-publications-browser__chapter',
                    href: '#publikationen-' + key,
                    onClick: function (event) {
                      event.preventDefault();
                    },
                  },
                  el('span', null, readContextNested(props, 'issPublicationsBrowser/formatCopy', key, 'label') || key),
                  el('small', null, String([8, 3, 4, 2][index]))
                );
              })
            ),
            el(
              'nav',
              {
                className: 'iss-publications-browser__discovery',
                'aria-label': readContextCopy(props, 'discoveryTitle') || 'Weiterentdecken',
              },
              el(
                'h3',
                { className: 'iss-publications-browser__discovery-title' },
                readContextCopy(props, 'discoveryTitle') || 'Weiterentdecken'
              ),
              el(
                'ul',
                { className: 'iss-publications-browser__discovery-list' },
                DISCOVERY_LINKS.map(function (label) {
                  return el(
                    'li',
                    null,
                    el(
                      'a',
                      {
                        className: 'iss-publications-browser__discovery-link',
                        href: '#',
                        onClick: function (event) {
                          event.preventDefault();
                        },
                      },
                      label
                    )
                  );
                })
              )
            )
          )
        );
      },
      save: function () {
        return null;
      },
    });
  }

  function registerResultsBlock() {
    if (window.wp.blocks.getBlockType('iss/publications-browser-results')) return;

    window.wp.blocks.registerBlockType('iss/publications-browser-results', {
      usesContext: ['issPublicationsBrowser/copy', 'issPublicationsBrowser/filterCopy'],
      attributes: {
        kicker: {
          type: 'string',
          default: '',
        },
        title: {
          type: 'string',
          default: '',
        },
      },
      edit: function (props) {
        const attrs = props.attributes || {};
        const setAttributes = props.setAttributes || function () {};

        return el(
          'div',
          { className: (props.className || '') + ' iss-publications-browser__results' },
          el(
            'header',
            { className: 'iss-publications-browser__results-head' },
            el(
              'div',
              { className: 'iss-publications-browser__topic-heading' },
              editableText('p', 'iss-kicker iss-kicker--compact', attrs.kicker || '', 'Kicker', function (value) {
                setAttributes({ kicker: value });
              }),
              editableText('h2', '', attrs.title || '', 'Title', function (value) {
                setAttributes({ title: value });
              })
            ),
            el(
              'div',
              { className: 'iss-related-feed__carousel iss-publications-browser__topic-carousel is-carousel-ready' },
              el(
                'nav',
                {
                  className: 'iss-publications-browser__topics iss-related-feed__grid--layout-strip',
                  'aria-label': readContextNested(props, 'issPublicationsBrowser/filterCopy', 'shared_topic', 'label') || 'Topic',
                },
                [readContextCopy(props, 'filterAllLabel') || 'All topics', 'Topic', 'Place', 'Work'].map(function (label, index) {
                  return el(
                    'a',
                    {
                      className: 'iss-publications-browser__topic' + (index === 0 ? ' is-active' : ''),
                      href: '#',
                      onClick: function (event) {
                        event.preventDefault();
                      },
                    },
                    el('span', null, label),
                    el('small', null, String([17, 7, 5, 3][index]))
                  );
                })
              ),
              el(
                'div',
                { className: 'iss-related-feed__carousel-controls' },
                el(
                  'button',
                  {
                    type: 'button',
                    className: 'iss-related-feed__carousel-control iss-related-feed__carousel-control--prev',
                    disabled: true,
                  },
                  el('span', { className: 'iss-related-feed__carousel-control-icon', 'aria-hidden': true }, '\u2190'),
                  el('span', { className: 'iss-related-feed__carousel-control-text' }, 'Zurück')
                ),
                el(
                  'button',
                  {
                    type: 'button',
                    className: 'iss-related-feed__carousel-control iss-related-feed__carousel-control--next',
                    disabled: true,
                  },
                  el('span', { className: 'iss-related-feed__carousel-control-text' }, 'Weiter'),
                  el('span', { className: 'iss-related-feed__carousel-control-icon', 'aria-hidden': true }, '\u25B6')
                )
              )
            )
          ),
          el(InnerBlocks, {
            allowedBlocks: ['iss/publications-browser-format'],
            template: RESULTS_TEMPLATE,
            templateLock: 'all',
          })
        );
      },
      save: function () {
        return el(InnerBlocks.Content);
      },
    });
  }

  function registerFormatBlock() {
    if (window.wp.blocks.getBlockType('iss/publications-browser-format')) return;

    window.wp.blocks.registerBlockType('iss/publications-browser-format', {
      usesContext: ['issPublicationsBrowser/copy'],
      attributes: {
        format: {
          type: 'string',
          default: 'standard',
        },
        title: {
          type: 'string',
          default: '',
        },
        description: {
          type: 'string',
          default: '',
        },
      },
      edit: function (props) {
        const attrs = props.attributes || {};
        const setAttributes = props.setAttributes || function () {};
        const formatIndex = FORMAT_KEYS.indexOf(attrs.format || '');
        const indexLabel = ('0' + String((formatIndex >= 0 ? formatIndex : 0) + 1)).slice(-2);

        return el(
          'section',
          { className: (props.className || '') + ' iss-publications-browser__section' },
          el(
            'div',
            { className: 'iss-publications-browser__section-head' },
            el('span', { className: 'iss-publications-browser__section-index' }, indexLabel),
            el(
              'div',
              null,
              editableText('h3', '', attrs.title || '', 'Format title', function (value) {
                setAttributes({ title: value });
              }),
              editableText('p', '', attrs.description || '', 'Format description', function (value) {
                setAttributes({ description: value });
              })
            ),
            el('span', { className: 'iss-publications-browser__section-count' }, previewCount(props, 'sectionCountPlural', 3))
          )
        );
      },
      save: function () {
        return null;
      },
    });
  }

  registerSidebarBlock();
  registerResultsBlock();
  registerFormatBlock();

  if (!window.wp.blocks.getBlockType('iss/publications-browser')) {
    window.wp.blocks.registerBlockType('iss/publications-browser', {
      providesContext: {
        'issPublicationsBrowser/copy': 'copy',
        'issPublicationsBrowser/formatCopy': 'formatCopy',
        'issPublicationsBrowser/filterCopy': 'filterCopy',
      },
      attributes: {
        copy: {
          type: 'object',
          default: {},
        },
        formatCopy: {
          type: 'object',
          default: {},
        },
        filterCopy: {
          type: 'object',
          default: {},
        },
      },
      edit: function (props) {
        const attrs = props.attributes || {};
        const setAttributes = props.setAttributes || function () {};

        return el(
          Fragment,
          null,
          renderControls(attrs, setAttributes),
          el(
            'section',
            { className: (props.className || '') + ' iss-publications-browser iss-publications-browser--editor' },
            el(InnerBlocks, {
              allowedBlocks: ['iss/publications-browser-sidebar', 'iss/publications-browser-results'],
              template: BROWSER_TEMPLATE,
              templateLock: 'all',
            })
          )
        );
      },
      save: function () {
        return el(InnerBlocks.Content);
      },
    });
  }
})();
