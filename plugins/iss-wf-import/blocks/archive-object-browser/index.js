(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const InspectorControls = window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components.PanelBody;
  const Placeholder = window.wp.components.Placeholder;
  const ServerSideRender = window.wp.serverSideRender;
  const TextControl = window.wp.components.TextControl;
  const ToggleControl = window.wp.components.ToggleControl;

  function stopPreviewNavigation(event) {
    if (event.target && event.target.closest && event.target.closest('a')) {
      event.preventDefault();
    }
  }

  function textControl(label, value, onChange) {
    return TextControl
      ? el(TextControl, {
          label: label,
          value: value || '',
          onChange: onChange,
        })
      : null;
  }

  function toggleControl(label, checked, onChange) {
    return ToggleControl
      ? el(ToggleControl, {
          label: label,
          checked: !!checked,
          onChange: onChange,
        })
      : null;
  }

  window.wp.blocks.registerBlockType('iss-wf-import/archive-object-browser', {
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes || function () {};

      const controls = InspectorControls && PanelBody
        ? el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: 'Abschnitte', initialOpen: true },
              toggleControl('Browser-Kopf anzeigen', attrs.showHero !== false, function (value) {
                setAttributes({ showHero: !!value });
              }),
              toggleControl('Statistik anzeigen', attrs.showStats !== false, function (value) {
                setAttributes({ showStats: !!value });
              }),
              toggleControl('Nachweis-Karten anzeigen', attrs.showSourceCards !== false, function (value) {
                setAttributes({ showSourceCards: !!value });
              }),
              toggleControl('Nachweis-Filter anzeigen', attrs.showSourceFilter !== false, function (value) {
                setAttributes({ showSourceFilter: !!value });
              }),
              toggleControl('Kontext-Filter anzeigen', attrs.showContextFilter !== false, function (value) {
                setAttributes({ showContextFilter: !!value });
              }),
              toggleControl('Dekaden-Filter anzeigen', attrs.showDecadeFilter !== false, function (value) {
                setAttributes({ showDecadeFilter: !!value });
              }),
              toggleControl('Aktuelle Seiten-URL verwenden', !!attrs.useCurrentUrl, function (value) {
                setAttributes({ useCurrentUrl: !!value });
              })
            ),
            el(
              PanelBody,
              { title: 'Texte', initialOpen: false },
              textControl('Vorspann', attrs.kicker, function (value) {
                setAttributes({ kicker: value });
              }),
              textControl('Titel', attrs.title, function (value) {
                setAttributes({ title: value });
              }),
              textControl('Einleitung', attrs.introText, function (value) {
                setAttributes({ introText: value });
              }),
              textControl('Schnellzugriff-Vorspann', attrs.quickKicker, function (value) {
                setAttributes({ quickKicker: value });
              }),
              textControl('Schnellzugriff-Titel', attrs.quickTitle, function (value) {
                setAttributes({ quickTitle: value });
              }),
              textControl('Discovery-Titel', attrs.discoveryTitle, function (value) {
                setAttributes({ discoveryTitle: value });
              })
            )
          )
        : null;

      const preview = ServerSideRender
        ? el(
            'div',
            {
              className: 'iss-archive-object-browser-editor-preview',
              onClick: stopPreviewNavigation,
            },
            el(ServerSideRender, {
              block: 'iss-wf-import/archive-object-browser',
              attributes: attrs,
            })
          )
        : Placeholder
          ? el(Placeholder, {
              label: 'ISS Archivobjekt-Browser',
              instructions: 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.',
            })
          : el('p', null, 'Frontend-Vorschau ist verfügbar, wenn ServerSideRender geladen ist.');

      return el(Fragment, null, controls, preview);
    },
    save: function () {
      return null;
    },
  });
})();
