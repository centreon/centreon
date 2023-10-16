/* eslint-disable import/extensions */
/* eslint-disable import/no-unresolved */

import { execSync } from 'child_process';

import { defineConfig } from 'cypress';
import installLogsPrinter from 'cypress-terminal-report/src/installLogsPrinter';

import esbuildPreprocessor from './esbuild-preprocessor';
import plugins from './plugins';
import tasks from './tasks';

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
  const resultsFolder = `${cypressFolder || 'cypress'}/results`;

  const webImageVersion = execSync('git rev-parse --abbrev-ref HEAD')
    .toString('utf8')
    .replace(/[\n\r\s]+$/, '');

  return defineConfig({
    chromeWebSecurity: false,
    defaultCommandTimeout: 6000,
    e2e: {
      excludeSpecPattern: ['*.js', '*.ts', '*.md'],
      reporter: require.resolve('cypress-multi-reporters'),
      reporterOptions: {
        configFile: `${__dirname}/reporter-config.js`
      },
      setupNodeEvents: async (on, config) => {
        installLogsPrinter(on);
        await esbuildPreprocessor(on, config);
        tasks(on);

        return plugins(on, config);
      },
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
    requestTimeout: 10000,
    retries: 0,
    screenshotsFolder: `${resultsFolder}/screenshots`,
    video: true,
    videoCompression: 0,
    videosFolder: `${resultsFolder}/videos`
  });
};
