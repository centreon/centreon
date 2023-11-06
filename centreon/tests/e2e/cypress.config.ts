import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  dockerName: 'e2e-tests-centreon',
  env: {
    OPENID_IMAGE_URL: 'http://localhost:8080'
  },
  envFile: `${__dirname}/../../../.version`,
  specPattern: 'features/**/*.feature'
});
