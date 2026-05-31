const { test, expect } = require("@playwright/test");

test("front page renders the industriesalon landing shell", async ({ page }) => {
  const response = await page.goto("/");

  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();

  await expect(page.getByRole("heading", { level: 1, name: /Industrie und/i })).toBeVisible();
  await expect(page.getByRole("heading", { level: 2, name: "Den Industriesalon entdecken" })).toBeVisible();
  await expect(page.locator(".iss-front-explore__grid")).toBeVisible();
});
