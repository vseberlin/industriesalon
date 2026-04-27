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
        },
      });
    });
  }

  function normalizeRuleList(rules) {
    if (!Array.isArray(rules)) return [];
    return rules
      .filter(function (rule) {
        return rule && rule.taxonomy;
      })
      .map(function (rule) {
        return {
          taxonomy: String(rule.taxonomy || ''),
          label: String(rule.label || ''),
          terms: normalizeList(rule.terms || []),
        };
      });
  }

  function setRuleTerms(rules, taxonomy, terms) {
    var next = normalizeRuleList(rules);
    var found = false;
    next = next.map(function (rule) {
      if (rule.taxonomy !== taxonomy) return rule;
      found = true;
      return {
        taxonomy: taxonomy,
        label: rule.label || '',
        terms: normalizeList(terms),
      };
    });
    if (!found) {
      next.push({ taxonomy: taxonomy, label: '', terms: normalizeList(terms) });
    }
    return next.filter(function (rule) {
      return rule.taxonomy;
    });
  }

  function removeRule(rules, taxonomy) {
    return normalizeRuleList(rules).filter(function (rule) {
      return rule.taxonomy !== taxonomy;
    });
  }

  window.wp.blocks.registerBlockType('industriesalon/timeline-query', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};
      const dataSelect = window.wp.data && window.wp.data.select ? window.wp.data.select('core') : null;
      const postTypeRecords = dataSelect && typeof dataSelect.getPostTypes === 'function' ? dataSelect.getPostTypes({ per_page: -1 }) : [];
      const taxonomyRecords = dataSelect && typeof dataSelect.getTaxonomies === 'function' ? dataSelect.getTaxonomies({ per_page: -1 }) : [];
      const postTypeChoices = Array.isArray(postTypeRecords)
        ? postTypeRecords
            .filter(function (postType) {
              return postType && postType.slug && postType.viewable !== false && postType.slug !== 'attachment';
            })
            .map(function (postType) {
              return {
                value: postType.slug,
                label: postType.labels && postType.labels.singular_name ? postType.labels.singular_name : postType.slug,
              };
            })
        : [];
      const taxonomyChoices = Array.isArray(taxonomyRecords)
        ? taxonomyRecords
            .filter(function (taxonomy) {
              return taxonomy && taxonomy.slug && taxonomy.visibility && taxonomy.visibility.publicly_queryable !== false;
            })
            .map(function (taxonomy) {
              return {
                value: taxonomy.slug,
                label: taxonomy.labels && taxonomy.labels.singular_name ? taxonomy.labels.singular_name : taxonomy.slug,
              };
            })
        : [];
      const typeChoices = [
        { value: 'fuehrungen', label: 'Führungen' },
        { value: 'veranstaltungen', label: 'Veranstaltungen' },
        { value: 'ausstellungen', label: 'Ausstellungen' },
      ];
      const timeModeChoices = [
        { value: 'upcoming', label: 'Kommend' },
        { value: 'month', label: 'Monat' },
        { value: 'past', label: 'Archiv' },
        { value: 'all', label: 'Alle' },
      ];
      const allowedTimeModesList = normalizeList(attrs.allowedTimeModesList && attrs.allowedTimeModesList.length ? attrs.allowedTimeModesList : ['upcoming', 'month', 'past', 'all']);
      const allowedTypesList = normalizeList(attrs.allowedTypesList && attrs.allowedTypesList.length ? attrs.allowedTypesList : ['fuehrungen', 'veranstaltungen']);
      const presetItemTypesList = normalizeList(attrs.presetItemTypesList || []);
      const postTypesList = normalizeList(attrs.postTypesList || []);
      const taxonomyPresetRules = normalizeRuleList(attrs.taxonomyPresetRules || []);
      const taxonomyUiRules = normalizeRuleList(attrs.taxonomyUiRules || []);
      const defaultTypeOptions = [{ label: 'Alle', value: 'all' }].concat(
        typeChoices
          .filter(function (choice) {
            return allowedTypesList.indexOf(choice.value) !== -1;
          })
          .map(function (choice) {
            return { label: choice.label, value: choice.value };
          })
      );
      const timeModeOptions = timeModeChoices.filter(function (choice) {
        return allowedTimeModesList.indexOf(choice.value) !== -1;
      });
      const presetControls = [];
      const taxonomyControls = [];

      var timeModeCheckboxes = renderCheckboxGroup(timeModeChoices, allowedTimeModesList, function (value, checked) {
        setAttributes({ allowedTimeModesList: toggleListValue(allowedTimeModesList, value, checked) });
      });
      if (Array.isArray(timeModeCheckboxes)) {
        presetControls.push.apply(presetControls, timeModeCheckboxes);
      }

      var typeCheckboxes = renderCheckboxGroup(typeChoices, allowedTypesList, function (value, checked) {
        setAttributes({ allowedTypesList: toggleListValue(allowedTypesList, value, checked) });
      });
      if (Array.isArray(typeCheckboxes)) {
        presetControls.push.apply(presetControls, typeCheckboxes);
      }

      var presetTypeCheckboxes = renderCheckboxGroup(typeChoices, presetItemTypesList, function (value, checked) {
        setAttributes({ presetItemTypesList: toggleListValue(presetItemTypesList, value, checked) });
      });
      if (Array.isArray(presetTypeCheckboxes)) {
        presetControls.push(
          el('p', { key: 'presetTypeLabel' }, 'Fixed item types (used when no visible type filter selection overrides them).')
        );
        presetControls.push.apply(presetControls, presetTypeCheckboxes);
      }

      var postTypeCheckboxes = renderCheckboxGroup(postTypeChoices, postTypesList, function (value, checked) {
        setAttributes({ postTypesList: toggleListValue(postTypesList, value, checked) });
      });
      if (Array.isArray(postTypeCheckboxes)) {
        presetControls.push.apply(presetControls, postTypeCheckboxes);
      }

      taxonomyChoices.forEach(function (taxonomyChoice) {
        var taxonomySlug = taxonomyChoice.value;
        var taxonomyLabel = taxonomyChoice.label;
        var termRecords = dataSelect && typeof dataSelect.getEntityRecords === 'function'
          ? dataSelect.getEntityRecords('taxonomy', taxonomySlug, { per_page: -1, hide_empty: false })
          : [];
        var termChoices = Array.isArray(termRecords)
          ? termRecords.map(function (term) {
              return {
                value: term.slug,
                label: term.name,
              };
            })
          : [];
        var presetRule = taxonomyPresetRules.find(function (rule) { return rule.taxonomy === taxonomySlug; }) || null;
        var uiRule = taxonomyUiRules.find(function (rule) { return rule.taxonomy === taxonomySlug; }) || null;

        taxonomyControls.push(
          el(ToggleControl, {
            key: taxonomySlug + '-preset-toggle',
            label: 'Preset: ' + taxonomyLabel,
            checked: !!presetRule,
            onChange: function (checked) {
              setAttributes({
                taxonomyPresetRules: checked
                  ? setRuleTerms(taxonomyPresetRules, taxonomySlug, presetRule ? presetRule.terms : [])
                  : removeRule(taxonomyPresetRules, taxonomySlug),
              });
            },
          })
        );
        if (presetRule && termChoices.length) {
          var presetTermControls = renderCheckboxGroup(termChoices, presetRule.terms, function (value, checked) {
            var nextTerms = toggleListValue(presetRule.terms, value, checked);
            setAttributes({ taxonomyPresetRules: setRuleTerms(taxonomyPresetRules, taxonomySlug, nextTerms) });
          });
          if (Array.isArray(presetTermControls)) {
            taxonomyControls.push.apply(taxonomyControls, presetTermControls);
          }
        }

        taxonomyControls.push(
          el(ToggleControl, {
            key: taxonomySlug + '-ui-toggle',
            label: 'Visible filter: ' + taxonomyLabel,
            checked: !!uiRule,
            onChange: function (checked) {
              setAttributes({
                taxonomyUiRules: checked
                  ? setRuleTerms(taxonomyUiRules, taxonomySlug, uiRule ? uiRule.terms : [])
                  : removeRule(taxonomyUiRules, taxonomySlug),
              });
            },
          })
        );
        if (uiRule) {
          taxonomyControls.push(
            el(TextControl, {
              key: taxonomySlug + '-ui-label',
              label: taxonomyLabel + ' label',
              value: uiRule.label || taxonomyLabel,
              onChange: function (value) {
                var nextRules = normalizeRuleList(taxonomyUiRules).map(function (rule) {
                  if (rule.taxonomy !== taxonomySlug) return rule;
                  return { taxonomy: rule.taxonomy, terms: rule.terms, label: value };
                });
                setAttributes({ taxonomyUiRules: nextRules });
              },
            })
          );
        }
        if (uiRule && termChoices.length) {
          var uiTermControls = renderCheckboxGroup(termChoices, uiRule.terms, function (value, checked) {
            var nextTerms = toggleListValue(uiRule.terms, value, checked);
            var nextRules = normalizeRuleList(taxonomyUiRules).map(function (rule) {
              if (rule.taxonomy !== taxonomySlug) return rule;
              return { taxonomy: rule.taxonomy, label: rule.label, terms: nextTerms };
            });
            setAttributes({ taxonomyUiRules: nextRules });
          });
          if (Array.isArray(uiTermControls)) {
            taxonomyControls.push.apply(taxonomyControls, uiTermControls);
          }
        }
      });

      const controls =
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'Timeline Query', initialOpen: true },
                TextControl
                  ? el(TextControl, {
                      label: 'Title',
                      value: attrs.title || '',
                      onChange: function (v) {
                        setAttributes({ title: v });
                      },
                    })
                  : null,
                TextareaControl
                  ? el(TextareaControl, {
                      label: 'Intro',
                      value: attrs.intro || '',
                      onChange: function (v) {
                        setAttributes({ intro: v });
                      },
                    })
                  : null,
                RangeControl
                  ? el(RangeControl, {
                      label: 'Limit',
                      value: attrs.limit || 12,
                      min: 1,
                      max: 50,
                      onChange: function (v) {
                        setAttributes({ limit: v });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Group (slug, optional)',
                      value: attrs.group || '',
                      onChange: function (v) {
                        setAttributes({ group: v });
                      },
                    })
                  : null,
                SelectControl
                  ? el(SelectControl, {
                      label: 'Time mode',
                      value: attrs.timeMode || 'upcoming',
                      options: timeModeOptions.length ? timeModeOptions : timeModeChoices,
                      onChange: function (v) {
                        setAttributes({ timeMode: v });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Default month (YYYY-MM)',
                      value: attrs.defaultMonth || '',
                      onChange: function (v) {
                        setAttributes({ defaultMonth: v });
                      },
                    })
                  : null,
                TextControl
                  && SelectControl
                  ? el(SelectControl, {
                      label: 'Default type',
                      value: attrs.defaultType || 'all',
                      options: defaultTypeOptions,
                      onChange: function (v) {
                        setAttributes({ defaultType: v });
                      },
                    })
                  : null
              ),
              el(
                PanelBody,
                { title: 'Visible Filters', initialOpen: false },
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show result count / empty note',
                      checked: attrs.showMeta !== false,
                      onChange: function (v) {
                        setAttributes({ showMeta: !!v });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show time scope filter',
                      checked: !!attrs.showTimeModeFilter,
                      onChange: function (v) {
                        setAttributes({ showTimeModeFilter: !!v });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show type filter',
                      checked: attrs.showTypeFilter !== false,
                      onChange: function (v) {
                        setAttributes({ showTypeFilter: !!v });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show month filter',
                      checked: !!attrs.showMonthFilter,
                      onChange: function (v) {
                        setAttributes({ showMonthFilter: !!v });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show post type filter',
                      checked: !!attrs.showPostTypeFilter,
                      onChange: function (v) {
                        setAttributes({ showPostTypeFilter: !!v });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Group by year',
                      checked: !!attrs.yearGrouping,
                      onChange: function (v) {
                        setAttributes({ yearGrouping: !!v });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show load more',
                      checked: !!attrs.showLoadMore,
                      onChange: function (v) {
                        setAttributes({ showLoadMore: !!v });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show bottom button',
                      checked: !!attrs.showBottomButton,
                      onChange: function (v) {
                        setAttributes({ showBottomButton: !!v });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Load more text',
                      value: attrs.loadMoreText || '',
                      onChange: function (v) {
                        setAttributes({ loadMoreText: v });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Bottom button text',
                      value: attrs.bottomButtonText || '',
                      onChange: function (v) {
                        setAttributes({ bottomButtonText: v });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Bottom button URL',
                      value: attrs.bottomButtonUrl || '',
                      onChange: function (v) {
                        setAttributes({ bottomButtonUrl: v });
                      },
                    })
                  : null
              ),
              el(
                PanelBody,
                { title: 'Presets', initialOpen: false },
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Laufende Ausstellungen einbeziehen',
                      checked: !!attrs.includeRunningRanges,
                      onChange: function (v) {
                        setAttributes({ includeRunningRanges: !!v });
                      },
                    })
                  : null,
                presetControls
              ),
              el(
                PanelBody,
                { title: 'Taxonomies', initialOpen: false },
                taxonomyControls
              )
            )
          : null;

      return el(
        'div',
        null,
        controls,
        el('p', null, 'Filterable timeline query (server-rendered, AJAX-enhanced).')
      );
    },
    save: function () {
      return null;
    },
  });
})();
