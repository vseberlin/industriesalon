const { defineConfig } = require('@playwright/test');
const path = require('node:path');

const baseURL = process.env.PLAYWRIGHT_BASE_URL;
const pauseMs = Number(process.env.ISS_CONSENT_TEST_PAUSE_MS || 0);
if (!Number.isFinite(pauseMs) || pauseMs < 0 || pauseMs > 60000) throw new Error('ISS_CONSENT_TEST_PAUSE_MS must be between 0 and 60000.');
if (!baseURL) throw new Error('Set PLAYWRIGHT_BASE_URL to the target site; this check never starts or updates WordPress.');

module.exports = defineConfig({
  testDir: './specs',
  testMatch: 'consent.spec.js',
  workers: 1,
  retries: 0,
  timeout: 60000 + pauseMs,
  expect: { timeout: 15000 },
  reporter: [['list'], ['json', { outputFile: process.env.ISS_CONSENT_REPORT || path.resolve(__dirname, '../../test-results/consent.json') }]],
  outputDir: '../../test-results/consent',
  metadata: { baseURL, issConsentFingerprint: process.env.ISS_CONSENT_FINGERPRINT || '' },
  use: { baseURL, channel: process.env.PLAYWRIGHT_CHANNEL, headless: true, viewport: { width: 1280, height: 900 }, screenshot: 'only-on-failure' },
});
