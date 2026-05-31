(function () {
  function toggleBySelect(selectId, expectedValue, selector) {
    var select = document.getElementById(selectId);
    if (!select) return;
    var targets = document.querySelectorAll(selector);
    var visible = select.value === expectedValue;
    targets.forEach(function (node) {
      node.style.display = visible ? "" : "none";
    });
  }

  function refresh() {
    toggleBySelect("iss_link_type", "internal", "[data-iss-link-internal]");
    toggleBySelect("iss_link_type", "external", "[data-iss-link-external]");
    toggleBySelect("iss_scope", "selected", "[data-iss-scope-selected]");
  }

  document.addEventListener("DOMContentLoaded", function () {
    var linkType = document.getElementById("iss_link_type");
    var scope = document.getElementById("iss_scope");

    if (linkType) linkType.addEventListener("change", refresh);
    if (scope) scope.addEventListener("change", refresh);
    refresh();
  });
})();
