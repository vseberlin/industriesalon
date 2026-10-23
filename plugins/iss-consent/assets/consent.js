(function () {
  'use strict';
  var config = window.issConsentConfig;
  var key = 'iss-consent';
  var state = null;
  var timer;
  var pending = null;
  var lastTrigger = null;

  function valid(value) {
    return value && value.version === config.version &&
      Number.isFinite(value.savedAt) && value.savedAt <= Date.now() &&
      Date.now() - value.savedAt < config.maxAge * 1000 && value.choices &&
      config.services.every(function (name) { return typeof value.choices[name] === 'boolean'; });
  }

  function read() {
    try { return JSON.parse(window.localStorage.getItem(key)); } catch (_) { return null; }
  }

  function has(service) {
    return config.services.includes(service) && valid(state) && state.choices[service] === true;
  }

  function sync() {
    clearTimeout(timer);
    if (!valid(state)) {
      state = null;
      try { window.localStorage.removeItem(key); } catch (_) { /* Storage may be unavailable. */ }
    }
    document.querySelectorAll('[data-iss-consent-frame]').forEach(function (frame) {
      var allowed = has(frame.dataset.issConsentFrame);
      if (allowed && frame.dataset.issConsentSrc && !frame.getAttribute('src')) {
        frame.src = frame.dataset.issConsentSrc;
      } else if (!allowed) {
        frame.removeAttribute('src');
      }
      frame.hidden = !allowed;
    });
    document.querySelectorAll('[data-iss-consent-prompt]').forEach(function (prompt) {
      prompt.hidden = has(prompt.dataset.issConsentPrompt);
    });
    window.dispatchEvent(new CustomEvent('iss-consent-change'));
    if (state) { timer = setTimeout(sync, Math.min(2147483647, Math.max(1, state.savedAt + config.maxAge * 1000 - Date.now()))); }
  }

  function save(choices) {
    state = { version: config.version, savedAt: Date.now(), choices: {} };
    config.services.forEach(function (name) { state.choices[name] = choices[name] === true; });
    try { window.localStorage.setItem(key, JSON.stringify(state)); } catch (_) { /* This page still works without storage. */ }
    sync();
  }

  function open(service, automatic) {
    var dialog = document.getElementById('iss-consent-dialog');
    if (!dialog) { return; }
    lastTrigger = document.activeElement;
    dialog.querySelectorAll('[data-iss-consent-choice]').forEach(function (input) {
      input.checked = has(input.value);
    });
    dialog.querySelector('[data-iss-consent-details]').open = false;
    if (!dialog.open) { dialog.show(); }
    dialog.scrollTop = 0;
    if (automatic) {
      if (lastTrigger && lastTrigger !== document.body && lastTrigger.isConnected) {
        lastTrigger.focus({ preventScroll: true });
      } else if (dialog.contains(document.activeElement)) { document.activeElement.blur(); }
    } else { dialog.querySelector('[data-iss-consent-accept]').focus({ preventScroll: true }); }
  }

  function close() {
    var dialog = document.getElementById('iss-consent-dialog');
    if (dialog) { dialog.close(); }
    if (lastTrigger && lastTrigger.isConnected) { lastTrigger.focus(); }
  }

  function request(service, callback) {
    if (!config.services.includes(service)) { return; }
    if (has(service)) { callback(); return; }
    pending = { service: service, callback: callback };
    open(service);
  }

  window.issConsent = { has: has, request: request, open: open };
  state = read();
  window.addEventListener('storage', function (event) {
    if (event.key === key || event.key === null) { state = read(); sync(); }
  });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { sync(); } });

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-iss-consent-request], a[href$="#datenschutzeinstellungen"]');
    if (!trigger) { return; }
    event.preventDefault();
    pending = null;
    open(trigger.dataset.issConsentRequest);

  });

  function init() {
    var dialog = document.getElementById('iss-consent-dialog');
    if (dialog) {
      dialog.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { event.preventDefault(); pending = null; close(); }
      });
      dialog.querySelector('[data-iss-consent-close]').addEventListener('click', function () { pending = null; close(); });
      dialog.querySelector('form').addEventListener('submit', function (event) {
        event.preventDefault();
        var choices = {};
        dialog.querySelectorAll('[data-iss-consent-choice]').forEach(function (input) { choices[input.value] = input.checked; });
        if (event.submitter && event.submitter.hasAttribute('data-iss-consent-reject')) { choices = {}; }
        if (event.submitter && event.submitter.hasAttribute('data-iss-consent-accept')) {
          config.services.forEach(function (name) { choices[name] = true; });
        }
        var action = pending;
        pending = null;
        save(choices);
        close();
        if (action && has(action.service)) { action.callback(); }
      });
    }
    sync();
    if (!valid(state)) { open(null, true); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
