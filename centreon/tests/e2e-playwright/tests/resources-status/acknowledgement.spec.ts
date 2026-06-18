import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import {
  criticalServiceNames,
  resourcesHostName
} from '../../fixtures/resources';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { ensureResourcesMonitored } from '../../helpers/provisioning';
import { ResourcesStatusPage } from '../../pages/ResourcesStatusPage';

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

const [acknowledgeService] = criticalServiceNames;

/**
 * Migration of the Cypress `Resources-status/02-acknowledgments` feature, kept
 * fast by **reusing the monitoring data already provisioned** by the listing
 * spec (no extra config apply / engine reload).
 *
 * The assertion checks the "Acknowledge command sent" feedback — the
 * deterministic, immediate signal that the UI built and sent the acknowledge
 * request — mirroring the Cypress scenario.
 *
 * Out of scope on this shared stack: the disacknowledge flow and the
 * sticky/notification-suppression scenarios. The passive services here get
 * actively re-checked and recover to OK, which makes the engine auto-clear the
 * acknowledgement; the disacknowledge UI only appears while a resource is seen
 * as acknowledged, so it cannot be exercised reliably without a dedicated,
 * frozen-state engine (the Cypress suite isolates each scenario in its own
 * fresh container). The POM exposes `disacknowledgeSelected()` for when such an
 * environment is available.
 */
test.describe('Resources status acknowledgement', () => {
  test.use({ storageState: adminStorageStatePath });

  test.beforeAll(async () => {
    test.setTimeout(300_000);
    await ensureStack({ services: ['web'] });
    await ensureResourcesMonitored(baseURL);
  });

  // Best-effort: drop the acknowledgement so the listing spec sees the service
  // as a plain problem again.
  test.afterEach(async ({ adminApi }) => {
    try {
      await adminApi.disacknowledgeService(acknowledgeService);
    } catch {
      // already unacknowledged (e.g. auto-cleared on recovery)
    }
  });

  test('acknowledges a problem resource from the listing', async ({
    page,
    adminApi
  }) => {
    // Make sure the target really is a CRITICAL problem to acknowledge.
    await adminApi.ensureServiceCritical(resourcesHostName, acknowledgeService);

    const resources = new ResourcesStatusPage(page);

    await resources.open();
    await resources.search(`type:service name:${acknowledgeService}`);

    await resources.selectResource(acknowledgeService);
    await resources.acknowledgeSelected('Acknowledged by the Playwright suite');

    await expect(resources.snackbar('Acknowledge command sent')).toBeVisible();
  });
});
