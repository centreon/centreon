import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  dockerName: 'e2e-tests-centreon',
  specPattern: 'cypress/e2e/**/*.feature'
});
