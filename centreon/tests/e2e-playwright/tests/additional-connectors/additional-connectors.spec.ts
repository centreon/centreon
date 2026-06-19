import { expect, request } from '@playwright/test';

import {
  additionalConnector,
  updatedAdditionalConnector
} from '../../fixtures/additional-connectors';
import { adminStorageStatePath } from '../../fixtures/auth';
import { adminUser } from '../../fixtures/credentials';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { AdditionalConnectorsPage } from '../../pages/AdditionalConnectorsPage';

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

const accEndpoint = `${baseURL}/api/latest/configuration/additional-connector-configurations`;

/**
 * Best-effort teardown: remove every `pw-` prefixed additional connector
 * through the configuration REST API so reruns stay idempotent.
 *
 * Inlined here (not added to the shared `CentreonApi` helper) so this migration
 * does not touch any shared file. It opens its own short-lived session.
 */
async function cleanupAdditionalConnectors(): Promise<void> {
  const context = await request.newContext({ ignoreHTTPSErrors: true });
  try {
    await context.post(
      `${baseURL}/authentication/providers/configurations/local`,
      { data: { login: adminUser.login, password: adminUser.password } }
    );
    const listResponse = await context.get(`${accEndpoint}?limit=100`);
    if (!listResponse.ok()) {
      return;
    }
    const { result } = (await listResponse.json()) as {
      result: Array<{ id: number; name: string }>;
    };
    for (const { id, name } of result) {
      if (name.startsWith('pw-')) {
        await context.delete(`${accEndpoint}/${id}`);
      }
    }
  } catch {
    // platform unreachable or already clean — ignore on teardown
  } finally {
    await context.dispose();
  }
}

/**
 * Migration of the Cypress `Additional-connectors` feature (create / list /
 * update / delete happy paths).
 *
 * This is pure configuration UI + REST API: no monitoring data, no engine
 * reload. The connector is attached to the built-in "Central" poller, so no
 * extra poller has to be provisioned. The flows run as admin (full privileges),
 * dropping the Cypress non-admin ACL matrix which is out of scope here.
 *
 * Connectors are created with `pw-` prefixed names and removed in afterEach so
 * reruns stay idempotent.
 */
test.describe('Additional connectors', () => {
  test.use({ storageState: adminStorageStatePath });

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test.afterEach(async () => {
    await cleanupAdditionalConnectors();
  });

  test('creates an additional connector and lists it', async ({ page }) => {
    const accPage = new AdditionalConnectorsPage(page);

    await accPage.open();
    await accPage.createConnector(additionalConnector);

    await expect(accPage.row(additionalConnector.name)).toBeVisible();
  });

  test('updates an existing additional connector', async ({ page }) => {
    const accPage = new AdditionalConnectorsPage(page);

    await accPage.open();
    await accPage.createConnector(additionalConnector);

    await accPage.openConnector(additionalConnector.name);
    await accPage.updateConnector(updatedAdditionalConnector);
    await accPage.save();

    await expect(accPage.row(updatedAdditionalConnector.name)).toBeVisible();
    await expect(accPage.row(additionalConnector.name)).toHaveCount(0);
  });

  test('deletes an additional connector after confirmation', async ({
    page
  }) => {
    const accPage = new AdditionalConnectorsPage(page);

    await accPage.open();
    await accPage.createConnector(additionalConnector);

    await accPage.deleteConnector(additionalConnector.name, { confirm: true });

    await expect(accPage.row(additionalConnector.name)).toHaveCount(0);
  });
});
