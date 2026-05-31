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

  function renderSummaryLabelControls(attrs, setAttributes) {
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
          label: 'Start anzeigen',
          checked: attrs.showSummaryStart !== false,
          onChange: function (value) {
            setAttributes({ showSummaryStart: !!value });
          },
        }),
        el(TextControl, {
          label: 'Startlabel',
          disabled: attrs.showSummaryStart === false,
          value: attrs.summaryStartLabel || '',
          onChange: function (value) {
            setAttributes({ summaryStartLabel: value });
          },
        }),
        el(ToggleControl, {
          label: 'Ende anzeigen',
          checked: attrs.showSummaryEnd !== false,
          onChange: function (value) {
            setAttributes({ showSummaryEnd: !!value });
          },
        }),
        el(TextControl, {
          label: 'Endlabel',
          disabled: attrs.showSummaryEnd === false,
          value: attrs.summaryEndLabel || '',
          onChange: function (value) {
            setAttributes({ summaryEndLabel: value });
          },
        }),
        el(ToggleControl, {
          label: 'Stationen anzeigen',
          checked: attrs.showSummaryItems !== false,
          onChange: function (value) {
            setAttributes({ showSummaryItems: !!value });
          },
        }),
        el(TextControl, {
          label: 'Stationslabel',
          disabled: attrs.showSummaryItems === false,
          value: attrs.summaryItemsLabel || '',
          onChange: function (value) {
            setAttributes({ summaryItemsLabel: value });
          },
        }),
        el(ToggleControl, {
          label: 'Epochen anzeigen',
          checked: attrs.showSummaryEpochs !== false,
          onChange: function (value) {
            setAttributes({ showSummaryEpochs: !!value });
          },
        }),
        el(TextControl, {
          label: 'Epochenlabel',
          disabled: attrs.showSummaryEpochs === false,
          value: attrs.summaryEpochsLabel || '',
          onChange: function (value) {
            setAttributes({ summaryEpochsLabel: value });
          },
        })
      )
    );
  }

  window.wp.blocks.registerBlockType('iss/publication-timeline', {
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};

      return el(
        Fragment,
        null,
        renderSummaryLabelControls(attrs, setAttributes),
        el(
          'section',
          { className: (props.className || '') + ' iss-publication-timeline-editor' },
          el(
            'div',
            { className: 'components-placeholder is-large' },
            el('strong', null, 'Publikations-Zeitleiste'),
            el(
              'p',
              null,
              'Jede Station erzeugt automatisch Jahr, Auswertung und Epochen-Navigation im Frontend.'
            ),
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
              : null
          ),
          el(
            'div',
            { className: 'iss-publication-chronicle' },
            el(InnerBlocks, {
              allowedBlocks: ['iss/publication-timeline-item'],
              template: [['iss/publication-timeline-item']],
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

      children.push(
        el(
          'div',
          { className: 'wp-block-group iss-publication-chronicle' },
          el(InnerBlocks.Content)
        )
      );

      return el(Fragment, null, children);
    },
  });
})();
