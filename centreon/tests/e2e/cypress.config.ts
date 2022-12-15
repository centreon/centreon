/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */

import { defineConfig } from 'cypress';

import setupNodeEvents from './cypress/plugins';

export default defineConfig({
  chromeWebSecurity: false,
  defaultCommandTimeout: 6000,
  e2e: {
    baseUrl: 'http://localhost:4000',

    excludeSpecPattern: ['*.js', '*.ts', '*.md'],
    experimentalSessionAndOrigin: true,
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents,
    specPattern: 'cypress/e2e/**/*.feature',
  },
  env: {
    dockerName: 'e2e-tests-centreon',
  },
  execTimeout: 60000,
  reporter: 'junit',
  reporterOptions: {
    mochaFile: 'cypress/results/reports/cypress-fe.xml',
  },
  requestTimeout: 10000,
  retries: 0,
  screenshotsFolder: 'cypress/results/screenshots',
  video: true,
  videosFolder: 'cypress/results/videos',
});
