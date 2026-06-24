(function () {
  function getScrollMax(strip) {
    return Math.max(0, strip.scrollWidth - strip.clientWidth);
  }

  function getScrollStep(strip) {
    var firstCard = strip.firstElementChild;
    var styles = window.getComputedStyle(strip);
    var gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;

    if (!firstCard) {
      return Math.max(strip.clientWidth * 0.9, 1);
    }

    return Math.max(firstCard.getBoundingClientRect().width + gap, 1);
  }

  function updateCarouselState(strip) {
    var carousel = strip.closest('[data-related-carousel], [data-iss-strip-carousel]');
    if (!carousel) {
      return;
    }

    var prevButton = carousel.querySelector('[data-related-carousel-prev], [data-iss-strip-carousel-prev]');
    var nextButton = carousel.querySelector('[data-related-carousel-next], [data-iss-strip-carousel-next]');
    var maxScroll = getScrollMax(strip);
    var canScroll = maxScroll > 1;
    var scrollLeft = strip.scrollLeft;
    var isAtStart = scrollLeft <= 2;
    var isAtEnd = scrollLeft >= (maxScroll - 2);

    carousel.classList.toggle('is-carousel-ready', canScroll);
    carousel.classList.toggle('is-carousel-static', !canScroll);

    if (prevButton) {
      prevButton.disabled = !canScroll || isAtStart;
    }

    if (nextButton) {
      nextButton.disabled = !canScroll || isAtEnd;
    }
  }

  function scrollStrip(strip, direction) {
    var maxScroll = getScrollMax(strip);
    if (maxScroll <= 1) {
      return;
    }

    var nextLeft = strip.scrollLeft + (getScrollStep(strip) * direction);
    nextLeft = Math.max(0, Math.min(maxScroll, nextLeft));

    strip.scrollTo({
      left: nextLeft,
      behavior: 'smooth'
    });
  }

  function bindRelatedStrip(strip) {
    if (!strip || strip.dataset.issRelatedStripBound === '1') {
      return;
    }

    strip.dataset.issRelatedStripBound = '1';

    var carousel = strip.closest('[data-related-carousel], [data-iss-strip-carousel]');
    var prevButton = carousel ? carousel.querySelector('[data-related-carousel-prev], [data-iss-strip-carousel-prev]') : null;
    var nextButton = carousel ? carousel.querySelector('[data-related-carousel-next], [data-iss-strip-carousel-next]') : null;
    var resizeObserver = null;
    var dragState = {
      active: false,
      moved: false,
      startX: 0,
      startScrollLeft: 0
    };

    strip.addEventListener(
      'wheel',
      function (event) {
        if (event.defaultPrevented || event.ctrlKey || event.metaKey) {
          return;
        }

        if ((strip.scrollWidth - strip.clientWidth) <= 1) {
          return;
        }

        var verticalDelta = Math.abs(event.deltaY);
        var horizontalDelta = Math.abs(event.deltaX);
        var delta = verticalDelta > horizontalDelta ? event.deltaY : event.deltaX;

        if (!delta) {
          return;
        }

        var previousScrollLeft = strip.scrollLeft;
        strip.scrollLeft += delta;

        if (strip.scrollLeft !== previousScrollLeft) {
          event.preventDefault();
        }
      },
      { passive: false }
    );

    Array.prototype.forEach.call(strip.querySelectorAll('a, img'), function (element) {
      element.setAttribute('draggable', 'false');
    });

    strip.addEventListener('dragstart', function (event) {
      event.preventDefault();
    });

    strip.addEventListener('selectstart', function (event) {
      if (!dragState.active) {
        return;
      }

      event.preventDefault();
    });

    if (prevButton) {
      prevButton.addEventListener('click', function () {
        scrollStrip(strip, -1);
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', function () {
        scrollStrip(strip, 1);
      });
    }

    strip.addEventListener('mousedown', function (event) {
      if (event.button !== 0) {
        return;
      }

      dragState.active = true;
      dragState.moved = false;
      dragState.startX = event.clientX;
      dragState.startScrollLeft = strip.scrollLeft;

      strip.classList.add('is-dragging');

      event.preventDefault();
    });

    window.addEventListener('mousemove', function (event) {
      var deltaX;

      if (!dragState.active) {
        return;
      }

      deltaX = event.clientX - dragState.startX;

      if (Math.abs(deltaX) > 5) {
        dragState.moved = true;
      }

      if (!dragState.moved) {
        return;
      }

      strip.scrollLeft = dragState.startScrollLeft - deltaX;
      event.preventDefault();
    });

    function endDrag() {
      if (!dragState.active) {
        return;
      }

      dragState.active = false;
      strip.classList.remove('is-dragging');

      window.setTimeout(function () {
        dragState.moved = false;
      }, 0);
    }

    window.addEventListener('mouseup', endDrag);
    window.addEventListener('blur', endDrag);

    strip.addEventListener('click', function (event) {
      if (!dragState.moved) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    }, true);

    strip.addEventListener('scroll', function () {
      window.requestAnimationFrame(function () {
        updateCarouselState(strip);
      });
    }, { passive: true });

    window.addEventListener('resize', function () {
      updateCarouselState(strip);
    }, { passive: true });

    if (window.ResizeObserver) {
      resizeObserver = new window.ResizeObserver(function () {
        updateCarouselState(strip);
      });

      resizeObserver.observe(strip);
      Array.prototype.forEach.call(strip.children || [], function (child) {
        resizeObserver.observe(child);
      });
    }

    Array.prototype.forEach.call(strip.querySelectorAll('img'), function (image) {
      if (image.complete) {
        return;
      }

      image.addEventListener('load', function () {
        updateCarouselState(strip);
      }, { passive: true, once: true });
    });

    updateCarouselState(strip);
  }

  function initRelatedStrips(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var strips = scope.querySelectorAll('.iss-related-feed__grid--layout-strip, [data-iss-strip-carousel-track]');

    strips.forEach(bindRelatedStrip);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initRelatedStrips(document);
    }, { once: true });
  } else {
    initRelatedStrips(document);
  }
}());
