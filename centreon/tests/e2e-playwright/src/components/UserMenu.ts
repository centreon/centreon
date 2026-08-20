import { expect, type Locator, type Page } from '@playwright/test';

/** Labels come from www/front_src/src/Header/UserMenu/translatedLabels.ts. */
const labels = {
  logout: 'Logout',
  profile: 'Profile'
} as const;

/**
 * Component object for the header user menu. Owned by the pages that display
 * the header, so no page object duplicates its locators.
 */
export class UserMenu {
  readonly profileButton: Locator;
  readonly logoutButton: Locator;

  constructor(page: Page) {
    this.profileButton = page.getByLabel(labels.profile, { exact: true });
    this.logoutButton = page.getByText(labels.logout, { exact: true });
  }

  async open(): Promise<void> {
    await this.profileButton.click();
    await expect(this.logoutButton).toBeVisible();
  }

  async logout(): Promise<void> {
    await this.open();
    await this.logoutButton.click();
  }
}
