const { test, expect } = require("@playwright/test");

test("/about/ renders its JSON dossier and live Team directory", async ({ page }) => {
  const response = await page.goto("/about/");

  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();
  await expect(page.getByRole("heading", { level: 1, name: "Über uns" })).toBeVisible();
  await expect(page.locator(".iss-landing-editorial--skin-dossier")).toHaveCount(2);
  await expect(page.locator(".iss-landing-section")).toHaveCount(9);
  await expect(page.locator(".iss-landing-section--gesture-text_bild_reihe")).toHaveCount(2);
  await expect(page.locator(".iss-landing-section--treatment-feature-origin-story")).toHaveCount(1);
  await expect(page.locator(".iss-landing-story-split")).toHaveCount(2);
  await expect(page.locator(".iss-landing-section--treatment-text-bild-reihe-visual .iss-landing-text-image-row__item")).toHaveCount(3);
  await expect(page.locator(".iss-landing-section--treatment-text-bild-reihe-compact .iss-landing-text-image-row__item")).toHaveCount(3);
  await expect(page.locator(".iss-team-directory__card")).toHaveCount(6);
  await expect(page.locator(".iss-about-origin, .iss-about-evidence, .iss-about-team, .wp-block-query")).toHaveCount(0);

  const orderedNames = await page.locator(".iss-team-directory__card .iss-card__title").allTextContents();
  expect(orderedNames.map((name) => name.trim())).toEqual([
    "Susanne Reumschüssel",
    "Klaus Burmeister",
    "Albert Markert",
    "Anja Meyer",
    "Alexa Steindorf-Aust",
    "Guido Schlootz",
  ]);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
  expect(await page.evaluate(() => {
    const hero = document.querySelector(".iss-about-hero");
    const triptych = document.querySelector(".iss-landing-section--treatment-text-bild-reihe-visual");
    const title = hero?.querySelector("h1");
    const images = [...(triptych?.querySelectorAll("img") || [])];
    return hero?.contains(triptych) && title && images.length === 3
      && images.every((image) => image.complete && image.naturalWidth > 0)
      && images.every((image) => Math.abs(image.clientHeight / image.clientWidth - 3.3 / 4) < 0.01)
      && title.getBoundingClientRect().bottom < images[0].getBoundingClientRect().top
      && title.getBoundingClientRect().right < images[2].getBoundingClientRect().right - 80;
  })).toBe(true);
});

test("/about/ stacks the triptych below a left-aligned heading on phones", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/about/");
  const images = page.locator(".iss-about-hero .iss-landing-text-image-row__media img");
  await expect(images).toHaveCount(3);
  expect(await images.evaluateAll((items) => items.every((image) =>
    Math.abs(image.clientHeight / image.clientWidth - 10 / 16) < 0.01
  ))).toBe(true);
  await expect(page.locator(".iss-about-hero h1")).toHaveCSS("text-align", "left");
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});
