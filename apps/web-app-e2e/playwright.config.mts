import { defineConfig, devices } from '@playwright/test';
import { nxE2EPreset } from '@nx/playwright/preset';
import { workspaceRoot } from '@nx/devkit';

const baseURL = process.env['BASE_URL'] || 'http://localhost:4200';

export default defineConfig({
  ...nxE2EPreset(import.meta.dirname, { testDir: './src' }),
  expect: {
    toHaveScreenshot: {
      animations: 'disabled',
      maxDiffPixels: 0,
    },
  },
  snapshotPathTemplate: '{testDir}/__screenshots__/{projectName}/{arg}{ext}',
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  webServer: [
    {
      command: 'node apps/web-app-e2e/proxy-target.mjs',
      url: 'http://127.0.0.1:3334/api/v1/health/live',
      reuseExistingServer: false,
      cwd: workspaceRoot,
    },
    {
      command: 'pnpm exec nx run web-app:serve --proxyConfig=apps/web-app/proxy.e2e.conf.json',
      url: 'http://localhost:4200',
      reuseExistingServer: !process.env['CI'],
      cwd: workspaceRoot,
    },
  ],
  projects: [
    {
      name: 'mobile-chromium',
      use: {
        ...devices['Desktop Chrome'],
        hasTouch: true,
        isMobile: true,
        viewport: { width: 320, height: 800 },
      },
    },
    {
      name: 'desktop-chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
      },
    },
    {
      name: 'tablet-chromium',
      use: {
        ...devices['Desktop Chrome'],
        hasTouch: true,
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'desktop-firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'desktop-webkit',
      use: { ...devices['Desktop Safari'] },
    },
  ],
});
