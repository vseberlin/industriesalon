(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor.InspectorControls;
  const InnerBlocks = window.wp.blockEditor.InnerBlocks;
  const RichText = window.wp.blockEditor.RichText;
  const PanelBody = window.wp.components.PanelBody;
  const TextControl = window.wp.components.TextControl;

  const BODY_TEMPLATE = [['core/paragraph', { placeholder: 'Kapiteltext schreiben ...' }]];
  const BODY_ALLOWED_BLOCKS = [
    'core/paragraph',
    'core/heading',
    'core/list',
    'core/quote',
    'core/image',
    'iss/related-place-map',
    'iss/related-content',
  ];

  function sanitizeAnchor(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  window.wp.blocks.registerBlockType('iss/publication-longread-chapter', {
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};

      return el(
        'section',
        { className: (props.className || '') + ' iss-publication-longread-chapter-editor' },
        InspectorControls
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'Kapitel', initialOpen: false },
                el(TextControl, {
                  label: 'Anker',
                  help: 'Optional. Leer lassen, wenn der Anker aus dem Titel entstehen soll.',
                  value: attrs.anchor || '',
                  onChange: function (value) {
                    setAttributes({ anchor: sanitizeAnchor(value) });
                  },
                })
              )
            )
          : null,
        RichText
          ? el(RichText, {
              tagName: 'h2',
              className: 'iss-publication-longread-source__chapter-title',
              value: attrs.title || '',
              placeholder: 'Kapiteltitel',
              onChange: function (value) {
                setAttributes({ title: value });
              },
            })
          : null,
        el(
          'div',
          { className: 'iss-publication-longread-source__chapter-body' },
          el(InnerBlocks, {
            allowedBlocks: BODY_ALLOWED_BLOCKS,
            template: BODY_TEMPLATE,
            templateLock: false,
            renderAppender: InnerBlocks.ButtonBlockAppender,
          })
        )
      );
    },
    save: function (props) {
      var attrs = props.attributes || {};
      var title = attrs.title || '';
      var anchor = attrs.anchor || '';

      return el(
        'section',
        { className: 'iss-publication-longread-source__chapter' },
        title && RichText && RichText.Content
          ? el(RichText.Content, {
              tagName: 'h2',
              id: anchor || undefined,
              className: 'wp-block-heading iss-publication-longread-source__chapter-title',
              value: title,
            })
          : null,
        el(
          'div',
          { className: 'iss-publication-longread-source__chapter-body' },
          el(InnerBlocks.Content)
        )
      );
    },
  });
})();
