import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

let router;
let navigation = 0;
let pending = 0;

window.addEventListener('popstate', () => {
  const version = ++navigation;
  if (!pending || !router) return;
  const href = location.href;
  // WP 7.0's popstate renderer does not supersede an unfinished navigate().
  // Replace through the same core API so the late response cannot restore it.
  router.then(({ actions }) => {
    if (version === navigation) return actions.navigate(href, { replace: true });
  }).catch(() => { if (version === navigation) location.assign(href); });
});

function* navigate(url, context) {
  const version = ++navigation;
  pending += 1;
  context.busy = true;
  try {
    router ||= import('@wordpress/interactivity-router');
    const { actions } = yield router;
    if (version !== navigation) return;
    // Fetch the same PHP page as native GET, including other instances' state.
    // No speculative prefetch or second JSON/card-rendering transport.
    yield actions.navigate(url.href, { force: true });
    if (version === navigation) {
      document.getElementById(context.scope)?.querySelector('[data-timeline-query-results]')?.focus({ preventScroll: true });
    }
  } catch {
    if (version === navigation) location.assign(url.href);
  } finally {
    pending -= 1;
    if (version === navigation) context.busy = false;
  }
}

store('iss/timeline', {
  actions: {
    changeFacet: withSyncEvent((event) => {
      if (event.target.matches('input[type="radio"]')) getElement().ref.requestSubmit();
    }),
    submit: withSyncEvent(function* (event) {
      const form = getElement().ref;
      if (!form.reportValidity()) return;
      const url = new URL(form.action, location.href);
      if (url.origin !== location.origin) return;
      event.preventDefault();
      url.search = new URLSearchParams(new FormData(form)).toString();
      yield* navigate(url, getContext());
    }),
    follow: withSyncEvent(function* (event) {
      const link = getElement().ref;
      if (event.button > 0 || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey || link.target || link.hasAttribute('download')) return;
      const url = new URL(link.href, location.href);
      if (url.origin !== location.origin) return;
      event.preventDefault();
      yield* navigate(url, getContext());
    }),
  },
});
