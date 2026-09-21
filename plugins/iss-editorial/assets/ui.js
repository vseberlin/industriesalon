(function () {
  var modalCount = 0;
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

  function createModal(options) {
    var config = options || {};
    var root = createElement('div', 'iss-editorial-modal');
    var dialog = createElement('div', 'iss-editorial-modal__dialog');
    var head = createElement('div', 'iss-editorial-modal__head');
    var titleWrap = createElement('div', 'iss-editorial-modal__title');
    var body = createElement('div', 'iss-editorial-modal__body');
    var foot = createElement('div', 'iss-editorial-modal__foot');
    var footTools = createElement('div', 'iss-editorial-modal__foot-tools');
    var close = createElement('button', 'button-link iss-editorial-modal__close');
    var closeIcon = createElement('span', 'dashicons dashicons-no-alt');
    var done = createElement('button', 'button button-primary', config.doneLabel || 'Fertig');
    var heading = createElement('h2', '', config.title || 'Abschnitt');
    var releaseFocus = null;
    heading.id = 'iss-editorial-dialog-title-' + (++modalCount);
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', heading.id);
    dialog.tabIndex = -1;

    close.type = 'button';
    close.setAttribute('aria-label', config.closeLabel || 'Schließen');
    closeIcon.setAttribute('aria-hidden', 'true');
    close.appendChild(closeIcon);
    done.type = 'button';

    if (config.kicker) {
      titleWrap.appendChild(createElement('span', 'iss-editorial-modal__kicker', config.kicker));
    }
    titleWrap.appendChild(heading);

    if (typeof config.onClose === 'function') {
      close.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        config.onClose();
      });
    }
    if (typeof config.onDone === 'function') {
      done.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        config.onDone();
      });
    }

    head.appendChild(titleWrap);
    head.appendChild(close);
    footTools.appendChild(done);
    foot.appendChild(createElement('div', 'iss-editorial-modal__foot-left'));
    foot.appendChild(footTools);
    dialog.appendChild(head);
    dialog.appendChild(body);
    dialog.appendChild(foot);
    root.appendChild(dialog);

    root.issEditorialDestroy = function () {
      if (releaseFocus) { releaseFocus(); }
    };
    root.issEditorialOpen = function () {
      releaseFocus = manageModalFocus(dialog, config.onClose, body.querySelector('input, textarea, button, select'));
    };

    return {
      root: root,
      dialog: dialog,
      heading: heading,
      body: body,
      foot: foot,
      footLeft: foot.firstChild,
      footTools: footTools,
      closeButton: close,
      doneButton: done
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

  window.issEditorialUi = {
    manageModalFocus: manageModalFocus,
    createModal: createModal,
    createPanel: createPanel
  };
}());
