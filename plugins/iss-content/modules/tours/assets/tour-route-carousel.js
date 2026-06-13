(function () {
  function setViewportHeight(viewport, slide) {
    if (!viewport || !slide) {
      return;
    }

    viewport.style.height = slide.offsetHeight + 'px';
  }

  function setActive(route, state, nextIndex, updateHash) {
    if (!state.slides.length) {
      return;
    }

    var index = Math.max(0, Math.min(nextIndex, state.slides.length - 1));
    state.index = index;

    var activeSlide = state.slides[index];
    state.track.style.transform = 'translate3d(' + (index * -100) + '%, 0, 0)';
    setViewportHeight(state.viewport, activeSlide);

    state.slides.forEach(function (slide, slideIndex) {
      var isActive = slideIndex === index;

      slide.classList.toggle('is-active', isActive);
      slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');

      if (isActive) {
        slide.removeAttribute('inert');
      } else {
        slide.setAttribute('inert', '');
      }
    });

    state.links.forEach(function (link) {
      var isActive = link.getAttribute('data-route-target') === activeSlide.id;
      var stop = link.closest('.iss-tour-route__rail-stop');

      link.classList.toggle('is-active', isActive);

      if (isActive) {
        link.setAttribute('aria-current', 'step');
      } else {
        link.removeAttribute('aria-current');
      }

      if (stop) {
        stop.classList.toggle('is-active', isActive);
      }
    });

    if (updateHash && activeSlide.id) {
      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState(null, '', '#' + activeSlide.id);
      } else {
        window.location.hash = activeSlide.id;
      }
    }
  }

  function initRoute(route) {
    var viewport = route.querySelector('[data-route-viewport]');
    var track = route.querySelector('[data-route-track]');
    if (!viewport || !track) {
      return;
    }

    var slides = Array.prototype.slice.call(track.querySelectorAll('[data-route-slide]'));
    var links = Array.prototype.slice.call(route.querySelectorAll('.iss-tour-route__rail-link[data-route-target]'));
    if (slides.length < 2 || !links.length) {
      return;
    }

    var indexedLinks = links.filter(function (link) {
      return slides.some(function (slide) {
        return slide.id === link.getAttribute('data-route-target');
      });
    });

    if (indexedLinks.length < 2) {
      return;
    }

    var state = {
      index: 0,
      links: indexedLinks,
      slides: slides,
      track: track,
      viewport: viewport
    };

    route.classList.add('is-carousel-ready');

    indexedLinks.forEach(function (link) {
      link.addEventListener('click', function (event) {
        var targetId = link.getAttribute('data-route-target');
        var targetIndex = slides.findIndex(function (slide) {
          return slide.id === targetId;
        });

        if (targetIndex < 0) {
          return;
        }

        event.preventDefault();
        setActive(route, state, targetIndex, true);
      });
    });

    var initialIndex = slides.findIndex(function (slide) {
      return window.location.hash === '#' + slide.id;
    });

    setActive(route, state, initialIndex >= 0 ? initialIndex : 0, false);

    track.querySelectorAll('img').forEach(function (image) {
      if (image.complete) {
        return;
      }

      image.addEventListener('load', function () {
        setViewportHeight(viewport, slides[state.index]);
      }, { passive: true, once: true });
    });

    window.addEventListener('resize', function () {
      setViewportHeight(viewport, slides[state.index]);
    }, { passive: true });
  }

  function initAll() {
    var routes = document.querySelectorAll('.iss-tour-route');
    if (!routes.length) {
      return;
    }

    routes.forEach(initRoute);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
