const defineCypressConfig = require('@centreon/js-config/cypress/component/configuration');

const webpackConfig = require('./webpack.config.cypress');

module.exports = defineCypressConfig({
  specPattern: './www/front_src/src/**/*.cypress.spec.tsx',
  webpackConfig
});
