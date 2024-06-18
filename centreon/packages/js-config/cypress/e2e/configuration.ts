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
        // Configurez les préprocesseurs et plugins en premier
        installLogsPrinter(on);
        await esbuildPreprocessor(on, config);
        tasks(on);

        // Définissez l'événement after:spec pour capturer les résultats
        on('after:spec', async (spec, results) => {
          const testRetries = {};
          if (results && results.tests) {
            results.tests.forEach((test) => {
              if (test.attempts && test.attempts.length > 1) {
                const testTitle = test.title.join(' > ');
                testRetries[testTitle] = true;
              }
            });
          }

          console.log('After spec results:', results);
          console.log('Test retries:', testRetries);

          // Sauvegardez les retries uniquement si nécessaire
          if (Object.keys(testRetries).length > 0) {
            const resultFilePath = path.join(
              __dirname,
              '../../../../tests/e2e/results',
              'hasRetries.json'
            );
            fs.writeFileSync(
              resultFilePath,
              JSON.stringify(testRetries, null, 2)
            );
          }

          // Logique pour générer report.json
          const reportDir = path.join(
            __dirname,
            '../../../../tests/e2e/results',
            'cucumber-logs'
          );
          const reportPath = path.join(reportDir, 'report.json');
          if (!fs.existsSync(reportDir)) {
            fs.mkdirSync(reportDir, { recursive: true });
          }

          if (!fs.existsSync(reportPath)) {
            fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
          }
        });

        // Retournez les plugins configurés
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
