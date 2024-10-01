const defineCypressConfig = require('@centreon/js-config/cypress/component/configuration');

module.exports = defineCypressConfig({
  env: {
    codeCoverage: {
      exclude: ['cypress/**/*.*', 'node_modules', '**/*.js']
    }
  },
  specPattern: './src/**/*.cypress.spec.tsx',
  webpackConfig: require('../../rspack.config.cypress')
});
