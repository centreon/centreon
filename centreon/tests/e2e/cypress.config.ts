import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  env: {
    OPENID_IMAGE_URL: 'http://localhost:8080'
  },
  envFile: `${__dirname}/../../../.version`,
  retries: {
    openMode: 2,
    runMode: 2
  },
  specPattern: 'features/**/*.feature'
});
