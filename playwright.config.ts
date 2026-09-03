import { defineConfig, devices } from '@playwright/test'

/**
 * The browser tier. Everything else in this repo runs in happy-dom, which has
 * no layout and no scroll — so the two things the plugin exists to do, the
 * zone switch and the scrub, were the only parts nothing executed.
 *
 * Runs against the same wp-env the PHPUnit suite uses, on the DEV port: the
 * tests port belongs to WordPress's own test bootstrap.
 */
export default defineConfig({
  testDir: './tests/e2e/specs',
  globalSetup: './tests/e2e/global-setup.ts',
  // One wp-env, one MySQL: parallel workers would race the same site.
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: [['html', { outputFolder: 'playwright-report' }], ['list']],
  outputDir: 'test-results',
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8894',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    // The self-hosted runner is slow enough that the default 30s can flake a
    // cold Elementor page load.
    navigationTimeout: 60000
  },
  projects: [
    // Chromium drives the native tier: real view-timeline, real timeline-scope.
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    // Firefox drives the POLYFILLED tier, and is the only thing anywhere in
    // the repo that does. Its scrub runs through WAAPI against the polyfill's
    // ViewTimeline; a stubbed one can't tell us that path still works.
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } }
  ]
})
