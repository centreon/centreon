import { defineConfig } from "cypress";

const {
  addMatchImageSnapshotPlugin
} = require('cypress-image-snapshot/plugin');

export default defineConfig({
  component: {
    devServer: {
      framework: "react",
      bundler: "webpack",
    },
    setupNodeEvents: (on, config) => {
      addMatchImageSnapshotPlugin(on, config);
    },
    specPattern: './src/**/*.cypress.spec.tsx',
    supportFile: './cypress/support/component.ts'
  },
  env: {
    baseUrl: 'http://localhost:9090'
  },
  reporter: 'junit',
  reporterOptions: {
    mochaFile: 'cypress/results/cypress-fe.xml'
  },
  video: true,
  videosFolder: 'cypress/results/videos'
});
