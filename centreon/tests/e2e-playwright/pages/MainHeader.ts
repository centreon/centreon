import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the top navigation header of the authenticated application.
 *
 * It only exposes what the authentication tests need: a way to assert the user
 * is logged in, and the profile menu used to log out.
 */
export class MainHeader extends BasePage {
  readonly profileButton: Locator;
  readonly logoutMenuItem: Locator;

  constructor(page: Page) {
    super(page);
    // `data-cy="userIcon"` is language-independent, unlike the translated
    // `aria-label`. The logout entry only exposes its (translated) text, so it
    // relies on the stack running in en_US — see README.
    this.profileButton = page
      .locator('[data-cy="userIcon"]')
      .describe('Profile menu button');
    this.logoutMenuItem = page
      .getByText(/^Logout$/)
      .describe('Logout menu item');
  }

  /** Assert the authenticated shell is loaded (profile menu is present). */
  async expectLoaded(): Promise<void> {
    await expect(this.profileButton).toBeVisible();
  }

  /** Open the profile menu and trigger the logout action. */
  async logout(): Promise<void> {
    await test.step('Log out', async () => {
      await this.profileButton.click();
      await this.logoutMenuItem.click();
    });
  }
}
