(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const RangeControl = window.wp.components && window.wp.components.RangeControl;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;

  window.wp.blocks.registerBlockType('industriesalon/ausstellungen-browser', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};
      const controls =
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'Ausstellungen Browser', initialOpen: true },
                TextControl
                  ? el(TextControl, {
                      label: 'Titel',
                      value: attrs.title || '',
                      onChange: function (value) {
                        setAttributes({ title: value });
                      },
                    })
                  : null,
                SelectControl
                  ? el(SelectControl, {
                      label: 'Standardfilter',
                      value: attrs.defaultFilter || 'aktuell',
                      options: [
                        { label: 'Aktuell', value: 'aktuell' },
                        { label: 'Dauer', value: 'dauer' },
                        { label: 'Digital', value: 'digital' },
                        { label: 'Archiv', value: 'archiv' },
                      ],
                      onChange: function (value) {
                        setAttributes({ defaultFilter: value });
                      },
                    })
                  : null,
                RangeControl
                  ? el(RangeControl, {
                      label: 'Anzahl',
                      min: 1,
                      max: 24,
                      value: attrs.limit || 6,
                      onChange: function (value) {
                        setAttributes({ limit: value || 6 });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Filter anzeigen',
                      checked: attrs.showFilters !== false,
                      onChange: function (value) {
                        setAttributes({ showFilters: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Suche anzeigen',
                      checked: attrs.showSearch !== false,
                      onChange: function (value) {
                        setAttributes({ showSearch: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Meta anzeigen',
                      checked: attrs.showMeta !== false,
                      onChange: function (value) {
                        setAttributes({ showMeta: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Text anzeigen',
                      checked: attrs.showSummary !== false,
                      onChange: function (value) {
                        setAttributes({ showSummary: !!value });
                      },
                    })
                  : null
              )
            )
          : null;

      return el(
        'div',
        { className: 'iss-ausstellungen-browser is-editor-preview' },
        controls,
        el('p', { className: 'iss-kicker iss-kicker--compact' }, 'Ausstellungen'),
        el('h3', {}, attrs.title || 'Ausstellungen im Überblick'),
        el('p', {}, attrs.showSearch === false ? 'Filter: Aktuell, Dauer, Digital, Archiv' : 'Filter und Suche')
      );
    },
    save: function () {
      return null;
    },
  });
})();
