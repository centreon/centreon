const defineCypressConfig = require('@centreon/js-config/cypress/component/configuration');

module.exports = defineCypressConfig({
  specPattern: './src/**/*.cypress.spec.tsx'
});
