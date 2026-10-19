import { expect, type Locator, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object of the legacy "My Account" form (`Administration > Parameters
 * > My Account`), opened at `main.get.php?p=50104&o=c` like
 * `LegacyListingPage`.
 */
export class MyAccountPage extends BasePage {
  /** The message the form shows after a refused change. */
  get formError(): Locator {
    return this.page.locator('#Form');
  }

  async open(): Promise<void> {
    await this.goto('/centreon/main.get.php?p=50104&o=c');
    await expect(this.page.locator('#current_password')).toBeVisible({
      timeout: 30_000
    });
  }

  /** Generate an autologin key and save it. */
  async generateAutologinKey(): Promise<void> {
    await test.step('Generate an autologin key', async () => {
      await this.open();
      const key = this.page.locator('#aKey');
      await this.page.locator('#generateAutologinKeyButton').click();
      await expect(key).not.toHaveValue('');
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page.locator('#validForm input[name="submitC"]').click()
      ]);
    });
  }

  /** Submit a password change. */
  async changePassword(current: string, next: string): Promise<void> {
    await test.step('Change the password', async () => {
      await this.open();
      await this.page.locator('#current_password').fill(current);
      await this.page.locator('#passwd1').fill(next);
      await this.page.locator('#passwd2').fill(next);
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page.locator('#validForm input[name="submitC"]').click()
      ]);
    });
  }
}
