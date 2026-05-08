(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const useEffect = window.wp.element.useEffect;
  const useState = window.wp.element.useState;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const SelectControl = window.wp.components && window.wp.components.SelectControl;
  const Notice = window.wp.components && window.wp.components.Notice;
  const Placeholder = window.wp.components && window.wp.components.Placeholder;
  const ServerSideRender = window.wp.serverSideRender;
  const apiFetch = window.wp.apiFetch;

  const LAYOUT_OPTIONS = [
    { label: 'Kapitelverzeichnis', value: 'toc' },
    { label: 'Kapitelkarten', value: 'cards' },
    { label: 'Lesefluss', value: 'stream' }
  ];

  window.wp.blocks.registerBlockType('iss-wf-import/archive-exhibition', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};
      const [termOptions, setTermOptions] = useState([{ label: 'Kategorie wählen', value: '' }]);
      const [loadError, setLoadError] = useState('');

      useEffect(function () {
        if (!apiFetch) return undefined;

        let active = true;
        apiFetch({
          path: '/wp/v2/archiv_kategorie?per_page=100&orderby=name&order=asc&_fields=name,slug,count'
        }).then(function (terms) {
          if (!active) return;

          const options = [{ label: 'Kategorie wählen', value: '' }];
          (Array.isArray(terms) ? terms : []).forEach(function (term) {
            if (!term || !term.slug) return;
            options.push({
              label: term.name + (typeof term.count === 'number' ? ' (' + term.count + ')' : ''),
              value: term.slug
            });
          });
          setTermOptions(options);
        }).catch(function () {
          if (!active) return;
          setLoadError('Archivkategorien konnten im Editor nicht geladen werden.');
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
              { title: 'Archive Exhibition', initialOpen: true },
              el(SelectControl, {
                label: 'Archivkategorie',
                value: attrs.termSlug || '',
                options: termOptions,
                onChange: function (value) {
                  setAttributes({ termSlug: value });
                }
              }),
              el(SelectControl, {
                label: 'Layout',
                value: attrs.layout || 'toc',
                options: LAYOUT_OPTIONS,
                onChange: function (value) {
                  setAttributes({ layout: value });
                }
              })
            )
          )
        : null;

      let preview = null;
      if (!attrs.termSlug) {
        preview = Placeholder
          ? el(
              Placeholder,
              { label: 'Archive Exhibition', instructions: 'Eine Archivkategorie auswählen, um Kapitel aus dem lokalen Archiv einzubinden.' }
            )
          : el('p', null, 'Eine Archivkategorie auswählen.');
      } else if (ServerSideRender) {
        preview = el(ServerSideRender, {
          block: 'iss-wf-import/archive-exhibition',
          attributes: attrs
        });
      } else {
        preview = el('p', null, 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.');
      }

      return el(
        'div',
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
    }
  });
})();
