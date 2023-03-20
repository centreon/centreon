/* eslint-disable @typescript-eslint/no-var-requires */
const { defineConfig } = require('cypress');
const {
  addMatchImageSnapshotPlugin
} = require('cypress-image-snapshot/plugin');

interface DefineCypressConfig {
  cypressFolder?: string;
  specPattern: string;
  webpackConfig;
}

module.exports = ({
  webpackConfig,
  cypressFolder = 'cypress',
  specPattern
}: DefineCypressConfig): unknown =>
  defineConfig({
    component: {
      devServer: {
        bundler: 'webpack',
        framework: 'react',
        webpackConfig
      },
      setupNodeEvents: (on, config) => {
        addMatchImageSnapshotPlugin(on, config);
      },
      specPattern,
      supportFile: `${cypressFolder}/support/component.tsx`
    },
    env: {
      baseUrl: 'http://localhost:9092'
    },
    reporter: 'mochawesome',
    reporterOptions: {
      html: false,
      json: true,
      overwrite: true,
      reportDir: `${cypressFolder}/results`,
      reportFilename: '[name]-report.json'
    },
    video: true,
    videosFolder: `${cypressFolder}/results/videos`
  });
