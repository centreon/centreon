import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  isDevelopment: true,
  specPattern: 'cypress/e2e/**/*.feature'
});
