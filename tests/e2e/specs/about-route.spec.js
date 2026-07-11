const { test, expect } = require("@playwright/test");

test("/about/ renders its JSON dossier and live Team directory", async ({ page }) => {
  const response = await page.goto("/about/");

  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();
  await expect(page.getByRole("heading", { level: 1, name: "Über uns" })).toBeVisible();
  await expect(page.locator(".iss-landing-editorial--skin-dossier")).toBeVisible();
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
    return hero && triptych && hero.getBoundingClientRect().height + triptych.getBoundingClientRect().height <= window.innerHeight + 1;
  })).toBe(true);
});
