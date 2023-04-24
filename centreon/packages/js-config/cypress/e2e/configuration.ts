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

  const baseUrlIPAddress = isDevelopment ? '0.0.0.0' : 'localhost';

  /*
  const getBranch = () => new Promise((resolve, reject) => {
    return exec('git rev-parse --abbrev-ref HEAD', (err, stdout, stderr) => {
      if (err)
        reject(`getBranch Error: ${err}`);
      else if (typeof stdout === 'string')
        resolve(stdout.trim());
    });
  });
  */
  const webImageVersion = execSync('git rev-parse --abbrev-ref HEAD')
    .toString('utf8')
    .replace(/[\n\r\s]+$/, '');
  /*
  execSync(
    'git branch --show-current',
    (err, stdout, stderr) => {
      if (err) {
        webImageVersion = 'develop';
        return;
      }
      webImageVersion = stdout;
    }
  );
  */

  return defineConfig({
    chromeWebSecurity: false,
    defaultCommandTimeout: 6000,
    e2e: {
      //baseUrl: 'http://0.0.0.0:4000',
      //port: 4000,
      excludeSpecPattern: ['*.js', '*.ts', '*.md'],
      setupNodeEvents,
      specPattern
    },
    env: {
      ...env,
      dockerName: dockerName || 'centreon-dev',
      WEB_IMAGE_VERSION: webImageVersion,
      OPENID_IMAGE_VERSION: '23.04',
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
    videosFolder: `${resultsFolder}/videos`,
  });
};
