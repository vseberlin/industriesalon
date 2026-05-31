document.addEventListener('DOMContentLoaded', function () {
  var tabs = document.querySelectorAll('.iss-tabs a');
  var panels = document.querySelectorAll('.iss-panel');

  function activate(hash) {
    var target = hash || '#iss-general';
    tabs.forEach(function (tab) {
      tab.classList.toggle('nav-tab-active', tab.getAttribute('href') === target);
    });
    panels.forEach(function (panel) {
      panel.hidden = ('#' + panel.id) !== target;
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function (event) {
      event.preventDefault();
      var hash = this.getAttribute('href');
      activate(hash);
      if (history.replaceState) {
        history.replaceState(null, '', hash);
      } else {
        window.location.hash = hash;
      }
    });
  });

  activate(window.location.hash || '#iss-general');

  var specialList = document.querySelector('[data-iss-special-list]');
  var specialTemplate = document.getElementById('iss-special-template');
  var specialAdd = document.querySelector('[data-iss-add-special]');

  function specialCount() {
    return specialList ? specialList.querySelectorAll('[data-iss-special-item]').length : 0;
  }

  function bindSpecialRemove(scope) {
    scope.querySelectorAll('[data-iss-remove-special]').forEach(function (button) {
      button.addEventListener('click', function () {
        var item = this.closest('[data-iss-special-item]');
        if (item) {
          item.remove();
        }
      });
    });
  }

  if (specialList) {
    bindSpecialRemove(specialList);
  }

  if (specialAdd && specialList && specialTemplate) {
    specialAdd.addEventListener('click', function () {
      if (specialCount() >= 20) {
        window.alert('Maximal 20 Sondertermine.');
        return;
      }
      var html = specialTemplate.innerHTML.replace(/__INDEX__/g, String(Date.now()));
      var wrapper = document.createElement('div');
      wrapper.innerHTML = html;
      while (wrapper.firstElementChild) {
        specialList.appendChild(wrapper.firstElementChild);
      }
      bindSpecialRemove(specialList);
    });
  }

  var settingsForm = document.querySelector('.iss-admin form[action="options.php"]');
  if (settingsForm) {
    settingsForm.addEventListener('submit', function (event) {
      var ok = true;
      var message = '';
      settingsForm.querySelectorAll('tr').forEach(function (row) {
        var closed = row.querySelector('input[type="checkbox"][name*="[closed]"]');
        var open = row.querySelector('input.iss-time[name*="[open]"]');
        var close = row.querySelector('input.iss-time[name*="[close]"]');
        if (!open || !close) {
          return;
        }
        if (closed && closed.checked) {
          return;
        }
        if (open.value !== '' && close.value !== '' && open.value > close.value) {
          ok = false;
          message = 'Bitte Zeiten prüfen: "Von" darf nicht später als "Bis" sein.';
        }
      });
      settingsForm.querySelectorAll('[data-iss-special-item]').forEach(function (row) {
        var date = row.querySelector('input[type="date"]');
        var open = row.querySelector('input.iss-time[name*="[open]"]');
        var close = row.querySelector('input.iss-time[name*="[close]"]');
        if (!date || !open || !close) {
          return;
        }
        if (date.value === '' && (open.value !== '' || close.value !== '')) {
          ok = false;
          message = 'Sondertermine benötigen ein Datum.';
        }
        if (open.value !== '' && close.value !== '' && open.value > close.value) {
          ok = false;
          message = 'Sondertermine: "Von" darf nicht später als "Bis" sein.';
        }
      });
      if (!ok) {
        event.preventDefault();
        window.alert(message);
      }
    });
  }
});
