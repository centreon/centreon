import { expect } from '@playwright/test';

import { telegrafAgent } from '../../fixtures/agent-configuration';
import { adminStorageStatePath } from '../../fixtures/auth';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { AgentConfigurationPage } from '../../pages/AgentConfigurationPage';

/**
 * Migration of the Cypress `Agent-configuration` feature, covering the reliable
 * single-user core happy paths: create a Telegraf agent configuration, see it
 * listed, and delete it.
 *
 * Scope choices:
 * - The Telegraf agent type is used because it only needs the built-in
 *   `Central` poller plus certificate/key file names — no CMA authentication
 *   token has to be provisioned first (unlike the Centreon Monitoring Agent
 *   type, which the Cypress suite seeds with a `CMA-Token-001`).
 * - The multi-host, permission/ACL-matrix, error-injection and TLS-mode
 *   scenarios are out of scope for this slice.
 *
 * This is pure configuration UI: no monitoring data and no engine reload, so it
 * is fast and reliable. The objects are created and deleted through the UI; a
 * best-effort UI cleanup in `afterEach` keeps reruns idempotent.
 */
// DRAFT (workflow): ported from Cypress, not yet validated live — un-skip and fix selectors to finish.
test.describe.skip('Agent configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test.afterEach(async ({ page }) => {
    // Best-effort cleanup: remove the agent through the UI if it survived.
    const agents = new AgentConfigurationPage(page);
    try {
      await agents.open();
      if ((await agents.row(telegrafAgent.name).count()) > 0) {
        await agents.deleteAgent(telegrafAgent.name, { confirm: true });
      }
    } catch {
      // Nothing to clean up (page never opened, or already deleted).
    }
  });

  test('creates a Telegraf agent configuration and lists it', async ({
    page
  }) => {
    const agents = new AgentConfigurationPage(page);

    await agents.open();
    await agents.createTelegrafAgent(telegrafAgent);

    await expect(agents.row(telegrafAgent.name)).toBeVisible();
    await expect(agents.row(telegrafAgent.name)).toContainText('Telegraf');
  });

  test('deletes an agent configuration after confirmation', async ({
    page
  }) => {
    const agents = new AgentConfigurationPage(page);

    await agents.open();
    await agents.createTelegrafAgent(telegrafAgent);
    await expect(agents.row(telegrafAgent.name)).toBeVisible();

    await agents.deleteAgent(telegrafAgent.name, { confirm: true });
    await expect(agents.row(telegrafAgent.name)).toHaveCount(0);
  });

  test('keeps the agent configuration when the deletion is cancelled', async ({
    page
  }) => {
    const agents = new AgentConfigurationPage(page);

    await agents.open();
    await agents.createTelegrafAgent(telegrafAgent);

    await agents.deleteAgent(telegrafAgent.name, { confirm: false });
    await expect(agents.row(telegrafAgent.name)).toBeVisible();
  });
});
