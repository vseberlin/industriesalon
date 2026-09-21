(function () {
  var recent = [];
  var markPattern = /^iss-(ink|mark)-([0-9a-f]{6})$/;
  function safeHref(value) {
    // eslint-disable-next-line no-control-regex -- Strip ASCII controls before checking the link protocol.
    value = String(value || '').replace(/[\u0000-\u0020\u007f]/g, '');
    return value && (!/^[a-z][a-z0-9+.-]*:/i.test(value) || /^(https?:|mailto:|tel:)/i.test(value));
  }
  function sanitize(value, profile) {
    var template = document.createElement('template'); template.innerHTML = value || '';
    var output = document.createElement('div');
    var allowed = ['strong', 'em', 'br', 'span'];
    if (profile !== 'inline-card') { allowed.push('a'); }
    if (profile === 'block') { allowed.push('p', 'ul', 'ol', 'li'); }
    function append(node, target) {
      if (node.nodeType === Node.TEXT_NODE) { target.appendChild(document.createTextNode(node.textContent)); return; }
      if (node.nodeType !== Node.ELEMENT_NODE) { return; }
      var tag = node.nodeName.toLowerCase();
      if (['script', 'style', 'iframe', 'object'].indexOf(tag) !== -1) { return; }
      tag = ({ b: 'strong', i: 'em', div: 'p' })[tag] || tag;
      var keep = allowed.indexOf(tag) !== -1 && (tag !== 'a' || safeHref(node.getAttribute('href')));
      var element = keep ? document.createElement(tag) : target;
      if (keep && tag === 'a') {
        element.setAttribute('href', node.getAttribute('href').trim());
        if (node.getAttribute('target') === '_blank') { element.setAttribute('target', '_blank'); element.setAttribute('rel', 'noopener noreferrer'); }
      }
      if (keep && tag === 'span') {
        var marks = Array.from(node.classList).filter(function (name) { return markPattern.test(name); });
        if (marks.length) { element.className = marks.join(' '); }
      }
      Array.from(node.childNodes).forEach(function (child) { append(child, element); });
      if (keep) { target.appendChild(element); }
      else if (profile !== 'block' && ['p', 'div', 'li'].indexOf(tag) !== -1 && target.lastChild) { target.appendChild(document.createElement('br')); }
    }
    Array.from(template.content.childNodes).forEach(function (node) { append(node, output); });
    return output.innerHTML;
  }
  function hasUnsupportedMarkup(value, profile) {
    var template = document.createElement('template'); template.innerHTML = value || '';
    var allowed = ['strong', 'b', 'em', 'i', 'br', 'span'];
    if (profile === 'block') { allowed.push('p', 'div', 'ul', 'ol', 'li'); }
    if (profile !== 'inline-card') { allowed.push('a'); }
    return Array.from(template.content.querySelectorAll('*')).some(function (node) {
      var tag = node.nodeName.toLowerCase();
      if (allowed.indexOf(tag) === -1) { return true; }
      return Array.from(node.attributes).some(function (attr) {
        if (tag === 'span' && attr.name === 'class') { return Array.from(node.classList).some(function (name) { return !markPattern.test(name); }); }
        if (tag === 'a' && attr.name === 'href') { return !safeHref(attr.value); }
        if (tag === 'a' && attr.name === 'target') { return attr.value !== '_blank'; }
        if (tag === 'a' && attr.name === 'rel') { return !/^(noopener|noreferrer)( (noopener|noreferrer))?$/.test(attr.value); }
        return true;
      });
    });
  }
  function button(text, action) {
    var node = document.createElement('button'); node.type = 'button'; node.className = 'button'; node.textContent = text;
    node.addEventListener('click', action); return node;
  }
  function configure(options) {
    var profile = options.profile;
    var palette = options.palette || [];
    var panel = document.createElement('fieldset');
    panel.className = 'iss-editorial-colour-panel'; panel.hidden = true;
    options.wrapper.appendChild(panel);
    return {
      wpautop: false,
      menubar: false,
      statusbar: false,
      height: profile === 'block' ? 230 : 140,
      forced_root_block: profile === 'block' ? 'p' : false,
      valid_elements: 'p,br,strong/b,em/i,ul,ol,li,span[class]' + (profile !== 'inline-card' ? ',a[href|target|rel]' : ''),
      paste_as_text: false,
      paste_remove_styles: true,
      paste_preprocess: function (_plugin, args) { args.content = sanitize(args.content, profile); },
      toolbar1: 'undo redo | bold italic' + (profile === 'block' ? ' | bullist numlist' : '') + (profile !== 'inline-card' ? ' | isslink unlink' : '') + ' | issink issmark | removeformat',
      toolbar2: '',
      content_style: 'body{font-family:system-ui,sans-serif;font-size:16px;line-height:1.6;margin:12px}p{margin:0 0 .8em}a{text-decoration:underline}',
      setup: function (editor) {
        var registered = {};
        function register(kind, hex) {
          var name = 'iss-' + kind + '-' + hex;
          if (!registered[name]) {
            editor.formatter.register(name, { inline: 'span', classes: name, exact: true });
            registered[name] = true;
            editor.dom.addStyle('.' + name + '{' + (kind === 'ink' ? 'color' : 'background-color') + ':#' + hex + '}');
          }
          return name;
        }
        function discover() {
          Array.from(editor.getBody().querySelectorAll('span[class]')).forEach(function (span) {
            Array.from(span.classList).forEach(function (name) { var match = name.match(markPattern); if (match) { register(match[1], match[2]); } });
          });
        }
        function colourPanel(kind) {
          var bookmark = editor.selection.getBookmark(2, true);
          discover();
          panel.replaceChildren(); panel.hidden = false;
          var legend = document.createElement('legend'); legend.textContent = kind === 'ink' ? 'Textfarbe' : 'Hervorhebung'; panel.appendChild(legend);
          var selectedText = editor.selection.getContent({ format: 'text' });
          var hint = document.createElement('p'); hint.textContent = selectedText ? 'Auswahl: ' + selectedText.substring(0, 100) : 'Farbe für den Text an der Schreibposition wählen.'; panel.appendChild(hint);
          function apply(hex) {
            editor.focus(); editor.selection.moveToBookmark(bookmark);
            editor.undoManager.transact(function () {
              var selectedRange = editor.selection.getBookmark();
              Object.keys(registered).filter(function (name) { return name.indexOf('iss-' + kind + '-') === 0; }).forEach(function (name) { editor.formatter.remove(name); });
              editor.selection.moveToBookmark(selectedRange);
              if (hex) { editor.formatter.apply(register(kind, hex)); }
            });
            panel.hidden = true;
            editor.nodeChanged(); editor.fire('change');
            if (hex) { recent = [hex].concat(recent.filter(function (item) { return item !== hex; })).slice(0, 5); }
          }
          var choices = document.createElement('div'); choices.className = 'iss-editorial-colour-panel__choices';
          var swatchStyle = document.createElement('style');
          function swatch(name, hex) {
            if (!/^[0-9a-f]{6}$/.test(hex)) { return; }
            var choice = button(name, function () { apply(hex); });
            choice.classList.add('iss-editorial-swatch', 'iss-editorial-swatch--' + hex);
            choices.appendChild(choice);
            swatchStyle.textContent += '.iss-editorial-swatch--' + hex + '::before{background:#' + hex + '}';
          }
          palette.forEach(function (color) { swatch(color.name, color.color.substring(1)); });
          recent.forEach(function (hex) { swatch('Zuletzt: #' + hex, hex); });
          panel.appendChild(swatchStyle);
          panel.appendChild(choices);
          var custom = document.createElement('label'); custom.textContent = 'Eigene Farbe ';
          var picker = document.createElement('input'); picker.type = 'color'; picker.value = palette.length ? palette[0].color : '#000000'; custom.appendChild(picker); panel.appendChild(custom);
          var hexLabel = document.createElement('label'); hexLabel.textContent = 'Hex-Farbwert ';
          var hexInput = document.createElement('input'); hexInput.type = 'text'; hexInput.value = picker.value; hexInput.placeholder = '#123abc'; hexInput.maxLength = 7; hexLabel.appendChild(hexInput); panel.appendChild(hexLabel);
          picker.addEventListener('input', function () { hexInput.value = picker.value; });
          hexInput.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(hexInput.value)) { picker.value = hexInput.value; hexInput.setCustomValidity(''); } });
          panel.appendChild(button('Farbe anwenden', function () {
            if (!/^#[0-9a-f]{6}$/i.test(hexInput.value)) { hexInput.setCustomValidity('Bitte einen Farbwert wie #123abc eingeben.'); hexInput.reportValidity(); return; }
            apply(hexInput.value.slice(1).toLowerCase());
          }));
          panel.appendChild(button(kind === 'ink' ? 'Textfarbe zurücksetzen' : 'Hervorhebung zurücksetzen', function () { apply(''); }));
          function cancel() { panel.hidden = true; editor.focus(); editor.selection.moveToBookmark(bookmark); }
          panel.appendChild(button('Abbrechen', cancel));
          panel.onkeydown = function (event) { if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); cancel(); } };
          panel.querySelector('button').focus();
        }
        editor.addButton('issink', { text: 'Farbe', icon: false, tooltip: 'Textfarbe', onclick: function () { colourPanel('ink'); } });
        editor.addButton('issmark', { text: 'Marker', icon: false, tooltip: 'Hervorhebung', onclick: function () { colourPanel('mark'); } });
        editor.addButton('isslink', { text: 'Link', icon: false, tooltip: 'Link einfügen oder bearbeiten', onclick: function () {
          editor.focus();
          editor.execCommand('WP_Link');
          window.wpLink.open(editor.id);
        } });
        editor.on('init SetContent', discover);
        editor.on('input change undo redo', function () { options.onChange(sanitize(editor.getContent(), profile)); });
        editor.on('keydown', function (event) {
          if (event.key === 'Escape' && panel.hidden) { event.preventDefault(); window.setTimeout(options.onClose, 0); }
        });
      }
    };
  }
  window.issEditorialRichText = { sanitize: sanitize, hasUnsupportedMarkup: hasUnsupportedMarkup, configure: configure };
})();
