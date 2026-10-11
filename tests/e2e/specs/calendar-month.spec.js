const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const slots = [
  { id: 'available', start: '2030-10-11 10:00:00', available: 2 },
  { id: 'later', start: '2030-10-11 14:00:00', available: 4 },
  { id: 'sold', start: '2030-10-12 10:00:00', available: 0 },
  { id: 'cancelled', start: '2030-10-13 10:00:00', available: 5, availability_state: 'cancelled' },
  { id: 'mixed-sold', start: '2030-10-14 10:00:00', available: 0 },
  { id: 'mixed-free', start: '2030-10-14 14:00:00', available: 2 },
  { id: 'leap', start: '2032-02-29 10:00:00', available: 2 },
];

test('booking month grid marks availability, handles empty months and leap days without extra reads', async ({ page }, testInfo) => {
  let reads = 0;
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.route('**/wp-json/iss/v1/booking-slots*', route => { reads += 1; return route.fulfill({ json: { mode: 'slots', inquiry: { allowed: true }, slots } }); });
  await page.goto('/kalender/', { waitUntil: 'networkidle' });
  await page.locator('[data-wp-on--click="actions.open"]').first().click();
  const dialog = page.locator('#iss-booking-dialog');
  const picker = dialog.locator('.iss-availability-calendar');
  await expect(picker.locator('[data-calendar-day]')).toHaveCount(31);
  await expect(picker.getByLabel('Monat und Jahr')).toHaveValue('2030-10');
  await expect(picker.getByRole('columnheader').first()).toHaveText('Mo');
  await expect(picker.locator('[data-calendar-day="2030-10-11"]')).toHaveAttribute('aria-disabled', 'false');
  await expect(picker.locator('[data-calendar-day="2030-10-12"]')).toHaveAttribute('aria-disabled', 'true');
  await expect(picker.locator('[data-calendar-day="2030-10-13"]')).toHaveAttribute('aria-disabled', 'true');
  await picker.locator('[data-calendar-day="2030-10-14"]').click();
  await expect(dialog.locator('[data-slot-id="mixed-sold"]')).toHaveJSProperty('disabled', true);
  await expect(dialog.locator('[data-slot-id="mixed-free"]')).toHaveJSProperty('disabled', false);
  await picker.locator('[data-calendar-day="2030-10-12"]').dispatchEvent('click');
  await expect(picker.locator('[data-calendar-day="2030-10-14"]')).toHaveAttribute('aria-pressed', 'true');
  await picker.getByRole('button', { name: 'Nächster Monat', exact: true }).click();
  await expect(picker.getByText('In diesem Monat sind keine Termine verfügbar.', { exact: true })).toBeVisible();
  await picker.getByRole('button', { name: 'Zum nächsten verfügbaren Termin' }).click();
  await expect(picker.getByLabel('Monat und Jahr')).toHaveValue('2032-02');
  await expect(picker.locator('[data-calendar-day]')).toHaveCount(29);
  await picker.locator('[data-calendar-day="2032-02-28"]').focus();
  await page.keyboard.press('ArrowRight');
  await expect(picker.locator('[data-calendar-day="2032-02-29"]')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(dialog.locator('[data-slot-id="leap"]')).toHaveJSProperty('disabled', false);
  await picker.getByRole('button', { name: 'Vorheriger Monat', exact: true }).click();
  await dialog.locator('[data-slot-id="leap"]').click();
  await expect(dialog.locator('[name="name"]')).toBeFocused();
  await dialog.getByRole('button', { name: 'Termin ändern', exact: true }).click();
  await expect(picker.getByLabel('Monat und Jahr')).toHaveValue('2032-02');
  await expect(picker.locator('[data-calendar-day="2032-02-29"]')).toBeFocused();
  await page.screenshot({ path: testInfo.outputPath('booking-month-grid.png'), fullPage: true });
  expect(reads).toBe(1);
  expect(errors).toEqual([]);
});

test('phone date grid keeps Berlin days in a different browser timezone and supports month keyboard navigation', async ({ browser, baseURL }, testInfo) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, timezoneId: 'America/Los_Angeles' });
  const page = await context.newPage();
  await page.route('**/wp-json/iss/v1/booking-slots*', route => route.fulfill({ json: { mode: 'slots', inquiry: { allowed: false }, slots } }));
  await page.goto(`${baseURL}/kalender/`, { waitUntil: 'networkidle' });
  await page.locator('[data-wp-on--click="actions.open"]').first().click();
  const picker = page.locator('#iss-booking-dialog .iss-availability-calendar');
  await picker.locator('[data-calendar-day="2030-10-11"]').focus();
  await page.keyboard.press('PageDown');
  await expect(picker.locator('[data-calendar-day="2030-11-11"]')).toBeFocused();
  await page.keyboard.press('PageUp');
  await expect(picker.locator('[data-calendar-day="2030-10-11"]')).toBeFocused();
  await page.keyboard.press('Home');
  await expect(picker.locator('[data-calendar-day="2030-10-07"]')).toBeFocused();
  await page.keyboard.press('End');
  await expect(picker.locator('[data-calendar-day="2030-10-13"]')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(picker.locator('[data-calendar-day="2030-10-11"]')).toHaveAttribute('aria-pressed', 'true');
  await picker.getByLabel('Monat und Jahr').selectOption('2030-10');
  await picker.locator('[data-calendar-day="2030-10-11"]').click();
  await expect(page.locator('.is-tour-calendar__selected-date')).toContainText('11. Oktober 2030');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const accessibility = await new AxeBuilder({ page }).include('#iss-booking-dialog').analyze();
  expect(accessibility.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
  await page.screenshot({ path: testInfo.outputPath('booking-month-phone.png'), fullPage: true });
  await context.close();
});

test('two inline calendars keep separate months and reuse their server availability', async ({ page }) => {
  let reads = 0;
  page.on('request', request => { if (request.url().includes('/iss/v1/booking-slots')) reads += 1; });
  await page.goto('/?iss_frontend_fixture=inline-pair', { waitUntil: 'networkidle' });
  const calendars = page.locator('.is-tour-calendar .iss-availability-calendar');
  await expect(calendars).toHaveCount(2);
  const secondMonth = await calendars.nth(1).getByLabel('Monat und Jahr').inputValue();
  await calendars.first().getByRole('button', { name: 'Nächster Monat', exact: true }).click();
  await expect(calendars.nth(1).getByLabel('Monat und Jahr')).toHaveValue(secondMonth);
  const chosen = calendars.nth(1).locator('[data-calendar-day][aria-disabled="false"]').first();
  const day = await chosen.getAttribute('data-calendar-day');
  await chosen.click();
  await expect(page.locator('#iss-booking-dialog')).toBeVisible();
  await expect(page.locator(`#iss-booking-dialog [data-calendar-day="${day}"]`)).toHaveAttribute('aria-pressed', 'true');
  expect(reads).toBe(0);
});

for (const javaScriptEnabled of [true, false]) {
  test(`programme month view, day results and month navigation with JavaScript ${javaScriptEnabled}`, async ({ browser, baseURL }, testInfo) => {
    const context = await browser.newContext({ javaScriptEnabled, viewport: javaScriptEnabled ? { width: 1366, height: 900 } : { width: 390, height: 844 } });
    const page = await context.newPage();
    await page.goto(`${baseURL}/kalender/`, { waitUntil: 'networkidle' });
    const region = page.locator('[data-timeline-query]').first();
    await expect(region.locator('.iss-programme-calendar')).toBeVisible();
    await expect(region.locator('[data-timeline-query-results] article').first()).toBeVisible();
    const scope = await region.getAttribute('id');
    const target = new URL(await region.getByRole('navigation', { name: 'Ansicht wählen' }).getByRole('link', { name: 'Kalender', exact: true }).getAttribute('href'), baseURL);
    // Use the fixture's recorded content month, independent of today's date.
    target.searchParams.set(`iss_timeline[${scope}][month]`, '2026-10');
    await page.goto(target.href, { waitUntil: 'networkidle' });
    if (javaScriptEnabled) await page.evaluate(() => { window.calendarDocumentMarker = 'same-document'; });
    await region.getByRole('navigation', { name: 'Ansicht wählen' }).getByRole('link', { name: 'Liste', exact: true }).click();
    await expect(region.locator('.iss-programme-calendar')).toHaveCount(0);
    await region.getByRole('navigation', { name: 'Ansicht wählen' }).getByRole('link', { name: 'Kalender', exact: true }).click();
    const calendar = region.locator('.iss-programme-calendar');
    await expect(calendar).toBeVisible();
    await expect(calendar.locator('[data-calendar-day]')).toHaveCount(31);
    await expect(region.locator('[data-timeline-query-results] article').first()).toBeVisible();
    if (javaScriptEnabled) {
      const pickerBox = await calendar.boundingBox();
      const resultsBox = await region.locator('[data-timeline-query-results]').boundingBox();
      expect(pickerBox.x + pickerBox.width).toBeLessThanOrEqual(resultsBox.x);
    }
    const first = calendar.locator('.has-events [data-calendar-day]').first();
    const day = await first.getAttribute('data-calendar-day');
    await page.screenshot({ path: testInfo.outputPath(`programme-month-${javaScriptEnabled ? 'desktop' : 'phone'}.png`), fullPage: true });
    await first.click();
    await expect(region.locator('input[type="date"]')).toHaveValue(day);
    await expect(calendar.locator(`[data-calendar-day="${day}"]`)).toHaveAttribute('data-selected', 'true');
    await expect(region.locator('[data-timeline-query-results] article').first()).toBeVisible();
    await calendar.getByRole('link', { name: 'Ganzen Monat anzeigen' }).click();
    await calendar.getByRole('link', { name: 'Nächster Monat', exact: true }).click();
    await expect(calendar.locator('h3')).toContainText('November 2026');
    await expect(region.locator('input[type="date"]')).toHaveValue('');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    if (javaScriptEnabled) {
      expect(await page.evaluate(() => window.calendarDocumentMarker)).toBe('same-document');
      const accessibility = await new AxeBuilder({ page }).include('.iss-programme-calendar').analyze();
      expect(accessibility.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
    }
    await context.close();
  });
}
