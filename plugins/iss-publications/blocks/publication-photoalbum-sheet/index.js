(function () {
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.blockEditor || !window.wp.components) return;

  const el = window.wp.element.createElement;
  const MediaUpload = window.wp.blockEditor.MediaUpload;
  const MediaUploadCheck = window.wp.blockEditor.MediaUploadCheck;
  const RichText = window.wp.blockEditor.RichText;
  const Button = window.wp.components.Button;
  const TextControl = window.wp.components.TextControl;

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
          { className: 'iss-publication-photoalbum-sheet-editor__media-preview' },
          el('img', {
            src: imageUrl,
            alt: imageAlt,
            style: {
              display: 'block',
              width: '100%',
              height: 'auto',
              maxHeight: '24rem',
              objectFit: 'contain',
            },
          })
        )
      : null;

    return el(
      'div',
      { className: 'iss-publication-photoalbum-sheet-editor__media' },
      imagePreview,
      el(
        'div',
        { className: 'iss-publication-photoalbum-sheet-editor__media-actions' },
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
            help: 'Kurzer Alt-Text fuer das Albumblatt.',
            value: imageAlt,
            onChange: function (value) {
              setAttributes({ imageAlt: value });
            },
          })
        : null
    );
  }

  window.wp.blocks.registerBlockType('iss/publication-photoalbum-sheet', {
    edit: function (props) {
      var attrs = props.attributes || {};
      var setAttributes = props.setAttributes || function () {};

      return el(
        'article',
        { className: (props.className || '') + ' iss-publication-photoalbum-sheet-editor' },
        TextControl
          ? el(TextControl, {
              label: 'Blattlabel',
              help: 'Zum Beispiel: Blatt 01.',
              value: attrs.sheetLabel || '',
              onChange: function (value) {
                setAttributes({ sheetLabel: value });
              },
            })
          : null,
        renderImageEditor(attrs, setAttributes),
        RichText
          ? el(RichText, {
              tagName: 'h3',
              className: 'iss-publication-photoalbum-source__sheet-title',
              value: attrs.title || '',
              placeholder: 'Kurztitel fuer Navigation und Blatt',
              onChange: function (value) {
                setAttributes({ title: value });
              },
            })
          : null,
        RichText
          ? el(RichText, {
              tagName: 'p',
              className: 'iss-publication-photoalbum-source__sheet-caption',
              value: attrs.caption || '',
              placeholder: 'Beschriftung, Transkription oder Quellenhinweis',
              onChange: function (value) {
                setAttributes({ caption: value });
              },
            })
          : null
      );
    },
    save: function (props) {
      var attrs = props.attributes || {};

      if (!attrs.imageUrl) {
        return null;
      }

      var captionParts = [];
      if (attrs.title && attrs.title !== '' && RichText && RichText.Content) {
        captionParts.push(
          el(RichText.Content, {
            tagName: 'strong',
            className: 'iss-publication-photoalbum-source__sheet-title',
            value: attrs.title,
          })
        );
      }
      if (attrs.caption && attrs.caption !== '' && RichText && RichText.Content) {
        captionParts.push(
          el(RichText.Content, {
            tagName: 'span',
            className: 'iss-publication-photoalbum-source__sheet-caption',
            value: attrs.caption,
          })
        );
      }

      return el(
        'figure',
        {
          className: 'wp-block-image iss-publication-photoalbum-source__sheet',
          'data-sheet-label': attrs.sheetLabel || undefined,
        },
        el('img', {
          src: attrs.imageUrl,
          alt: attrs.imageAlt || '',
          className: attrs.imageId ? 'wp-image-' + String(attrs.imageId) : undefined,
        }),
        captionParts.length ? el('figcaption', null, captionParts) : null
      );
    },
  });
})();
