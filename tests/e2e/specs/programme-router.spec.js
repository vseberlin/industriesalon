const { test, expect } = require('@playwright/test');

test('filters replace one server region, preserve history and keep booking usable', async ({ page }) => {
  const reads = [];
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  await page.evaluate(() => { window.issRouterSentinel = 'same-document'; });
  page.on('request', request => {
    if (request.resourceType() === 'fetch' && new URL(request.url()).pathname === '/kalender/') reads.push(request.url());
  });
  const block = page.locator('[data-timeline-query]').first();
  await block.locator('input[type="date"]').fill('2030-10-11');
  await block.getByRole('button', { name: 'Anwenden', exact: true }).click();
  await expect(page).toHaveURL(/2030-10-11/);
  expect(await page.evaluate(() => window.issRouterSentinel)).toBe('same-document');
  await expect(block.locator('[data-timeline-query-results]')).toBeFocused();
  expect(reads).toHaveLength(1);
  await block.getByRole('link', { name: 'Filter zurücksetzen', exact: true }).click();
  await expect(block.locator('input[type="date"]')).toHaveValue('');
  expect(reads).toHaveLength(2);
  await page.route('**/wp-json/iss/v1/booking-slots*', route => route.fulfill({ json: { slots: [], inquiry: { allowed: true }, mode: 'inquiry' } }));
  await block.locator('[data-wp-on--click="actions.open"]').first().click();
  await expect(page.locator('#iss-booking-dialog')).toBeVisible();
  await page.goBack();
  await expect(page.locator('#iss-booking-dialog')).not.toBeVisible();
  await expect(block.locator('input[type="date"]')).toHaveValue('2030-10-11');
  await page.goForward();
  await expect(block.locator('input[type="date"]')).toHaveValue('');
  expect(reads).toHaveLength(2);
});

test('multiple blocks keep independent filters and bounded pagination', async ({ page }) => {
  await page.goto('/?iss_frontend_fixture=multiple', { waitUntil: 'networkidle' });
  await page.evaluate(() => { window.issRouterSentinel = true; });
  const blocks = page.locator('[data-timeline-query]');
  await expect(blocks).toHaveCount(2);
  const first = blocks.nth(0);
  const second = blocks.nth(1);
  const scope = await first.getAttribute('id');
  await first.locator('input[type="date"]').fill('2030-10-11');
  await first.getByRole('button', { name: 'Anwenden', exact: true }).click();
  await expect(page).toHaveURL(/2030-10-11/);
  await expect(first.locator('input[type="date"]')).toHaveValue('2030-10-11');
  await second.getByRole('link', { name: 'Weiter', exact: true }).click();
  await expect(second.getByRole('link', { name: 'Zurück', exact: true })).toBeVisible();
  expect(new URL(page.url()).searchParams.get(`iss_timeline[${scope}][day]`)).toBe('2030-10-11');
  await expect(first.locator('input[type="date"]')).toHaveValue('2030-10-11');
  await second.getByRole('link', { name: 'Filter zurücksetzen', exact: true }).click();
  await expect(second.getByRole('link', { name: 'Zurück', exact: true })).toHaveCount(0);
  await expect(first.locator('input[type="date"]')).toHaveValue('2030-10-11');
  expect(await page.evaluate(() => window.issRouterSentinel)).toBe(true);
});

test('a later filter wins when an older page response arrives last', async ({ page }) => {
  let release;
  let started = false;
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  await page.route('**/kalender/*', async route => {
    if (route.request().resourceType() === 'fetch' && route.request().url().includes('2030-10-11')) {
      started = true;
      await new Promise(resolve => { release = resolve; });
    }
    await route.continue();
  });
  const block = page.locator('[data-timeline-query]').first();
  const day = block.locator('input[type="date"]');
  await day.fill('2030-10-11');
  await block.getByRole('button', { name: 'Anwenden', exact: true }).click();
  await expect.poll(() => started).toBe(true);
  await day.fill('2030-10-12');
  await block.getByRole('button', { name: 'Anwenden', exact: true }).click();
  await expect(page).toHaveURL(/2030-10-12/);
  const oldResponse = page.waitForResponse(response => response.url().includes('2030-10-11'));
  release();
  await (await oldResponse).finished();
  await page.waitForTimeout(150);
  await expect(page).toHaveURL(/2030-10-12/);
  await expect(day).toHaveValue('2030-10-12');
});

test('a failed region fetch falls back to its ordinary GET page', async ({ page }) => {
  let failed = 0;
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  await page.evaluate(() => { window.issRouterSentinel = true; });
  await page.route('**/kalender/*', async route => {
    if (route.request().resourceType() === 'fetch') { failed += 1; await route.abort('failed'); }
    else await route.continue();
  });
  const block = page.locator('[data-timeline-query]').first();
  await block.locator('input[type="date"]').fill('2030-10-11');
  await block.getByRole('button', { name: 'Anwenden', exact: true }).click();
  await expect(page).toHaveURL(/2030-10-11/);
  await expect(block.locator('input[type="date"]')).toHaveValue('2030-10-11');
  expect(failed).toBe(1);
  expect(await page.evaluate(() => window.issRouterSentinel)).toBeUndefined();
});

test('Back while a filter is pending keeps the selected history entry', async ({ page }) => {
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  const block = page.locator('[data-timeline-query]').first();
  const day = block.locator('input[type="date"]');
  await day.fill('2030-10-10');
  await block.getByRole('button', { name: 'Anwenden', exact: true }).click();
  await expect(page).toHaveURL(/2030-10-10/);
  let release;
  let started = false;
  await page.route('**/kalender/*', async route => {
    if (route.request().resourceType() === 'fetch' && route.request().url().includes('2030-10-11')) {
      started = true;
      await new Promise(resolve => { release = resolve; });
    }
    await route.continue();
  });
  await day.fill('2030-10-11');
  await block.getByRole('button', { name: 'Anwenden', exact: true }).click();
  await expect.poll(() => started).toBe(true);
  await page.goBack();
  await expect(day).toHaveValue('');
  const oldResponse = page.waitForResponse(response => response.url().includes('2030-10-11'));
  release();
  await (await oldResponse).finished();
  await page.waitForTimeout(150);
  await expect(day).toHaveValue('');
  await expect(page).not.toHaveURL(/2030-10-11/);
});
