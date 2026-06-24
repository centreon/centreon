import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { hostActions } from '../../fixtures/monitoring';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { ServicesConfigurationPage } from '../../pages/ServicesConfigurationPage';

/**
 * Migration of the Cypress `Services-configuration/03-service-configuration`
 * feature, covering the reliable single-user core: create a service (on a host
 * seeded via CLAPI) and delete it again.
 *
 * The properties-edit and duplicate scenarios, plus everything depending on
 * service templates/categories/groups/dependencies, are out of scope for this
 * slice (quality over quantity).
 *
 * The "Services by host" configuration page is a legacy PHP page rendered in the
 * React shell's `#main-content` iframe, driven through a Playwright frame
 * locator (see `ServicesConfigurationPage`).
 */
const hostName = 'pw-host-1';
const serviceName = 'pw-service';
const serviceTemplate = 'Ping-LAN';

test.describe('Services configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  // Seed a host (the service's required parent) via CLAPI before each test, and
  // remove both objects afterwards so reruns stay idempotent.
  test.beforeEach(async ({ adminApi }) => {
    try {
      await adminApi.provision(
        hostActions({ hostGroup: 'Linux-Servers', name: hostName })
      );
    } catch {
      // host already provisioned by a previous run
    }
  });

  test.afterEach(async ({ adminApi }) => {
    try {
      await adminApi.provision([
        {
          action: 'DEL',
          object: 'SERVICE',
          values: `${hostName};${serviceName}`
        }
      ]);
    } catch {
      // service already deleted by the test
    }
    try {
      await adminApi.provision([
        { action: 'DEL', object: 'HOST', values: hostName }
      ]);
    } catch {
      // host already removed
    }
  });

  test('creates a service then deletes it', async ({ page }) => {
    const services = new ServicesConfigurationPage(page);

    await services.createService({
      host: hostName,
      name: serviceName,
      template: serviceTemplate
    });

    await services.open();
    await services.filterByHost(hostName);
    await expect(services.serviceListLink(serviceName)).toBeVisible({
      timeout: 30_000
    });

    await services.deleteService(serviceName);
    await expect(services.serviceListLink(serviceName)).toHaveCount(0);
  });
});
