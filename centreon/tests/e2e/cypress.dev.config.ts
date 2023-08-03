/* eslint-disable no-console */
// import { defineConfig } from 'cypress';

import configuration from '@centreon/js-config/cypress/e2e/configuration';

// const resultsFolder = `cypress/results`;
// const webImageVersion = 'develop';
export { setupNodeEvents } from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  isDevelopment: true,
  specPattern: 'cypress/e2e/**/*.feature'
});

// export default defineConfig({
//   chromeWebSecurity: false,
//   defaultCommandTimeout: 6000,
//   e2e: {
//     excludeSpecPattern: ['*.js', '*.ts', '*.md'],
//     reporter: require.resolve(
//       '@badeball/cypress-cucumber-preprocessor/pretty-reporter'
//     ),
//     reporter,
//     setupNodeEvents,
//     specPattern: '**/*.feature'
//   },
//   env: {
//     OPENID_IMAGE_VERSION: '23.04',
//     WEB_IMAGE_OS: 'alma9',
//     WEB_IMAGE_VERSION: webImageVersion,
//     dockerName: 'centreon-dev'
//   },
//   execTimeout: 60000,
//   reporter: 'mochawesome',
//   reporterOptions: {
//     html: false,
//     json: true,
//     overwrite: true,
//     reportDir: `${resultsFolder}/reports`,
//     reportFilename: '[name]-report.json'
//   },
//   requestTimeout: 10000,
//   retries: 0,
//   screenshotsFolder: `${resultsFolder}/screenshots`,
//   video: true,
//   videosFolder: `${resultsFolder}/videos`
// });
