const { defineConfig, devices } = require("@playwright/test");

module.exports = defineConfig({
  testDir: "./tests/e2e/specs",
  fullyParallel: false,
  retries: 0,
  timeout: 60000,
  reporter: [
    ["list"],
    ["html", { open: "never", outputFolder: "test-results/playwright-html" }],
  ],
  outputDir: "test-results/playwright-artifacts",
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || "http://localhost:8888",
    headless: true,
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
    viewport: {
      width: 1280,
      height: 900,
    },
  },
  webServer: {
    command: "npm run wp-env:start",
    port: 8888,
    timeout: 180000,
    reuseExistingServer: true,
  },
  projects: [
    {
      name: "chromium",
      use: devices["Desktop Chrome"],
    },
  ],
});
