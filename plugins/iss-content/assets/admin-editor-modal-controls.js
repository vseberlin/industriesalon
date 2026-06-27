(function () {
  var active = null;
  var modalConfig = window.issContentEditorModalControls || {};
  window.issContentEditorModalHandlers = window.issContentEditorModalHandlers || {};

  function isEnabled(value) {
    return value === true || value === '1' || value === 1;
  }

  if (document.body) {
    if (isEnabled(modalConfig.hideManagedBoxes)) {
      document.body.classList.add('iss-editor-modal-controls-ready');
    }
    if (isEnabled(modalConfig.hideDashboardTechnicalBoxes)) {
      document.body.classList.add('iss-editor-dashboard-simplified');
    }
    if (isEnabled(modalConfig.moveEditorTopGroups) || isEnabled(modalConfig.moveAusstellungTopGroups)) {
      document.body.classList.add('iss-editor-dashboard-ready');
    }
  }

  function moveEditorBox(id, target) {
    var box = document.getElementById(id);
    if (box && target) {
      box.classList.add('iss-editor-dashboard__box');
      target.appendChild(box);
    }

    return box;
  }

  function moveDashboardSelector(selector, target) {
    var item = document.querySelector(selector);
    if (item && target && !item.closest('.iss-editor-dashboard')) {
      item.classList.add('iss-editor-dashboard__item');
      target.appendChild(item);
    }

    return item;
  }

  function dashboardSections() {
    return (Array.isArray(modalConfig.dashboardSections) ? modalConfig.dashboardSections : []).filter(function (section) {
      return section && section.slug && (
        (Array.isArray(section.boxIds) && section.boxIds.length) ||
        (Array.isArray(section.selectors) && section.selectors.length)
      );
    });
  }

  function createDashboardSection(section) {
    var panel = document.createElement('section');
    var header = document.createElement('div');
    var title = document.createElement('h2');
    var body = document.createElement('div');

    panel.className = 'iss-editor-dashboard__section iss-editor-dashboard__section--' + section.slug;
    header.className = 'iss-editor-dashboard__section-head';
    body.className = 'iss-editor-dashboard__section-body';
    title.textContent = section.label || section.slug;

    header.appendChild(title);
    if (section.description) {
      var description = document.createElement('p');
      description.textContent = section.description;
      header.appendChild(description);
    }

    panel.appendChild(header);
    panel.appendChild(body);

    (Array.isArray(section.boxIds) ? section.boxIds : []).forEach(function (id) {
      moveEditorBox(id, body);
    });
    (Array.isArray(section.selectors) ? section.selectors : []).forEach(function (selector) {
      moveDashboardSelector(selector, body);
    });

    if (!body.children.length) {
      return null;
    }

    return panel;
  }

  function setupEditorDashboardSections(title, editor) {
    var sections = dashboardSections();
    var dashboard;

    if (!sections.length || document.querySelector('.iss-editor-dashboard')) {
      return false;
    }

    dashboard = document.createElement('div');
    dashboard.className = 'iss-editor-dashboard';
    title.parentNode.insertBefore(dashboard, editor || title.nextSibling);

    sections.forEach(function (section) {
      var panel = createDashboardSection(section);
      if (panel) {
        dashboard.appendChild(panel);
      }
    });

    if (!dashboard.children.length) {
      dashboard.parentNode.removeChild(dashboard);
      return false;
    }

    return true;
  }

  function setupEditorTopGroups() {
    var moveDashboard = isEnabled(modalConfig.moveEditorTopGroups) || isEnabled(modalConfig.moveAusstellungTopGroups);
    if (!moveDashboard) {
      return;
    }

    var title = document.getElementById('titlediv');
    var editor = document.getElementById('postdivrich');
    if (!title || document.querySelector('.iss-editor-top-dashboard')) {
      return;
    }

    if (setupEditorDashboardSections(title, editor)) {
      return;
    }

    var top = document.createElement('div');
    top.className = 'iss-editor-top-dashboard';
    title.parentNode.insertBefore(top, editor || title.nextSibling);

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
