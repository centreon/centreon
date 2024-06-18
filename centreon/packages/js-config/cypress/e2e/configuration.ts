/* eslint-disable @typescript-eslint/explicit-function-return-type */
/* eslint-disable consistent-return */
/* eslint-disable no-restricted-syntax */
/* eslint-disable @typescript-eslint/no-unused-vars */
/* eslint-disable import/extensions */
/* eslint-disable import/no-unresolved */

import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

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
      setupNodeEvents: async (on, config) => {
        installLogsPrinter(on);
        await esbuildPreprocessor(on, config);
        tasks(on);

        on('after:run', async (results) => {
          const testRetries: { [key: string]: boolean } = {};
          if ('runs' in results) {
            results.runs.forEach((run) => {
              run.tests.forEach((test) => {
                if (test.attempts && test.attempts.length > 1) {
                  const testTitle = test.title.join(' > '); // Convert the array to a string
                  testRetries[testTitle] = true;
                }
              });
            });
          }

          console.log('After run results:', results);
          console.log('Test retries:', testRetries);

          // Save the testRetries object to a file in the e2e/results directory
          const resultFilePath = path.join(
            __dirname,
            '../../../../tests/e2e/results',
            'hasRetries.json'
          );
          fs.writeFileSync(
            resultFilePath,
            JSON.stringify(testRetries, null, 2)
          );

          const reportDir = path.join(
            __dirname,
            '../../../../tests/e2e/results',
            'cucumber-logs'
          );

          const reportPath = path.join(reportDir, 'report.json');
          if (!fs.existsSync(reportDir)) {
            fs.mkdirSync(reportDir, { recursive: true });
          }

          // Ensure the report.json is created and filled properly
          const ensureReportCreated = (retries = 5) => {
            if (fs.existsSync(reportPath)) {
              const reportContent = fs.readFileSync(reportPath, 'utf8');
              if (reportContent) {
                console.log('report.json created and filled successfully.');

                return true;
              }
            }
            if (retries > 0) {
              console.log(
                `Retrying to check report.json... ${retries} retries left`
              );

              return ensureReportCreated(retries - 1);
            }
            console.error('Failed to create or fill report.json.');

            return false;
          };

          if (!ensureReportCreated()) {
            console.error('Error: report.json is not created or empty.');
          } else {
            // Continue with other tasks if needed
          }
        });

        return plugins(on, config);
      },
      specPattern,
      supportFile: 'support/e2e.{js,jsx,ts,tsx}'
    },
    env: {
      ...env,
      DATABASE_IMAGE: 'bitnami/mariadb:10.11',
      OPENID_IMAGE_VERSION: process.env.MAJOR || '24.04',
      SAML_IMAGE_VERSION: process.env.MAJOR || '24.04',
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
    video: isDevelopment,
    videoCompression: 0,
    videosFolder: `${resultsFolder}/videos`,
    viewportHeight: 1080,
    viewportWidth: 1920
  });
};
