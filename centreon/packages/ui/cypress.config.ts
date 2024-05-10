const defineCypressConfig = require('@centreon/js-config/cypress/component/configuration');

module.exports = defineCypressConfig({
  env: {
    codeCoverage: {
      exclude: ['cypress/**/*.*', 'node_modules', '**/*.js']
    }
  },
  rspackConfig: require('../../rspack.config.cypress'),
  specPattern: './src/**/*.cypress.spec.tsx'
});
