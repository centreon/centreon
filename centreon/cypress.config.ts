const defineCypressConfig = require('@centreon/js-config/cypress/component/configuration');

const rspackConfig = require('./rspack.config.cypress');

module.exports = defineCypressConfig({
  excludeSpecPattern: './www/modules',
  rspackConfig,
  specPattern: './www/**/*.cypress.spec.tsx'
});
