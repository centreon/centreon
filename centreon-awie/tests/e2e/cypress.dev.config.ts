import configuration from './configuration';

export default configuration({
  env: {
    OPENID_IMAGE_URL: 'http://localhost:8080'
  },
  envFile: `${__dirname}/../../../.version`,
  isDevelopment: true,
  specPattern: 'features/**/*.feature'
});
