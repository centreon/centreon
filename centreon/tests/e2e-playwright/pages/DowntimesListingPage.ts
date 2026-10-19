import { expect, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object of the legacy downtimes listing (`Monitoring > Downtimes`),
 * opened at `main.get.php?p=21001` like `LegacyListingPage`.
 */
export class DowntimesListingPage extends BasePage {
  async open(): Promise<void> {
    await this.goto('/centreon/main.get.php?p=21001');
    await expect(this.page.locator('table.ListTable').first()).toBeVisible({
      timeout: 30_000
    });
  }

  /** Rows of the downtimes of a service. */
  rows(service: string) {
    return this.page
      .locator('table.ListTable tr')
      .filter({ hasText: service })
      .describe(`downtimes of "${service}"`);
  }

  /** Tick the downtimes of the services and cancel them. */
  async cancel(services: Array<string>): Promise<void> {
    await test.step(`Cancel the downtimes of ${services.join(', ')}`, async () => {
      // Wait for the engine to start the downtimes (the broker fills Started):
      // a downtime cancelled before it starts still starts afterwards.
      await expect(async () => {
        await this.open();
        for (const service of services) {
          await expect(
            this.rows(service).first().locator('td').nth(-2)
          ).toHaveText('Yes', { timeout: 1_000 });
        }
      }).toPass({ intervals: [2_000], timeout: 90_000 });
      for (const service of services) {
        await this.rows(service)
          .locator('input[type="checkbox"]')
          .first()
          .check({ force: true });
      }
      this.page.once('dialog', (dialog) => void dialog.accept());
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page.locator('input[name="submit2"]').first().click()
      ]);
    });
  }

  /**
   * Schedule a downtime on a service from the legacy form, with the dates
   * (MM/DD/YYYY) and times (HH:mm) read in the timezone of the user.
   */
  async schedule({
    end,
    hostId,
    serviceId,
    start
  }: {
    end: { date: string; time: string };
    hostId: number;
    serviceId: number;
    start: { date: string; time: string };
  }): Promise<void> {
    await test.step(`Schedule a downtime from ${start.date} ${start.time} to ${end.date} ${end.time}`, async () => {
      await this.goto(
        `/centreon/main.get.php?p=21001&o=a&host_id=${hostId}&service_id=${serviceId}`
      );
      // The datepicker alert()s on DST wall-clock times it finds inconsistent.
      const accept = (dialog: { accept: () => Promise<void> }) =>
        void dialog.accept();
      this.page.on('dialog', accept);
      try {
        for (const [field, value] of [
          ['start', start.date],
          ['start_time', start.time],
          ['end', end.date],
          ['end_time', end.time]
        ]) {
          // The datepicker copies the value to its hidden field on key events.
          const input = this.page.locator(`input[name="${field}"]`);
          await input.fill('');
          await input.pressSequentially(value);
        }
        // The datepicker can recompute the end time from the duration.
        await expect(this.page.locator('input[name="end_time"]')).toHaveValue(
          end.time
        );
        await Promise.all([
          this.page.waitForEvent('load'),
          this.page.locator('input[name="submitA"]').first().click()
        ]);
      } finally {
        this.page.off('dialog', accept);
      }
    });
  }
}
