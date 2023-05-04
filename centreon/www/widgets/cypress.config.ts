const defineCypressConfig = require('@centreon/js-config/cypress/component/configuration');

module.exports = defineCypressConfig({
  cypressFolder: '../../cypress',
  specPattern: './src/**/*.cypress.spec.tsx'
});
