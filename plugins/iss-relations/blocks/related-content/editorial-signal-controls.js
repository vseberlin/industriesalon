(function () {
  const editor = window.issRelationsEditor;
  if (!editor) return;

  const el = editor.components.el;
  const Button = editor.components.Button;
  const TextControl = editor.components.TextControl;

  function getEditorialSignalLabel(value) {
    var normalized = String(value || '');

    if (normalized === 'feature') {
      return 'Vorne zeigen';
    }

    if (normalized === 'hide') {
      return 'Nicht automatisch zeigen';
    }

    return '';
  }

  function getEditorialSignalDraft(drafts, targetPostId, source) {
    var key = String(targetPostId || '');
    var hasDraft = !!(drafts && Object.prototype.hasOwnProperty.call(drafts, key));
    var draft = hasDraft ? drafts[key] || {} : {};
    var fallback = source || {};

    return {
      reason: typeof draft.reason === 'string'
        ? draft.reason
        : String(fallback.editorialReason || fallback.reason || ''),
      expiresAt: typeof draft.expiresAt === 'string'
        ? draft.expiresAt
        : String(fallback.editorialExpiresAt || fallback.expiresAt || ''),
    };
  }

  function setEditorialSignalDraft(drafts, setDrafts, targetPostId, patch) {
    if (!setDrafts) return;

    var key = String(targetPostId || '');
    var next = Object.assign({}, drafts || {});
    next[key] = Object.assign({}, next[key] || {}, patch || {});
    setDrafts(next);
  }

  function renderActionButton(key, label, onClick, disabled, variant) {
    if (Button) {
      return el(
        Button,
        {
          key: key,
          variant: variant || 'secondary',
          disabled: !!disabled,
          onClick: onClick,
        },
        label
      );
    }

    return el(
      'button',
      {
        key: key,
        type: 'button',
        disabled: !!disabled,
        onClick: onClick,
      },
      label
    );
  }

  function renderCardControls(item, options) {
    options = options || {};
    var targetPostId = item && item.id ? parseInt(item.id, 10) || 0 : 0;
    if (!targetPostId) return null;

    var currentSignal = String(item.editorialSignal || '');
    var draft = getEditorialSignalDraft(options.drafts, targetPostId, item);
    var action = options.action || {};
    var isBusy = parseInt(action.targetPostId, 10) === targetPostId && action.status === 'saving';
    var isCurrentTarget = parseInt(action.targetPostId, 10) === targetPostId;
    var isDisabled = isBusy || !options.currentPostId;

    return el(
      'div',
      {
        style: {
          borderTop: '1px solid rgba(30,30,30,0.08)',
          paddingTop: '10px',
          display: 'grid',
          gap: '8px',
        },
      },
      currentSignal
        ? el(
            'p',
            {
              style: {
                margin: 0,
                fontSize: '12px',
                fontWeight: '700',
                color: 'rgba(30,30,30,0.72)',
              },
            },
            getEditorialSignalLabel(currentSignal)
          )
        : null,
      TextControl
        ? el(TextControl, {
            label: 'Notiz',
            value: draft.reason,
            onChange: function (value) {
              setEditorialSignalDraft(options.drafts, options.setDrafts, targetPostId, { reason: value });
            },
          })
        : null,
      TextControl
        ? el(TextControl, {
            label: 'Gilt bis',
            type: 'date',
            value: draft.expiresAt,
            onChange: function (value) {
              setEditorialSignalDraft(options.drafts, options.setDrafts, targetPostId, { expiresAt: value });
            },
          })
        : null,
      el(
        'div',
        {
          style: {
            display: 'flex',
            flexWrap: 'wrap',
            gap: '6px',
          },
        },
        renderActionButton('feature', 'Vorne zeigen', function () {
          if (options.onSave) options.onSave(item, 'feature');
        }, isDisabled, 'secondary'),
        renderActionButton('hide', 'Nicht automatisch zeigen', function () {
          if (options.onSave) options.onSave(item, 'hide');
        }, isDisabled, 'secondary'),
        currentSignal
          ? renderActionButton('remove', 'Auswahl entfernen', function () {
              if (options.onRemove) options.onRemove(targetPostId);
            }, isDisabled, 'tertiary')
          : null
      ),
      !options.currentPostId
        ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, 'Seite zuerst speichern.')
        : null,
      isCurrentTarget && action.message
        ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, action.message)
        : null
    );
  }

  function renderSummary(signals, options) {
    options = options || {};
    var items = Array.isArray(signals) ? signals : [];
    var action = options.action || {};

    return el(
      'div',
      {
        style: {
          borderTop: '1px solid rgba(30,30,30,0.12)',
          marginTop: '14px',
          paddingTop: '12px',
          display: 'grid',
          gap: '8px',
        },
      },
      el(
        'strong',
        {
          style: {
            fontSize: '13px',
            lineHeight: '1.3',
          },
        },
        'Redaktionelle Auswahl'
      ),
      !items.length
        ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, 'Keine Auswahl aktiv.')
        : null,
      items.map(function (signal) {
        var target = signal.target || {};
        var targetPostId = parseInt(signal.targetPostId || target.id, 10) || 0;
        var isBusy = parseInt(action.targetPostId, 10) === targetPostId && action.status === 'saving';
        var title = target.title || 'Ohne Titel';
        var meta = [
          getEditorialSignalLabel(signal.signal),
          target.postType ? editor.getPostTypeLabel(target.postType) : '',
          signal.expiresAt ? 'Gilt bis ' + signal.expiresAt : '',
        ].filter(Boolean).join(' · ');

        return el(
          'div',
          {
            key: 'editorial-signal-' + String(targetPostId),
            style: {
              border: '1px solid rgba(30,30,30,0.1)',
              background: '#fff',
              padding: '10px',
              display: 'grid',
              gap: '6px',
            },
          },
          el(
            'div',
            {
              style: {
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'space-between',
                gap: '8px',
              },
            },
            el(
              'div',
              {
                style: {
                  minWidth: 0,
                },
              },
              el(
                'div',
                {
                  style: {
                    fontWeight: '700',
                    fontSize: '13px',
                    lineHeight: '1.35',
                  },
                },
                title
              ),
              meta
                ? el(
                    'div',
                    {
                      style: {
                        fontSize: '11px',
                        color: 'rgba(30,30,30,0.66)',
                      },
                    },
                    meta
                  )
                : null
            ),
            renderActionButton('remove-' + String(targetPostId), 'Auswahl entfernen', function () {
              if (options.onRemove) options.onRemove(targetPostId);
            }, isBusy || !options.currentPostId, 'tertiary')
          ),
          signal.reason
            ? el('p', { style: { margin: 0, fontSize: '12px', lineHeight: '1.45' } }, signal.reason)
            : null,
          parseInt(action.targetPostId, 10) === targetPostId && action.message
            ? el('p', { className: 'components-base-control__help', style: { margin: 0 } }, action.message)
            : null
        );
      })
    );
  }

  function createActions(options) {
    options = options || {};

    return {
      save: function (item, signal) {
        var apiFetch = editor.components.apiFetch;
        var targetPostId = item && item.id ? parseInt(item.id, 10) || 0 : 0;
        if (!apiFetch || !options.currentPostId || !targetPostId) return;

        var draft = getEditorialSignalDraft(options.drafts, targetPostId, item);
        options.setAction({ targetPostId: targetPostId, status: 'saving', message: '' });

        apiFetch({
          path: '/iss-graph/v1/editorial-signals',
          method: 'POST',
          data: {
            contextPostId: options.currentPostId || 0,
            targetPostId: targetPostId,
            surface: 'related',
            signal: signal,
            reason: draft.reason || '',
            expiresAt: draft.expiresAt || '',
          },
        })
          .then(function () {
            options.setAction({ targetPostId: targetPostId, status: 'saved', message: 'Auswahl aktualisiert.' });
            options.refresh();
          })
          .catch(function (error) {
            options.setAction({
              targetPostId: targetPostId,
              status: 'error',
              message: error && error.message ? error.message : 'Auswahl konnte nicht gespeichert werden.',
            });
          });
      },
      remove: function (targetPostId) {
        var apiFetch = editor.components.apiFetch;
        targetPostId = parseInt(targetPostId, 10) || 0;
        if (!apiFetch || !options.currentPostId || !targetPostId) return;

        options.setAction({ targetPostId: targetPostId, status: 'saving', message: '' });

        apiFetch({
          path: editor.buildApiPath('/iss-graph/v1/editorial-signals', {
            contextPostId: options.currentPostId || 0,
            targetPostId: targetPostId,
            surface: 'related',
          }),
          method: 'DELETE',
        })
          .then(function () {
            options.setAction({ targetPostId: targetPostId, status: 'saved', message: 'Auswahl entfernt.' });
            options.refresh();
          })
          .catch(function (error) {
            options.setAction({
              targetPostId: targetPostId,
              status: 'error',
              message: error && error.message ? error.message : 'Auswahl konnte nicht entfernt werden.',
            });
          });
      },
    };
  }

  window.issRelationsEditor.editorialSignalControls = {
    createActions: createActions,
    renderCardControls: renderCardControls,
    renderSummary: renderSummary,
  };
})();
