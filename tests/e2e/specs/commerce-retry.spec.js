const { test, expect } = require('@playwright/test');

for (const kind of ['booking', 'inquiry', 'order']) {
  test(`${kind} native dialog retains failed submissions and suppresses double sends`, async ({ page }, testInfo) => {
    const errors = [];
    const retired = [];
    const requests = [];
    let slotReads = 0;
    let release;
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (/flatpickr|\/programm\.js|publication-order\.js/.test(request.url())) retired.push(request.url()); });
    await page.route('**/wp-json/iss/v1/booking-slots*', async route => {
      slotReads += 1;
      const url = new URL(route.request().url());
      await route.fulfill({ json: { source: 'fixture', mode: kind === 'inquiry' ? 'inquiry' : 'slots', inquiry: { allowed: true }, slots: kind === 'inquiry' ? [] : [
        { id: 'fixture-a', start: '2030-10-11 10:00:00', available: 3, amount: 1000, source_post_id: Number(url.searchParams.get('source_post_id')), source_post_type: url.searchParams.get('source_post_type') },
        { id: 'fixture-b', start: '2030-10-12 11:00:00', available: 2, amount: 1000, source_post_id: Number(url.searchParams.get('source_post_id')), source_post_type: url.searchParams.get('source_post_type') },
      ] } });
    });
    await page.route('**/wp-json/iss-payments/v1/request', async route => {
      requests.push(route.request().postDataJSON());
      if (requests.length === 1) { await new Promise(resolve => { release = resolve; }); await route.abort('failed'); }
      else await route.fulfill({ json: { ok: true } });
    });
    await page.goto(kind === 'order' ? '/?p=12978' : '/kalender/', { waitUntil: 'networkidle' });
    const trigger = page.locator('[data-wp-on--click="actions.open"]').first();
    await trigger.click();
    const dialog = page.locator('#iss-booking-dialog');
    await expect(dialog).toBeVisible();
    if (kind === 'booking') {
      await dialog.locator('[name="day"]').selectOption('2030-10-11');
      await dialog.locator('[name="slot_id"]').selectOption('fixture-a');
      await dialog.locator('[name="day"]').selectOption('2030-10-12');
      await dialog.locator('[name="slot_id"]').selectOption('fixture-b');
      expect(slotReads).toBe(1);
    } else if (kind === 'inquiry') {
      await dialog.locator('[name="preferred_date"]').fill('2030-10-11');
      await dialog.locator('[name="preferred_time"]').fill('12:30');
    }
    await dialog.locator('[name="name"]').fill('Fixture Visitor');
    await dialog.locator('[name="email"]').fill('fixture@example.invalid');
    const submit = dialog.getByRole('button', { name: 'Anfrage senden', exact: true });
    await submit.click();
    await expect.poll(() => requests.length).toBe(1);
    await expect(submit).toBeDisabled();
    await dialog.locator('form').evaluate(form => form.requestSubmit());
    await page.waitForTimeout(100);
    expect(requests).toHaveLength(1);
    release();
    await expect(dialog.getByRole('status')).toContainText('Netzwerkfehler');
    await expect(submit).toBeEnabled();
    await expect(dialog.locator('[name="email"]')).toHaveValue('fixture@example.invalid');
    await submit.click();
    await expect(dialog.getByRole('status')).toContainText('gespeichert');
    expect(requests).toHaveLength(2);
    expect(requests[1].request_key).toMatch(/^[a-f0-9]{32}$/);
    expect(requests[0].request_key).toBe(requests[1].request_key);
    expect(requests[1].intent).toBe(kind);
    await expect(submit).toBeDisabled();
    await page.screenshot({ path: testInfo.outputPath(`${kind}-dialog.png`), fullPage: true });
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(trigger).toBeFocused();
    await trigger.click();
    await expect(dialog.locator('[name="name"]')).toHaveValue('');
    expect(errors).toEqual([]);
    expect(retired).toEqual([]);
  });
}
