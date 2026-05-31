const { test, expect } = require("@playwright/test");
const AxeBuilder = require("@axe-core/playwright").default;

const pages = [
  {
    path: "/",
    name: "front page",
  },
  {
    path: "/fuehrungen/",
    name: "fuehrungen landing",
  },
];

for (const entry of pages) {
  test(`${entry.name} has no serious axe violations`, async ({ page }) => {
    const response = await page.goto(entry.path);

    expect(response).not.toBeNull();
    expect(response.ok()).toBeTruthy();

    const results = await new AxeBuilder({ page })
      .disableRules([
        // Gutenberg/frontend defaults can legitimately emit contrast issues
        // while we phase in a stricter accessibility baseline.
        "color-contrast",
      ])
      .analyze();

    const seriousViolations = results.violations.filter((violation) =>
      ["serious", "critical"].includes(violation.impact || "")
    );

    expect(seriousViolations).toEqual([]);
  });
}
