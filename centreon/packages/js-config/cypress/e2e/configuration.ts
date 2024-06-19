/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

import { defineConfig } from 'cypress';
import installLogsPrinter from 'cypress-terminal-report/src/installLogsPrinter';
import { config as configDotenv } from 'dotenv';
import cucumber from 'cypress-cucumber-preprocessor';

import esbuildPreprocessor from './esbuild-preprocessor';
import plugins from './plugins';
import tasks from './tasks';

// Import du préprocesseur Cucumber pour Cypress

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

  const handleTestResults = async (results: CypressCommandLine.RunResult) => {
    return new Promise<void>((resolve, reject) => {
      try {
        const testRetries: { [key: string]: boolean } = {};
        if (results && results.tests) {
          results.tests.forEach((test) => {
            if (test.attempts && test.attempts.length > 1) {
              const testTitle = test.title.join(' > ');
              testRetries[testTitle] = true;
            }
          });
        }

        console.log('Test retries:', testRetries);
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

        resolve(); // Résolution de la promesse une fois le traitement terminé
      } catch (error) {
        reject(error); // Rejet de la promesse en cas d'erreur
      }
    });
  };

  return defineConfig({
    chromeWebSecurity: false,
    defaultCommandTimeout: 20000,
    downloadsFolder: `${resultsFolder}/downloads`,
    e2e: {
      excludeSpecPattern: ['*.js', '*.ts', '*.md'],
      fixturesFolder: 'fixtures',
      // Reporter Cucumber JSON
      reporter: require.resolve('cucumber-json'),
      reporterOptions: {
        // Chemin du fichier de rapport JSON généré
        outputFile: `${resultsFolder}/cucumber-report.json`
      },
      setupNodeEvents: async (on, config) => {
        // Manipulation des résultats des tests après chaque spécification
        on('after:spec', (spec, results) => {
          // Utilisation de then() pour capturer les erreurs potentielles
          handleTestResults(results)
            .then(() => {
              console.log('Handled test results successfully');
            })
            .catch((error) => {
              console.error('Error while handling test results:', error);
              // Gérer l'erreur ou la journaliser selon les besoins
            });
        });

        // Installation du preprocessor Cucumber pour Cypress
        on('file:preprocessor', cucumber());

        installLogsPrinter(on);
        await esbuildPreprocessor(on, config);
        tasks(on);

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
