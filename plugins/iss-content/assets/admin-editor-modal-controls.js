(function () {
  var active = null;
  var modalConfig = window.issContentEditorModalControls || {};
  window.issContentEditorModalHandlers = window.issContentEditorModalHandlers || {};

  if (document.body) {
    if (modalConfig.hideManagedBoxes !== false) {
      document.body.classList.add('iss-editor-modal-controls-ready');
    }
    if (modalConfig.moveEditorTopGroups === true || modalConfig.moveAusstellungTopGroups === true) {
      document.body.classList.add('iss-editor-dashboard-ready');
    }
  }

  function moveEditorBox(id, target) {
    var box = document.getElementById(id);
    if (box && target) {
      target.appendChild(box);
    }
  }

  function setupEditorTopGroups() {
    var moveDashboard = modalConfig.moveEditorTopGroups === true || modalConfig.moveAusstellungTopGroups === true;
    if (!moveDashboard) {
      return;
    }

    var editor = document.getElementById('postdivrich');
    var title = document.getElementById('titlediv');
    if (!editor || !title || document.querySelector('.iss-editor-top-dashboard')) {
      return;
    }

    var top = document.createElement('div');
    top.className = 'iss-editor-top-dashboard';
    title.parentNode.insertBefore(top, editor);

    var boxIds = Array.isArray(modalConfig.editorTopGroupIds) && modalConfig.editorTopGroupIds.length
      ? modalConfig.editorTopGroupIds
      : ['postexcerpt', 'postimagediv'];

    boxIds.forEach(function (id) {
      moveEditorBox(id, top);
    });

    if (!top.children.length) {
      top.parentNode.removeChild(top);
    }
  }

  function createModal() {
    var form = document.getElementById('post') || document.body;
    var modal = document.createElement('div');
    modal.className = 'iss-editor-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML =
      '<div class="iss-editor-modal__backdrop" data-iss-editor-modal-close></div>' +
      '<div class="iss-editor-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="iss-editor-modal-title">' +
        '<div class="iss-editor-modal__header">' +
          '<h2 class="iss-editor-modal__title" id="iss-editor-modal-title"></h2>' +
          '<button type="button" class="iss-editor-modal__close" data-iss-editor-modal-close aria-label="Schliessen">&times;</button>' +
        '</div>' +
        '<div class="iss-editor-modal__body"></div>' +
        '<div class="iss-editor-modal__footer">' +
          '<span class="description">Aenderungen werden beim Speichern des Inhalts uebernommen.</span>' +
          '<button type="button" class="button button-primary" data-iss-editor-modal-close>Fertig</button>' +
        '</div>' +
      '</div>';
    form.appendChild(modal);
    return modal;
  }

  function getModal() {
    var modal = document.querySelector('.iss-editor-modal');
    return modal || createModal();
  }

  function clear(element) {
    while (element.firstChild) {
      element.removeChild(element.firstChild);
    }
  }

  function restoreActiveBox() {
    if (active && active.box && active.inside) {
      active.box.appendChild(active.inside);
    }
    active = null;
  }

  function closeModal() {
    var modal = getModal();

    restoreActiveBox();
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }

  function openModalContent(titleText, content, options) {
    var modal = getModal();
    var body = modal.querySelector('.iss-editor-modal__body');
    var title = modal.querySelector('.iss-editor-modal__title');
    options = options || {};

    if (!body || !title) {
      return null;
    }

    restoreActiveBox();
    clear(body);
    title.textContent = titleText || 'Inhalt bearbeiten';

    if (typeof content === 'string') {
      body.innerHTML = content;
    } else if (content) {
      body.appendChild(content);
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    if (options.focusSelector) {
      var focusTarget = body.querySelector(options.focusSelector);
      if (focusTarget) {
        focusTarget.focus();
      }
    }

    return body;
  }

  function openBoxModal(targetId) {
    var box = document.getElementById(targetId);
    var inside = box ? box.querySelector('.inside') : null;
    var titleSource = box ? box.querySelector('.hndle, .postbox-header h2, h2') : null;
    var title = titleSource ? titleSource.textContent : 'Inhalt bearbeiten';

    if (!box || !inside) {
      return;
    }

    openModalContent(title, inside);
    active = {
      box: box,
      inside: inside
    };

    var firstField = inside.querySelector('input:not([type="hidden"]), select, textarea, button');
    if (firstField) {
      firstField.focus();
    }
  }

  function openModal(targetId) {
    var handlers = window.issContentEditorModalHandlers || {};
    if (handlers[targetId] && typeof handlers[targetId].open === 'function') {
      handlers[targetId].open({
        openContent: openModalContent,
        close: closeModal
      });
      return;
    }

    openBoxModal(targetId);
  }

  window.issContentEditorModalControlsApi = {
    open: openModal,
    close: closeModal,
    openContent: openModalContent
  };

  document.addEventListener('click', function (event) {
    var openButton = event.target.closest('[data-iss-editor-modal-target]');
    if (openButton) {
      event.preventDefault();
      openModal(openButton.getAttribute('data-iss-editor-modal-target'));
      return;
    }

    if (event.target.closest('[data-iss-editor-modal-close]')) {
      event.preventDefault();
      closeModal();
    }
  });

  document.addEventListener('keydown', function (event) {
    var modal = document.querySelector('.iss-editor-modal');
    if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
      closeModal();
    }
  });

  document.addEventListener('DOMContentLoaded', setupEditorTopGroups);
}());
