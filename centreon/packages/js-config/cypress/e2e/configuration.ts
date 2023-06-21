/* eslint-disable import/extensions */
/* eslint-disable import/no-unresolved */

import { execSync } from 'child_process';

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

  const webImageVersion = execSync('git rev-parse --abbrev-ref HEAD')
    .toString('utf8')
    .replace(/[\n\r\s]+$/, '');

  return defineConfig({
    chromeWebSecurity: false,
    defaultCommandTimeout: 6000,
    e2e: {
      excludeSpecPattern: ['*.js', '*.ts', '*.md'],
      setupNodeEvents,
      specPattern
    },
    env: {
      ...env,
      OPENID_IMAGE_VERSION: '23.04',
      WEB_IMAGE_OS: 'alma9',
      WEB_IMAGE_VERSION: webImageVersion,
      dockerName: dockerName || 'centreon-dev'
    },
    execTimeout: 60000,
    experimentalMemoryManagement: true,
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
