(function () {
  if (!window.wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.data) {
    return;
  }

  var createElement = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var ToggleControl = wp.components.ToggleControl;
  var RadioControl = wp.components.RadioControl;
  var TextControl = wp.components.TextControl;
  var Notice = wp.components.Notice;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;

  var typeOptions = [
    { label: 'Sonderausstellung', value: 'sonderausstellung' },
    { label: 'Dauerausstellung', value: 'dauerausstellung' },
    { label: 'Digitale Ausstellung', value: 'digitaleausstellungen' },
  ];

  function normalizeMeta(meta) {
    return meta && typeof meta === 'object' ? meta : {};
  }

  function metaEnabled(meta) {
    if (meta.iss_timeline_enabled === undefined || meta.iss_timeline_enabled === null || meta.iss_timeline_enabled === '') {
      return false;
    }

    return !!meta.iss_timeline_enabled;
  }

  function getTermBySlug(typeTerms, slug) {
    if (!Array.isArray(typeTerms)) {
      return null;
    }

    for (var i = 0; i < typeTerms.length; i += 1) {
      if (typeTerms[i] && typeTerms[i].slug === slug) {
        return typeTerms[i];
      }
    }

    return null;
  }

  function getSelectedTypeSlug(terms, typeTerms) {
    if (!Array.isArray(terms) || !Array.isArray(typeTerms)) {
      return '';
    }

    var selectedTermIds = terms.map(function (termId) {
      return String(termId);
    });

    for (var i = 0; i < typeOptions.length; i += 1) {
      var term = getTermBySlug(typeTerms, typeOptions[i].value);
      if (term && selectedTermIds.indexOf(String(term.id)) !== -1) {
        return typeOptions[i].value;
      }
    }

    return '';
  }

  function AusstellungTimelinePanel() {
    var postType = useSelect(function (select) {
      return select('core/editor').getCurrentPostType();
    }, []);

    var meta = useSelect(function (select) {
      return normalizeMeta(select('core/editor').getEditedPostAttribute('meta'));
    }, []);

    var terms = useSelect(function (select) {
      return select('core/editor').getEditedPostAttribute('ausstellung_typ') || [];
    }, []);

    var typeTerms = useSelect(function (select) {
      var core = select('core');
      if (!core || !core.getEntityRecords) {
        return [];
      }

      return core.getEntityRecords('taxonomy', 'ausstellung_typ', { per_page: 100 }) || [];
    }, []);

    var editPost = useDispatch('core/editor').editPost;

    if (postType !== 'ausstellung') {
      return null;
    }

    var enabled = metaEnabled(meta);
    var startDate = String(meta.iss_start_date || '');
    var endDate = String(meta.iss_end_date || '');
    var hasStartDate = startDate !== '';
    var selectedType = getSelectedTypeSlug(terms, typeTerms);
    var isPermanent = selectedType === 'dauerausstellung';
    var isDigital = selectedType === 'digitaleausstellungen';
    var isAvailabilityOnly = isPermanent || isDigital;
    var isCurrentTemporary = selectedType === 'sonderausstellung' && hasStartDate;
    var willShow = enabled && (isAvailabilityOnly || isCurrentTemporary);

    function updateMeta(next) {
      editPost({ meta: Object.assign({}, meta, next) });
    }

    function updateType(slug) {
      var term = getTermBySlug(typeTerms, slug);
      if (!term) {
        return;
      }

      editPost({
        ausstellung_typ: [term.id],
      });
    }

    return createElement(
      PluginDocumentSettingPanel,
      {
        name: 'iss-ausstellung-timeline',
        title: 'Ausstellung',
        className: 'iss-ausstellung-timeline-panel',
      },
      createElement(
        Fragment,
        null,
        RadioControl
          ? createElement(RadioControl, {
              label: 'Ausstellungsart',
              selected: selectedType,
              options: typeOptions,
              onChange: updateType,
            })
          : null,
        ToggleControl
          ? createElement(ToggleControl, {
              label: 'Öffentlich in Ausstellungsübersichten zeigen',
              checked: enabled,
              onChange: function (value) {
                updateMeta({ iss_timeline_enabled: !!value });
              },
            })
          : null,
        TextControl
          ? createElement(TextControl, {
              label: 'Startdatum',
              type: 'date',
              value: startDate,
              onChange: function (value) {
                updateMeta({ iss_start_date: value || '' });
              },
            })
          : null,
        TextControl
          ? createElement(TextControl, {
              label: 'Enddatum',
              type: 'date',
              value: endDate,
              onChange: function (value) {
                updateMeta({ iss_end_date: value || '' });
              },
            })
          : null,
        Notice
          ? createElement(
              Notice,
              { status: willShow ? 'success' : 'warning', isDismissible: false },
              willShow
                ? (isAvailabilityOnly
                  ? 'Diese Ausstellung erscheint in den Ausstellungsübersichten und wird nicht als Kalendertermin geführt.'
                  : 'Diese Sonderausstellung erscheint mit ihren Laufdaten in den Ausstellungsübersichten.')
                : 'Für öffentliche Ausstellungsübersichten braucht die Ausstellung eine Ausstellungsart, die Sichtbarkeit und bei Sonderausstellungen ein Startdatum.'
            )
          : null
      )
    );
  }

  registerPlugin('iss-ausstellung-timeline-panel', {
    render: AusstellungTimelinePanel,
  });
}());
