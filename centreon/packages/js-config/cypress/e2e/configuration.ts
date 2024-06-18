/* eslint-disable @typescript-eslint/explicit-function-return-type */
/* eslint-disable @typescript-eslint/no-explicit-any */
// Import des modules nécessaires
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

import { defineConfig } from 'cypress';
import installLogsPrinter from 'cypress-terminal-report/src/installLogsPrinter';
import { config as configDotenv } from 'dotenv';

import esbuildPreprocessor from './esbuild-preprocessor';
import plugins from './plugins';
import tasks from './tasks';

// Définition des options de configuration pour Cypress
interface ConfigurationOptions {
  cypressFolder?: string;
  env?: Record<string, unknown>;
  envFile?: string;
  isDevelopment?: boolean;
  specPattern: string;
}

// Définition du chemin vers report.json
const reportPath = path.join(
  __dirname,
  '../../../../tests/e2e/results',
  'cucumber-logs',
  'report.json'
);

// Fonction pour attendre la création de report.json
const waitForReportJson = (
  callback: () => void,
  timeout: number,
  interval: number
) => {
  let elapsedTime = 0;

  const checkFileExists = () => {
    if (fs.existsSync(reportPath)) {
      callback();
    } else {
      elapsedTime += interval;
      if (elapsedTime < timeout) {
        setTimeout(checkFileExists, interval);
      } else {
        console.error('Timeout waiting for report.json');
      }
    }
  };

  checkFileExists();
};

// Définition de la configuration Cypress
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

  // Fonction pour traiter after:run une fois que report.json est prêt
  const handleAfterRun = (results: any) => {
    const testRetries: { [key: string]: boolean } = {};

    if ('runs' in results) {
      results.runs.forEach((run: any) => {
        run.tests.forEach((test: any) => {
          if (test.attempts && test.attempts.length > 1) {
            const testTitle = test.title.join(' > '); // Convert the array to a string
            testRetries[testTitle] = true;
          }
        });
      });
    }

    console.log('After run results:', results);
    console.log('Test retries:', testRetries);

    // Lisez et manipulez report.json ici si nécessaire
    const reportContent = fs.readFileSync(reportPath, 'utf8');
    console.log('Content of report.json:', reportContent);

    // Sauvegardez les testRetries dans hasRetries.json
    const resultFilePath = path.join(
      __dirname,
      '../../../../tests/e2e/results',
      'hasRetries.json'
    );
    fs.writeFileSync(resultFilePath, JSON.stringify(testRetries, null, 2));
  };

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
      setupNodeEvents: (on, config) => {
        installLogsPrinter(on);
        esbuildPreprocessor(on, config);
        tasks(on);

        // Attendre la création de report.json avant de continuer
        waitForReportJson(
          () => {
            // Définir l'événement after:run une fois que report.json est prêt
            on('after:run', handleAfterRun);

            // Continuer avec d'autres configurations et plugins
            plugins(on, config);
          },
          30000,
          1000
        ); // Timeout de 30 secondes, vérification toutes les 1 seconde

        // Ne pas retourner de valeurs dans setupNodeEvents
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
