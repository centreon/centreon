import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import {
  configHostActions,
  configHostDeleteActions,
  hostNamePrefix
} from '../../fixtures/hosts';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { HostsPage } from '../../pages/HostsPage';

/**
 * Migration of the core, single-user happy paths of the Cypress
 * `Hosts/01-host-configuration` feature: create a host, rename (edit) a host,
 * and delete a host.
 *
 * The Hosts configuration page (`main.php?p=60101`) is a legacy PHP page
 * rendered inside the React shell's `#main-content` iframe, so it is driven
 * through a Playwright frame locator (see `HostsPage`).
 *
 * Out of scope for this slice (kept in the Cypress suite): the duplicate
 * scenario (it relies on injecting an `onchange` attribute into a legacy
 * <select>), the geo-coordinates truncation edge cases (error-injection), host
 * templates/categories/dependencies, massive change and ACL matrices. No
 * monitoring engine data is needed here — these are pure configuration objects.
 */
test.describe('Hosts configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  // Unique-ish names so reruns are idempotent even if a previous cleanup failed.
  const createName = `${hostNamePrefix}host-create`;
  const editFromName = `${hostNamePrefix}host-edit`;
  const editToName = `${hostNamePrefix}host-edited`;
  const deleteName = `${hostNamePrefix}host-delete`;

  // Every name this spec may leave behind; cleaned up best-effort after each test.
  const allNames = [createName, editFromName, editToName, deleteName];

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  // Best-effort: remove anything a previous run created so the UI starts clean.
  test.beforeEach(async ({ adminApi }) => {
    for (const name of allNames) {
      try {
        await adminApi.provision(configHostDeleteActions(name));
      } catch {
        // host does not exist — nothing to remove
      }
    }
  });

  test.afterEach(async ({ adminApi }) => {
    for (const name of allNames) {
      try {
        await adminApi.provision(configHostDeleteActions(name));
      } catch {
        // host already gone (e.g. the delete test removed it)
      }
    }
  });

  test('creates a host through the form', async ({ page }) => {
    const hosts = new HostsPage(page);

    await hosts.open();
    await hosts.createHost({ name: createName });

    await expect(hosts.hostLink(createName)).toBeVisible();
  });

  test('renames an existing host', async ({ page, adminApi }) => {
    await test.step('Seed a host via the API', async () => {
      await adminApi.provision(configHostActions({ name: editFromName }));
    });

    const hosts = new HostsPage(page);

    await hosts.open();
    await hosts.renameHost(editFromName, editToName);

    await expect(hosts.hostLink(editToName)).toBeVisible();
    await expect(hosts.hostLink(editFromName)).toHaveCount(0);
  });

  test('deletes an existing host', async ({ page, adminApi }) => {
    await test.step('Seed a host via the API', async () => {
      await adminApi.provision(configHostActions({ name: deleteName }));
    });

    const hosts = new HostsPage(page);

    await hosts.open();
    await expect(hosts.hostLink(deleteName)).toBeVisible();

    await hosts.deleteHost(deleteName);

    await expect(hosts.hostLink(deleteName)).toHaveCount(0);
  });
});
