/* eslint-disable @typescript-eslint/no-unused-vars */
/* eslint-disable import/extensions */
/* eslint-disable import/no-unresolved */

import { execSync } from 'child_process';

import { defineConfig } from 'cypress';
import installLogsPrinter from 'cypress-terminal-report/src/installLogsPrinter';
import { config as configDotenv } from 'dotenv';

import esbuildPreprocessor from './esbuild-preprocessor';
import plugins from './plugins';
import tasks from './tasks';

interface ConfigurationOptions {
  cypressFolder?: string;
  env?: Record<string, unknown>;
  envFile?: string;
  isDevelopment?: boolean;
  specPattern: string;
}

export default ({
  specPattern,
  cypressFolder,
  isDevelopment,
  env,
  envFile
}: ConfigurationOptions): Cypress.ConfigOptions => {
  if (envFile) {
    configDotenv({ path: envFile });
  }

  const resultsFolder = `${cypressFolder || '.'}/results`;

  const webImageVersion = execSync('git rev-parse --abbrev-ref HEAD')
    .toString('utf8')
    .replace(/[\n\r\s]+$/, '');

  return defineConfig({
    chromeWebSecurity: false,
    defaultCommandTimeout: 20000,
    downloadsFolder: `${resultsFolder}/downloads`,
    e2e: {
      excludeSpecPattern: ['*.js', '*.ts', '*.md'],
      fixturesFolder: 'fixtures',
      reporter: require.resolve('cypress-multi-reporters'),
      reporterOptions: {
        configFile: `${__dirname}/reporter-config.js`
      },
      setupNodeEvents: async (cypressOn, config) => {
        const on = require('cypress-on-fix')(cypressOn)

        installLogsPrinter(on, {
          commandTrimLength: 5000,
          defaultTrimLength: 5000,
        });

        on("task", {
          logVersion(message) {
            console.log(`[LOG]: ${message}`);
            return null;
          },
        });

        // ------------------------------------------------------------------
        //  FORCING BROWSER LANGUAGE TO ENGLISH (CHROME / EDGE / FIREFOX)
        // ------------------------------------------------------------------
        on('before:browser:launch', (browser: Cypress.Browser, launchOptions) => {
          // For Chromium browsers (Chrome, Edge, Electron)
          if (browser.family === 'chromium') {
            launchOptions.args.push('--lang=en');
            launchOptions.args.push('--accept-lang=en,en-US');
          }

          // For Firefox
          if (browser.family === 'firefox') {
            launchOptions.preferences['intl.accept_languages'] = 'en-US,en';
          }

          return launchOptions;
        });
        // ------------------------------------------------------------------

        await esbuildPreprocessor(on, config);
        tasks(on);

        return plugins(on, config);
      },
      specPattern,
      supportFile: 'support/e2e.{js,jsx,ts,tsx}',
      testIsolation: true,
    },
    env: {
      ...env,
      DATABASE_IMAGE: 'bitnamilegacy/mariadb:10.11',
      OPENID_IMAGE_VERSION: process.env.MAJOR || '24.04',
      SAML_IMAGE_VERSION: process.env.MAJOR || '24.04',
      STABILITY: 'unstable',
      TARGET_STABILITY: 'unstable',
      WEB_IMAGE_OS: 'alma9',
      WEB_IMAGE_VERSION: webImageVersion
    },
    execTimeout: 60000,
    requestTimeout: 20000,
    retries: {
      openMode: 0,
      runMode: 2
    },
    screenshotsFolder: `${resultsFolder}/screenshots`,
    trashAssetsBeforeRuns: true,
    video: true,
    videoCompression: process.env.CI ? 32 : 0,
    videosFolder: `${resultsFolder}/videos`,
    viewportHeight: 1080,
    viewportWidth: 1920
  });
};
