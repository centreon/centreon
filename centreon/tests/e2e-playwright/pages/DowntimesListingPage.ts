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
}
