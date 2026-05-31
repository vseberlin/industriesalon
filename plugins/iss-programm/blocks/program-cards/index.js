(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const TextControl = window.wp.components && window.wp.components.TextControl;
  const TextareaControl = window.wp.components && window.wp.components.TextareaControl;
  const RangeControl = window.wp.components && window.wp.components.RangeControl;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;
  const CheckboxControl = window.wp.components && window.wp.components.CheckboxControl;

  function normalizeList(values) {
    if (!Array.isArray(values)) return [];
    return values
      .map(function (value) {
        return String(value || '').trim();
      })
      .filter(function (value, index, items) {
        return value && items.indexOf(value) === index;
      });
  }

  function toggleListValue(values, target, checked) {
    var next = normalizeList(values);
    var index = next.indexOf(target);
    if (checked && index === -1) {
      next.push(target);
    }
    if (!checked && index !== -1) {
      next.splice(index, 1);
    }
    return next;
  }

  function renderCheckboxGroup(choices, selectedValues, onToggle) {
    if (!CheckboxControl || !Array.isArray(choices) || !choices.length) return null;
    var selected = normalizeList(selectedValues);
    return choices.map(function (choice) {
      return el(CheckboxControl, {
        key: choice.value,
        label: choice.label,
        checked: selected.indexOf(choice.value) !== -1,
        onChange: function (checked) {
          onToggle(choice.value, !!checked);
        }
      });
    });
  }

  window.wp.blocks.registerBlockType('industriesalon/program-cards', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};
      const dataSelect = window.wp.data && window.wp.data.select ? window.wp.data.select('core') : null;
      const postTypeRecords = dataSelect && typeof dataSelect.getPostTypes === 'function' ? dataSelect.getPostTypes({ per_page: -1 }) : [];
      const postTypeChoices = Array.isArray(postTypeRecords)
        ? postTypeRecords
            .filter(function (postType) {
              return postType && postType.slug && postType.viewable !== false && postType.slug !== 'attachment';
            })
            .map(function (postType) {
              return {
                value: postType.slug,
                label: postType.labels && postType.labels.singular_name ? postType.labels.singular_name : postType.slug
              };
            })
        : [];

      const typeChoices = [
        { value: 'fuehrungen', label: 'Führungen' },
        { value: 'veranstaltungen', label: 'Veranstaltungen' },
        { value: 'ausstellungen', label: 'Ausstellungen' }
      ];

      const timeModeChoices = [
        { value: 'upcoming', label: 'Kommend' },
        { value: 'month', label: 'Monat' },
        { value: 'past', label: 'Archiv' },
        { value: 'all', label: 'Alle' }
      ];

      const postTypesList = normalizeList(attrs.postTypesList || []);
      const presetItemTypesList = normalizeList(attrs.presetItemTypesList || []);
      const postTypeCheckboxes = renderCheckboxGroup(postTypeChoices, postTypesList, function (value, checked) {
        setAttributes({ postTypesList: toggleListValue(postTypesList, value, checked) });
      });
      const typeCheckboxes = renderCheckboxGroup(typeChoices, presetItemTypesList, function (value, checked) {
        setAttributes({ presetItemTypesList: toggleListValue(presetItemTypesList, value, checked) });
      });

      const controls =
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'Programm Karten', initialOpen: true },
                TextControl
                  ? el(TextControl, {
                      label: 'Kicker',
                      value: attrs.kicker || '',
                      onChange: function (v) {
                        setAttributes({ kicker: v });
                      }
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Titel',
                      value: attrs.title || '',
                      onChange: function (v) {
                        setAttributes({ title: v });
                      }
                    })
                  : null,
                TextareaControl
                  ? el(TextareaControl, {
                      label: 'Intro',
                      value: attrs.intro || '',
                      onChange: function (v) {
                        setAttributes({ intro: v });
                      }
                    })
                  : null,
                RangeControl
                  ? el(RangeControl, {
                      label: 'Anzahl',
                      value: attrs.limit || 3,
                      min: 1,
                      max: 12,
                      onChange: function (v) {
                        setAttributes({ limit: v });
                      }
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Gruppe (Slug, optional)',
                      value: attrs.group || '',
                      onChange: function (v) {
                        setAttributes({ group: v });
                      }
                    })
                  : null,
                SelectControl
                  ? el(SelectControl, {
                      label: 'Zeitraum',
                      value: attrs.timeMode || 'upcoming',
                      options: timeModeChoices,
                      onChange: function (v) {
                        setAttributes({ timeMode: v });
                      }
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Monat (YYYY-MM)',
                      value: attrs.defaultMonth || '',
                      onChange: function (v) {
                        setAttributes({ defaultMonth: v });
                      }
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Laufende Ausstellungen einbeziehen',
                      checked: !!attrs.includeRunningRanges,
                      onChange: function (v) {
                        setAttributes({ includeRunningRanges: !!v });
                      }
                    })
                  : null
              ),
              el(
                PanelBody,
                { title: 'Inhalte', initialOpen: false },
                postTypeCheckboxes,
                typeCheckboxes
              ),
              el(
                PanelBody,
                { title: 'Anzeige', initialOpen: false },
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Bild zeigen',
                      checked: attrs.showImage !== false,
                      onChange: function (v) {
                        setAttributes({ showImage: !!v });
                      }
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Text zeigen',
                      checked: attrs.showExcerpt !== false,
                      onChange: function (v) {
                        setAttributes({ showExcerpt: !!v });
                      }
                    })
                  : null,
                RangeControl
                  ? el(RangeControl, {
                      label: 'Textlänge',
                      value: attrs.excerptLength || 20,
                      min: 8,
                      max: 40,
                      onChange: function (v) {
                        setAttributes({ excerptLength: v });
                      }
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Datum zeigen',
                      checked: attrs.showDate !== false,
                      onChange: function (v) {
                        setAttributes({ showDate: !!v });
                      }
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Typ zeigen',
                      checked: attrs.showType !== false,
                      onChange: function (v) {
                        setAttributes({ showType: !!v });
                      }
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Link zeigen',
                      checked: attrs.showButton !== false,
                      onChange: function (v) {
                        setAttributes({ showButton: !!v });
                      }
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Linktext',
                      value: attrs.buttonText || '',
                      onChange: function (v) {
                        setAttributes({ buttonText: v });
                      }
                    })
                  : null
              )
            )
          : null;

      return el(
        'div',
        null,
        controls,
        el('p', null, 'Programm Karten (server-rendered, nutzt die Timeline-Abfrage).')
      );
    },
    save: function () {
      return null;
    }
  });
})();
