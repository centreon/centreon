import { expect, type Locator, type Page } from '@playwright/test';

import { UserMenu } from '../components/UserMenu';
import { BasePage } from './BasePage';

export class ResourcesStatusPage extends BasePage {
  protected readonly path = '/monitoring/resources';

  readonly userMenu: UserMenu;
  readonly title: Locator;

  constructor(page: Page) {
    super(page);

    this.userMenu = new UserMenu(page);
    this.title = page.getByText('Resources Status', { exact: true });
  }

  async expectToBeLoaded(): Promise<void> {
    await this.expectToBeDisplayed();
    await expect(this.title).toBeVisible();
  }
}
