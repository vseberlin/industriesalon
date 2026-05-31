(function () {
  function setupRail(root) {
    var track = root.querySelector('.iss-reading-nav__inner');

    if (!track || root.dataset.readingNavReady === 'true') {
      return;
    }

    if (root.dataset.readingNavMode === 'stack') {
      root.dataset.readingNavReady = 'true';
      return;
    }

    root.dataset.readingNavReady = 'true';

    var previous = document.createElement('button');
    previous.type = 'button';
    previous.className = 'iss-reading-nav__control iss-reading-nav__control--prev';
    previous.setAttribute('aria-label', 'Vorherige Kapitel');
    previous.innerHTML = '&#8249;';

    var next = document.createElement('button');
    next.type = 'button';
    next.className = 'iss-reading-nav__control iss-reading-nav__control--next';
    next.setAttribute('aria-label', 'Nächste Kapitel');
    next.innerHTML = '&#8250;';

    root.insertBefore(previous, track);
    root.appendChild(next);

    function sync() {
      var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
      var hidden = maxScroll < 8;

      previous.hidden = hidden;
      next.hidden = hidden;
      previous.disabled = hidden || track.scrollLeft <= 4;
      next.disabled = hidden || track.scrollLeft >= maxScroll - 4;
    }

    function move(direction) {
      track.scrollBy({
        left: direction * Math.max(track.clientWidth * 0.82, 220),
        behavior: 'smooth'
      });
    }

    previous.addEventListener('click', function () {
      move(-1);
    });

    next.addEventListener('click', function () {
      move(1);
    });

    track.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    sync();
  }

  function init() {
    var rails = document.querySelectorAll('.iss-reading-nav');
    rails.forEach(setupRail);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
