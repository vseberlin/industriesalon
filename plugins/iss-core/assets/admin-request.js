(function () {
  // Shared transport only. Each owner decides recovery, permissions and persistence.
  window.issAdminRequest = function (url, options) {
    var controller = new window.AbortController();
    var timer;
    function failure(code, message, data) {
      var error = new Error(message);
      error.code = code;
      error.data = data || {};
      return error;
    }
    var timeout = new Promise(function (_resolve, reject) {
      timer = window.setTimeout(function () {
        controller.abort();
        reject(failure('request_timeout', 'Keine Antwort vom Server. Der Speicherstand ist unklar. Bitte erneut prüfen und dieses Fenster geöffnet lassen.'));
      }, 15000);
    });
    var request = Promise.resolve().then(function () {
      return window.fetch(url, Object.assign({}, options, { signal: controller.signal }));
    }).then(function (response) {
      return response.json().catch(function () {
        throw failure('invalid_response', 'Der Server hat keine lesbare Antwort geliefert. Bitte Anmeldung und Verbindung prüfen.', { status: response.status });
      }).then(function (body) {
        if (!response.ok || !body || body.success === false || body.ok === false || typeof body !== 'object') {
          var data = body && (body.data || body) || {};
          throw failure(data.code || body && body.code || 'request_failed', data.message || body && body.message || 'Die Anfrage ist fehlgeschlagen. Bitte Anmeldung und Verbindung prüfen.', data);
        }
        return body;
      });
    }).catch(function (error) {
      if (error.code) { throw error; }
      throw failure('network_error', 'Die Verbindung wurde unterbrochen. Der Speicherstand ist unklar. Bitte erneut prüfen und dieses Fenster geöffnet lassen.');
    });
    return Promise.race([request, timeout]).finally(function () { window.clearTimeout(timer); });
  };
}());
