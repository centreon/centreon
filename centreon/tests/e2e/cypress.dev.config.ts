import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  env: {
    FAIL_FAST_ENABLED: 'true',
    FAIL_FAST_STRATEGY: 'spec'
  },
  isDevelopment: true,
  specPattern: 'cypress/e2e/**/*.feature'
});
