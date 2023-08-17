import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  env: {
    OPENID_IMAGE_URL: 'http://localhost:8080'
  },
  isDevelopment: true,
  specPattern: 'cypress/e2e/**/*.feature'
});
