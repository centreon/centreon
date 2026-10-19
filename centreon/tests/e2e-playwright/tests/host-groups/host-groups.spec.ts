import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { test } from '../../fixtures/test';
import type { ClapiAction } from '../../helpers/CentreonApi';
import { ensureStack } from '../../helpers/docker';
import { HostGroupsPage } from '../../pages/HostGroupsPage';

/**
 * Migration of the Cypress `HostGroups/01-host-group-configuration` feature.
 *
 * Only the reliable single-admin happy paths are ported: create a host group
 * (via the React modal) and delete an existing one (inline delete + confirm).
 * The edit/duplicate scenarios depend on monitoring data (hosts/services pushed
 * to the engine, the "Up status hosts" counter, MAP geo-coordinates, icon
 * pickers) and are left out of this slice.
 *
 * Host groups are a modern React configuration page (`ConfigurationBase`
 * listing + modal), so no iframe is involved. Prerequisites and cleanup go
 * through CLAPI (`ADD`/`DEL HG`) via `adminApi.provision(...)` instead of a
 * dedicated CentreonApi method, keeping reruns idempotent.
 */
const deleteHostGroupActions = (name: string): Array<ClapiAction> => [
  { action: 'DEL', object: 'HG', values: name }
];

const addHostGroupActions = (
  name: string,
  alias = name
): Array<ClapiAction> => [
  { action: 'ADD', object: 'HG', values: `${name};${alias}` }
];

test.describe('Host groups', () => {
  test.use({ storageState: adminStorageStatePath });

  const createdGroup = 'pw-host-group-created';
  const seededGroup = 'pw-host-group-to-delete';

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test.afterEach(async ({ adminApi }) => {
    // Best-effort cleanup so reruns start from a clean slate.
    for (const name of [createdGroup, seededGroup]) {
      try {
        await adminApi.provision(deleteHostGroupActions(name));
      } catch {
        // Already removed by the test (or never created).
      }
    }
  });

  test('creates a host group through the configuration modal', async ({
    page
  }) => {
    const hostGroups = new HostGroupsPage(page);

    await hostGroups.open();
    await hostGroups.createHostGroup({
      alias: createdGroup,
      name: createdGroup
    });

    await expect(hostGroups.snackbar('Host group created')).toBeVisible();
    await expect(hostGroups.row(createdGroup)).toBeVisible();
  });

  test('deletes a host group after confirmation', async ({
    page,
    adminApi
  }) => {
    await adminApi.provision(addHostGroupActions(seededGroup));

    const hostGroups = new HostGroupsPage(page);
    await hostGroups.open();
    await expect(hostGroups.row(seededGroup)).toBeVisible();

    await hostGroups.deleteHostGroup(seededGroup, { confirm: true });
    await expect(hostGroups.row(seededGroup)).toHaveCount(0);
  });
});
