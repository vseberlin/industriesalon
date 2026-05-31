(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const useEffect = window.wp.element.useEffect;
  const useState = window.wp.element.useState;
  const InspectorControls = window.wp.blockEditor.InspectorControls;
  const ComboboxControl = window.wp.components.ComboboxControl;
  const PanelBody = window.wp.components.PanelBody;
  const Placeholder = window.wp.components.Placeholder;
  const SelectControl = window.wp.components.SelectControl;
  const SearchControl = window.wp.components.SearchControl;
  const TextControl = window.wp.components.TextControl;
  const Notice = window.wp.components.Notice;
  const ServerSideRender = window.wp.serverSideRender;
  const apiFetch = window.wp.apiFetch;

  function decodeTitle(value) {
    const holder = document.createElement('textarea');
    holder.innerHTML = String(value || '').replace(/<[^>]*>/g, '');
    return holder.value.trim();
  }

  function intValue(value) {
    const parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function objectLabel(post) {
    if (!post || !post.id) {
      return '';
    }

    const title = decodeTitle(post.title && post.title.rendered ? post.title.rendered : '');
    return title || 'Archivobjekt #' + String(post.id);
  }

  function buildOptions(posts, selectedPostId) {
    const options = [
      {
        label: 'Automatische Auswahl',
        value: '0',
      },
    ];
    let hasSelected = selectedPostId <= 0;

    (Array.isArray(posts) ? posts : []).forEach(function (post) {
      if (!post || !post.id) {
        return;
      }

      const id = String(post.id);
      if (intValue(id) === selectedPostId) {
        hasSelected = true;
      }

      options.push({
        label: objectLabel(post),
        value: id,
      });
    });

    if (!hasSelected) {
      options.push({
        label: 'Ausgewähltes Archivobjekt #' + String(selectedPostId),
        value: String(selectedPostId),
      });
    }

    return options;
  }

  function objectSearchPath(searchTerm) {
    const term = String(searchTerm || '').trim();
    let path = '/wp/v2/archivobjekt?per_page=20&status=publish&orderby=title&order=asc&_fields=id,title';

    if (term !== '') {
      path += '&search=' + encodeURIComponent(term);
    }

    return path;
  }

  function stopPreviewNavigation(event) {
    if (event.target && event.target.closest && event.target.closest('a')) {
      event.preventDefault();
    }
  }

  window.wp.blocks.registerBlockType('iss-wf-import/featured-archive-object', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};
      const selectedPostId = intValue(attrs.postId || 0);
      const [posts, setPosts] = useState([]);
      const [searchTerm, setSearchTerm] = useState('');
      const [loadError, setLoadError] = useState('');

      useEffect(function () {
        if (!apiFetch) {
          setLoadError('Archivobjekte konnten im Editor nicht geladen werden.');
          return undefined;
        }

        let active = true;
        apiFetch({
          path: objectSearchPath(searchTerm),
        }).then(function (records) {
          if (!active) {
            return;
          }

          setPosts(Array.isArray(records) ? records : []);
          setLoadError('');
        }).catch(function () {
          if (!active) {
            return;
          }

          setLoadError('Archivobjekte konnten im Editor nicht geladen werden.');
        });

        return function () {
          active = false;
        };
      }, [searchTerm]);

      const options = buildOptions(posts, selectedPostId);
      const pickerControl = ComboboxControl
        ? el(ComboboxControl, {
            label: 'Auswahl',
            value: String(selectedPostId),
            options: options,
            onChange: function (value) {
              setAttributes({ postId: intValue(value) });
            },
          })
        : SelectControl
          ? el(SelectControl, {
              label: 'Auswahl',
              value: String(selectedPostId),
              options: options,
              onChange: function (value) {
                setAttributes({ postId: intValue(value) });
              },
            })
          : null;

      const controls = InspectorControls && PanelBody
        ? el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: 'Archivobjekt', initialOpen: true },
              SearchControl
                ? el(SearchControl, {
                    label: 'Archivobjekt suchen',
                    value: searchTerm,
                    onChange: function (value) {
                      setSearchTerm(value || '');
                    },
                  })
                : null,
              pickerControl,
              TextControl
                ? el(TextControl, {
                    label: 'Objekt-ID',
                    type: 'number',
                    value: selectedPostId > 0 ? String(selectedPostId) : '',
                    help: 'Leer lassen für automatische Auswahl.',
                    onChange: function (value) {
                      setAttributes({ postId: intValue(value) });
                    },
                  })
                : null
            )
          )
        : null;

      const preview = ServerSideRender
        ? el(
            'div',
            {
              className: 'iss-featured-archive-object-editor-preview',
              onClick: stopPreviewNavigation,
            },
            el(ServerSideRender, {
              block: 'iss-wf-import/featured-archive-object',
              attributes: attrs,
            })
          )
        : Placeholder
          ? el(Placeholder, {
              label: 'ISS Ausgewähltes Archivobjekt',
              instructions: 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.',
            })
          : el('p', null, 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.');

      return el(
        Fragment,
        null,
        controls,
        loadError && Notice
          ? el(Notice, { status: 'warning', isDismissible: false }, loadError)
          : null,
        preview
      );
    },
    save: function () {
      return null;
    },
  });
})();
