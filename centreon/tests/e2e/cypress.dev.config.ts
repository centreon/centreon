import configuration from '../../packages/js-config/cypress/e2e/configuration';

// biome-ignore lint/suspicious/noConsole: This is intentional to keep the architecture visible upon launch
console.log('cypress.dev.config] arch:', process.arch);
if (process.arch === 'arm64') {
  process.env.DOCKER_DEFAULT_PLATFORM = 'linux/amd64';
}

export default configuration({
  envFile: `${__dirname}/../../../.env`,
  isDevelopment: true,
  specPattern: 'features/**/*.feature'
});
