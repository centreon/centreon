import { expect, type Locator } from '@playwright/test';

import { BasePage } from './BasePage';

export type ChangeAction =
  | 'Added'
  | 'Changed'
  | 'Deleted'
  | 'Disabled'
  | 'Enabled';

/**
 * Page Object of the legacy configuration change log
 * (`Administration > Logs`), opened directly at `main.get.php?p=508` like
 * `LegacyListingPage`.
 */
export class ChangeLogsPage extends BasePage {
  /** Open the log filtered on the objects whose name contains `name`. */
  async open(name: string): Promise<void> {
    await this.goto(
      `/centreon/main.get.php?p=508&SearchB=1&searchU=&otype=0&searchO=${encodeURIComponent(name)}`
    );
    await expect(this.page.locator('input[name="searchO"]')).toHaveValue(
      name,
      { timeout: 30_000 }
    );
  }

  /** Log lines of an action (badge text), most recent first. */
  entries(action: ChangeAction): Locator {
    return this.page
      .locator('tr.list_one, tr.list_two')
      .filter({ has: this.page.locator('span.badge', { hasText: action }) })
      .describe(`"${action}" log lines`);
  }

  /**
   * Open the details of an object, which list every change made to it, and
   * return the `Before` and `After` values recorded for a field.
   */
  async fieldChanges(
    field: string
  ): Promise<Array<{ after: string; before: string }>> {
    await this.entries('Added').first().getByRole('link').first().click();
    const rows = this.page.locator('tr').filter({
      has: this.page.locator('td', {
        hasText: new RegExp(`^\\s*${field}\\s*$`)
      })
    });
    await expect(rows.first()).toBeVisible({ timeout: 30_000 });

    return rows.evaluateAll((elements) =>
      elements.map((element) => {
        const [, before, after] = Array.from(
          element.querySelectorAll('td')
        ).map((cell) => cell.textContent?.trim() ?? '');

        return { after: after ?? '', before: before ?? '' };
      })
    );
  }
}
