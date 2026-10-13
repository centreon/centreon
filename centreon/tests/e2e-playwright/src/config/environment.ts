import { resolve } from 'node:path';

/** Root of the centreon monorepo (where `.github/docker` lives). */
export const repositoryRoot = resolve(__dirname, '../../../../..');

export const composeFile = resolve(
  repositoryRoot,
  '.github/docker/docker-compose.yml'
);

/**
 * Isolated compose project so a Playwright run never collides with a stack
 * started by hand or by the Cypress suite.
 */
export const composeProjectName =
  process.env.E2E_COMPOSE_PROJECT ?? 'centreon-e2e-playwright';

export const webImage =
  process.env.WEB_IMAGE ??
  'docker.centreon.com/centreon/centreon-web-alma9:develop';

/** Host port published by the `web` service of the compose stack. */
export const baseUrl = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:4000';

export const centreonBasePath = '/centreon';

/** Endpoint used as readiness probe: it answers only once Centreon is up. */
export const platformVersionsUrl = `${baseUrl}${centreonBasePath}/api/latest/platform/versions`;

/** Admin session saved by tests/auth.setup.ts and reused by every spec. */
export const adminStorageState = resolve(__dirname, '../../.auth/admin.json');

/** For specs that must start logged out. */
export const anonymousStorageState = { cookies: [], origins: [] };

/** Reuse a stack already running instead of starting/stopping one. */
export const reuseExistingStack = process.env.E2E_SKIP_STACK === '1';

/** Leave the stack running after the suite (useful while writing tests). */
export const keepStackRunning = process.env.E2E_KEEP_STACK === '1';
