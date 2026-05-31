(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const InnerBlocks = window.wp.blockEditor.InnerBlocks;
  const MediaUpload = window.wp.blockEditor.MediaUpload;
  const MediaUploadCheck = window.wp.blockEditor.MediaUploadCheck;
  const RichText = window.wp.blockEditor.RichText;
  const Button = window.wp.components.Button;
  const TextControl = window.wp.components.TextControl;

  const BODY_TEMPLATE = [['core/paragraph', { placeholder: 'Einordnung, Quelle oder Entwicklung beschreiben …' }]];
  const BODY_ALLOWED_BLOCKS = ['core/paragraph', 'core/list', 'core/quote'];

  function getMediaSelection(media) {
    var mediaId = media && media.id ? media.id : 0;
    var mediaAlt = media && media.alt ? media.alt : '';
    var mediaUrl = '';

    if (media && media.sizes) {
      if (media.sizes.large && media.sizes.large.url) {
        mediaUrl = media.sizes.large.url;
      } else if (media.sizes.full && media.sizes.full.url) {
        mediaUrl = media.sizes.full.url;
      }
    }

    if (!mediaUrl && media && media.url) {
      mediaUrl = media.url;
    }

    return {
      imageId: mediaId,
      imageUrl: mediaUrl,
      imageAlt: mediaAlt,
    };
  }

  function renderImageEditor(attrs, setAttributes) {
    if (!MediaUpload || !MediaUploadCheck || !Button) {
      return null;
    }

    var imageUrl = attrs.imageUrl || '';
    var imageAlt = attrs.imageAlt || '';
    var imageId = attrs.imageId || 0;

    var imagePreview = imageUrl
      ? el(
          'div',
          { className: 'iss-publication-timeline-item-editor__media-preview' },
          el('img', {
            src: imageUrl,
            alt: imageAlt,
            style: {
              display: 'block',
              width: '100%',
              height: 'auto',
              maxHeight: '16rem',
              objectFit: 'contain',
            },
          })
        )
      : null;

    return el(
      'div',
      { className: 'iss-publication-timeline-item-editor__media' },
      imagePreview,
      el(
        'div',
        { className: 'iss-publication-timeline-item-editor__media-actions' },
        el(
          MediaUploadCheck,
          null,
          el(MediaUpload, {
            value: imageId,
            allowedTypes: ['image'],
            onSelect: function (media) {
              setAttributes(getMediaSelection(media));
            },
            render: function (renderProps) {
              return el(
                Button,
                {
                  variant: imageUrl ? 'secondary' : 'primary',
                  isSecondary: !!imageUrl,
                  onClick: renderProps.open,
                },
                imageUrl ? 'Bild ersetzen' : 'Bild auswählen'
              );
            },
          })
        ),
        imageUrl
          ? el(
              Button,
              {
                variant: 'tertiary',
                onClick: function () {
                  setAttributes({
                    imageId: 0,
                    imageUrl: '',
                    imageAlt: '',
                  });
                },
              },
              'Bild entfernen'
            )
          : null
      ),
      imageUrl && TextControl
        ? el(TextControl, {
            label: 'Bildbeschreibung',
            help: 'Kurzer Alt-Text fuer das Bild. Leer lassen nur bei rein dekorativen Bildern.',
            value: imageAlt,
            onChange: function (value) {
              setAttributes({ imageAlt: value });
            },
          })
        : null
    );
  }

  window.wp.blocks.registerBlockType('iss/publication-timeline-item', {
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};

      return el(
        'article',
        { className: (props.className || '') + ' iss-publication-timeline-item-editor' },
        TextControl
          ? el(TextControl, {
              label: 'Jahr',
              help: 'Vierstelliges Jahr fuer Auswertung und Epochensortierung.',
              value: attrs.year || '',
              onChange: function (value) {
                setAttributes({ year: value.replace(/[^\d]/g, '').slice(0, 4) });
              },
            })
          : null,
        renderImageEditor(attrs, setAttributes),
        RichText
          ? el(RichText, {
              tagName: 'h3',
              className: 'iss-publication-chronicle__title',
              value: attrs.title || '',
              placeholder: 'Titel der Station (erforderlich)',
              onChange: function (value) {
                setAttributes({ title: value });
              },
            })
          : null,
        el(
          'div',
          { className: 'iss-publication-chronicle__text' },
          el(InnerBlocks, {
            allowedBlocks: BODY_ALLOWED_BLOCKS,
            template: BODY_TEMPLATE,
            templateLock: false,
            renderAppender: InnerBlocks.ButtonBlockAppender,
          })
        )
      );
    },
    save: function (props) {
      var attrs = props.attributes || {};
      var cardChildren = [];

      if (attrs.imageUrl) {
        cardChildren.push(
          el(
            'figure',
            { className: 'wp-block-image size-large iss-publication-chronicle__media' },
            el('img', {
              src: attrs.imageUrl,
              alt: attrs.imageAlt || '',
              className: attrs.imageId ? 'wp-image-' + String(attrs.imageId) : undefined,
            })
          )
        );
      }

      if (attrs.title && attrs.title !== '' && RichText && RichText.Content) {
        cardChildren.push(
          el(RichText.Content, {
            tagName: 'h3',
            className: 'wp-block-heading iss-publication-chronicle__title',
            value: attrs.title,
          })
        );
      }

      cardChildren.push(
        el(
          'div',
          { className: 'wp-block-group iss-publication-chronicle__text' },
          el(InnerBlocks.Content)
        )
      );

      return el(
        'div',
        { className: 'wp-block-group iss-publication-chronicle__item' },
        el('p', { className: 'iss-publication-chronicle__year' }, attrs.year || ''),
        el(
          'div',
          { className: 'wp-block-group iss-publication-chronicle__card' },
          cardChildren
        )
      );
    },
  });
})();
