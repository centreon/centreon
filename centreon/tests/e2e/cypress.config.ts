import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  dockerName: 'e2e-tests-centreon',
  env: {
    OPENID_IMAGE_URL: 'http://172.17.0.3:8080'
  },
  specPattern: 'cypress/e2e/**/*.feature'
});
