(function (window, document) {
  const registerApp = window.ISSRegisterApp = window.ISSRegisterApp || {};
  const helpers = registerApp.helpers || {};
  const safeText = typeof helpers.safeText === 'function'
    ? helpers.safeText
    : function (value, fallback) {
      const text = (value || '').toString().trim();
      return text !== '' ? text : (fallback === undefined ? '—' : fallback);
    };
  const firstImage = typeof helpers.firstImage === 'function'
    ? helpers.firstImage
    : function () {
      return null;
    };

  function createMediaNode(place, fallback, className, groups) {
    const image = firstImage(place, groups);
    if (!image) {
      const emptyNode = document.createElement('div');
      emptyNode.className = className + ' ' + className + '--empty';
      const emptyText = document.createElement('span');
      emptyText.textContent = fallback;
      emptyNode.appendChild(emptyText);
      return emptyNode;
    }

    const alt = image.caption || safeText(place.name, 'Standortbild');
    const mediaNode = document.createElement('div');
    mediaNode.className = className;

    const imageNode = document.createElement('img');
    imageNode.src = image.url;
    imageNode.alt = alt;
    imageNode.loading = 'lazy';
    imageNode.decoding = 'async';
    mediaNode.appendChild(imageNode);

    return mediaNode;
  }

  function createEmptyNode(tagName, text) {
    const node = document.createElement(tagName);
    node.className = 'iss-register-empty';
    node.textContent = text;
    return node;
  }

  function createTextCell(tagName, text) {
    const node = document.createElement(tagName);
    node.textContent = text;
    return node;
  }

  function createOptionNode(value, label, dataset) {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;

    if (dataset && typeof dataset === 'object') {
      Object.keys(dataset).forEach((key) => {
        option.dataset[key] = dataset[key];
      });
    }

    return option;
  }

  function replaceNodeChildren(node, children) {
    if (!node) {
      return;
    }

    node.replaceChildren(...children.filter(Boolean));
  }

  function renderSimpleList(node, values, fallbackText) {
    if (!node) {
      return;
    }

    if (!values.length) {
      replaceNodeChildren(node, [createTextCell('li', fallbackText)]);
      return;
    }

    replaceNodeChildren(node, values.map((value) => createTextCell('li', value)));
  }

  function setSelectOptions(selectNode, options) {
    if (!selectNode) {
      return;
    }

    replaceNodeChildren(selectNode, options.map((option) => createOptionNode(option.value, option.label, option.dataset)));
  }

  registerApp.dom = Object.assign({}, registerApp.dom, {
    createMediaNode,
    createEmptyNode,
    createTextCell,
    createOptionNode,
    replaceNodeChildren,
    renderSimpleList,
    setSelectOptions
  });
})(window, document);
