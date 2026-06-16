import { execFileSync } from 'node:child_process';
import path from 'node:path';

/**
 * Helpers to drive the docker compose stack used by the E2E tests.
 *
 * The Cypress suite relies on `cy.execInContainer(...)` to flip feature flags
 * and recompute ACLs inside the running `web` container. Playwright runs in
 * Node, so we shell out to `docker compose exec` for the equivalent operations.
 */

const composeFile = path.resolve(
  __dirname,
  '../../../../.github/docker/docker-compose.yml'
);

const webApacheUser = 'apache'; // alma9 image — Debian images would use www-data

const execCompose = (args: Array<string>): string =>
  execFileSync('docker', ['compose', '-f', composeFile, ...args], {
    encoding: 'utf-8'
  });

/** Run a shell command inside the `web` service container. */
export const execInWebContainer = (command: string): string =>
  execCompose(['exec', '-T', 'web', 'sh', '-c', command]);

/**
 * Enable the dashboard feature flag (0..3 → 3 = fully enabled) in the running
 * platform, mirroring `cy.enableDashboardFeature()`.
 */
export const enableDashboardFeature = (): void => {
  execInWebContainer(
    `sed -i 's@"dashboard": [0-3]@"dashboard": 3@' /usr/share/centreon/config/features.json`
  );
};

/**
 * Recompute ACLs so that freshly created ACL groups take effect, mirroring
 * `cy.applyAcl()`.
 */
export const applyAcl = (): void => {
  execInWebContainer(
    `su -s /bin/sh ${webApacheUser} -c "/usr/bin/env php -q /usr/share/centreon/cron/centAcl.php"`
  );
};
