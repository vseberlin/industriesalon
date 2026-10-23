const { test, expect } = require('@playwright/test');

const videoPath = '/video/orte-ost-oberschoeneweide/';

// Let protected staging replenish its request budget between scenarios.
// No server rate-limit/authentication configuration is changed by this suite.
test.beforeEach(async () => {
  const pause = Number(process.env.ISS_CONSENT_TEST_PAUSE_MS || 0);
  if (pause) await new Promise(resolve => setTimeout(resolve, pause));
});

// Observe outbound attempts without sending test traffic/identifiers to external providers.
async function observe(context) {
  const requests = [];
  context.on('page', page => page.on('pageerror', error => console.error('PAGE ERROR:', error.message)));
  context.pages().forEach(page => page.on('pageerror', error => console.error('PAGE ERROR:', error.message)));
  await context.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.origin === new URL(process.env.PLAYWRIGHT_BASE_URL).origin) return route.continue();
    requests.push(url.hostname);
    if (url.hostname.includes('cartocdn')) {
      return route.fulfill({ contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8r8AAAAASUVORK5CYII=', 'base64') });
    }
    return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Provider fixture</title><p>Video fixture</p>' });
  });
  return requests;
}

async function choose(page, name) {
  await page.getByText('Einzeln auswählen', { exact: true }).click();
  await page.getByRole('checkbox', { name }).check();
  await page.getByRole('button', { name: 'Auswahl speichern' }).click();
}

async function settings(page) {
  if (await page.getByRole('dialog', { name: 'Datenschutzeinstellungen' }).isVisible()) {
    await page.getByRole('button', { name: 'Schließen', exact: true }).click();
  }
  const link = page.getByRole('link', { name: '› Datenschutzeinstellungen', exact: true });
  await link.scrollIntoViewIfNeeded();
  await expect(link).toBeInViewport();
  await link.click();
  await expect(page.getByRole('dialog', { name: 'Datenschutzeinstellungen' })).toBeVisible();
}

test('initial blocking and granular withdrawal', async ({ page, context }) => {
  const requests = await observe(context);
  for (const path of ['/', '/verein/', '/fuehrungen/']) {
    await page.goto(path);
    await expect(page.getByRole('button', { name: 'Alle akzeptieren' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Alle ablehnen' })).toBeVisible();
    await page.getByRole('button', { name: 'Schließen', exact: true }).click();
    await page.getByRole('link', { name: '› Datenschutzeinstellungen', exact: true }).scrollIntoViewIfNeeded();
    expect(requests).toEqual([]);
  }
  await page.goto(videoPath);
  await expect(page.getByRole('button', { name: 'Video abspielen' })).toBeVisible();
  await expect(page.locator('[data-video-single-frame]')).not.toHaveAttribute('src');
  expect(requests).toEqual([]);
  const preview = page.locator('.iss-consent-poster img');
  await expect(preview).toBeVisible();
  expect(await preview.evaluate(img => img.complete && img.naturalWidth > 0 && new URL(img.currentSrc).origin === location.origin)).toBeTruthy();
  await settings(page);
  await page.getByText('Einzeln auswählen', { exact: true }).click();
  await expect(page.getByRole('checkbox', { name: 'YouTube-Videos' })).not.toBeChecked();
  await expect(page.getByRole('checkbox', { name: 'Externe Karte von CARTO' })).not.toBeChecked();
  await page.getByRole('button', { name: 'Alle ablehnen' }).click();
  expect(requests).toEqual([]);
  await page.getByRole('button', { name: 'Video abspielen', exact: true }).click();
  await choose(page, 'YouTube-Videos');
  await page.locator('[data-video-single-frame]').scrollIntoViewIfNeeded();
  await expect.poll(() => requests.length).toBeGreaterThan(0);
  expect(requests.every(host => host.includes('youtube'))).toBeTruthy();
  await settings(page);
  await page.getByText('Einzeln auswählen', { exact: true }).click();
  await expect(page.getByRole('checkbox', { name: 'YouTube-Videos' })).toBeChecked();
  await expect(page.getByRole('checkbox', { name: 'Externe Karte von CARTO' })).not.toBeChecked();
  await page.getByRole('button', { name: 'Schließen', exact: true }).click();
  await page.reload();
  await expect(page.locator('[data-video-single-frame]')).toHaveAttribute('src', /youtube/);
  await settings(page);
  await page.getByRole('button', { name: 'Alle ablehnen' }).click();
  await expect(page.locator('[data-video-single-frame]')).not.toHaveAttribute('src');
  const count = requests.length;
  await page.reload();
  await expect(page.getByRole('button', { name: 'Video abspielen' })).toBeVisible();
  expect(requests).toHaveLength(count);
  await settings(page);
  await page.getByRole('button', { name: 'Alle akzeptieren' }).click();
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('iss-consent')).choices)).toEqual({ youtube: true, carto: true });
  await page.reload();
  await expect(page.getByRole('dialog', { name: 'Datenschutzeinstellungen' })).not.toBeVisible();
  await settings(page);
  await page.getByRole('button', { name: 'Alle ablehnen' }).click();
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('iss-consent')).choices)).toEqual({ youtube: false, carto: false });
  const cookies = await context.cookies();
  expect(cookies.filter(cookie => /newsletter|tnpe/.test(cookie.name))).toEqual([]);
});

test('atlas CARTO basemap and consent', async ({ page, context }) => {
  const requests = await observe(context);
  await page.goto('/schoneweide/');
  const atlas = page.locator('[data-iss-schoneweide-atlas]');
  await atlas.scrollIntoViewIfNeeded();
  await expect(atlas).toHaveAttribute('data-iss-schoneweide-atlas-ready', 'true');
  await expect(atlas.locator('.leaflet-image-layer')).toHaveCount(0);
  await expect(atlas.locator('.leaflet-tile')).toHaveCount(0);
  expect(requests).toEqual([]);
  const markers = await atlas.locator('.leaflet-marker-icon').count();
  expect(markers).toBeGreaterThan(0);
  await atlas.getByRole('button', { name: 'Externe Karte laden' }).click();
  await choose(page, 'Externe Karte von CARTO');
  await expect.poll(() => requests.filter(host => host.includes('cartocdn')).length).toBeGreaterThan(0);
  await expect(atlas.locator('.leaflet-tile').first()).toBeVisible();
  await expect(atlas.locator('.leaflet-image-layer')).toHaveCount(0);
  await settings(page);
  await page.getByText('Einzeln auswählen', { exact: true }).click();
  await expect(page.getByRole('checkbox', { name: 'YouTube-Videos' })).not.toBeChecked();
  await page.getByRole('button', { name: 'Alle ablehnen' }).click();
  await expect(atlas.locator('.leaflet-image-layer')).toHaveCount(0);
  await expect(atlas.locator('.leaflet-tile')).toHaveCount(0);
  await expect(atlas.locator('.leaflet-tile')).toHaveCount(0);
  expect(await atlas.locator('.leaflet-marker-icon').count()).toBe(markers);
  const count = requests.length;
  await page.reload();
  await atlas.scrollIntoViewIfNeeded();
  await expect(atlas).toHaveAttribute('data-iss-schoneweide-atlas-ready', 'true');
  expect(requests).toHaveLength(count);
});

test('stored consent expiry and cross-tab withdrawal', async ({ page, context }) => {
  const requests = await observe(context);
  await page.goto(videoPath);
  await page.getByRole('button', { name: 'Video abspielen', exact: true }).click();
  await choose(page, 'YouTube-Videos');
  const second = await context.newPage();
  await second.goto(videoPath);
  await expect(second.locator('[data-video-single-frame]')).toHaveAttribute('src', /youtube/);
  await settings(page);
  await page.getByRole('button', { name: 'Alle ablehnen' }).click();
  await expect(second.locator('[data-video-single-frame]')).not.toHaveAttribute('src');
  // Simulate expiry/version drift only in this isolated test browser.
  await page.evaluate(() => {
    const value = JSON.parse(localStorage.getItem('iss-consent'));
    value.savedAt = Date.now() - 181 * 86400000;
    value.choices.youtube = true;
    localStorage.setItem('iss-consent', JSON.stringify(value));
  });
  const count = requests.length;
  await page.reload();
  await expect(page.locator('[data-video-single-frame]')).not.toHaveAttribute('src');
  expect(requests).toHaveLength(count);
  expect(await page.evaluate(() => localStorage.getItem('iss-consent'))).toBeNull();
  await page.evaluate(() => localStorage.setItem('iss-consent', JSON.stringify({ version: 'retired-policy', savedAt: Date.now(), choices: { youtube: true, carto: true } })));
  await page.reload();
  await expect(page.locator('[data-video-single-frame]')).not.toHaveAttribute('src');
  expect(requests).toHaveLength(count);
});

test('keyboard mobile and no JavaScript', async ({ page, context, browser }, testInfo) => {
  const requests = await observe(context);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(videoPath);
  await settings(page);
  const dialog = page.getByRole('dialog', { name: 'Datenschutzeinstellungen' });
  await expect(page.getByRole('button', { name: 'Alle akzeptieren' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await expect(page.getByRole('link', { name: '› Datenschutzeinstellungen', exact: true })).toBeFocused();
  await settings(page);
  await page.getByRole('button', { name: 'Alle ablehnen' }).scrollIntoViewIfNeeded();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
  expect(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth)).toBeTruthy();
  const box = await dialog.boundingBox();
  expect(box.height).toBeLessThan(420);
  expect(Math.abs(box.y + box.height - 828)).toBeLessThan(2);
  await page.screenshot({ path: testInfo.outputPath('consent-mobile.png') });
  expect(requests).toEqual([]);
  const nojs = await browser.newContext({ javaScriptEnabled: false });
  const nojsRequests = await observe(nojs);
  const plain = await nojs.newPage();
  await plain.goto(new URL(videoPath, testInfo.project.use.baseURL).href);
  await expect(plain.locator('[data-video-single-frame]')).not.toHaveAttribute('src');
  await expect(plain.getByRole('heading', { name: 'Gesprächsdokumentation' })).toBeVisible();
  expect(nojsRequests).toEqual([]);
  await nojs.close();
});

test('video library and newsletter links', async ({ page, context, request }) => {
  const requests = await observe(context);
  await page.goto('/videos/');
  const play = page.locator('[data-video-player-play]').first();
  await expect(play).toBeVisible();
  const images = page.locator('[data-video-player-poster-image], .iss-video-card img');
  expect(await images.count()).toBeGreaterThan(0);
  expect(await images.evaluateAll(imgs => imgs.every(img => new URL(img.src).origin === location.origin))).toBeTruthy();
  await expect(page.locator('[data-video-player-poster-image]').first()).toBeVisible();
  expect(requests).toEqual([]);
  await play.click();
  await expect(page.getByRole('dialog', { name: 'Datenschutzeinstellungen' })).toBeVisible();
  await choose(page, 'YouTube-Videos');
  await expect(page.locator('[data-video-player-frame]').first()).toHaveAttribute('src', /youtube/);
  await expect(page.frameLocator('[data-video-player-frame]').getByText('Video fixture')).toBeVisible();
  await settings(page);
  await page.getByRole('button', { name: 'Alle ablehnen' }).click();
  await expect(play).toBeVisible();
  await expect(page.locator('[data-video-player-frame]').first()).not.toHaveAttribute('src');
  await page.goto('/newsletter/');
  await expect(page.locator('main')).toContainText('Ich möchte den Newsletter des Industriesalons erhalten');
  await expect(page.locator('main a[href$="/verein/#datenschutz"]').first()).toBeVisible();
  const pixel = await request.get('/?noti=invalid-test-token');
  expect(pixel.status()).toBe(204);
  expect(pixel.headers()['set-cookie']).toBeUndefined();
  const badLink = await request.get('/?nltr=invalid-test-token', { maxRedirects: 0 });
  expect(badLink.status()).toBe(400);
  const oldCookies = await request.get('/newsletter/', { headers: { Cookie: 'newsletter=fixture; tnpe=fixture' } });
  const setCookies = oldCookies.headersArray().filter(header => header.name.toLowerCase() === 'set-cookie');
  expect(setCookies).toHaveLength(2);
  expect(setCookies.every(header => /Max-Age=0/i.test(header.value))).toBeTruthy();
});
