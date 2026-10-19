import { expect, type Locator } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object of the legacy Event Logs page (`Monitoring > Event Logs`),
 * opened at `main.get.php?p=20301` like `LegacyListingPage`.
 */
export class EventLogsPage extends BasePage {
  async open(): Promise<void> {
    await this.goto('/centreon/main.get.php?p=20301');
    await expect(this.page.locator('select#host_filter')).toBeAttached({
      timeout: 30_000
    });
  }

  /** Hosts offered by the host filter, i.e. the hosts with events. */
  async filterHosts(): Promise<Array<string>> {
    await this.page
      .locator('select#host_filter + span.select2-container')
      .click();
    const options: Locator = this.page.locator(
      'ul.select2-results__options li.select2-results__option'
    );
    // The count is rendered once the results are loaded.
    await expect(
      this.page.locator('.select2-results-header__nb-elements-value')
    ).toHaveText(/^\d+$/);
    const names = (await options.allInnerTexts()).map((name) => name.trim());
    await this.page.keyboard.press('Escape');

    return names.filter((name) => name && !/no result/i.test(name));
  }
}
