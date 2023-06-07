const defineCypressConfig = require('@centreon/js-config/cypress/component/configuration');

const webpackConfig = require('./webpack.config.cypress');

module.exports = defineCypressConfig({
  excludeSpecPattern: './www/modules',
  specPattern: './www/**/*.cypress.spec.tsx',
  webpackConfig
});
