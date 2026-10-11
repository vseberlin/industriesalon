const { test, expect } = require('@playwright/test');

for (const scenario of [{ name: 'desktop', js: true, width: 1280 }, { name: 'phone without JavaScript', js: false, width: 390 }]) {
  test(`public search retains ordered pages beyond 1000 matches: ${scenario.name}`, async ({ browser, baseURL }) => {
    const context = await browser.newContext({ javaScriptEnabled: scenario.js, viewport: { width: scenario.width, height: 900 } });
    const page = await context.newPage();
    const response = await page.goto(`${baseURL}/?s=Sch%C3%B6neweide`, { waitUntil: 'networkidle' });
    expect(response.status()).toBe(200);
    const links = page.locator('.iss-search-results .wp-block-post-title a');
    await expect(links.first()).toBeVisible();
    const first = await links.evaluateAll(elements => elements.map(element => element.href));
    expect(first.length).toBeGreaterThan(0);
    expect(first.length).toBeLessThanOrEqual(100);
    await page.locator('.iss-search-results__pagination').getByRole('link', { name: 'Weiter', exact: true }).click();
    await expect(page).toHaveURL(/(?:page\/2|paged=2)/);
    const second = await links.evaluateAll(elements => elements.map(element => element.href));
    expect(second.length).toBe(first.length);
    expect(second.some(url => first.includes(url))).toBe(false);
    const beyondOldCap = Math.floor(1000 / first.length) + 1;
    const later = await page.goto(`${baseURL}/?s=Sch%C3%B6neweide&paged=${beyondOldCap}`, { waitUntil: 'networkidle' });
    expect(later.status()).toBe(200);
    await expect(links.first()).toBeVisible();
    await expect(page.locator('.iss-search-results__pagination').getByRole('link', { name: 'Zurück', exact: true })).toBeVisible();
    if (scenario.js) expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await context.close();
  });
}
