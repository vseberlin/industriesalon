(function () {
  var SHORTCODE_TAG = 'iss_archive_object';
  var ARCHIVE_OBJECT_BLOCK = 'iss-wf-import/archive-object';
  var tinyMceViewRegistered = false;
  var config = window.issArchiveSetsAdmin || {};
  var strings = config.strings || {};

  function t(key, fallback) {
    return strings[key] || fallback;
  }

  function intValue(value) {
    var parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function escapeHtml(value) {
    var element = document.createElement('div');
    element.textContent = String(value || '');
    return element.innerHTML;
  }

  function formatArchiveObjectShortcode(objectId) {
    var id = intValue(objectId);

    return id > 0 ? '[iss_archive_object id="' + String(id) + '"]' : '';
  }

  function formatInsertedShortcode(objectId) {
    var shortcode = formatArchiveObjectShortcode(objectId);

    return shortcode ? '\n' + shortcode + '\n' : '';
  }

  function getCurrentPostId() {
    var picker = window.issArchiveSetsPicker || {};
    var pickerConfig = picker.config || config || {};
    var input = document.getElementById('post_ID');
    var fromConfig = intValue(pickerConfig.postId || 0);
    var fromInput = input ? intValue(input.value || 0) : 0;
    var fromEditor = 0;

    if (window.wp && window.wp.data && window.wp.data.select) {
      try {
        var editor = window.wp.data.select('core/editor');
        if (editor && editor.getCurrentPostId) {
          fromEditor = intValue(editor.getCurrentPostId());
        }
      } catch (error) {
        fromEditor = 0;
      }
    }

    return fromEditor || fromConfig || fromInput || 0;
  }

  function getClassicEditor(editorId) {
    var id = editorId || 'content';

    if (!window.tinymce || !window.tinymce.get) {
      return null;
    }

    return window.tinymce.get(id) || null;
  }

  function getShortcodeObjectId(shortcode) {
    var attrs = shortcode && shortcode.attrs ? shortcode.attrs.named || {} : {};

    return intValue(attrs.id || attrs.objectId || attrs.object_id || 0);
  }

  function renderTinyMceView(instance) {
    var objectId = getShortcodeObjectId(instance.shortcode);
    var label = objectId > 0 ? 'Objekt #' + String(objectId) : t('archiveObject', 'Archivobjekt');

    return [
      '<div class="iss-archive-tinymce-view" data-iss-archive-object-id="',
      escapeHtml(objectId),
      '">',
      '<p><strong>',
      escapeHtml(t('archiveMaterial', 'Archivmaterial')),
      '</strong></p>',
      '<p>',
      escapeHtml(label),
      '</p>',
      '<p class="description">',
      escapeHtml(t('archiveObjectPreview', 'Wird auf der Website als Archivkarte angezeigt.')),
      '</p>',
      '</div>'
    ].join('');
  }

  function registerTinyMceView() {
    var wp = window.wp || {};

    if (tinyMceViewRegistered) {
      return true;
    }

    if (!wp.mce || !wp.mce.views || !wp.shortcode || !wp.mce.views.register) {
      return false;
    }

    if (wp.mce.views.get && wp.mce.views.get(SHORTCODE_TAG)) {
      tinyMceViewRegistered = true;
      return true;
    }

    wp.mce.views.register(SHORTCODE_TAG, {
      loader: false,
      getContent: function () {
        return renderTinyMceView(this);
      }
    });

    tinyMceViewRegistered = true;

    return true;
  }

  function insertClassicShortcode(objectId, options) {
    var shortcode = formatInsertedShortcode(objectId);
    var editorId = options && options.editorId ? options.editorId : 'content';
    var editor = getClassicEditor(editorId);
    var textarea;
    var start;
    var end;

    if (!shortcode) {
      return false;
    }

    registerTinyMceView();

    if (editor && !editor.isHidden()) {
      editor.focus();
      editor.undoManager.transact(function () {
        editor.execCommand('mceInsertContent', false, shortcode);
      });
      return true;
    }

    textarea = document.getElementById(editorId);
    if (!textarea) {
      return false;
    }

    start = textarea.selectionStart || textarea.value.length;
    end = textarea.selectionEnd || textarea.value.length;
    textarea.value = textarea.value.slice(0, start) + shortcode + textarea.value.slice(end);
    textarea.selectionStart = textarea.selectionEnd = start + shortcode.length;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
    textarea.focus();

    return true;
  }

  function insertBlock(objectId, options) {
    var wp = window.wp || {};
    var id = intValue(objectId);
    var postId = options && options.contextPostId ? intValue(options.contextPostId) : getCurrentPostId();
    var setId = options && options.setId ? intValue(options.setId) : 0;
    var memberPosition = options && options.memberPosition !== undefined ? intValue(options.memberPosition) : -1;
    var attributes;
    var block;
    var dispatcher = null;

    if (id <= 0 && (setId <= 0 || memberPosition < 0)) {
      return false;
    }

    if (!wp.blocks || !wp.blocks.createBlock || !wp.data || !wp.data.dispatch) {
      return false;
    }

    if (wp.blocks.getBlockType && !wp.blocks.getBlockType(ARCHIVE_OBJECT_BLOCK)) {
      return false;
    }

    try {
      dispatcher = wp.data.dispatch('core/block-editor');
    } catch (error) {
      dispatcher = null;
    }

    if (!dispatcher || !dispatcher.insertBlocks) {
      try {
        dispatcher = wp.data.dispatch('core/editor');
      } catch (error) {
        dispatcher = null;
      }
    }

    if (!dispatcher || !dispatcher.insertBlocks) {
      return false;
    }

    attributes = {
      contextPostId: postId,
      variant: 'featured'
    };

    if (setId > 0 && memberPosition >= 0) {
      attributes.setId = setId;
      attributes.memberPosition = memberPosition;
    } else {
      attributes.postId = id;
    }

    block = wp.blocks.createBlock(ARCHIVE_OBJECT_BLOCK, attributes);
    dispatcher.insertBlocks(block);

    return true;
  }

  function insertArchiveObject(objectId, options) {
    options = options || {};

    if (options.preferBlock !== false && insertBlock(objectId, options)) {
      return 'block';
    }

    if (insertClassicShortcode(objectId, options)) {
      return 'shortcode';
    }

    return '';
  }

  function attachAdapter() {
    var picker = window.issArchiveSetsPicker || {};

    picker.formatArchiveObjectShortcode = formatArchiveObjectShortcode;
    picker.insertArchiveObject = insertArchiveObject;
    picker.insertArchiveObjectShortcode = insertClassicShortcode;
    picker.registerArchiveObjectTinyMceView = registerTinyMceView;
    window.issArchiveSetsPicker = picker;

    window.issArchiveEditorAdapter = {
      formatArchiveObjectShortcode: formatArchiveObjectShortcode,
      insertArchiveObject: insertArchiveObject,
      insertClassicShortcode: insertClassicShortcode,
      registerTinyMceView: registerTinyMceView
    };
  }

  attachAdapter();
  registerTinyMceView();

  document.addEventListener('DOMContentLoaded', function () {
    registerTinyMceView();
  });
  window.addEventListener('load', function () {
    registerTinyMceView();
  });
}());
