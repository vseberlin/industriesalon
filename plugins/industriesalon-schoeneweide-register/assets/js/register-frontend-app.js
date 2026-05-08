(function (window, document) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};
  const controllerApi = registerApp.controller || {};

  function boot() {
    const roots = Array.from(document.querySelectorAll('.iss-register'));
    const controllers = roots.map((root) => {
      if (typeof controllerApi.createRegisterController !== 'function') {
        return null;
      }

      return controllerApi.createRegisterController(root).init();
    }).filter(Boolean);

    registerApp.instances = controllers;
    return controllers;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
