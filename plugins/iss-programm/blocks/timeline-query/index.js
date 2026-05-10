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
  const Button = window.wp.components && window.wp.components.Button;

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

  function setRuleLabel(rules, taxonomy, label) {
    return normalizeRuleList(rules).map(function (rule) {
      if (rule.taxonomy !== taxonomy) return rule;
      return {
        taxonomy: rule.taxonomy,
        label: String(label || ''),
        terms: normalizeList(rule.terms || []),
      };
    });
  }

  function removeRule(rules, taxonomy) {
    return normalizeRuleList(rules).filter(function (rule) {
      return rule.taxonomy !== taxonomy;
    });
  }

  function normalizePresetButtons(buttons) {
    if (!Array.isArray(buttons)) return [];

    return buttons
      .map(function (button) {
        if (!button) return null;

        return {
          label: String(button.label || '').trim(),
          timeMode: String(button.timeMode || '').trim(),
          taxonomy: String(button.taxonomy || '').trim(),
          terms: normalizeList(button.terms || []),
          isDefault: !!button.isDefault,
        };
      })
      .filter(function (button) {
        return button && button.label;
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
      const allowedTimeModesList = normalizeList(
        attrs.allowedTimeModesList && attrs.allowedTimeModesList.length ? attrs.allowedTimeModesList : ['upcoming', 'month', 'past', 'all']
      );
      const allowedTypesList = normalizeList(
        attrs.allowedTypesList && attrs.allowedTypesList.length ? attrs.allowedTypesList : ['fuehrungen', 'veranstaltungen']
      );
      const fixedItemTypesList = normalizeList(
        attrs.fixedItemTypesList && attrs.fixedItemTypesList.length ? attrs.fixedItemTypesList : attrs.presetItemTypesList || []
      );
      const postTypesList = normalizeList(attrs.postTypesList || []);
      const fixedTaxonomyRules = normalizeRuleList(
        attrs.fixedTaxonomyRules && attrs.fixedTaxonomyRules.length ? attrs.fixedTaxonomyRules : attrs.taxonomyPresetRules || []
      );
      const taxonomyUiRules = normalizeRuleList(attrs.taxonomyUiRules || []);
      const presetButtons = normalizePresetButtons(attrs.presetButtons || []);
      const showItemTypeFilter =
        typeof attrs.showItemTypeFilter === 'boolean' ? attrs.showItemTypeFilter : attrs.showTypeFilter !== false;
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
      const scopeControls = [];
      const fixedTaxonomyControls = [];
      const visibleTaxonomyControls = [];

      var timeModeCheckboxes = renderCheckboxGroup(timeModeChoices, allowedTimeModesList, function (value, checked) {
        setAttributes({ allowedTimeModesList: toggleListValue(allowedTimeModesList, value, checked) });
      });
      if (Array.isArray(timeModeCheckboxes)) {
        scopeControls.push(el('p', { key: 'allowed-time-modes-label' }, 'Allowed Zeitraum options'));
        scopeControls.push.apply(scopeControls, timeModeCheckboxes);
      }

      var typeCheckboxes = renderCheckboxGroup(typeChoices, allowedTypesList, function (value, checked) {
        setAttributes({ allowedTypesList: toggleListValue(allowedTypesList, value, checked) });
      });
      if (Array.isArray(typeCheckboxes)) {
        scopeControls.push(el('p', { key: 'allowed-item-types-label' }, 'Allowed Inhaltstyp options'));
        scopeControls.push.apply(scopeControls, typeCheckboxes);
      }

      var fixedTypeCheckboxes = renderCheckboxGroup(typeChoices, fixedItemTypesList, function (value, checked) {
        var nextValues = toggleListValue(fixedItemTypesList, value, checked);
        setAttributes({ fixedItemTypesList: nextValues, presetItemTypesList: nextValues });
      });
      if (Array.isArray(fixedTypeCheckboxes)) {
        scopeControls.push(el('p', { key: 'fixed-item-types-label' }, 'Fixed content families'));
        scopeControls.push.apply(scopeControls, fixedTypeCheckboxes);
      }

      var postTypeCheckboxes = renderCheckboxGroup(postTypeChoices, postTypesList, function (value, checked) {
        setAttributes({ postTypesList: toggleListValue(postTypesList, value, checked) });
      });
      if (Array.isArray(postTypeCheckboxes)) {
        scopeControls.push(el('p', { key: 'post-types-label' }, 'Fixed post types'));
        scopeControls.push.apply(scopeControls, postTypeCheckboxes);
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
        var fixedRule = fixedTaxonomyRules.find(function (rule) {
          return rule.taxonomy === taxonomySlug;
        }) || null;
        var uiRule = taxonomyUiRules.find(function (rule) {
          return rule.taxonomy === taxonomySlug;
        }) || null;

        fixedTaxonomyControls.push(
          el(ToggleControl, {
            key: taxonomySlug + '-fixed-toggle',
            label: 'Fixed filter: ' + taxonomyLabel,
            checked: !!fixedRule,
            onChange: function (checked) {
              var nextRules = checked
                ? setRuleTerms(fixedTaxonomyRules, taxonomySlug, fixedRule ? fixedRule.terms : [])
                : removeRule(fixedTaxonomyRules, taxonomySlug);
              setAttributes({
                fixedTaxonomyRules: nextRules,
                taxonomyPresetRules: nextRules,
              });
            },
          })
        );
        if (fixedRule && termChoices.length) {
          var fixedTermControls = renderCheckboxGroup(termChoices, fixedRule.terms, function (value, checked) {
            var nextTerms = toggleListValue(fixedRule.terms, value, checked);
            var nextRules = setRuleTerms(fixedTaxonomyRules, taxonomySlug, nextTerms);
            setAttributes({
              fixedTaxonomyRules: nextRules,
              taxonomyPresetRules: nextRules,
            });
          });
          if (Array.isArray(fixedTermControls)) {
            fixedTaxonomyControls.push.apply(fixedTaxonomyControls, fixedTermControls);
          }
        }

        visibleTaxonomyControls.push(
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
          visibleTaxonomyControls.push(
            el(TextControl, {
              key: taxonomySlug + '-ui-label',
              label: taxonomyLabel + ' label',
              value: uiRule.label || taxonomyLabel,
              onChange: function (value) {
                setAttributes({ taxonomyUiRules: setRuleLabel(taxonomyUiRules, taxonomySlug, value) });
              },
            })
          );
        }
        if (uiRule && termChoices.length) {
          var uiTermControls = renderCheckboxGroup(termChoices, uiRule.terms, function (value, checked) {
            var nextTerms = toggleListValue(uiRule.terms, value, checked);
            setAttributes({ taxonomyUiRules: setRuleTerms(taxonomyUiRules, taxonomySlug, nextTerms) });
          });
          if (Array.isArray(uiTermControls)) {
            visibleTaxonomyControls.push.apply(visibleTaxonomyControls, uiTermControls);
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
                      onChange: function (value) {
                        setAttributes({ title: value });
                      },
                    })
                  : null,
                TextareaControl
                  ? el(TextareaControl, {
                      label: 'Intro',
                      value: attrs.intro || '',
                      onChange: function (value) {
                        setAttributes({ intro: value });
                      },
                    })
                  : null,
                RangeControl
                  ? el(RangeControl, {
                      label: 'Limit',
                      value: attrs.limit || 12,
                      min: 1,
                      max: 50,
                      onChange: function (value) {
                        setAttributes({ limit: value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Group (slug, optional)',
                      value: attrs.group || '',
                      onChange: function (value) {
                        setAttributes({ group: value });
                      },
                    })
                  : null,
                SelectControl
                  ? el(SelectControl, {
                      label: 'Default Zeitraum',
                      value: attrs.timeMode || 'upcoming',
                      options: timeModeOptions.length ? timeModeOptions : timeModeChoices,
                      onChange: function (value) {
                        setAttributes({ timeMode: value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Default month (YYYY-MM)',
                      value: attrs.defaultMonth || '',
                      onChange: function (value) {
                        setAttributes({ defaultMonth: value });
                      },
                    })
                  : null,
                SelectControl
                  ? el(SelectControl, {
                      label: 'Default Inhaltstyp',
                      value: attrs.defaultType || 'all',
                      options: defaultTypeOptions,
                      onChange: function (value) {
                        setAttributes({ defaultType: value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Laufende Ausstellungen einbeziehen',
                      checked: !!attrs.includeRunningRanges,
                      onChange: function (value) {
                        setAttributes({ includeRunningRanges: !!value });
                      },
                    })
                  : null
              ),
              el(
                PanelBody,
                { title: 'Base Scope', initialOpen: false },
                scopeControls,
                fixedTaxonomyControls.length ? el('p', null, 'Fixed taxonomy filters') : null,
                fixedTaxonomyControls
              ),
              el(
                PanelBody,
                { title: 'Visible Filters', initialOpen: false },
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show result count / empty note',
                      checked: attrs.showMeta !== false,
                      onChange: function (value) {
                        setAttributes({ showMeta: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show time scope filter',
                      checked: !!attrs.showTimeModeFilter,
                      onChange: function (value) {
                        setAttributes({ showTimeModeFilter: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show Inhaltstyp filter',
                      checked: showItemTypeFilter,
                      onChange: function (value) {
                        setAttributes({ showItemTypeFilter: !!value, showTypeFilter: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show month filter',
                      checked: !!attrs.showMonthFilter,
                      onChange: function (value) {
                        setAttributes({ showMonthFilter: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show calendar bridge',
                      checked: !!attrs.showCalendarBridge,
                      onChange: function (value) {
                        setAttributes({ showCalendarBridge: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show post type filter',
                      checked: !!attrs.showPostTypeFilter,
                      onChange: function (value) {
                        setAttributes({ showPostTypeFilter: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Group by year',
                      checked: !!attrs.yearGrouping,
                      onChange: function (value) {
                        setAttributes({ yearGrouping: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Wiederkehrende Führungen bündeln',
                      checked: !!attrs.groupRecurringTours,
                      onChange: function (value) {
                        setAttributes({ groupRecurringTours: !!value });
                      },
                    })
                  : null,
                SelectControl
                  ? el(SelectControl, {
                      label: 'Render mode',
                      value: attrs.renderMode || 'timeline',
                      options: [
                        { label: 'Timeline', value: 'timeline' },
                        { label: 'Cards', value: 'cards' },
                      ],
                      onChange: function (value) {
                        setAttributes({ renderMode: value || 'timeline' });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show load more',
                      checked: !!attrs.showLoadMore,
                      onChange: function (value) {
                        setAttributes({ showLoadMore: !!value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show bottom button',
                      checked: !!attrs.showBottomButton,
                      onChange: function (value) {
                        setAttributes({ showBottomButton: !!value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Load more text',
                      value: attrs.loadMoreText || '',
                      onChange: function (value) {
                        setAttributes({ loadMoreText: value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Bottom button text',
                      value: attrs.bottomButtonText || '',
                      onChange: function (value) {
                        setAttributes({ bottomButtonText: value });
                      },
                    })
                  : null,
                TextControl
                  ? el(TextControl, {
                      label: 'Bottom button URL',
                      value: attrs.bottomButtonUrl || '',
                      onChange: function (value) {
                        setAttributes({ bottomButtonUrl: value });
                      },
                    })
                  : null,
                visibleTaxonomyControls.length ? el('p', null, 'Visible taxonomy filters') : null,
                visibleTaxonomyControls
              ),
              el(
                PanelBody,
                { title: 'Preset Buttons', initialOpen: false },
                presetButtons.map(function (preset, index) {
                  var taxonomyOptions = [{ label: 'Keine Taxonomie', value: '' }].concat(
                    taxonomyChoices.map(function (choice) {
                      return { label: choice.label, value: choice.value };
                    })
                  );

                  function updatePreset(nextPatch) {
                    var nextPresets = normalizePresetButtons(attrs.presetButtons || []);
                    if (!nextPresets[index]) return;
                    nextPresets[index] = Object.assign({}, nextPresets[index], nextPatch);
                    if (Object.prototype.hasOwnProperty.call(nextPatch, 'isDefault') && nextPatch.isDefault) {
                      nextPresets = nextPresets.map(function (item, itemIndex) {
                        return Object.assign({}, item, { isDefault: itemIndex === index });
                      });
                    }
                    setAttributes({ presetButtons: nextPresets });
                  }

                  return el(
                    'div',
                    { key: 'preset-' + index, style: { marginTop: '1rem', paddingTop: '1rem', borderTop: '1px solid #ddd' } },
                    TextControl
                      ? el(TextControl, {
                          label: 'Preset-Label',
                          value: preset.label,
                          onChange: function (value) {
                            updatePreset({ label: value });
                          },
                        })
                      : null,
                    SelectControl
                      ? el(SelectControl, {
                          label: 'Zeitraum',
                          value: preset.timeMode || '',
                          options: [{ label: 'Kein Zeitraum-Override', value: '' }].concat(
                            timeModeChoices.map(function (choice) {
                              return { label: choice.label, value: choice.value };
                            })
                          ),
                          onChange: function (value) {
                            updatePreset({ timeMode: value || '' });
                          },
                        })
                      : null,
                    SelectControl
                      ? el(SelectControl, {
                          label: 'Taxonomie',
                          value: preset.taxonomy || '',
                          options: taxonomyOptions,
                          onChange: function (value) {
                            updatePreset({
                              taxonomy: value || '',
                              terms: value ? preset.terms : [],
                            });
                          },
                        })
                      : null,
                    TextControl
                      ? el(TextControl, {
                          label: 'Terms (Slug, kommagetrennt)',
                          value: (preset.terms || []).join(', '),
                          onChange: function (value) {
                            updatePreset({ terms: normalizeList(String(value || '').split(',')) });
                          },
                          help: 'Nur noetig, wenn eine Taxonomie gesetzt ist.',
                        })
                      : null,
                    ToggleControl
                      ? el(ToggleControl, {
                          label: 'Als Standard aktiv',
                          checked: !!preset.isDefault,
                          onChange: function (value) {
                            updatePreset({ isDefault: !!value });
                          },
                        })
                      : null,
                    Button
                      ? el(
                          Button,
                          {
                            isDestructive: true,
                            variant: 'secondary',
                            onClick: function () {
                              var nextPresets = normalizePresetButtons(attrs.presetButtons || []).filter(function (_preset, presetIndex) {
                                return presetIndex !== index;
                              });
                              setAttributes({ presetButtons: nextPresets });
                            },
                          },
                          'Preset entfernen'
                        )
                      : null
                  );
                }),
                Button
                  ? el(
                      Button,
                      {
                        variant: 'secondary',
                        onClick: function () {
                          var nextPresets = normalizePresetButtons(attrs.presetButtons || []);
                          nextPresets.push({
                            label: 'Neues Preset',
                            timeMode: '',
                            taxonomy: '',
                            terms: [],
                            isDefault: nextPresets.length === 0,
                          });
                          setAttributes({ presetButtons: nextPresets });
                        },
                      },
                      'Preset hinzufuegen'
                    )
                  : null
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
