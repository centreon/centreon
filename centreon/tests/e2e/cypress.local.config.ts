// Local-only Cypress config: same as cypress.config.ts but with the harness
// above. Never committed.
import configuration from '../../packages/js-config/cypress/e2e/configuration';

const config = configuration({
  envFile: `${__dirname}/../../../.version`,
  specPattern: 'features/**/*.feature'
});

config.e2e.supportFile = 'support/e2e.local.ts';

// The CDE answers a cold legacy page far slower than CI's throwaway container.
// Local-only headroom, so a slow first load is not read as a missing request.
config.e2e.defaultCommandTimeout = 90_000;
config.e2e.requestTimeout = 60_000;
config.e2e.responseTimeout = 60_000;
config.e2e.pageLoadTimeout = 120_000;

export default config;
