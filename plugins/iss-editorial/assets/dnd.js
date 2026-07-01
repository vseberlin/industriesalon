(function () {
  function closest(node, selector, boundary) {
    while (node && node !== boundary) {
      if (node.matches && node.matches(selector)) {
        return node;
      }
      node = node.parentNode;
    }

    return node === boundary && node.matches && node.matches(selector) ? node : null;
  }

  function intAttr(node, name, fallback) {
    var value = parseInt(node && node.getAttribute(name), 10);

    return Number.isFinite(value) ? value : fallback;
  }

  function ensurePlaceholder(stage) {
    var placeholder = stage.querySelector('.iss-editorial-dnd-placeholder');
    if (!placeholder) {
      placeholder = document.createElement('div');
      placeholder.className = 'iss-editorial-dnd-placeholder';
      placeholder.setAttribute('aria-hidden', 'true');
    }

    return placeholder;
  }

  function sectionCards(stage, selector) {
    return Array.prototype.slice.call(stage.querySelectorAll(selector)).filter(function (card) {
      return !card.classList.contains('iss-editorial-card--deleted');
    });
  }

  function dragStartIsCancelled(target, boundary, handleSelector) {
    if (closest(target, handleSelector, boundary)) {
      return false;
    }

    return !!closest(target, 'button, a, input, textarea, select, option, [contenteditable="true"]', boundary);
  }

  function dropIndexForPointer(stage, selector, clientY) {
    var cards = sectionCards(stage, selector);
    var fallback = intAttr(stage, 'data-section-count', cards.length);
    var index = fallback;

    cards.some(function (card) {
      var rect = card.getBoundingClientRect();
      var cardIndex = intAttr(card, 'data-section-index', index);
      if (clientY < rect.top + (rect.height / 2)) {
        index = cardIndex;
        return true;
      }

      index = cardIndex + 1;
      return false;
    });

    return index;
  }

  function placePlaceholder(stage, selector, index) {
    var placeholder = ensurePlaceholder(stage);
    var cards = sectionCards(stage, selector);
    var before = null;

    cards.some(function (card) {
      if (intAttr(card, 'data-section-index', 0) >= index) {
        before = card;
        return true;
      }

      return false;
    });

    if (before) {
      stage.insertBefore(placeholder, before);
    } else {
      stage.appendChild(placeholder);
    }
  }

  function removePlaceholder(stage) {
    var placeholder = stage.querySelector('.iss-editorial-dnd-placeholder');
    if (placeholder) {
      placeholder.remove();
    }
  }

  function bindSectionCanvas(options) {
    var stage = options && options.stage;
    var palette = options && options.palette;
    var cardSelector = options.cardSelector || '.iss-editorial-card';
    var handleSelector = options.handleSelector || '.iss-editorial-card__drag-handle';
    var paletteSelector = options.paletteSelector || '.iss-editorial-gesture';
    var state = null;

    if (!stage || !palette) {
      return;
    }

    function cleanup() {
      var active = stage.querySelector('.iss-editorial-card.is-dragging');
      if (active) {
        active.classList.remove('is-dragging');
      }
      removePlaceholder(stage);
      stage.classList.remove('is-drag-target');
      palette.classList.remove('is-drag-source');
      state = null;
    }

    palette.addEventListener('dragstart', function (event) {
      var item = closest(event.target, paletteSelector, palette);
      var type = item ? item.getAttribute('data-section-type') : '';
      if (!type) {
        return;
      }

      state = { kind: 'insert', type: type, dropIndex: intAttr(stage, 'data-section-count', 0) };
      palette.classList.add('is-drag-source');
      event.dataTransfer.effectAllowed = 'copy';
      event.dataTransfer.setData('text/plain', type);
    });

    palette.addEventListener('dragend', cleanup);

    stage.addEventListener('dragstart', function (event) {
      var handle = closest(event.target, handleSelector, stage);
      var card;
      var index;
      if (dragStartIsCancelled(event.target, stage, handleSelector)) {
        event.preventDefault();
        return;
      }

      card = handle ? closest(handle, cardSelector, stage) : closest(event.target, cardSelector, stage);
      index = intAttr(card, 'data-section-index', -1);
      if (!card || index < 0) {
        return;
      }

      state = { kind: 'reorder', fromIndex: index, dropIndex: index };
      card.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', String(index));
    });

    stage.addEventListener('dragover', function (event) {
      if (!state) {
        return;
      }

      event.preventDefault();
      state.dropIndex = dropIndexForPointer(stage, cardSelector, event.clientY);
      event.dataTransfer.dropEffect = state.kind === 'insert' ? 'copy' : 'move';
      stage.classList.add('is-drag-target');
      placePlaceholder(stage, cardSelector, state.dropIndex);
    });

    stage.addEventListener('dragleave', function (event) {
      if (event.relatedTarget && stage.contains(event.relatedTarget)) {
        return;
      }
      removePlaceholder(stage);
      stage.classList.remove('is-drag-target');
    });

    stage.addEventListener('drop', function (event) {
      if (!state) {
        return;
      }

      event.preventDefault();
      if (state.kind === 'insert' && typeof options.onInsert === 'function') {
        options.onInsert(state.type, state.dropIndex);
      } else if (state.kind === 'reorder' && typeof options.onReorder === 'function') {
        options.onReorder(state.fromIndex, state.dropIndex);
      }
      cleanup();
    });

    stage.addEventListener('dragend', cleanup);

    stage.addEventListener('keydown', function (event) {
      var handle = closest(event.target, handleSelector, stage);
      var card = handle ? closest(handle, cardSelector, stage) : null;
      var index = intAttr(card, 'data-section-index', -1);
      var direction = 0;

      if (!handle || index < 0) {
        return;
      }

      if (event.key === 'ArrowUp') {
        direction = -1;
      } else if (event.key === 'ArrowDown') {
        direction = 1;
      }

      if (direction !== 0 && typeof options.onKeyboardMove === 'function') {
        event.preventDefault();
        options.onKeyboardMove(index, direction);
      }
    });
  }

  window.issEditorialDnd = {
    bindSectionCanvas: bindSectionCanvas
  };
}());
