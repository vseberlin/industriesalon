const { defineConfig } = require('@playwright/test');
const path = require('node:path');

const baseURL = process.env.PLAYWRIGHT_BASE_URL;
if (!baseURL) throw new Error('Set PLAYWRIGHT_BASE_URL to the target site; this check never starts or updates WordPress.');

module.exports = defineConfig({
  testDir: './specs',
  testMatch: 'consent.spec.js',
  workers: 1,
  retries: 0,
  timeout: 60000,
  reporter: [['list'], ['json', { outputFile: process.env.ISS_CONSENT_REPORT || path.resolve(__dirname, '../../test-results/consent.json') }]],
  outputDir: '../../test-results/consent',
  metadata: { baseURL, issConsentFingerprint: process.env.ISS_CONSENT_FINGERPRINT || '' },
  use: { baseURL, channel: process.env.PLAYWRIGHT_CHANNEL, headless: true, viewport: { width: 1280, height: 900 }, screenshot: 'only-on-failure' },
});
