const { test, expect } = require('@playwright/test');

async function setup(page, type = 'projekt') {
  await page.route('**/iss-order-fixture', (route) => route.fulfill({ contentType: 'text/html', body: '<!doctype html><html lang="de"><head><title>Order fixture</title></head><body><div class="wrap"><h1>Ordering</h1><table><tbody id="the-list"></tbody></table></div></body></html>' }));
  await page.goto('/iss-order-fixture');
  await page.evaluate(({ type }) => {
    window.issContentOrder = { enabled: true, ajaxUrl: '/wp-admin/admin-ajax.php', action: type === 'projekt' ? 'iss_content_project_reorder' : 'iss_content_team_reorder', nonce: 'fixture-nonce', rowSelector: `tr.type-${type}`, strings: { saved: 'Saved', error: 'Could not save', earlier: 'Move up', later: 'Move down' } };
    document.getElementById('the-list').innerHTML = [1, 2, 3].map((id) => `<tr id="post-${id}" class="type-${type}"><td><a class="row-title" href="#">Item ${id}</a></td><td><button type="button" class="iss-content-order-handle" data-post-id="${id}" aria-label="Move item ${id}">Move</button><span class="iss-content-order-value">${id}</span></td></tr>`).join('');
    // Prove this owner does not depend on the jQuery objects WordPress may load.
    window.jQuery = undefined; window.$ = undefined;
  }, { type });
  await page.addStyleTag({ url: '/wp-content/plugins/iss-content/assets/admin-project-order.css' });
  await page.addScriptTag({ url: '/wp-content/plugins/iss-content/assets/admin-project-order.js' });
  await expect(page.locator('.iss-content-order-step')).toHaveCount(6);
}
const order = (page) => page.locator('#the-list > tr').evaluateAll((rows) => rows.map((row) => row.id));

for (const type of ['projekt', 'team_member']) {
  test(`${type} keyboard order retains POST contract, suppresses overlap and restores failures`, async ({ page }) => {
    if (type === 'team_member') await page.setViewportSize({ width: 390, height: 844 });
    await setup(page, type);
    const requests = [];
    let finish;
    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
      requests.push(new URLSearchParams(route.request().postData()));
      await new Promise((resolve) => { finish = resolve; });
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { orders: { 1: 1, 2: 0, 3: 2 } } }) });
    });
    const handle = page.getByRole('button', { name: 'Move item 2', exact: true });
    await handle.focus(); await handle.press('ArrowUp');
    await expect.poll(() => requests.length).toBe(1);
    await expect(handle).toBeDisabled();
    await page.locator('#the-list').dispatchEvent('keydown', { key: 'ArrowDown' });
    expect(requests[0].get('action')).toBe(type === 'projekt' ? 'iss_content_project_reorder' : 'iss_content_team_reorder');
    expect(requests[0].get('nonce')).toBe('fixture-nonce');
    expect(requests[0].getAll('post_ids[]')).toEqual(['2', '1', '3']);
    finish();
    await expect(page.getByRole('status')).toHaveText('Saved');
    await expect(handle).toBeFocused();
    expect(requests).toHaveLength(1);
    await expect(page.locator('#post-2 .iss-content-order-value')).toHaveText('0');
    await page.unroute('**/wp-admin/admin-ajax.php');
    await page.route('**/wp-admin/admin-ajax.php', (route) => route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ success: false, data: { message: 'Expired nonce' } }) }));
    await handle.press('ArrowDown');
    await expect(page.getByRole('status')).toHaveText('Expired nonce');
    expect(await order(page)).toEqual(['post-2', 'post-1', 'post-3']);
    await expect(handle).toBeFocused();
    await page.unroute('**/wp-admin/admin-ajax.php');
    await page.route('**/wp-admin/admin-ajax.php', (route) => route.abort('failed'));
    await handle.press('ArrowDown');
    await expect(page.getByRole('status')).toHaveText('Could not save');
    expect(await order(page)).toEqual(['post-2', 'post-1', 'post-3']);
    await page.unroute('**/wp-admin/admin-ajax.php');
    await page.route('**/wp-admin/admin-ajax.php', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {} }) }));
    await page.getByRole('button', { name: 'Move down: Item 2' }).click();
    await expect(page.getByRole('status')).toHaveText('Saved');
    expect(await order(page)).toEqual(['post-1', 'post-2', 'post-3']);
  });
}

test('native dragging cancels safely and saves one drop; Quick Edit replacement stays usable', async ({ page }) => {
  await setup(page);
  let writes = 0;
  await page.route('**/wp-admin/admin-ajax.php', (route) => { ++writes; return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {} }) }); });
  const data = await page.evaluateHandle(() => new DataTransfer());
  const handle = page.getByRole('button', { name: 'Move item 3', exact: true });
  const first = page.locator('#post-1');
  const bounds = await first.boundingBox();
  await handle.dispatchEvent('dragstart', { dataTransfer: data });
  await first.dispatchEvent('dragover', { dataTransfer: data, clientY: bounds.y });
  expect(await order(page)).toEqual(['post-3', 'post-1', 'post-2']);
  await handle.dispatchEvent('dragend');
  expect(await order(page)).toEqual(['post-1', 'post-2', 'post-3']);
  expect(writes).toBe(0);
  await handle.dispatchEvent('dragstart', { dataTransfer: data });
  await first.dispatchEvent('dragover', { dataTransfer: data, clientY: bounds.y });
  await first.dispatchEvent('drop', { dataTransfer: data });
  await handle.dispatchEvent('dragend');
  await expect(page.getByRole('status')).toHaveText('Saved');
  expect(writes).toBe(1);
  expect(await order(page)).toEqual(['post-3', 'post-1', 'post-2']);
  await page.locator('#post-2').evaluate((row) => { row.querySelector('.iss-content-order-controls').remove(); row.querySelector('button').removeAttribute('draggable'); row.replaceWith(row.cloneNode(true)); });
  await expect(page.locator('#post-2 .iss-content-order-controls')).toHaveCount(1);
  await expect(page.locator('#post-2 .iss-content-order-handle')).toHaveAttribute('draggable', 'true');
  await page.getByRole('button', { name: 'Move up: Item 2' }).click();
  await expect.poll(() => writes).toBe(2);
  await expect(page.getByRole('status')).toHaveText('Saved');
  expect(await order(page)).toEqual(['post-3', 'post-2', 'post-1']);
});

test('pointer drag uses the native handle without jQuery', async ({ page }) => {
  await setup(page);
  let writes = 0;
  await page.route('**/wp-admin/admin-ajax.php', (route) => { ++writes; return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {} }) }); });
  await page.getByRole('button', { name: 'Move item 3', exact: true }).dragTo(page.locator('#post-1'), { targetPosition: { x: 5, y: 1 } });
  await expect(page.getByRole('status')).toHaveText('Saved');
  expect(writes).toBe(1);
  expect(await order(page)).toEqual(['post-3', 'post-1', 'post-2']);
});
