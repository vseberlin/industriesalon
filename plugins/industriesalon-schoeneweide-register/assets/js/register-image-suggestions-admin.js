(function () {
  function setMessage(container, message, isError) {
    var status = container.querySelector('.iss-register-image-suggestions__status');
    if (!status) {
      status = document.createElement('div');
      status.className = 'iss-register-image-suggestions__status notice inline';
      var toolbar = container.querySelector('.iss-register-image-suggestions__toolbar');
      if (toolbar && toolbar.parentNode) {
        toolbar.parentNode.insertBefore(status, toolbar.nextSibling);
      } else {
        container.insertBefore(status, container.firstChild);
      }
    }

    status.className = 'iss-register-image-suggestions__status notice inline ' + (isError ? 'notice-error' : 'notice-success');
    status.innerHTML = '<p>' + message + '</p>';
  }

  function setLoading(container, isLoading) {
    var spinner = container.querySelector('.iss-register-image-suggestions__spinner');
    var buttons = container.querySelectorAll('.iss-register-image-suggestions__search, .iss-register-image-suggestion-card__import');

    buttons.forEach(function (button) {
      button.disabled = !!isLoading;
    });

    if (spinner) {
      if (isLoading) {
        spinner.classList.add('is-active');
      } else {
        spinner.classList.remove('is-active');
      }
    }
  }

  function updateResults(container, html) {
    var wrapper = container.querySelector('.iss-register-image-suggestions__dynamic');
    if (!wrapper) {
      wrapper = document.createElement('div');
      wrapper.className = 'iss-register-image-suggestions__dynamic';
      container.appendChild(wrapper);
    }

    wrapper.innerHTML = html;
  }

  function sendRequest(action, payload) {
    var formData = new window.FormData();
    formData.append('action', action);
    formData.append('nonce', issRegisterImageSuggestionsAdmin.nonce);

    Object.keys(payload).forEach(function (key) {
      formData.append(key, payload[key]);
    });

    return window.fetch(issRegisterImageSuggestionsAdmin.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().then(function (json) {
        return {
          ok: response.ok,
          json: json
        };
      });
    });
  }

  function initSuggestions(container) {
    container.addEventListener('click', function (event) {
      var searchButton = event.target.closest('.iss-register-image-suggestions__search');
      if (searchButton) {
        event.preventDefault();
        setLoading(container, true);
        sendRequest('iss_register_search_wikimedia_images', {
          post_id: searchButton.getAttribute('data-post-id') || container.getAttribute('data-post-id') || ''
        }).then(function (result) {
          setLoading(container, false);
          if (!result.ok || !result.json || !result.json.success) {
            var errorMessage = result.json && result.json.data && result.json.data.message ? result.json.data.message : 'Bildvorschläge konnten nicht geladen werden.';
            setMessage(container, errorMessage, true);
            return;
          }

          updateResults(container, result.json.data.html || '');
          setMessage(container, result.json.data.message || 'Bildvorschläge aktualisiert.', false);
        }).catch(function () {
          setLoading(container, false);
          setMessage(container, 'Bildvorschläge konnten nicht geladen werden.', true);
        });
        return;
      }

      var importButton = event.target.closest('.iss-register-image-suggestion-card__import');
      if (!importButton) {
        return;
      }

      event.preventDefault();
      setLoading(container, true);
      sendRequest('iss_register_import_image_candidate', {
        post_id: importButton.getAttribute('data-post-id') || container.getAttribute('data-post-id') || '',
        candidate_id: importButton.getAttribute('data-candidate-id') || '',
        target_field: importButton.getAttribute('data-target-field') || ''
      }).then(function (result) {
        setLoading(container, false);
        if (!result.ok || !result.json || !result.json.success) {
          var errorMessage = result.json && result.json.data && result.json.data.message ? result.json.data.message : 'Bild konnte nicht importiert werden.';
          setMessage(container, errorMessage, true);
          return;
        }

        updateResults(container, result.json.data.html || '');
        setMessage(container, result.json.data.message || 'Bild importiert.', false);
        window.setTimeout(function () {
          window.location.reload();
        }, 700);
      }).catch(function () {
        setLoading(container, false);
        setMessage(container, 'Bild konnte nicht importiert werden.', true);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.iss-register-image-suggestions').forEach(initSuggestions);
  });
})();
