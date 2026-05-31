(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const InnerBlocks = window.wp.blockEditor.InnerBlocks;
  const RichText = window.wp.blockEditor.RichText;

  function hasValue(value) {
    return typeof value === 'string' && value.trim() !== '';
  }

  function legacySave(props) {
    var attrs = props.attributes || {};
    var children = [];

    if (attrs.sourceNote && attrs.sourceNote !== '' && RichText && RichText.Content) {
      children.push(
        el(RichText.Content, {
          tagName: 'p',
          className: 'iss-publication-source-note',
          value: attrs.sourceNote,
        })
      );
    }

    if (attrs.introHtml && attrs.introHtml !== '' && RichText && RichText.Content) {
      children.push(
        el(RichText.Content, {
          tagName: 'div',
          className: 'iss-publication-photoalbum-source__intro',
          value: attrs.introHtml,
        })
      );
    }

    children.push(
      el('h2', { className: 'wp-block-heading iss-publication-photoalbum-source__heading' }, 'Albumblaetter')
    );
    children.push(
      el(
        'div',
        { className: 'iss-publication-photoalbum-source__sheets' },
        el(InnerBlocks.Content)
      )
    );

    return el('div', { className: 'iss-publication-photoalbum-source' }, children);
  }

  window.wp.blocks.registerBlockType('iss/publication-photoalbum', {
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};

      return el(
        Fragment,
        null,
        el(
          'section',
          { className: (props.className || '') + ' iss-publication-photoalbum-editor' },
          el(
            'div',
            { className: 'components-placeholder is-large' },
            el('strong', null, 'Publikations-Fotoalbum'),
            el('p', null, 'Albumblätter erzeugen automatisch die linke Navigation im Frontend.'),
            RichText
              ? el(RichText, {
                  tagName: 'p',
                  className: 'iss-kicker iss-kicker--compact iss-publication-photoalbum-source__kicker',
                  value: attrs.headingKicker || '',
                  placeholder: 'Optionale Abschnittsmarke',
                  onChange: function (value) {
                    setAttributes({ headingKicker: value });
                  },
                })
              : null,
            RichText
              ? el(RichText, {
                  tagName: 'h2',
                  className: 'wp-block-heading iss-publication-photoalbum-source__heading',
                  value: attrs.headingTitle || '',
                  placeholder: 'Optionale Überschrift der Albumfolge',
                  onChange: function (value) {
                    setAttributes({ headingTitle: value });
                  },
                })
              : null,
            RichText
              ? el(RichText, {
                  tagName: 'p',
                  className: 'iss-publication-photoalbum-source__text',
                  value: attrs.headingText || '',
                  placeholder: 'Optionale Einordnung der Albumfolge',
                  onChange: function (value) {
                    setAttributes({ headingText: value });
                  },
                })
              : null,
            RichText
              ? el(RichText, {
                  tagName: 'p',
                  className: 'iss-publication-source-note',
                  value: attrs.sourceNote || '',
                  placeholder: 'Optionale Quellen- oder Redaktionsnotiz',
                  onChange: function (value) {
                    setAttributes({ sourceNote: value });
                  },
                })
              : null,
            RichText
              ? el(RichText, {
                  tagName: 'div',
                  multiline: 'p',
                  className: 'iss-publication-photoalbum-source__intro',
                  value: attrs.introHtml || '',
                  placeholder: 'Einleitung des Fotoalbums',
                  onChange: function (value) {
                    setAttributes({ introHtml: value });
                  },
                })
              : null
          ),
          el(
            'div',
            { className: 'iss-publication-photoalbum-source__sheets' },
            el(InnerBlocks, {
              allowedBlocks: ['iss/publication-photoalbum-sheet'],
              template: [['iss/publication-photoalbum-sheet', { sheetLabel: 'Blatt 01' }]],
              renderAppender: InnerBlocks.ButtonBlockAppender,
            })
          )
        )
      );
    },
    save: function (props) {
      var attrs = props.attributes || {};
      var children = [];

      if (hasValue(attrs.headingKicker) && RichText && RichText.Content) {
        children.push(
          el(RichText.Content, {
            tagName: 'p',
            className: 'iss-kicker iss-kicker--compact iss-publication-photoalbum-source__kicker',
            value: attrs.headingKicker,
          })
        );
      }

      if (hasValue(attrs.headingTitle) && RichText && RichText.Content) {
        children.push(
          el(RichText.Content, {
            tagName: 'h2',
            className: 'wp-block-heading iss-publication-photoalbum-source__heading',
            value: attrs.headingTitle,
          })
        );
      }

      if (hasValue(attrs.headingText) && RichText && RichText.Content) {
        children.push(
          el(RichText.Content, {
            tagName: 'p',
            className: 'iss-publication-photoalbum-source__text',
            value: attrs.headingText,
          })
        );
      }

      if (hasValue(attrs.sourceNote) && RichText && RichText.Content) {
        children.push(
          el(RichText.Content, {
            tagName: 'p',
            className: 'iss-publication-source-note',
            value: attrs.sourceNote,
          })
        );
      }

      if (hasValue(attrs.introHtml) && RichText && RichText.Content) {
        children.push(
          el(RichText.Content, {
            tagName: 'div',
            className: 'iss-publication-photoalbum-source__intro',
            value: attrs.introHtml,
          })
        );
      }

      children.push(
        el(
          'div',
          { className: 'iss-publication-photoalbum-source__sheets' },
          el(InnerBlocks.Content)
        )
      );

      return el('div', { className: 'iss-publication-photoalbum-source' }, children);
    },
    deprecated: [
      {
        attributes: {
          sourceNote: {
            type: 'string',
            default: '',
          },
          introHtml: {
            type: 'string',
            source: 'html',
            selector: '.iss-publication-photoalbum-source__intro',
            default: '',
          },
        },
        save: legacySave,
      },
    ],
  });
})();
