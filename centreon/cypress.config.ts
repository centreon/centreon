const { defineConfig } = require('cypress');
const {
  addMatchImageSnapshotPlugin,
} = require('cypress-image-snapshot/plugin');

const webpackConfig = require('./webpack.config.dev');

module.exports = defineConfig({
  component: {
    devServer: {
      bundler: 'webpack',
      framework: 'react',
      webpackConfig,
    },
    setupNodeEvents: (on, config) => {
      addMatchImageSnapshotPlugin(on, config);

      /*
      on('before:browser:launch', (browser, launchOptions) => {
        if (browser.name === 'chrome' && browser.isHeadless) {
          launchOptions.args = launchOptions.args.map((arg) => {
            if (arg === '--headless') {
              return '--headless=new';
            }

            return arg;
          });
        }

        return launchOptions;
      });
      */
    },
    specPattern: './www/front_src/src/**/*.cypress.spec.tsx',
    supportFile: './cypress/support/component.tsx',
  },
  reporter: 'mochawesome',
  reporterOptions: {
    html: false,
    json: true,
    overwrite: true,
    reportDir: 'cypress/results',
  },
  video: true,
  videosFolder: 'cypress/results/videos',
});
