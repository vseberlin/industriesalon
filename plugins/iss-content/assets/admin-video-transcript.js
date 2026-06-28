(function () {
  function parseJson(value, fallback) {
    try {
      return JSON.parse(value || '');
    } catch (error) {
      return fallback;
    }
  }

  function createElement(tag, className, text) {
    var element = document.createElement(tag);
    if (className) {
      element.className = className;
    }
    if (typeof text === 'string') {
      element.textContent = text;
    }
    return element;
  }

  function clear(element) {
    while (element && element.firstChild) {
      element.removeChild(element.firstChild);
    }
  }

  function normalizeTimecode(value) {
    return String(value || '').replace(/^\[|\]$/g, '').trim();
  }

  function parseSeconds(timecode) {
    var parts = normalizeTimecode(timecode).split(':').map(function (part) {
      return parseInt(part, 10) || 0;
    });
    if (parts.length === 2) {
      return (parts[0] * 60) + parts[1];
    }
    if (parts.length === 3) {
      return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
    }
    return 0;
  }

  function compactSegment(segment) {
    return {
      timecode: normalizeTimecode(segment.timecode),
      seconds: parseSeconds(segment.timecode),
      speaker: String(segment.speaker || '').trim(),
      text: String(segment.text || '').trim(),
      source: String(segment.source || 'manual').trim() || 'manual',
      review_state: String(segment.review_state || 'needs_review').trim() || 'needs_review'
    };
  }

  function initEditor(container) {
    var root = container.querySelector('.iss-video-transcript-editor__root');
    var field = container.querySelector('.iss-video-transcript-editor__field');
    if (!root || !field) {
      return;
    }

    var reviewStates = parseJson(container.getAttribute('data-review-states'), {
      raw: 'Rohfassung',
      needs_review: 'Zu pruefen',
      reviewed: 'Geprueft'
    });
    var documentState = parseJson(container.getAttribute('data-document'), {
      schema_version: 1,
      source: 'manual',
      segments: []
    });

    function updateField() {
      documentState.schema_version = 1;
      documentState.source = documentState.source || 'manual';
      documentState.segments = (Array.isArray(documentState.segments) ? documentState.segments : []).map(compactSegment).filter(function (segment) {
        return segment.timecode || segment.text;
      });
      field.value = JSON.stringify(documentState);
    }

    function addSegment(afterIndex) {
      var segment = {
        timecode: '',
        seconds: 0,
        speaker: '',
        text: '',
        source: 'manual',
        review_state: 'needs_review'
      };
      documentState.segments.splice(afterIndex + 1, 0, segment);
      updateField();
      render();
    }

    function moveSegment(index, offset) {
      var next = index + offset;
      if (next < 0 || next >= documentState.segments.length) {
        return;
      }
      var segment = documentState.segments.splice(index, 1)[0];
      documentState.segments.splice(next, 0, segment);
      updateField();
      render();
    }

    function removeSegment(index) {
      documentState.segments.splice(index, 1);
      updateField();
      render();
    }

    function createTextField(label, value, onInput) {
      var wrapper = createElement('label', 'iss-video-transcript-editor__field-row');
      var input = document.createElement('input');
      input.type = 'text';
      input.value = value || '';
      input.addEventListener('input', function () {
        onInput(input.value);
        updateField();
      });
      wrapper.appendChild(createElement('span', '', label));
      wrapper.appendChild(input);
      return wrapper;
    }

    function createTextarea(label, value, onInput) {
      var wrapper = createElement('label', 'iss-video-transcript-editor__field-row iss-video-transcript-editor__field-row--text');
      var textarea = document.createElement('textarea');
      textarea.rows = 4;
      textarea.value = value || '';
      textarea.addEventListener('input', function () {
        onInput(textarea.value);
        updateField();
      });
      wrapper.appendChild(createElement('span', '', label));
      wrapper.appendChild(textarea);
      return wrapper;
    }

    function createReviewSelect(segment) {
      var wrapper = createElement('label', 'iss-video-transcript-editor__field-row');
      var select = document.createElement('select');
      Object.keys(reviewStates).forEach(function (state) {
        var option = document.createElement('option');
        option.value = state;
        option.textContent = reviewStates[state] || state;
        option.selected = state === segment.review_state;
        select.appendChild(option);
      });
      select.addEventListener('change', function () {
        segment.review_state = select.value || 'needs_review';
        updateField();
      });
      wrapper.appendChild(createElement('span', '', 'Status'));
      wrapper.appendChild(select);
      return wrapper;
    }

    function renderSegment(segment, index, target) {
      var row = createElement('article', 'iss-video-transcript-editor__segment');
      var head = createElement('div', 'iss-video-transcript-editor__segment-head');
      var title = createElement('h4', '', segment.timecode ? '[' + segment.timecode + ']' : 'Neue Marke');
      var actions = createElement('div', 'iss-video-transcript-editor__actions');
      var up = createElement('button', 'button', 'Hoch');
      var down = createElement('button', 'button', 'Runter');
      var add = createElement('button', 'button', 'Danach einfuegen');
      var remove = createElement('button', 'button button-link-delete', 'Entfernen');
      var grid = createElement('div', 'iss-video-transcript-editor__grid');

      [up, down, add, remove].forEach(function (button) {
        button.type = 'button';
      });
      up.disabled = index === 0;
      down.disabled = index >= documentState.segments.length - 1;
      up.addEventListener('click', function () { moveSegment(index, -1); });
      down.addEventListener('click', function () { moveSegment(index, 1); });
      add.addEventListener('click', function () { addSegment(index); });
      remove.addEventListener('click', function () { removeSegment(index); });

      actions.appendChild(up);
      actions.appendChild(down);
      actions.appendChild(add);
      actions.appendChild(remove);
      head.appendChild(title);
      head.appendChild(actions);

      grid.appendChild(createTextField('Zeitmarke', segment.timecode || '', function (value) {
        segment.timecode = normalizeTimecode(value);
        segment.seconds = parseSeconds(value);
      }));
      grid.appendChild(createTextField('Sprecher', segment.speaker || '', function (value) {
        segment.speaker = value;
      }));
      grid.appendChild(createReviewSelect(segment));
      grid.appendChild(createTextarea('Text', segment.text || '', function (value) {
        segment.text = value;
      }));

      row.appendChild(head);
      row.appendChild(grid);
      target.appendChild(row);
    }

    function render() {
      var toolbar = createElement('div', 'iss-video-transcript-editor__toolbar');
      var add = createElement('button', 'button button-primary', 'Segment hinzufuegen');
      var list = createElement('div', 'iss-video-transcript-editor__segments');
      clear(root);
      documentState.segments = Array.isArray(documentState.segments) ? documentState.segments : [];
      add.type = 'button';
      add.addEventListener('click', function () {
        addSegment(documentState.segments.length - 1);
      });
      toolbar.appendChild(createElement('p', 'description', String(documentState.segments.length) + ' Segment(e)'));
      toolbar.appendChild(add);
      root.appendChild(toolbar);

      if (!documentState.segments.length) {
        list.appendChild(createElement('p', 'iss-video-transcript-editor__empty', 'Noch keine Segmente.'));
      } else {
        documentState.segments.forEach(function (segment, index) {
          renderSegment(segment, index, list);
        });
      }

      root.appendChild(list);
      updateField();
    }

    render();
  }

  function hideBodyCanvas(doc) {
    if (!doc || doc.getElementById('iss-video-transcript-body-canvas-style')) {
      return;
    }

    var style = doc.createElement('style');
    style.id = 'iss-video-transcript-body-canvas-style';
    style.textContent = 'body.post-type-video .block-editor-block-list__layout{display:none;}';
    doc.head.appendChild(style);
  }

  function hideBlockEditorBodyCanvas() {
    if (!document.body.classList.contains('post-type-video')) {
      return;
    }

    hideBodyCanvas(document);
    var frame = document.querySelector('iframe[name="editor-canvas"]');
    if (!frame) {
      window.setTimeout(hideBlockEditorBodyCanvas, 300);
      return;
    }

    function applyFrameStyle() {
      try {
        hideBodyCanvas(frame.contentDocument);
      } catch (error) {
      }
    }

    frame.addEventListener('load', applyFrameStyle);
    applyFrameStyle();
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('.iss-video-transcript-editor'), initEditor);
    hideBlockEditorBodyCanvas();
  });
}());
