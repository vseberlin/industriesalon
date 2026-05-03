(function (blocks, element, components, blockEditor, serverSideRender) {
  var el = element.createElement;
  var Fragment = element.Fragment;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;
  var TextControl = components.TextControl;
  var TextareaControl = components.TextareaControl;
  var ToggleControl = components.ToggleControl;
  var ServerSideRender = serverSideRender;
  var skinOptions = [
    { label: 'Standard', value: 'default' },
    { label: 'Hell', value: 'light' },
    { label: 'Dunkel', value: 'dark' },
    { label: 'Gedämpft', value: 'muted' },
    { label: 'Front', value: 'front' },
    { label: 'Akzent Rot', value: 'accent-red' }
  ];
  var hoursVariantOptions = [
    { label: 'Kurz', value: 'compact' },
    { label: 'Ausführlich', value: 'full' },
    { label: 'Einzeilig', value: 'inline' },
    { label: 'Footer', value: 'footer' },
    { label: 'Front Card', value: 'front-card' },
    { label: 'Info Panel', value: 'info-panel' }
  ];
  var contactVariantOptions = [
    { label: 'Karte', value: 'card' },
    { label: 'Footer', value: 'footer' },
    { label: 'Front Card', value: 'front-card' },
    { label: 'Info Panel', value: 'info-panel' },
    { label: 'Kompakte Zeile', value: 'compact-row' }
  ];
  var infoPanelAccentOptions = [
    { label: 'Grün', value: 'green' },
    { label: 'Rot', value: 'red' },
    { label: 'Blau', value: 'blue' },
    { label: 'Gelb', value: 'yellow' },
    { label: 'Braun', value: 'brown' }
  ];
  var infoPanelSurfaceOptions = [
    { label: 'Hell', value: 'light' },
    { label: 'Dunkel', value: 'dark' }
  ];

  var fieldOptions = [
    { label: 'Telefon', value: 'contact.phone' },
    { label: 'E-Mail', value: 'contact.email' },
    { label: 'Buchungs-E-Mail', value: 'contact.booking_email' },
    { label: 'Ansprechperson', value: 'contact.contact_person' },
    { label: 'Website', value: 'contact.website' },
    { label: 'Buchungslink', value: 'contact.booking_url' },
    { label: 'Straße', value: 'general.street' },
    { label: 'PLZ', value: 'general.postal_code' },
    { label: 'Ort', value: 'general.city' },
    { label: 'Stadtteil', value: 'general.district' },
    { label: 'Volle Adresse', value: 'address.full' },
    { label: 'Google Maps URL', value: 'maps.google_maps_url' },
    { label: 'Preis Erwachsene', value: 'prices.adult' },
    { label: 'Preis Ermäßigt', value: 'prices.reduced' },
    { label: 'Preis Gruppen', value: 'prices.group' },
    { label: 'Preis Schulklassen', value: 'prices.school' },
    { label: 'Preis Führung', value: 'prices.tour' },
    { label: 'Preis Raumvermietung', value: 'prices.rental' }
  ];

  function fieldInspector(props) {
    return el(
      InspectorControls,
      null,
      el(
        PanelBody,
        { title: 'Feld', initialOpen: true },
        el(SelectControl, {
          label: 'Schlüssel',
          value: props.attributes.key,
          options: fieldOptions,
          onChange: function (value) { props.setAttributes({ key: value }); }
        }),
        el(SelectControl, {
          label: 'HTML-Tag',
          value: props.attributes.tagName,
          options: [
            { label: 'div', value: 'div' },
            { label: 'p', value: 'p' },
            { label: 'span', value: 'span' },
            { label: 'strong', value: 'strong' },
            { label: 'h2', value: 'h2' },
            { label: 'h3', value: 'h3' }
          ],
          onChange: function (value) { props.setAttributes({ tagName: value }); }
        }),
        el(SelectControl, {
          label: 'Link',
          value: props.attributes.linkMode,
          options: [
            { label: 'Automatisch', value: 'auto' },
            { label: 'Kein Link', value: 'none' },
            { label: 'Telefon-Link', value: 'tel' },
            { label: 'E-Mail-Link', value: 'email' },
            { label: 'URL-Link', value: 'url' }
          ],
          onChange: function (value) { props.setAttributes({ linkMode: value }); }
        }),
        el(TextControl, {
          label: 'Optionales Label',
          value: props.attributes.label,
          onChange: function (value) { props.setAttributes({ label: value }); }
        })
      )
    );
  }

  blocks.registerBlockType('industriesalon/field', {
    title: 'IS Feld',
    icon: 'admin-generic',
    category: 'widgets',
    attributes: {
      key: { type: 'string', default: 'contact.phone' },
      tagName: { type: 'string', default: 'div' },
      linkMode: { type: 'string', default: 'auto' },
      label: { type: 'string', default: '' }
    },
    edit: function (props) {
      return el(
        Fragment,
        null,
        fieldInspector(props),
        el(ServerSideRender, {
          block: 'industriesalon/field',
          attributes: props.attributes
        })
      );
    },
    save: function () { return null; }
  });

  blocks.registerBlockType('industriesalon/hours', {
    title: 'IS Zeiten',
    icon: 'clock',
    category: 'widgets',
    attributes: {
      type: { type: 'string', default: 'public' },
      title: { type: 'string', default: '' },
      variant: { type: 'string', default: 'compact' },
      skin: { type: 'string', default: 'default' },
      show_status: { type: 'boolean', default: false },
      show_exceptions: { type: 'boolean', default: false }
    },
    edit: function (props) {
      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Zeiten', initialOpen: true },
            el(SelectControl, {
              label: 'Typ',
              value: props.attributes.type,
              options: [
                { label: 'Besuchszeiten', value: 'public' },
                { label: 'Bürozeiten', value: 'office' }
              ],
              onChange: function (value) { props.setAttributes({ type: value }); }
            }),
            el(SelectControl, {
              label: 'Variante',
              value: props.attributes.variant,
              options: hoursVariantOptions,
              onChange: function (value) { props.setAttributes({ variant: value }); }
            }),
            el(SelectControl, {
              label: 'Skin',
              value: props.attributes.skin,
              options: skinOptions,
              onChange: function (value) { props.setAttributes({ skin: value }); }
            }),
            el(TextControl, {
              label: 'Optionaler Titel',
              value: props.attributes.title,
              onChange: function (value) { props.setAttributes({ title: value }); }
            }),
            el(ToggleControl, {
              label: 'Status davor zeigen',
              checked: !!props.attributes.show_status,
              onChange: function (value) { props.setAttributes({ show_status: value }); }
            }),
            el(ToggleControl, {
              label: 'Sondertage einblenden',
              checked: !!props.attributes.show_exceptions,
              onChange: function (value) { props.setAttributes({ show_exceptions: value }); }
            })
          )
        ),
        el(ServerSideRender, {
          block: 'industriesalon/hours',
          attributes: props.attributes
        })
      );
    },
    save: function () { return null; }
  });

  blocks.registerBlockType('industriesalon/visit-info', {
    title: 'Besuchszeiten',
    icon: 'clock',
    category: 'widgets',
    supports: {
      align: ['wide', 'full'],
      html: false
    },
    attributes: {
      align: { type: 'string', default: '' },
      variant: { type: 'string', default: 'info-panel' },
      accent: { type: 'string', default: 'green' },
      surface: { type: 'string', default: 'light' },
      kicker: { type: 'string', default: 'ANREISE & MEHR' },
      title: { type: 'string', default: 'Besuch planen' },
      intro: { type: 'string', default: 'Die wichtigsten Hinweise für Ihren Besuch im Industriesalon.' },
      show_address: { type: 'boolean', default: true },
      show_museum_hours: { type: 'boolean', default: true },
      show_office_hours: { type: 'boolean', default: true },
      show_arrival: { type: 'boolean', default: true },
      show_accessibility: { type: 'boolean', default: true }
    },
    edit: function (props) {
      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Info Panel', initialOpen: true },
            el(SelectControl, {
              label: 'Akzentfarbe',
              value: props.attributes.accent,
              options: infoPanelAccentOptions,
              onChange: function (value) { props.setAttributes({ accent: value }); }
            }),
            el(SelectControl, {
              label: 'Hintergrund',
              value: props.attributes.surface,
              options: infoPanelSurfaceOptions,
              onChange: function (value) { props.setAttributes({ surface: value }); }
            }),
            el(ToggleControl, {
              label: 'Adresse zeigen',
              checked: !!props.attributes.show_address,
              onChange: function (value) { props.setAttributes({ show_address: value }); }
            }),
            el(ToggleControl, {
              label: 'Besuchszeiten zeigen',
              checked: !!props.attributes.show_museum_hours,
              onChange: function (value) { props.setAttributes({ show_museum_hours: value }); }
            }),
            el(ToggleControl, {
              label: 'Bürozeiten zeigen',
              checked: !!props.attributes.show_office_hours,
              onChange: function (value) { props.setAttributes({ show_office_hours: value }); }
            }),
            el(ToggleControl, {
              label: 'ÖPNV zeigen',
              checked: !!props.attributes.show_arrival,
              onChange: function (value) { props.setAttributes({ show_arrival: value }); }
            }),
            el(ToggleControl, {
              label: 'Barrierefreiheit zeigen',
              checked: !!props.attributes.show_accessibility,
              onChange: function (value) { props.setAttributes({ show_accessibility: value }); }
            })
          ),
          el(
            PanelBody,
            { title: 'Texte', initialOpen: true },
            el(TextControl, {
              label: 'Kicker',
              value: props.attributes.kicker,
              onChange: function (value) { props.setAttributes({ kicker: value }); }
            }),
            el(TextControl, {
              label: 'Titel',
              value: props.attributes.title,
              onChange: function (value) { props.setAttributes({ title: value }); }
            }),
            el(TextareaControl, {
              label: 'Intro',
              value: props.attributes.intro,
              onChange: function (value) { props.setAttributes({ intro: value }); }
            })
          )
        ),
        el(ServerSideRender, {
          block: 'industriesalon/visit-info',
          attributes: props.attributes
        })
      );
    },
    save: function () { return null; }
  });

  function simpleGroupBlock(name, title, icon, variantOptions, supportsSkin) {
    blocks.registerBlockType(name, {
      title: title,
      icon: icon,
      category: 'widgets',
      attributes: {
        title: { type: 'string', default: '' },
        variant: { type: 'string', default: name === 'industriesalon/contact' ? 'card' : 'default' },
        skin: { type: 'string', default: 'default' }
      },
      edit: function (props) {
        var controls = [
          el(TextControl, {
            label: 'Optionaler Titel',
            value: props.attributes.title,
            onChange: function (value) { props.setAttributes({ title: value }); }
          })
        ];

        if (supportsSkin) {
          controls.push(el(SelectControl, {
            label: 'Skin',
            value: props.attributes.skin,
            options: skinOptions,
            onChange: function (value) { props.setAttributes({ skin: value }); }
          }));
        }

        if (variantOptions) {
          controls.splice(1, 0, el(SelectControl, {
            label: 'Variante',
            value: props.attributes.variant,
            options: variantOptions,
            onChange: function (value) { props.setAttributes({ variant: value }); }
          }));
        }

        return el(
          Fragment,
          null,
          el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: title, initialOpen: true },
              controls
            )
          ),
          el(ServerSideRender, {
            block: name,
            attributes: props.attributes
          })
        );
      },
      save: function () { return null; }
    });
  }

  simpleGroupBlock('industriesalon/contact', 'IS Kontakt', 'id', contactVariantOptions, false);
  simpleGroupBlock('industriesalon/faq', 'IS FAQ', 'editor-help', null, false);
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender);
