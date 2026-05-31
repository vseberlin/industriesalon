(function () {
  function initGallery(gallery) {
    if (!gallery) return;

    var heroColumn = gallery.closest('.iss-tour-hero__media-col');
    if (!heroColumn) return;

    var heroImage = heroColumn.querySelector('.iss-tour-hero__featured-image img');
    if (!heroImage) return;

    var thumbs = gallery.querySelectorAll('.iss-tour-hero-gallery__thumb');
    if (!thumbs.length) return;

    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var src = thumb.getAttribute('data-hero-src');
        if (!src) return;

        heroImage.setAttribute('src', src);

        var srcset = thumb.getAttribute('data-hero-srcset');
        if (srcset) {
          heroImage.setAttribute('srcset', srcset);
        } else {
          heroImage.removeAttribute('srcset');
        }

        var sizes = thumb.getAttribute('data-hero-sizes');
        if (sizes) {
          heroImage.setAttribute('sizes', sizes);
        } else {
          heroImage.removeAttribute('sizes');
        }

        var alt = thumb.getAttribute('data-hero-alt');
        if (alt !== null) {
          heroImage.setAttribute('alt', alt);
        }

        thumbs.forEach(function (item) {
          item.classList.remove('is-active');
        });
        thumb.classList.add('is-active');
      });
    });
  }

  function initAll() {
    var galleries = document.querySelectorAll('.iss-tour-hero-gallery');
    if (!galleries.length) return;
    galleries.forEach(initGallery);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
