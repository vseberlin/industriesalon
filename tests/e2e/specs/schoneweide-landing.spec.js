const { test, expect } = require("@playwright/test");

test("/schoneweide/ renders its territorial landing and existing Atlas", async ({ page }) => {
  const response = await page.goto("/schoneweide/");

  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();
  await expect(page.getByRole("heading", { level: 1, name: "Schöneweide" })).toBeVisible();
  await expect(page.locator(".iss-landing-editorial--skin-territorial")).toBeVisible();
  await expect(page.locator(".iss-landing-section")).toHaveCount(7);
  await expect(page.locator(".iss-landing-section--treatment-text-story-split")).toHaveCount(1);
  await expect(page.locator(".iss-landing-section--treatment-map-img-editorial-atlas")).toHaveCount(1);
  await expect(page.locator(".iss-landing-map-image__visual .iss-gesture-atlas-map--variant-map-image-panel")).toHaveCount(1);
  await expect(page.locator(".iss-landing-map-image__cards .iss-landing-text-image-row__item")).toHaveCount(4);
  await expect(page.locator(".iss-landing-section--treatment-text-bild-reihe-chronology .iss-landing-text-image-row__item")).toHaveCount(4);
  await expect(page.locator(".iss-landing-section--treatment-gateway-atlas-plates .iss-landing-gateway__item")).toHaveCount(6);
  await expect(page.locator(".iss-landing-section--treatment-gateway-pathways .iss-landing-gateway__item")).toHaveCount(4);
  await expect(page.locator(".iss-landing-section--treatment-feature-media-text")).toHaveCount(1);
  await expect(page.locator("[data-iss-schoneweide-atlas]")).toHaveCount(1);
  await expect(page.locator(".iss-related-place-map__marker")).toHaveCount(4);
  await expect(page.locator(".iss-schoneweide-intro-section, .iss-schoneweide-topography, .iss-schoneweide-today-section")).toHaveCount(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});
