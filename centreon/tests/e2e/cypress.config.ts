import configuration from '@centreon/js-config/cypress/e2e/configuration';

export default configuration({
  envFile: `${__dirname}/../../../.version`,
  specPattern: 'features/**/*.feature'
});
