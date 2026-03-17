import configuration from '../../packages/js-config/cypress/e2e/configuration';

// The comments are included to keep the architecture visible upon launch. This is intentional
console.log('cypress.dev.config] arch:', process.arch);
if (process.arch === 'arm64') {
  process.env.DOCKER_DEFAULT_PLATFORM = 'linux/amd64';
  console.log(
    '[cypress.dev.config] DOCKER_DEFAULT_PLATFORM set to linux/amd64',
  );
}

export default configuration({
  envFile: `${__dirname}/../../../.env`,
  isDevelopment: true,
  specPattern: 'features/**/*.feature'
});
