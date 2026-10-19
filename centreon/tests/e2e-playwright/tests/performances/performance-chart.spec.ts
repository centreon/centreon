import { randomUUID } from 'node:crypto';

import type { Page } from '@playwright/test';

import { adminUser } from '../../fixtures/credentials';
import { insertVirtualMetric } from '../../fixtures/legacy-objects';
import { expect, test } from '../../fixtures/test';
import { queryCentreonDb } from '../../helpers/docker';
import { waitForServiceMetrics } from '../../helpers/metrics';
import { signIn } from '../../helpers/session';

/**
 * Replaces Services-configuration/14: the performance page holds back a chart
 * of more than 20 metrics behind a warning, and draws it on demand.
 */
const service = 'Disk-/';

const openChart = async (page: Page): Promise<void> => {
  await page.goto('/centreon/main.get.php?p=204');
  await page.locator('input.select2-search__field').first().fill('disk');
  await page.getByText(`Centreon-Server - ${service}`, { exact: true }).click();
};

test('holds back a chart of more than 20 metrics behind a warning', async ({
  page
}) => {
  test.setTimeout(420_000);
  const metrics = await waitForServiceMetrics({ service });
  test.skip(metrics > 20, `${service} already has ${metrics} metrics`);
  const prefix = `pw-vm-${randomUUID().slice(0, 6)}`;
  await signIn(page, adminUser);
  const warning = page.getByText('More than 20 may cause performance issues.');

  await test.step('The chart of the metrics of the service is drawn', async () => {
    await openChart(page);
    await expect(page.getByText('Split chart').first()).toBeVisible({
      timeout: 30_000
    });
    await expect(warning).toHaveCount(0);
  });

  try {
    for (let index = metrics; index <= 20; index += 1) {
      queryCentreonDb(insertVirtualMetric(`${prefix}-${index}`, service));
    }
    await test.step('Virtual metrics past 20 hold it back', async () => {
      await openChart(page);
      await expect(warning).toBeVisible({ timeout: 30_000 });
      const displayAnyway = page.getByRole('button', { name: 'Display anyway' });
      await displayAnyway.click();
      await expect(displayAnyway).toHaveCount(0);
    });
  } finally {
    queryCentreonDb(
      `DELETE FROM virtual_metrics WHERE vmetric_name LIKE '${prefix}-%'`
    );
  }
});
