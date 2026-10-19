import { expect, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object of the legacy "Centreon UI" parameters
 * (`Administration > Parameters > Centreon UI`), opened at
 * `main.get.php?p=50110&o=general` like `LegacyListingPage`.
 */
export class CentreonUiParametersPage extends BasePage {
  private get autologin() {
    return this.page.locator('#Form #enableAutoLogin');
  }

  async open(): Promise<void> {
    await this.goto('/centreon/main.get.php?p=50110&o=general');
    await expect(this.autologin).toBeAttached({ timeout: 30_000 });
  }

  async isAutologinEnabled(): Promise<boolean> {
    await this.open();

    return this.autologin.isChecked();
  }

  /** Allow or forbid the autologin links on the platform. */
  async setAutologin(enabled: boolean): Promise<void> {
    await test.step(`${enabled ? 'Enable' : 'Disable'} autologin`, async () => {
      await this.open();
      // The legacy checkbox is drawn over its input.
      await this.autologin.setChecked(enabled, { force: true });
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page.locator('#submitGeneralOptionsForm').click()
      ]);
    });
  }
}
