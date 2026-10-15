/* Enhance the shared agenda; every entry remains readable without JavaScript. */
(function () {
  'use strict';
  document.querySelectorAll('[data-iss-editorial-agenda]').forEach(function (agenda) {
    var filters = agenda.querySelector('.iss-landing-agenda__filters');
    if (!filters) { return; }
    var entries = Array.from(agenda.querySelectorAll('[data-agenda-group]'));
    var buttons = Array.from(filters.querySelectorAll('[data-agenda-filter]'));
    var count = filters.querySelector('[data-agenda-count]');
    function select(group) {
      var visible = 0;
      entries.forEach(function (entry) {
        entry.hidden = !!group && entry.dataset.agendaGroup !== group;
        if (!entry.hidden) { visible += 1; }
      });
      buttons.forEach(function (button) {
        button.setAttribute('aria-pressed', String(button.dataset.agendaFilter === group));
      });
      count.textContent = visible + ' / ' + entries.length + ' ' + count.dataset.countLabel;
    }
    buttons.forEach(function (button) {
      button.addEventListener('click', function () { select(button.dataset.agendaFilter); });
    });
    select('');
    filters.hidden = false;
  });
}());
