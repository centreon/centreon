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
  readonly menu: Locator;
  readonly connectedUser: Locator;
  readonly logoutButton: Locator;

  constructor(page: Page) {
    this.profileButton = page
      .getByRole('banner')
      .getByRole('button', { name: labels.profile });
    this.menu = page.getByRole('tooltip').filter({
      has: page.getByRole('button', { name: labels.logout })
    });
    // The first entry of the menu is the alias of the connected user.
    this.connectedUser = this.menu.getByRole('listitem').first();
    this.logoutButton = this.menu.getByRole('button', { name: labels.logout });
  }

  async open(): Promise<void> {
    await this.profileButton.click();
    await expect(this.menu).toBeVisible();
  }

  async logout(): Promise<void> {
    await this.open();
    await this.logoutButton.click();
  }

  async expectConnectedAs(alias: string): Promise<void> {
    await this.open();
    await expect(this.connectedUser).toHaveText(alias);
  }
}
