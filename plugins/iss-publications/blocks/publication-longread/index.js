(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const InspectorControls = window.wp.blockEditor.InspectorControls;
  const InnerBlocks = window.wp.blockEditor.InnerBlocks;
  const RichText = window.wp.blockEditor.RichText;
  const PanelBody = window.wp.components.PanelBody;
  const TextControl = window.wp.components.TextControl;
  const ToggleControl = window.wp.components.ToggleControl;

  function renderSummaryControls(attrs, setAttributes) {
    if (!InspectorControls || !PanelBody || !TextControl || !ToggleControl) {
      return null;
    }

    return el(
      InspectorControls,
      null,
      el(
        PanelBody,
        { title: 'Rahmendaten', initialOpen: false },
        el(ToggleControl, {
          label: 'Kapitel anzeigen',
          checked: attrs.showSummaryChapters !== false,
          onChange: function (value) {
            setAttributes({ showSummaryChapters: !!value });
          },
        }),
        el(TextControl, {
          label: 'Kapitellabel',
          disabled: attrs.showSummaryChapters === false,
          value: attrs.summaryChaptersLabel || '',
          onChange: function (value) {
            setAttributes({ summaryChaptersLabel: value });
          },
        }),
        el(ToggleControl, {
          label: 'Unterthemen anzeigen',
          checked: attrs.showSummarySubheadings !== false,
          onChange: function (value) {
            setAttributes({ showSummarySubheadings: !!value });
          },
        }),
        el(TextControl, {
          label: 'Unterthemenlabel',
          disabled: attrs.showSummarySubheadings === false,
          value: attrs.summarySubheadingsLabel || '',
          onChange: function (value) {
            setAttributes({ summarySubheadingsLabel: value });
          },
        }),
        el(ToggleControl, {
          label: 'Themenfelder anzeigen',
          checked: attrs.showSummaryTopics !== false,
          onChange: function (value) {
            setAttributes({ showSummaryTopics: !!value });
          },
        }),
        el(TextControl, {
          label: 'Themenfeldlabel',
          disabled: attrs.showSummaryTopics === false,
          value: attrs.summaryTopicsLabel || '',
          onChange: function (value) {
            setAttributes({ summaryTopicsLabel: value });
          },
        }),
        el(ToggleControl, {
          label: 'Kontext anzeigen',
          checked: attrs.showSummaryContext !== false,
          onChange: function (value) {
            setAttributes({ showSummaryContext: !!value });
          },
        }),
        el(TextControl, {
          label: 'Kontextwert',
          disabled: attrs.showSummaryContext === false,
          value: attrs.summaryContextValue || '',
          onChange: function (value) {
            setAttributes({ summaryContextValue: value });
          },
        }),
        el(TextControl, {
          label: 'Kontextlabel',
          disabled: attrs.showSummaryContext === false,
          value: attrs.summaryContextLabel || '',
          onChange: function (value) {
            setAttributes({ summaryContextLabel: value });
          },
        })
      ),
      el(
        PanelBody,
        { title: 'Atlas', initialOpen: false },
        el(ToggleControl, {
          label: 'Atlas-Karte anzeigen',
          checked: attrs.showAtlasMap !== false,
          onChange: function (value) {
            setAttributes({ showAtlasMap: !!value });
          },
        })
      )
    );
  }

  window.wp.blocks.registerBlockType('iss/publication-longread', {
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};

      return el(
        Fragment,
        null,
        renderSummaryControls(attrs, setAttributes),
        el(
          'section',
          { className: (props.className || '') + ' iss-publication-longread-editor' },
          el(
            'div',
            { className: 'components-placeholder is-large' },
            el('strong', null, 'Publikations-Longread'),
            el('p', null, 'Kapitel erzeugen automatisch Navigation, Rahmendaten und die Longread-Darstellung im Frontend.'),
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
                  className: 'iss-publication-longread-source__intro',
                  value: attrs.introHtml || '',
                  placeholder: 'Einleitung des Longreads',
                  onChange: function (value) {
                    setAttributes({ introHtml: value });
                  },
                })
              : null
          ),
          el(
            'div',
            { className: 'iss-publication-longread-source__chapters' },
            el(InnerBlocks, {
              allowedBlocks: ['iss/publication-longread-chapter'],
              template: [['iss/publication-longread-chapter']],
              renderAppender: InnerBlocks.ButtonBlockAppender,
            })
          )
        )
      );
    },
    save: function (props) {
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
            className: 'iss-publication-longread-source__intro',
            value: attrs.introHtml,
          })
        );
      }

      children.push(
        el(
          'div',
          { className: 'iss-publication-longread-source__chapters' },
          el(InnerBlocks.Content)
        )
      );

      return el('div', { className: 'iss-publication-longread-source' }, children);
    },
  });
})();
