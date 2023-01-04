const { defineConfig } = require("cypress");
const {
  addMatchImageSnapshotPlugin,
} = require("cypress-image-snapshot/plugin");

const webpackConfig = require("./webpack.config.cypress");

module.exports = defineConfig({
  component: {
    devServer: {
      bundler: "webpack",
      framework: "react",
      webpackConfig,
    },
    setupNodeEvents: (on, config) => {
      addMatchImageSnapshotPlugin(on, config);
    },
    specPattern: "./www/front_src/src/**/*.cypress.spec.tsx",
    supportFile: "./cypress/support/component.tsx",
  },

  env: {
    baseUrl: "http://localhost:9090",
  },

  reporter: "junit",

  reporterOptions: {
    mochaFile: "cypress/results/cypress-fe.xml",
  },

  video: true,
  videosFolder: "cypress/results/videos",

  e2e: {
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
});
