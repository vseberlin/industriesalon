(function () {
  function createItem(id, label) {
    var item = document.createElement('li');
    item.className = 'iss-content-model-corpus__item';
    item.setAttribute('data-iss-corpus-item', '');
    item.setAttribute('data-id', String(id));

    var text = document.createElement('span');
    text.className = 'iss-content-model-corpus__label';
    text.textContent = label;
    item.appendChild(text);

    var actions = document.createElement('span');
    actions.className = 'iss-content-model-corpus__actions';

    var up = document.createElement('button');
    up.type = 'button';
    up.className = 'button-link';
    up.setAttribute('data-iss-corpus-up', '');
    up.textContent = 'Nach oben';
    actions.appendChild(up);
    actions.appendChild(document.createTextNode(' '));

    var down = document.createElement('button');
    down.type = 'button';
    down.className = 'button-link';
    down.setAttribute('data-iss-corpus-down', '');
    down.textContent = 'Nach unten';
    actions.appendChild(down);
    actions.appendChild(document.createTextNode(' '));

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button-link-delete';
    remove.setAttribute('data-iss-corpus-remove', '');
    remove.textContent = 'Entfernen';
    actions.appendChild(remove);

    item.appendChild(actions);

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'iss_content_model[iss_corpus_chapter_ids][]';
    input.value = String(id);
    item.appendChild(input);

    return item;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var sourceSelect = document.querySelector('[data-iss-exhibition-source-select]');
    var sourcePanels = Array.prototype.slice.call(document.querySelectorAll('[data-iss-exhibition-source-panel]'));

    function updateSourcePanels() {
      if (!sourceSelect || !sourcePanels.length) {
        return;
      }

      var activeSource = sourceSelect.value || 'manual';
      sourcePanels.forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-iss-exhibition-source-panel') !== activeSource;
      });
    }

    if (sourceSelect) {
      sourceSelect.addEventListener('change', updateSourcePanels);
      updateSourcePanels();
    }

    document.querySelectorAll('[data-iss-corpus-builder]').forEach(function (builder) {
      var picker = builder.querySelector('[data-iss-corpus-picker]');
      var list = builder.querySelector('[data-iss-corpus-list]');
      var addButton = builder.querySelector('[data-iss-corpus-add]');

      if (!picker || !list || !addButton) {
        return;
      }

      addButton.addEventListener('click', function () {
        var option = picker.options[picker.selectedIndex];
        if (!option || !option.value) {
          return;
        }

        if (list.querySelector('[data-id="' + CSS.escape(option.value) + '"]')) {
          return;
        }

        list.appendChild(createItem(option.value, option.textContent));
      });

      list.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        var item = target.closest('[data-iss-corpus-item]');
        if (!item) {
          return;
        }

        if (target.hasAttribute('data-iss-corpus-remove')) {
          item.remove();
          return;
        }

        if (target.hasAttribute('data-iss-corpus-up')) {
          var previous = item.previousElementSibling;
          if (previous) {
            list.insertBefore(item, previous);
          }
          return;
        }

        if (target.hasAttribute('data-iss-corpus-down')) {
          var next = item.nextElementSibling;
          if (next) {
            list.insertBefore(next, item);
          }
        }
      });
    });
  });
}());
