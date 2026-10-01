import { defineConfig, devices } from '@playwright/test';

// Browser tests for the customer checkout. Run from the repository root:
//
//   npx playwright test -c tests/browser/playwright.config.ts
//
// The webServer below runs tests/browser/setup.sh (fresh SQLite database, seeded, BML faked)
// and PHP's built-in server, and stops it afterwards. In CI `npx playwright install --with-deps
// chromium` provides the browser. On a machine that already has browsers under
// PLAYWRIGHT_BROWSERS_PATH nothing needs installing; if that browser's build does not match this
// Playwright version, point PLAYWRIGHT_CHROMIUM_EXECUTABLE at a Chromium binary instead
// (for example PLAYWRIGHT_CHROMIUM_EXECUTABLE=/opt/pw-browsers/chromium).

const port = Number(process.env.BROWSER_TEST_PORT || 8001);
const baseURL = `http://127.0.0.1:${port}`;
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined;

export default defineConfig({
    // Paths are relative to this file's folder (tests/browser); so is the webServer's cwd.
    testDir: '.',
    testMatch: /.*\.spec\.ts/,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never', outputFolder: 'report' }]] : 'list',
    outputDir: 'results',
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        locale: 'en',
        ...(executablePath ? { launchOptions: { executablePath } } : {}),
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'bash setup.sh',
        url: `${baseURL}/up`,
        reuseExistingServer: false,
        timeout: 180_000,
        stdout: 'pipe',
        stderr: 'pipe',
        env: { BROWSER_TEST_PORT: String(port) },
    },
});
