import { adminStorageStatePath } from '../../fixtures/auth';
import {
  criticalServiceNames,
  okServiceName,
  resourcesHostName,
  resourcesSubmitResults
} from '../../fixtures/resources';
import { expect, test } from '../../fixtures/test';
import { ensureResourcesMonitored } from '../../helpers/provisioning';
import { DowntimesListingPage } from '../../pages/DowntimesListingPage';
import { ResourcesStatusPage } from '../../pages/ResourcesStatusPage';

/**
 * Replaces Resources-status/02, 03, 06 and 07: the actions of the listing on
 * the monitored fixture, checked against the monitoring state the API reads
 * back rather than the snackbar alone.
 */
const [first, second, third] = criticalServiceNames;
const byHost = `type:service parent_name:${resourcesHostName}`;

test.use({ storageState: adminStorageStatePath });

test.beforeAll(async ({ baseURL }) => {
  test.setTimeout(300_000);
  await ensureResourcesMonitored(baseURL ?? '');
});

test('acknowledges then disacknowledges a problem', async ({
  adminApi,
  page
}) => {
  const resources = new ResourcesStatusPage(page);
  await adminApi.ensureServiceCritical(resourcesHostName, first);

  try {
    await resources.open();
    await resources.search(byHost);
    await resources.selectResource(first);
    await resources.acknowledgeSelected('Acknowledged by Playwright');
    await expect(resources.snackbar('Acknowledge command sent')).toBeVisible();
    await expect
      .poll(async () => (await adminApi.serviceState(first))?.acknowledged)
      .toBe(true);

    await resources.search(`${byHost} state:acknowledged`);
    await resources.expectResourceVisible(first);
    await resources.selectResource(first);
    await resources.disacknowledgeSelected();
    await expect
      .poll(async () => (await adminApi.serviceState(first))?.acknowledged)
      .toBe(false);
  } finally {
    await adminApi.disacknowledgeService(first).catch(() => undefined);
  }
});

test('acknowledges several problems at once', async ({ adminApi, page }) => {
  const resources = new ResourcesStatusPage(page);
  for (const service of [second, third]) {
    await adminApi.ensureServiceCritical(resourcesHostName, service);
  }

  try {
    await resources.open();
    await resources.search(byHost);
    await resources.selectResource(second);
    await resources.selectResource(third);
    await resources.acknowledgeSelected('Acknowledged together');
    for (const service of [second, third]) {
      await expect
        .poll(async () => (await adminApi.serviceState(service))?.acknowledged)
        .toBe(true);
    }
  } finally {
    for (const service of [second, third]) {
      await adminApi.disacknowledgeService(service).catch(() => undefined);
    }
  }
});

test('sets downtimes on several resources then cancels them', async ({
  adminApi,
  page
}) => {
  test.setTimeout(240_000);
  const resources = new ResourcesStatusPage(page);
  const downtimes = new DowntimesListingPage(page);
  const targets: Array<[string, 'critical' | 'ok']> = [
    [first, 'critical'],
    [okServiceName, 'ok']
  ];
  const clear = async (): Promise<void> => {
    for (const [service, status] of targets) {
      await adminApi.clearServiceDowntimes(resourcesHostName, service, status);
    }
  };
  // Without it, a downtime left over would satisfy the next checks at once.
  await clear();

  try {
    await resources.open();
    await resources.search(byHost);
    for (const [service] of targets) {
      await resources.selectResource(service);
    }
    await resources.setDowntimeOnSelection();
    await expect(resources.snackbar('Downtime command sent')).toBeVisible();
    for (const [service] of targets) {
      await expect
        .poll(async () => (await adminApi.serviceState(service))?.inDowntime, {
          timeout: 60_000
        })
        .toBe(true);
    }

    await downtimes.open();
    await downtimes.cancel(targets.map(([service]) => service));
    // The engine drops the downtimes at once; the resource state follows with
    // the next result of these passive services, so keep feeding them.
    await expect
      .poll(
        async () => {
          await adminApi.submitResults(
            resourcesSubmitResults.filter(({ service }) =>
              targets.some(([target]) => target === service)
            )
          );
          const states = await Promise.all(
            targets.map(([service]) => adminApi.serviceState(service))
          );

          return states.map((state) => state?.inDowntime);
        },
        { intervals: [5_000], timeout: 90_000 }
      )
      .toEqual([false, false]);
  } finally {
    await clear();
  }
});

test('submits a result to a passive service', async ({ adminApi, page }) => {
  const resources = new ResourcesStatusPage(page);

  try {
    await resources.open();
    await resources.search(byHost);
    await resources.selectResource(okServiceName);
    await resources.submitStatusOnSelection(
      'Warning',
      'Submitted by Playwright'
    );
    await expect
      .poll(async () => (await adminApi.serviceState(okServiceName))?.status, {
        timeout: 60_000
      })
      .toBe('WARNING');
  } finally {
    await adminApi.submitResults([
      {
        host: resourcesHostName,
        output: 'OK - submitted by Playwright',
        service: okServiceName,
        status: 'ok'
      }
    ]);
  }
});

test('exports the listing to CSV', async ({ page }) => {
  const resources = new ResourcesStatusPage(page);
  await resources.open();
  await resources.search(byHost);
  await resources.expectResourceVisible(first);

  const csv = await resources.exportCsv();
  const [header, ...rows] = csv.trim().split('\n');
  expect(header).toContain('Resource');
  for (const service of [...criticalServiceNames, okServiceName]) {
    expect(rows.some((row) => row.includes(service))).toBe(true);
  }
});
