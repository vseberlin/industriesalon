const { test, expect } = require('@playwright/test');
const path = require('path');

for (const kind of ['booking', 'inquiry', 'order']) {
  test(`${kind} keeps its key after network failure and suppresses pending submissions`, async ({ page }) => {
    const bodies = [];
    let releaseFirst;
    await page.route('**/__commerce_retry_fixture', (route) => route.fulfill({
      contentType: 'text/html',
      body: '<!doctype html><html><body><button class="iss-publication-order-trigger" data-publication-id="42" data-title="Fixture" data-amount="600">Order</button><div data-shared-publication-order-modal="1" hidden><div class="iss-publication-order-modal__content"></div></div></body></html>'
    }));
    await page.route('**/__commerce_request', async (route) => {
      bodies.push(route.request().postDataJSON());
      if (bodies.length === 1) {
        await new Promise((resolve) => { releaseFirst = resolve; });
        await route.abort('failed');
      } else {
        await route.fulfill({ status: 200, contentType: 'application/json', body: '{"ok":true}' });
      }
    });
    await page.goto('/__commerce_retry_fixture');
    await page.evaluate(() => {
      window.ISS_OCCURRENCE_CALENDAR = { requestUrl: '/__commerce_request', nonce: 'fixture' };
      window.ISS_PUBLICATION_ORDER = { orderUrl: '/__commerce_request', nonce: 'fixture' };
    });
    const script = kind === 'order' ? 'plugins/iss-commerce-lite/assets/publication-order.js' : 'plugins/iss-frontend/modules/programme/assets/programm.js';
    await page.addScriptTag({ path: path.resolve(__dirname, '../../..', script) });
    const open = async () => {
      if (kind === 'order') {
        await page.locator('.iss-publication-order-trigger').click();
      } else {
        await page.evaluate((formKind) => {
          document.querySelectorAll('.is-tour-calendar__booking-sheet').forEach((node) => node.remove());
          const widget = document.createElement('div');
          Object.assign(widget.dataset, { sourcePostId: '42', sourcePostType: 'veranstaltung', title: 'Fixture' });
          const form = formKind === 'booking'
            ? window.createBookingForm(widget, { id: 'fixture-slot', title: 'Fixture', start: '2030-11-01T12:00:00', amount: 1000 })
            : window.createInquiryForm(widget);
          document.body.appendChild(form);
        }, kind);
      }
    };
    await open();
    const form = page.locator('form');
    const key = await form.locator('[name="request_key"]').inputValue();
    expect(key).toMatch(/^[a-f0-9]{32}$/);
    if (kind === 'order') {
      await form.locator('[name="name"]').fill('Fixture Visitor');
    } else {
      await form.locator('[name="first_name"]').fill('Fixture');
      await form.locator('[name="last_name"]').fill('Visitor');
    }
    await form.locator('[name="email"]').fill('fixture@example.invalid');
    if (kind === 'inquiry') await form.locator('[name="preferred_date"]').fill('2030-11-01');
    const submit = form.locator('button[type="submit"]');
    await submit.click();
    await expect.poll(() => bodies.length).toBe(1);
    await expect(submit).toBeDisabled();
    await form.evaluate((node) => node.requestSubmit());
    // Allow the browser/network event queue to expose an accidental second submission.
    await page.waitForTimeout(100);
    expect(bodies).toHaveLength(1);
    releaseFirst();
    await expect(form.locator('[aria-live="polite"]')).toContainText('Netzwerkfehler');
    await expect(submit).toBeEnabled();
    await submit.click();
    await expect.poll(() => bodies.length).toBe(2);
    expect(bodies[0].request_key).toBe(key);
    expect(bodies[1].request_key).toBe(key);
    if (kind === 'order') await expect(page.locator('[data-shared-publication-order-modal]')).toBeHidden();
    await open();
    expect(await page.locator('form [name="request_key"]').inputValue()).not.toBe(key);
  });
}
