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

// Définition du chemin vers report.json et hasRetries.json
const resultsFolder = '${cypressFolder || '.'}/results';
const reportPath = path.join(resultsFolder, 'cucumber-logs', 'report.json');
const hasRetriesPath = path.join(resultsFolder, 'hasRetries.json');

// Fonction pour assurer la création des fichiers
function ensureFilesExist() {
  try {
    // Création de report.json s'il n'existe pas
    if (!fs.existsSync(reportPath)) {
      fs.writeFileSync(reportPath, '{}'); // Vous pouvez initialiser avec un objet vide ou autre contenu
      console.log('Created report.json:', reportPath);
    }

    // Création de hasRetries.json s'il n'existe pas
    if (!fs.existsSync(hasRetriesPath)) {
      fs.writeFileSync(hasRetriesPath, '{}'); // Vous pouvez initialiser avec un objet vide ou autre contenu
      console.log('Created hasRetries.json:', hasRetriesPath);
    }
  } catch (err) {
    console.error('Error creating files:', err);
    throw err;
  }
}

// Appeler la fonction pour s'assurer que les fichiers existent
ensureFilesExist();

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

        on('after:run', (results) => {
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

          // Lire et manipuler report.json ici si nécessaire
          const reportContent = fs.readFileSync(reportPath, 'utf8');
          console.log('Content of report.json:', reportContent);

          // Sauvegarder les testRetries dans hasRetries.json
          fs.writeFileSync(hasRetriesPath, JSON.stringify(testRetries, null, 2));

          return plugins(on, config);
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