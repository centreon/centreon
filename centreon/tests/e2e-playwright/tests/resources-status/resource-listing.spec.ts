import { expect, test } from '../../fixtures/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { criticalServiceNames, okServiceName } from '../../fixtures/resources';
import { ensureStack } from '../../helpers/docker';
import { ensureResourcesMonitored } from '../../helpers/provisioning';
import { ResourcesStatusPage } from '../../pages/ResourcesStatusPage';

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

/**
 * Migration of the Cypress `Resources-status/01-listing` feature.
 *
 * The monitoring objects (one passive host with three CRITICAL services and one
 * OK service) are seeded once via the API, mirroring the Cypress CLAPI setup +
 * `submitResults`. The session is reused from the `setup` project.
 */
test.describe('Resources status listing', () => {
  test.use({ storageState: adminStorageStatePath });

  // Provisioning the monitoring engine (config apply + engine reload) is slow,
  // so give the one-time setup a generous budget. The host is left in place so
  // reruns against a warm stack skip provisioning entirely.
  test.beforeAll(async () => {
    test.setTimeout(300_000);
    await ensureStack({ services: ['web'] });
    await ensureResourcesMonitored(baseURL);
  });

  test('selects the "Unhandled alerts" filter by default and hides OK resources', async ({
    page
  }) => {
    const resources = new ResourcesStatusPage(page);

    await resources.open();

    // On first access the saved "Unhandled alerts" filter is pre-selected.
    await resources.expectSelectedFilter('Unhandled alerts');
    // An OK service can never be an unhandled alert, so it is filtered out of
    // the default view (the CRITICAL ones showing up is covered by the search
    // test, which avoids the virtualized listing's pagination).
    await resources.expectResourceHidden(okServiceName);
  });

  test('filters resources by a typed search criterion', async ({ page }) => {
    const resources = new ResourcesStatusPage(page);
    const [target, other] = criticalServiceNames;

    await resources.open();
    await resources.search(`type:service name:${target}`);

    await resources.expectResourceVisible(target);
    await resources.expectResourceHidden(other);
  });
});
