import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { test } from '../../fixtures/test';
import {
  deleteTimePeriodActions,
  holidaysTimePeriod,
  rangeExclusionTimePeriod
} from '../../fixtures/time-period';
import { ensureStack } from '../../helpers/docker';
import { TimePeriodPage } from '../../pages/TimePeriodPage';

/**
 * Migration of the Cypress `Time-Period` feature, covering the happy-path
 * creation scenarios:
 *  - a time period excluding separated holiday dates (MON-162178)
 *  - a time period excluding a range of dates (MON-162179)
 *
 * The duplicate (MON-162180) and delete (MON-162181) scenarios are left out of
 * this slice: they drive the legacy "More actions..." toolbar select via JS
 * `onchange` injection, which is brittle to reproduce reliably in Playwright.
 *
 * The Time periods page is a legacy PHP page rendered in the React shell's
 * `#main-content` iframe, driven through a Playwright frame locator. Created
 * periods are removed best-effort through CLAPI (`TP DEL`) so reruns stay
 * idempotent even if a save froze the form.
 */
// DRAFT (workflow): ported from Cypress, not yet validated live — un-skip and fix selectors to finish.
test.describe.skip('Time period configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test('creates a time period excluding separated holiday dates', async ({
    page,
    adminApi
  }) => {
    const seed = holidaysTimePeriod('pw-time-period-holidays');

    try {
      const timePeriod = new TimePeriodPage(page);

      await timePeriod.open();
      await timePeriod.startCreation();
      await timePeriod.fillForm(seed);
      await timePeriod.submit();

      await expect(timePeriod.row(seed.name)).toBeVisible();
    } finally {
      await adminApi
        .provision(deleteTimePeriodActions(seed.name))
        .catch(() => undefined);
    }
  });

  test('creates a time period excluding a range of dates', async ({
    page,
    adminApi
  }) => {
    const seed = rangeExclusionTimePeriod('pw-time-period-range');

    try {
      const timePeriod = new TimePeriodPage(page);

      await timePeriod.open();
      await timePeriod.startCreation();
      await timePeriod.fillForm(seed);
      await timePeriod.submit();

      await expect(timePeriod.row(seed.name)).toBeVisible();
    } finally {
      await adminApi
        .provision(deleteTimePeriodActions(seed.name))
        .catch(() => undefined);
    }
  });
});
