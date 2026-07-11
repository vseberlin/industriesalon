const { test, expect } = require("@playwright/test");

test("/fuehrungen/ renders its landing JSON offer carousels", async ({ page }) => {
  const response = await page.goto("/fuehrungen/");

  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();

  await expect(page.getByRole("heading", { level: 1, name: "Industrie und Wandel" })).toBeVisible();
  await expect(page.getByRole("heading", { level: 2, name: "Alle Führungen im Überblick" })).toBeVisible();
  await expect(page.locator(".iss-landing-editorial--skin-typografisch")).toBeVisible();
  const formatMap = page.locator(".iss-landing-section--treatment-atlas-map-editorial-split");
  await expect(formatMap.locator(".iss-gesture-atlas-map--variant-map-only")).toBeVisible();
  await expect(formatMap.locator(".iss-related-place-map__marker")).toHaveCount(3);
  await expect(formatMap.locator('.iss-related-place-map__marker[data-place-name="Industriesalon Schöneweide"]')).toBeVisible();
  const pathways = page.locator(".iss-landing-section--treatment-gateway-pathways");
  await expect(pathways.getByRole("heading", { level: 2, name: "Dieselben Orte anders weiterlesen" })).toBeVisible();
  await expect(pathways.locator(".iss-landing-gateway__item")).toHaveCount(6);
  const gallery = page.locator('.iss-landing-section--gesture-galerie[data-gallery-layout="sequence"]');
  await expect(gallery.getByRole("heading", { level: 2, name: "Führungen in Bildern" })).toBeVisible();
  await expect(gallery.locator(".iss-landing-gallery__item")).toHaveCount(6);
  await expect(gallery.locator("[data-iss-strip-carousel-track]")).toBeVisible();

  const groups = page.locator(".iss-fuehrungen-catalog__group");
  await expect(groups).toHaveCount(4);
  await expect(page.locator("[data-iss-strip-carousel-track]")).toHaveCount(4);
  await expect(page.locator(".iss-fuehrungen-catalog__group:visible")).toHaveCount(1);

  await page.getByRole("tab", { name: "Gruppen" }).click();
  await expect(page.getByRole("tabpanel", { name: "Gruppen" })).toBeVisible();
  await expect(page.locator(".wp-block-query, .iss-tour-offer-catalog, .iss-dense-image-wall")).toHaveCount(0);
});
