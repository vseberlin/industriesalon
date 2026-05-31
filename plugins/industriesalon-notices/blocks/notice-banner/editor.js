(function (blocks, element, serverSideRender, blockEditor, components) {
  var el = element.createElement;
  var useBlockProps = blockEditor.useBlockProps;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;

  blocks.registerBlockType("industriesalon/notice-banner", {
    apiVersion: 2,
    title: "Notice Banner",
    icon: "megaphone",
    category: "theme",
    attributes: {
      area: {
        type: "string",
        default: "front_page_banner",
      },
      skin: {
        type: "string",
        default: "",
      },
    },
    edit: function (props) {
      var blockProps = useBlockProps();
      return el(
        "div",
        blockProps,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: "Notice Banner", initialOpen: true },
            el(SelectControl, {
              label: "Bereich",
              value: props.attributes.area || "front_page_banner",
              options: [
                { label: "Startseiten-Banner", value: "front_page_banner" },
                { label: "Startseite Kalender-Hinweis", value: "front_page_timeline_promo" },
                { label: "Website-Hinweis", value: "site_notice" },
                { label: "Ausgewählte Seiten (Banner)", value: "selected_pages_banner" },
                { label: "Admin-Hinweis", value: "admin_notice" },
              ],
              onChange: function (value) {
                props.setAttributes({ area: value });
              },
            }),
            el(SelectControl, {
              label: "Skin",
              value: props.attributes.skin || "",
              options: [
                { label: "Aus Hinweis übernehmen", value: "" },
                { label: "Front Banner", value: "front" },
                { label: "Landing Note", value: "landing" },
                { label: "Atlas Note", value: "atlas" },
                { label: "Pinned Note", value: "pinned" },
              ],
              onChange: function (value) {
                props.setAttributes({ skin: value });
              },
            })
          )
        ),
        el(serverSideRender, {
          block: "industriesalon/notice-banner",
          attributes: props.attributes,
        })
      );
    },
    save: function () {
      return null;
    },
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.serverSideRender,
  window.wp.blockEditor,
  window.wp.components
);
