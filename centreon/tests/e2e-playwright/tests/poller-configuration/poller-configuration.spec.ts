import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import {
  configHostActions,
  configHostDeleteActions
} from '../../fixtures/hosts';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { PollerConfigurationPage } from '../../pages/PollerConfigurationPage';

/**
 * Migration of the Cypress `Poller-configuration` feature
 * (`features/Poller-configuration/01-poller-configuration.feature`).
 *
 * Scenario ported (the most critical happy path): "Generate the configuration
 * to all pollers quickly" (`@TEST_MON-22138`) — the React one-click export from
 * the header Pollers menu, asserting the success snackbar.
 *
 * Deliberately NOT ported here:
 *  - The legacy `main.php?p=60901`/`p=60902` generate page Scenario Outline
 *    (Reload/Restart): it lives in the `#main-content` iframe, depends on a
 *    provisioned poller-configuration ACL user + post-generation commands, and
 *    its assertion greps `centengine.log` inside the `web` container (engine
 *    reload). That is an OS-level, multi-step legacy flow out of scope for this
 *    fast happy-path slice.
 *  - "no poller selected" / "broken pollers" (error-injection edge cases).
 *  - "Duplicate an existing remote poller" (legacy iframe + remote poller).
 *
 * A bare configuration host is seeded through CLAPI so the platform genuinely
 * has a configuration to export, then removed afterwards for idempotent reruns.
 */
test.describe('Poller configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  const hostName = 'pw-poller-export-host';

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test.beforeEach(async ({ adminApi }) => {
    // Seed a configuration host so there is a pending change to export. Best
    // effort: if a previous run left it behind, remove it first.
    try {
      await adminApi.provision(configHostDeleteActions(hostName));
    } catch {
      // host does not exist yet — nothing to clean
    }
    await adminApi.provision(configHostActions({ name: hostName }));
  });

  test.afterEach(async ({ adminApi }) => {
    try {
      await adminApi.provision(configHostDeleteActions(hostName));
    } catch {
      // best-effort cleanup
    }
  });

  test('exports and reloads the configuration on all pollers from the header', async ({
    page
  }) => {
    const pollers = new PollerConfigurationPage(page);

    await pollers.open();
    await pollers.openPollerMenu();
    await pollers.exportAndReloadAllPollers();

    // Match leniently: the exact reload wording can vary with the platform's
    // global config state (other tests mutate hosts/services before this one).
    await expect(pollers.snackbar(/exported/i)).toBeVisible({
      timeout: 30_000
    });
  });
});
