const { test, expect } = require("@playwright/test");

test("/fuehrungen/ resolves to the slug-specific landing template", async ({ page }) => {
  const response = await page.goto("/fuehrungen/");

  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();

  await expect(page.getByRole("heading", { level: 1, name: "Industrie und Wandel" })).toBeVisible();
  await expect(page.getByRole("heading", { level: 2, name: "Nächste Termine" })).toBeVisible();
  await expect(page.locator(".iss-tours-booking__cards")).toBeVisible();
});
