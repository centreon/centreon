/* eslint-disable import/extensions */
/* eslint-disable import/no-unresolved */

import { defineConfig } from 'cypress';

import setupNodeEvents from './plugins';

interface ConfigurationOptions {
  cypressFolder?: string;
  dockerName?: string;
  env?: Record<string, unknown>;
  isDevelopment?: boolean;
  specPattern: string;
}

export default ({
  specPattern,
  cypressFolder,
  isDevelopment,
  dockerName,
  env
}: ConfigurationOptions): Cypress.ConfigOptions => {
  const resultsFolder = `${cypressFolder || 'cypress'}/results${
    isDevelopment ? '/dev' : ''
  }`;

  const baseUrlIPAddress = isDevelopment ? '0.0.0.0' : 'localhost';

  return defineConfig({
    chromeWebSecurity: false,
    defaultCommandTimeout: 6000,
    e2e: {
      baseUrl: `http://${baseUrlIPAddress}:4000`,
      excludeSpecPattern: ['*.js', '*.ts', '*.md'],
      experimentalSessionAndOrigin: true,
      setupNodeEvents,
      specPattern
    },
    env: {
      ...env,
      dockerName: dockerName || 'centreon-dev'
    },
    execTimeout: 60000,
    reporter: 'mochawesome',
    reporterOptions: {
      html: false,
      json: true,
      overwrite: true,
      reportDir: `${resultsFolder}/reports`,
      reportFilename: '[name]-report.json'
    },
    requestTimeout: 10000,
    retries: 0,
    screenshotsFolder: `${resultsFolder}/screenshots`,
    video: true,
    videosFolder: `${resultsFolder}/videos`
  });
};
