import { adminStorageStatePath } from '../../fixtures/auth';
import {
  criticalServiceNames,
  okServiceName,
  resourcesHostName
} from '../../fixtures/resources';
import { test } from '../../fixtures/test';
import { ensureResourcesMonitored } from '../../helpers/provisioning';
import { ResourcesStatusPage } from '../../pages/ResourcesStatusPage';

/**
 * Replaces Resources-status/05: the listing returns the resources matching
 * each criterion. Building the search from the filter panel is covered by the
 * component suite; this checks the backend filtering, one search per row, on
 * the monitored fixture (three CRITICAL services and one OK on `host1`).
 */
const [firstCritical] = criticalServiceNames;
const all = [...criticalServiceNames, okServiceName];

const searches: Array<{
  expression: string;
  label: string;
  shown: Array<string>;
}> = [
  {
    expression: `type:service status:critical parent_name:${resourcesHostName}`,
    label: 'status',
    shown: criticalServiceNames
  },
  {
    expression: `type:service status:ok,critical parent_name:${resourcesHostName}`,
    label: 'several statuses',
    shown: all
  },
  {
    expression: `type:service status:ok status_type:hard,soft parent_name:${resourcesHostName}`,
    label: 'status types',
    shown: [okServiceName]
  },
  {
    expression: `type:service monitoring_server:Central status:ok parent_name:${resourcesHostName}`,
    label: 'monitoring server',
    shown: [okServiceName]
  },
  {
    expression: `type:service service_category:Ping status:critical parent_name:${resourcesHostName}`,
    label: 'service category',
    shown: criticalServiceNames
  },
  {
    expression: `name:${firstCritical} parent_name:${resourcesHostName}`,
    label: 'host and service names',
    shown: [firstCritical]
  }
];

test.use({ storageState: adminStorageStatePath });

test.beforeAll(async ({ baseURL }) => {
  test.setTimeout(300_000);
  await ensureResourcesMonitored(baseURL ?? '');
});

test('filters the resources on each criterion', async ({ page }) => {
  const resources = new ResourcesStatusPage(page);
  await resources.open();

  for (const { expression, label, shown } of searches) {
    await test.step(`Filter on ${label}`, async () => {
      await resources.search(expression);
      // Wait for a matching row first: a hidden row is only meaningful once
      // the listing reflects the new search.
      for (const name of shown) {
        await resources.expectResourceVisible(name);
      }
      for (const name of all.filter((name) => !shown.includes(name))) {
        await resources.expectResourceHidden(name);
      }
    });
  }
});
