// Local-only Cypress config: same as cypress.config.ts, aimed at a running CDE.
// Written by the test-pr skill. Never committed.
import configuration from '../../packages/js-config/cypress/e2e/configuration';

const config = configuration({
  envFile: `${__dirname}/../../../.version`,
  specPattern: 'features/**/*.feature'
});

config.e2e.supportFile = 'support/e2e.local.ts';

// A CDE answers a legacy page far slower than CI's throwaway container — a host
// form can take tens of seconds. Local-only headroom, so a slow load is not read
// as a missing request.
config.e2e.defaultCommandTimeout = 90_000;
config.e2e.requestTimeout = 60_000;
config.e2e.responseTimeout = 60_000;
config.e2e.pageLoadTimeout = 120_000;

// requestOnDatabase reaches its database through testcontainers, which owns no
// container here, so every suite building fixtures with it dies on "Cannot get
// container web". The CDE exposes its own database on 3307; pointing the task
// there makes those suites runnable locally. Registered last on purpose —
// Cypress keeps the final registration of a task name, hence the harmless
// "Multiple attempts to register requestOnDatabase" warning at startup.
const originalSetupNodeEvents = config.e2e.setupNodeEvents;

config.e2e.setupNodeEvents = async (on, cypressConfig) => {
  const result = await originalSetupNodeEvents?.(on, cypressConfig);

  on('task', {
    requestOnDatabase: async ({ database, query, params }) => {
      const { createConnection } = await import('mysql2/promise');
      const client = await createConnection({
        database,
        host: '127.0.0.1',
        password: 'centreon',
        port: 3307,
        user: 'centreon'
      });

      try {
        const [rows, fields] = params
          ? await client.execute(query, params)
          : await client.query(query);

        return [rows, fields];
      } finally {
        await client.end();
      }
    }
  });

  return result;
};

export default config;
