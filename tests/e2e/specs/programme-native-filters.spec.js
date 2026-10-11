const { test, expect } = require('@playwright/test');
for (const scenario of [{ name: 'desktop', js: true, width: 1280 }, { name: 'phone', js: true, width: 390 }, { name: 'without JavaScript', js: false, width: 1280 }]) {
  test(`native programme GET controls ${scenario.name}`, async ({ browser, baseURL }, testInfo) => {
    const context = await browser.newContext({ javaScriptEnabled: scenario.js, viewport: { width: scenario.width, height: 900 } });
    const page = await context.newPage();
    const obsolete = [];
    const errors = [];
    page.on('request', request => { if (/timeline-query\.js|\/iss\/v1\/timeline/.test(request.url())) obsolete.push(request.url()); });
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${baseURL}/kalender/`, { waitUntil: 'networkidle' });
    const block = page.locator('[data-timeline-query]').first();
    const scope = await block.getAttribute('id');
    await expect(block.locator('[data-timeline-query-results]')).not.toBeEmpty();
    const date = block.locator('input[type="date"]');
    await expect(date).toBeVisible();
    await date.fill('2030-10-11');
    await block.getByRole('button', { name: 'Anwenden', exact: true }).click();
    await expect(page).toHaveURL(/2030-10-11/);
    await expect(page.locator(`[id="${scope}"] input[type="date"]`)).toHaveValue('2030-10-11');
    await expect(page.locator('[data-timeline-query-results]')).not.toBeEmpty();
    expect(new URL(page.url()).searchParams.get(`iss_timeline[${scope}][day]`)).toBe('2030-10-11');
    await page.screenshot({ path: testInfo.outputPath('native-date.png'), fullPage: true });
    if (scenario.js) expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.getByRole('link', { name: 'Filter zurücksetzen', exact: true }).click();
    await expect(block.locator('input[type="date"]')).toHaveValue('');
    expect(new URL(page.url()).searchParams.has(`iss_timeline[${scope}][day]`)).toBe(false);
    await page.goBack();
    await expect(block.locator('input[type="date"]')).toHaveValue('2030-10-11');
    await page.goForward();
    await expect(block.locator('input[type="date"]')).toHaveValue('');
    expect(obsolete).toEqual([]);
    expect(errors).toEqual([]);
    await context.close();
  });
}
