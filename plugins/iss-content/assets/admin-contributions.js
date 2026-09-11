(function () {
  'use strict';
  var root = document.querySelector('.iss-report-source-search');
  if (!root) { return; }
  var search = root.querySelector('input');
  var select = root.querySelector('select');
  var pages = root.querySelector('[data-source-pages]');
  var status = root.querySelector('[data-source-status]');
  var page = 1;
  var query = '';
  var sequence = 0;
  function load() {
    var request = ++sequence;
    status.textContent = 'Bezüge werden gesucht…';
    window.wp.apiFetch({ path: '/iss-content/v1/editorial-set-targets?sourceOnly=1&page=' + page + '&search=' + encodeURIComponent(query) }).then(function (response) {
      if (request !== sequence) { return; }
      select.replaceChildren(new Option('Keinen weiteren Bezug', ''));
      (response.items || []).forEach(function (item) { select.add(new Option(item.title + ' · ' + item.typeLabel, item.id)); });
      pages.replaceChildren();
      ['Zurück', 'Weiter'].forEach(function (label, index) {
        var button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = label;
        button.disabled = index ? page >= response.totalPages : page <= 1;
        button.addEventListener('click', function () { page += index ? 1 : -1; load(); }); pages.appendChild(button);
      });
      status.textContent = 'Seite ' + page + ' / ' + Math.max(1, response.totalPages);
    }).catch(function (error) { status.textContent = error.message || 'Suche fehlgeschlagen.'; });
  }
  function run() { query = search.value; page = 1; load(); }
  root.querySelector('[data-source-search]').addEventListener('click', run);
  search.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); run(); } });
}());
