(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element) return;

  const el = window.wp.element.createElement;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const ToggleControl = window.wp.components && window.wp.components.ToggleControl;
  const Notice = window.wp.components && window.wp.components.Notice;

  window.wp.blocks.registerBlockType('iss/video-library-playlists', {
    apiVersion: 3,
    title: 'ISS Video Playlists',
    category: 'widgets',
    icon: 'playlist-video',
    description: 'Server-rendered grouped video playlist rails.',
    keywords: ['video', 'playlist', 'rail'],
    supports: {
      html: false,
    },
    attributes: {
      showDescriptions: {
        type: 'boolean',
        default: true,
      },
      showCounts: {
        type: 'boolean',
        default: true,
      },
    },
    edit: function (props) {
      const attrs = props.attributes || {};
      const setAttributes = props.setAttributes;

      return el(
        'div',
        null,
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'ISS Video Playlists', initialOpen: true },
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show category descriptions',
                      checked: attrs.showDescriptions !== false,
                      onChange: function (value) {
                        setAttributes({ showDescriptions: value });
                      },
                    })
                  : null,
                ToggleControl
                  ? el(ToggleControl, {
                      label: 'Show video counts',
                      checked: attrs.showCounts !== false,
                      onChange: function (value) {
                        setAttributes({ showCounts: value });
                      },
                    })
                  : null,
                Notice ? el(Notice, { status: 'info', isDismissible: false }, 'Playlist titles and descriptions come from video categories.') : null
              )
            )
          : null,
        el('div', { className: 'components-placeholder iss-content-model-video-block-editor' }, el('strong', null, 'ISS Video Playlists'), el('p', null, 'Renders grouped video playlist rails.'))
      );
    },
    save: function () {
      return null;
    },
  });
})();
