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
    var done = createElement('button', 'button button-primary', config.doneLabel || 'Übernehmen');

    close.type = 'button';
    close.setAttribute('aria-label', config.closeLabel || 'Schließen');
    closeIcon.setAttribute('aria-hidden', 'true');
    close.appendChild(closeIcon);
    done.type = 'button';

    if (config.kicker) {
      titleWrap.appendChild(createElement('span', 'iss-editorial-modal__kicker', config.kicker));
    }
    titleWrap.appendChild(createElement('h2', '', config.title || 'Abschnitt'));

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

    return {
      root: root,
      dialog: dialog,
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

    title.appendChild(createIcon(config.icon || config.name));
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
    createModal: createModal,
    createPanel: createPanel
  };
}());
