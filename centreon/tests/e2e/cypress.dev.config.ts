import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  env: {
    OPENID_IMAGE_URL: 'http://localhost:8080'
  },
  envFile: `${__dirname}/../../../.env`,
  isDevelopment: true,
  specPattern: 'features/**/*.feature'
});
