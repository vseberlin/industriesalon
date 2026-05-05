(function () {
  function renumberRows(container) {
    var rows = container.querySelectorAll('[data-iss-relations-row]');
    rows.forEach(function (row, index) {
      row.querySelectorAll('[name]').forEach(function (field) {
        field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
      });
    });
  }

  function getRowsContainer(box) {
    return box.querySelector('[data-iss-relations-rows]');
  }

  function getTemplate(box) {
    return box.querySelector('[data-iss-relations-template]');
  }

  function addRow(box) {
    var rows = getRowsContainer(box);
    var template = getTemplate(box);

    if (!rows || !template) {
      return;
    }

    var wrapper = document.createElement('tbody');
    wrapper.innerHTML = template.innerHTML.trim();

    if (!wrapper.firstElementChild) {
      return;
    }

    rows.appendChild(wrapper.firstElementChild);
    renumberRows(rows);
  }

  function moveRow(row, direction) {
    if (!row || !row.parentNode) {
      return;
    }

    var sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) {
      return;
    }

    if (direction === 'up') {
      row.parentNode.insertBefore(row, sibling);
      return;
    }

    row.parentNode.insertBefore(sibling, row);
  }

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest('[data-iss-relations-add]');
    if (addButton) {
      var addBox = addButton.closest('[data-iss-relations-box]');
      if (addBox) {
        addRow(addBox);
      }
      event.preventDefault();
      return;
    }

    var removeButton = event.target.closest('[data-iss-relations-remove]');
    if (removeButton) {
      var removeRow = removeButton.closest('[data-iss-relations-row]');
      var removeBox = removeButton.closest('[data-iss-relations-box]');
      if (removeRow) {
        removeRow.remove();
      }
      if (removeBox) {
        renumberRows(getRowsContainer(removeBox));
      }
      event.preventDefault();
      return;
    }

    var moveButton = event.target.closest('[data-iss-relations-move]');
    if (moveButton) {
      var row = moveButton.closest('[data-iss-relations-row]');
      var box = moveButton.closest('[data-iss-relations-box]');
      moveRow(row, moveButton.getAttribute('data-iss-relations-move'));
      if (box) {
        renumberRows(getRowsContainer(box));
      }
      event.preventDefault();
    }
  });
})();
