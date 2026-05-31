(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const useEffect = window.wp.element.useEffect;
  const useState = window.wp.element.useState;
  const InspectorControls = window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components.PanelBody;
  const SelectControl = window.wp.components.SelectControl;
  const Notice = window.wp.components.Notice;
  const Placeholder = window.wp.components.Placeholder;
  const ServerSideRender = window.wp.serverSideRender;
  const apiFetch = window.wp.apiFetch;

  function decodeTitle(value) {
    const holder = document.createElement('textarea');
    holder.innerHTML = String(value || '').replace(/<[^>]*>/g, '');
    return holder.value.trim();
  }

  function publicationLabel(post) {
    if (!post || !post.id) {
      return '';
    }

    const title = decodeTitle(post.title && post.title.rendered ? post.title.rendered : '');
    return title || 'Publikation #' + String(post.id);
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
      if ((intValue(id)) === selectedPostId) {
        hasSelected = true;
      }

      options.push({
        label: publicationLabel(post),
        value: id,
      });
    });

    if (!hasSelected) {
      options.push({
        label: 'Ausgewaehlte Publikation #' + String(selectedPostId),
        value: String(selectedPostId),
      });
    }

    return options;
  }

  function intValue(value) {
    const parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function stopPreviewNavigation(event) {
    if (event.target && event.target.closest && event.target.closest('a')) {
      event.preventDefault();
    }
  }

  window.wp.blocks.registerBlockType('iss/featured-publication', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};
      const selectedPostId = intValue(attrs.postId || 0);
      const [posts, setPosts] = useState([]);
      const [loadError, setLoadError] = useState('');

      useEffect(function () {
        if (!apiFetch) {
          setLoadError('Publikationen konnten im Editor nicht geladen werden.');
          return undefined;
        }

        let active = true;
        apiFetch({
          path: '/wp/v2/publication?per_page=100&status=publish&orderby=title&order=asc&_fields=id,title',
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

          setLoadError('Publikationen konnten im Editor nicht geladen werden.');
        });

        return function () {
          active = false;
        };
      }, []);

      const controls = InspectorControls && PanelBody && SelectControl
        ? el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: 'Publikation', initialOpen: true },
              el(SelectControl, {
                label: 'Auswahl',
                value: String(selectedPostId),
                options: buildOptions(posts, selectedPostId),
                onChange: function (value) {
                  setAttributes({ postId: intValue(value) });
                },
              })
            )
          )
        : null;

      let preview = null;
      if (ServerSideRender) {
        preview = el(
          'div',
          {
            className: 'iss-featured-publication-editor-preview',
            onClick: stopPreviewNavigation,
          },
          el(ServerSideRender, {
            block: 'iss/featured-publication',
            attributes: attrs,
          })
        );
      } else {
        preview = Placeholder
          ? el(
              Placeholder,
              {
                label: 'ISS Featured Publication',
                instructions: 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.',
              }
            )
          : el('p', null, 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.');
      }

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
