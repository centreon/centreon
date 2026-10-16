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

// Optional extra compose files layered on top of the base one, taken from
// CENTREON_COMPOSE_OVERRIDES (space/comma/colon-separated). Names are resolved
// relative to the base compose directory, so the project directory — and thus
// relative paths like `env_file: .env` — stay anchored there. The CI e2e job
// uses this to swap in a faster test database (see docker-compose-test-db.yml).
const composeDir = path.dirname(composeFile);
const overrideArgs: Array<string> = (
  process.env.CENTREON_COMPOSE_OVERRIDES ?? ''
)
  .split(/[\s,:]+/)
  .filter(Boolean)
  .flatMap((name) => [
    '-f',
    path.isAbsolute(name) ? name : path.resolve(composeDir, name)
  ]);

const webApacheUser = 'apache'; // alma9 image — Debian images would use www-data

const execCompose = (args: Array<string>): string =>
  execFileSync(
    'docker',
    ['compose', '-f', composeFile, ...overrideArgs, ...args],
    {
      encoding: 'utf-8'
    }
  );

const sleep = (ms: number): Promise<void> =>
  new Promise((resolve) => setTimeout(resolve, ms));

/** Run a shell command inside the `web` service container. */
export const execInWebContainer = (command: string): string =>
  execCompose(['exec', '-T', 'web', 'sh', '-c', command]);

/** Names of the compose services currently in the "running" state. */
const runningServices = (): Array<string> =>
  execCompose(['ps', '--services', '--status', 'running'])
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

export interface StackRequirement {
  /** docker compose profiles to enable (e.g. ['openid']). */
  profiles?: Array<string>;
  /** services that must be up (e.g. ['web', 'openid', 'sso-proxy']). */
  services: Array<string>;
}

/**
 * Make sure the required services are up before the tests run.
 *
 * It inspects the running services and, if any required one is missing, runs
 * `docker compose up -d` for the requested services/profiles. `up -d` is
 * idempotent and recreates containers whose configuration drifted (e.g. a
 * different image), so this also handles "the running stack does not match the
 * one the tests need". Set SKIP_STACK_MANAGEMENT=1 to manage the stack yourself.
 */
export const ensureStack = async ({
  services,
  profiles = []
}: StackRequirement): Promise<void> => {
  if (process.env.SKIP_STACK_MANAGEMENT) {
    return;
  }

  const running = runningServices();
  const missing = services.filter((service) => !running.includes(service));

  if (missing.length > 0) {
    const profileArgs = profiles.flatMap((profile) => ['--profile', profile]);
    // eslint-disable-next-line no-console
    console.log(
      `[stack] starting services: ${missing.join(', ')} (running: ${
        running.join(', ') || 'none'
      })`
    );
    // `--quiet-pull` avoids flooding the logs with per-layer pull progress.
    execCompose([...profileArgs, 'up', '-d', '--quiet-pull', ...services]);
  }

  if (services.includes('web')) {
    await waitForWebHealthy();
  }
};

/** Poll the `web` container health until it reports healthy. */
export const waitForWebHealthy = async (timeoutMs = 300_000): Promise<void> => {
  const containerId = execCompose(['ps', '-q', 'web']).trim();
  if (!containerId) {
    throw new Error('web container not found');
  }
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const status = execFileSync(
      'docker',
      ['inspect', '--format', '{{.State.Health.Status}}', containerId],
      { encoding: 'utf-8' }
    ).trim();
    if (status === 'healthy') {
      return;
    }
    await sleep(5_000);
  }
  throw new Error('web container did not become healthy in time');
};

/** Poll an HTTP endpoint until it answers with the expected status (default 200). */
export const waitForHttpOk = async (
  url: string,
  { timeoutMs = 180_000, expectedStatus = 200 } = {}
): Promise<void> => {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url);
      if (response.status === expectedStatus) {
        return;
      }
    } catch {
      // service not reachable yet
    }
    await sleep(5_000);
  }
  throw new Error(`endpoint ${url} not ready in time`);
};

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
 * Enable the cloud notification feature flag (0..3 → 3 = fully enabled) in the
 * running platform, mirroring `enableNotificationFeature()` from the Cypress
 * Cloud-notifications suite.
 */
export const enableNotificationFeature = (): void => {
  execInWebContainer(
    `sed -i 's@"notification": [0-3]@"notification": 3@' /usr/share/centreon/config/features.json`
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

// The platform-side logs worth capturing when a test fails: PHP-FPM and Apache
// for 4xx/5xx, the Symfony app/access logs for the modern APIs, and the
// gorgone/engine/broker logs for monitoring/config-apply flows.
const webLogFiles = [
  '/var/log/php-fpm/centreon-error.log',
  '/var/log/php-fpm/error.log',
  '/var/log/httpd/error_log',
  '/var/log/centreon/prod.web.log',
  '/var/log/centreon/prod.access.log',
  '/var/log/centreon/login.log',
  '/var/log/centreon-gorgone/gorgoned.log',
  '/var/log/centreon-engine/centengine.log',
  '/var/log/centreon-broker/central-broker-master.log'
];

/**
 * Tail the most useful Centreon / Apache / PHP logs from the `web` container,
 * concatenated with per-file headers, to attach platform-side context to a
 * failing test (the browser side already has trace/screenshot/video). Returns a
 * short note instead of throwing if the container is unreachable.
 */
export const dumpWebLogs = (lines = 300): string => {
  const script = webLogFiles
    .map(
      (file) =>
        `echo "===== ${file} (last ${lines} lines) ====="; tail -n ${lines} "${file}" 2>/dev/null || echo "(missing)"; echo`
    )
    .join('; ');
  try {
    return execInWebContainer(script);
  } catch (error) {
    return `failed to collect web logs: ${(error as Error).message}`;
  }
};
