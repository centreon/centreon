/* eslint-disable @typescript-eslint/no-var-requires */
const { defineConfig } = require('cypress');
const {
  addMatchImageSnapshotPlugin
} = require('@simonsmith/cypress-image-snapshot/plugin');
const cypressCodeCoverageTask = require('@cypress/code-coverage/task');

module.exports = ({
  webpackConfig,
  cypressFolder,
  specPattern,
  env,
  useVite = false,
  excludeSpecPattern
}) => {
  const mainCypressFolder = cypressFolder || 'cypress';

  return defineConfig({
    component: {
      devServer: {
        bundler: useVite ? 'vite' : 'webpack',
        framework: 'react',
        webpackConfig
      },
      excludeSpecPattern,
      setupNodeEvents: (on, config) => {
        addMatchImageSnapshotPlugin(on, config);

        cypressCodeCoverageTask(on, config);

        on('before:browser:launch', (browser, launchOptions) => {
          if (browser.name === 'chrome' && browser.isHeadless) {
            launchOptions.args.push('--headless=new');
          }

          return launchOptions;
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
    reporter: 'mochawesome',
    reporterOptions: {
      html: false,
      json: true,
      overwrite: true,
      reportDir: `${mainCypressFolder}/results`,
      reportFilename: '[name]-report.json'
    },
    video: true,
    videosFolder: `${mainCypressFolder}/results/videos`,
    viewportHeight: 590,
    viewportWidth: 1280
  });
};
