(function () {
  function getCandidateSource(candidate, key) {
    var value = candidate.getAttribute('data-iss-image-' + key);
    if (value !== null && value !== '') {
      return value;
    }

    return candidate.getAttribute('data-hero-' + key);
  }

  function resolveViewport(gallery) {
    var target = gallery.getAttribute('data-iss-image-target') || gallery.getAttribute('data-hero-target');
    var scopeSelector = gallery.getAttribute('data-iss-image-scope');
    var scope = scopeSelector ? gallery.closest(scopeSelector) : null;

    if (!scope) {
      scope = gallery.closest('[data-iss-image-viewport-scope]') || gallery.closest('section') || document;
    }

    if (target) {
      return scope.querySelector(target) || document.querySelector(target);
    }

    return scope.querySelector('[data-iss-image-viewport]');
  }

  function selectImage(gallery, candidate, viewport) {
    var src = getCandidateSource(candidate, 'src');
    var srcset = getCandidateSource(candidate, 'srcset');
    var sizes = getCandidateSource(candidate, 'sizes');
    var alt = getCandidateSource(candidate, 'alt');

    if (!src || !viewport) {
      return;
    }

    viewport.setAttribute('src', src);

    if (srcset) {
      viewport.setAttribute('srcset', srcset);
    } else {
      viewport.removeAttribute('srcset');
    }

    if (sizes) {
      viewport.setAttribute('sizes', sizes);
    } else {
      viewport.removeAttribute('sizes');
    }

    if (alt !== null) {
      viewport.setAttribute('alt', alt);
    }

    gallery.querySelectorAll('[data-iss-image-choice], .iss-tour-hero-gallery__thumb').forEach(function (item) {
      item.classList.toggle('is-active', item === candidate);
      if (item === candidate) {
        item.setAttribute('aria-current', 'true');
      } else {
        item.removeAttribute('aria-current');
      }
    });
  }

  function bindGallery(gallery) {
    if (!gallery || gallery.dataset.issImageViewportGalleryBound === '1') {
      return;
    }

    var viewport = resolveViewport(gallery);
    if (!viewport) {
      return;
    }

    gallery.dataset.issImageViewportGalleryBound = '1';

    gallery.querySelectorAll('[data-iss-image-choice], .iss-tour-hero-gallery__thumb').forEach(function (candidate) {
      candidate.addEventListener('click', function () {
        selectImage(gallery, candidate, viewport);
      });
    });
  }

  function initImageViewportGalleries(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('[data-iss-image-viewport-gallery], .iss-tour-hero-gallery').forEach(bindGallery);
  }

  window.ISSFrontendImageViewportGallery = {
    init: initImageViewportGalleries
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initImageViewportGalleries(document);
    }, { once: true });
  } else {
    initImageViewportGalleries(document);
  }
}());
