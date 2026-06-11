(function () {
  if (!window.wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.data) {
    return;
  }

  var createElement = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var ToggleControl = wp.components.ToggleControl;
  var TextControl = wp.components.TextControl;
  var Notice = wp.components.Notice;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;

  function normalizeMeta(meta) {
    return meta && typeof meta === 'object' ? meta : {};
  }

  function metaEnabled(meta) {
    if (meta.iss_timeline_enabled === undefined || meta.iss_timeline_enabled === null || meta.iss_timeline_enabled === '') {
      return false;
    }

    return !!meta.iss_timeline_enabled;
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

      return core.getEntityRecords('taxonomy', 'ausstellung_typ', { per_page: -1 }) || [];
    }, []);

    var editPost = useDispatch('core/editor').editPost;

    if (postType !== 'ausstellung') {
      return null;
    }

    var enabled = metaEnabled(meta);
    var startDate = String(meta.iss_start_date || '');
    var endDate = String(meta.iss_end_date || '');
    var hasStartDate = startDate !== '';
    var isPermanent = Array.isArray(terms) && Array.isArray(typeTerms) && typeTerms.some(function (term) {
      return term && term.slug === 'dauerausstellung' && terms.indexOf(term.id) !== -1;
    });
    var willSync = enabled && (hasStartDate || isPermanent);

    function updateMeta(next) {
      editPost({ meta: Object.assign({}, meta, next) });
    }

    return createElement(
      PluginDocumentSettingPanel,
      {
        name: 'iss-ausstellung-timeline',
        title: 'Timeline',
        className: 'iss-ausstellung-timeline-panel',
      },
      createElement(
        Fragment,
        null,
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
              { status: willSync ? 'success' : 'warning', isDismissible: false },
              willSync
                ? 'Diese Ausstellung erscheint in den öffentlichen Ausstellungsübersichten.'
                : 'Für öffentliche Ausstellungsübersichten muss die Ausstellung aktiviert sein und Datums- oder Typangaben haben.'
            )
          : null
      )
    );
  }

  registerPlugin('iss-ausstellung-timeline-panel', {
    render: AusstellungTimelinePanel,
  });
}());
