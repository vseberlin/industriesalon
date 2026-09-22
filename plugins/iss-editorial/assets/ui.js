(function () {
  function createElement(tag, className, text) {
    var element = document.createElement(tag);
    if (className) {
      element.className = className;
    }
    if (typeof text === 'string') {
      element.textContent = text;
    }
    return element;
  }

  function createIcon(name) {
    var icon = createElement('span', 'iss-editorial-panel__icon');
    icon.setAttribute('aria-hidden', 'true');
    icon.dataset.icon = name || 'panel';
    return icon;
  }

  function manageModalFocus(dialog, onClose, initialFocus) {
    var opener = document.activeElement;
    var background = (opener && opener.closest('[role="dialog"]')) || document.getElementById('wpwrap');
    var wasInert = background ? background.inert : false;
    dialog.tabIndex = -1;
    if (background) { background.inert = true; }

    function keydown(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        if (onClose) { onClose(); }
      } else if (event.key === 'Tab') {
        var items = Array.prototype.filter.call(dialog.querySelectorAll('button, a[href], input, select, textarea, iframe, [tabindex="0"], [contenteditable="true"]'), function (item) {
          return !item.disabled && item.getClientRects().length > 0;
        });
        var first = items[0] || dialog;
        var last = items[items.length - 1] || dialog;
        if (event.shiftKey && (event.target === first || event.target === dialog)) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && event.target === last) {
          event.preventDefault();
          first.focus();
        }
      }
    }
    // Nested WordPress media/link dialogs outside this element keep their own keyboard handling.
    dialog.addEventListener('keydown', keydown);
    (initialFocus || dialog.querySelector('input, textarea, button, select') || dialog).focus();
    return function () {
      dialog.removeEventListener('keydown', keydown);
      if (background) { background.inert = wasInert; }
      if (opener && opener.isConnected) { opener.focus(); }
    };
  }

  function createPanel(options) {
    var config = options || {};
    var root = createElement('section', 'iss-editorial-panel');
    var head = createElement('div', 'iss-editorial-panel__head');
    var title = createElement('div', 'iss-editorial-panel__title');
    var label = createElement('span', 'iss-editorial-panel__label', config.label || 'Panel');
    var body = createElement('div', 'iss-editorial-panel__body');
    var count = null;
    var action = null;

    if (config.name) {
      root.className += ' iss-editorial-panel--' + String(config.name).replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
    }

    if (config.plain) { root.classList.add('iss-editorial-panel--plain'); }
    head.hidden = !!config.hideHeading;
    if (!config.plain) { title.appendChild(createIcon(config.icon || config.name)); }
    title.appendChild(label);
    head.appendChild(title);

    if (typeof config.count === 'number') {
      count = createElement('span', 'iss-editorial-panel__count', String(config.count));
      head.appendChild(count);
    }

    if (config.actionLabel && typeof config.onAction === 'function') {
      action = createElement('button', 'button-link iss-editorial-panel__action', config.actionLabel);
      action.type = 'button';
      action.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        config.onAction();
      });
      head.appendChild(action);
    }

    root.appendChild(head);
    root.appendChild(body);

    return {
      root: root,
      body: body,
      count: count,
      action: action,
      setCount: function (value) {
        if (!count) {
          count = createElement('span', 'iss-editorial-panel__count');
          head.insertBefore(count, action || null);
        }
        count.textContent = String(value);
      }
    };
  }

    function treatmentSketch(choice) {
    var shapes = {
      heading: [[8, 12, 82, 12], [8, 33, 104, 5], [8, 45, 88, 5]],
      text: [[8, 10, 66, 9], [8, 28, 104, 4], [8, 39, 104, 4], [8, 50, 85, 4]],
      callout: [[8, 10, 104, 20], [8, 42, 40, 12]],
      split: [[8, 8, 48, 50], [64, 12, 48, 9], [64, 31, 48, 5], [64, 44, 40, 5]],
      'split-flip': [[64, 8, 48, 50], [8, 12, 48, 9], [8, 31, 48, 5], [8, 44, 40, 5]],
      notes: [[8, 8, 3, 40], [17, 8, 38, 9], [17, 25, 38, 4], [17, 36, 32, 4], [65, 8, 3, 40], [74, 8, 38, 9], [74, 25, 38, 4], [74, 36, 32, 4]],
      cards: [[8, 8, 30, 30], [45, 8, 30, 30], [82, 8, 30, 30], [8, 46, 30, 5], [45, 46, 30, 5], [82, 46, 30, 5]],
      list: [[8, 10, 14, 12], [30, 13, 82, 5], [8, 30, 14, 12], [30, 33, 82, 5], [8, 50, 14, 12], [30, 53, 82, 5]],
      strip: [[8, 8, 34, 50], [50, 13, 62, 8], [50, 31, 62, 4], [50, 44, 50, 4]],
      panel: [[8, 8, 104, 42], [64, 25, 42, 33]],
      overlay: [[8, 8, 104, 50], [18, 20, 60, 9], [18, 38, 80, 5]],
      opening: [[4, 4, 112, 58], [14, 24, 70, 12], [14, 46, 28, 8]]
    };
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 120 66'); svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'iss-editorial-treatment-sketch');
    (shapes[choice.schematic] || shapes.text).forEach(function (box, index) {
      var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      ['x', 'y', 'width', 'height'].forEach(function (attr, i) { rect.setAttribute(attr, String(box[i])); });
      rect.setAttribute('fill', 'currentColor'); rect.setAttribute('opacity', index === 0 ? '0.3' : '0.8'); svg.appendChild(rect);
    });
    return svg;
  }

    function createTextInput(label, value, onChange) {
    var wrapper = createElement('label', 'iss-editorial-field');
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'widefat';
    input.value = value;
    input.addEventListener('input', function () { onChange(input.value); });
    wrapper.appendChild(createElement('span', '', label));
    wrapper.appendChild(input);
    return wrapper;
  }

  function createNumberInput(label, value, min, max, onChange) {
    var wrapper = createElement('label', 'iss-editorial-field');
    var input = document.createElement('input');
    input.type = 'number';
    input.className = 'small-text';
    input.min = String(min);
    input.max = String(max);
    input.value = value || '';
    input.addEventListener('input', function () {
      onChange(input.value === '' ? '' : parseInt(input.value, 10));
    });
    wrapper.appendChild(createElement('span', '', label));
    wrapper.appendChild(input);
    return wrapper;
  }

  function createSelect(label, value, choices, onChange) {
    var wrapper = createElement('label', 'iss-editorial-field');
    var select = document.createElement('select');
    select.className = 'widefat';
    choices.forEach(function (choice) {
      var option = document.createElement('option');
      option.value = choice.value;
      option.textContent = choice.label;
      option.selected = String(choice.value) === String(value);
      select.appendChild(option);
    });
    select.addEventListener('change', function () { onChange(select.value); });
    wrapper.appendChild(createElement('span', '', label));
    wrapper.appendChild(select);
    return wrapper;
  }

  function createTextarea(label, value, onChange, rows) {
    var wrapper = createElement('label', 'iss-editorial-field');
    var input = document.createElement('textarea');
    input.className = 'widefat';
    input.rows = rows || 7;
    input.value = value;
    input.addEventListener('input', function () { onChange(input.value); });
    wrapper.appendChild(createElement('span', '', label));
    wrapper.appendChild(input);
    return wrapper;
  }

  function createCheckbox(label, checked, onChange) {
    var wrapper = createElement('label', 'iss-editorial-check');
    var input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = !!checked;
    input.addEventListener('change', function () { onChange(input.checked); });
    wrapper.appendChild(input);
    wrapper.appendChild(createElement('span', '', label));
    return wrapper;
  }

  window.issEditorialUi = {
    treatmentSketch: treatmentSketch,
    createTextInput: createTextInput,
    createNumberInput: createNumberInput,
    createSelect: createSelect,
    createTextarea: createTextarea,
    createCheckbox: createCheckbox,

    manageModalFocus: manageModalFocus,
    createPanel: createPanel
  };
}());
