import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  envFile: `${__dirname}/../../../.env`,
  isDevelopment: true,
  specPattern: 'features/**/*.feature'
});
