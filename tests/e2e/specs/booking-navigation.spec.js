const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const payload = (id = 'fixture', allowed = true) => ({ mode: 'slots', inquiry: { allowed }, slots: [
  { id, start: '2030-10-11 10:00:00', available: 2, amount: 500 },
  { id: `${id}-sold`, start: '2030-10-12 11:00:00', available: 0 },
  { id: `${id}-cancelled`, start: '2030-10-13 11:00:00', available: 4, availability_state: 'cancelled' },
] });

test('availability reads deduplicate across reopen and ignore a stale different source', async ({ page }) => {
  let releaseFirst;
  let reads = 0;
  await page.route('**/wp-json/iss/v1/booking-slots*', async route => {
    reads += 1;
    if (reads === 1) {
      await new Promise(resolve => { releaseFirst = resolve; });
      await route.fulfill({ json: payload('older-source') });
    } else await route.fulfill({ json: payload('newer-source') });
  });
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  const triggers = page.locator('[data-wp-on--click="actions.open"]');
  const firstContext = JSON.parse(await triggers.first().getAttribute('data-wp-context'));
  let second;
  for (let index = 1; index < await triggers.count(); index += 1) {
    const context = JSON.parse(await triggers.nth(index).getAttribute('data-wp-context'));
    if (context.source.id !== firstContext.source.id) { second = triggers.nth(index); break; }
  }
  expect(second).toBeTruthy();
  const dialog = page.locator('#iss-booking-dialog');
  await triggers.first().click();
  await expect.poll(() => reads).toBe(1);
  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await triggers.first().click();
  await expect(dialog.getByRole('status')).toContainText('geladen');
  expect(reads).toBe(1);
  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await second.click();
  await expect.poll(() => reads).toBe(2);
  await dialog.locator('[data-calendar-day="2030-10-11"]').click();
  await dialog.locator('[name="slot_id"]').selectOption('newer-source');
  releaseFirst();
  await page.waitForTimeout(150);
  await expect(dialog.locator('[name="slot_id"]')).toHaveValue('newer-source');
  expect(await dialog.locator('option[value="older-source"]').count()).toBe(0);
  await expect(dialog.locator('[data-calendar-day="2030-10-12"]')).toHaveAttribute('aria-disabled', 'true');
  await expect(dialog.locator('[data-calendar-day="2030-10-13"]')).toHaveAttribute('aria-disabled', 'true');
});

test('availability failure can be retried and an expired nonce preserves form values', async ({ page }) => {
  let reads = 0;
  let sends = 0;
  await page.route('**/wp-json/iss/v1/booking-slots*', async route => {
    reads += 1;
    if (reads === 1) await route.abort('failed');
    else await route.fulfill({ json: payload() });
  });
  await page.route('**/wp-json/iss-payments/v1/request', async route => { sends += 1; await route.fulfill({ status: 403, json: { ok: false } }); });
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  await page.locator('[data-wp-on--click="actions.open"]').first().click();
  const dialog = page.locator('#iss-booking-dialog');
  await dialog.getByRole('button', { name: 'Termine erneut laden' }).click();
  await dialog.locator('[data-calendar-day="2030-10-11"]').click();
  await dialog.locator('[name="slot_id"]').selectOption('fixture');
  await dialog.locator('[name="name"]').fill('Fixture');
  await dialog.locator('[name="email"]').fill('fixture@example.invalid');
  await page.waitForTimeout(100);
  await dialog.getByRole('button', { name: 'Anfrage senden', exact: true }).click();
  await expect(dialog.getByRole('status')).toContainText('Sitzung ist abgelaufen');
  await expect(dialog.locator('[name="email"]')).toHaveValue('fixture@example.invalid');
  expect(sends).toBe(1);
  expect(reads).toBe(2);
});

test('inline dates reuse server availability and remain visible without JavaScript', async ({ browser, baseURL }) => {
  for (const javaScriptEnabled of [true, false]) {
    const context = await browser.newContext({ javaScriptEnabled, viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    let reads = 0;
    page.on('request', request => { if (request.url().includes('/iss/v1/booking-slots')) reads += 1; });
    await page.goto(`${baseURL}/?iss_frontend_fixture=inline`, { waitUntil: 'networkidle' });
    const calendar = page.locator('.is-tour-calendar').first();
    await expect(calendar).toBeVisible();
    await expect(calendar.locator('.is-tour-calendar__dates li').first()).toContainText(/20\d\d/);
    expect(reads).toBe(0);
    if (!javaScriptEnabled) await expect(calendar.locator('.iss-availability-calendar')).toBeHidden();
    if (javaScriptEnabled) {
      await calendar.locator('[data-wp-on--click="actions.open"]').click();
      const dialog = page.locator('#iss-booking-dialog');
      await expect(dialog).toBeVisible();
      await expect(dialog.getByRole('status')).not.toContainText('geladen');
      expect(reads).toBe(0);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await page.keyboard.press('Escape');
    }
    await expect(calendar.locator('a[href^="mailto:"]').first()).toBeVisible();
    await context.close();
  }
});

test('phone dialog keeps keyboard focus and accessible request controls', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.route('**/wp-json/iss/v1/booking-slots*', route => route.fulfill({ json: payload() }));
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  const trigger = page.locator('[data-wp-on--click="actions.open"]').first();
  await trigger.focus();
  await page.keyboard.press('Enter');
  const dialog = page.locator('#iss-booking-dialog');
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('status')).toContainText('wählen');
  expect(await dialog.evaluate(element => element.matches(':modal'))).toBe(true);
  for (let index = 0; index < 14; index += 1) {
    await page.keyboard.press('Tab');
    // Native dialogs may expose browser chrome between cycles (activeElement
    // becomes body); no background page control may receive keyboard focus.
    expect(await dialog.evaluate(element => element.contains(document.activeElement) || document.activeElement === document.body)).toBe(true);
  }
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const accessibility = await new AxeBuilder({ page }).include('#iss-booking-dialog').analyze();
  expect(accessibility.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
  await page.keyboard.press('Escape');
  await expect(trigger).toBeFocused();
});
