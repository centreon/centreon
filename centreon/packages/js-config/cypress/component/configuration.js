/* eslint-disable @typescript-eslint/no-var-requires */
const { devServer } = require('cypress-rspack-dev-server');
const { defineConfig } = require('cypress');
const {
  addMatchImageSnapshotPlugin
} = require('@simonsmith/cypress-image-snapshot/plugin');
const cypressCodeCoverageTask = require('@cypress/code-coverage/task');

import fs from 'fs';
import path from 'path';

module.exports = ({
  rspackConfig,
  cypressFolder,
  specPattern,
  env,
  excludeSpecPattern
}) => {
  const mainCypressFolder = cypressFolder || 'cypress';

  return defineConfig({
    component: {
      devServer: (devServerConfig) =>
        devServer({
          ...devServerConfig,
          framework: 'react',
          rspackConfig
        }),
      excludeSpecPattern,
      setupNodeEvents: (on, config) => {
        addMatchImageSnapshotPlugin(on, config);

        cypressCodeCoverageTask(on, config);

        on('before:browser:launch', (browser, launchOptions) => {
          if (
            ['chrome', 'chromium'].includes(browser.name) &&
            browser.isHeadless
          ) {
            launchOptions.args.push('--headless=new');
            launchOptions.args.push('--force-color-profile=srgb');
            launchOptions.args.push('--window-size=1400,1200');
          }

          return launchOptions;
        });

        on('after:run', (results) => {
          const testRetries = {};
          if ('runs' in results) {
            results.runs.forEach((run) => {
              run.tests.forEach((test) => {
                if (test.attempts && test.attempts.length > 1 && test.state === 'passed') {
                  const testTitle = test.title.join(' > '); // Convert the array to a string
                  testRetries[testTitle] = test.attempts.length - 1;
                }
              });
            });
          }

          // Save the testRetries object to a file in the e2e/results directory
          const resultFilePath = path.join(
            mainCypressFolder,
            'results',
            'retries.json'
          );

          fs.writeFileSync(resultFilePath, JSON.stringify(testRetries, null, 2));
        });

        on('after:spec', () => {
          if (global.__coverage__) {
            delete global.__coverage__;
          }
        });
      },
      specPattern,
      supportFile: `${mainCypressFolder}/support/component.tsx`
    },
    env: {
      baseUrl: 'http://localhost:9092',
      codeCoverage: {
        exclude: [
          'cypress/**/*.*',
          'packages/**',
          'node_modules',
          '**/*.js',
          '**/*.spec.tsx'
        ]
      },
      ...env
    },
    numTestsKeptInMemory: 1,
    reporter: 'mochawesome',
    reporterOptions: {
      html: false,
      json: true,
      overwrite: true,
      reportDir: `${mainCypressFolder}/results`,
      reportFilename: '[name]-report.json'
    },
    retries: {
      openMode: 0,
      runMode: 2
    },
    video: true,
    videosFolder: `${mainCypressFolder}/results/videos`,
    viewportHeight: 590,
    viewportWidth: 1280
  });
};
