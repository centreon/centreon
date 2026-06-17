import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the dashboards library (overview) page.
 *
 * The listing renders one card per dashboard. Dashboards are addressed **by
 * name** (via `card(name)`) rather than by list position — more robust than the
 * index-based selection used by the Cypress suite.
 */
export class DashboardsListPage extends BasePage {
  readonly createButton: Locator;

  constructor(page: Page) {
    super(page);
    this.createButton = page
      .locator('[data-testid="create-dashboard"]')
      .describe('Create dashboard button');
  }

  /** Open the library and wait for the listing request to settle. */
  async open(): Promise<void> {
    const listing = this.page.waitForResponse(
      (response) =>
        response.url().includes('/configuration/dashboards') &&
        response.request().method() === 'GET'
    );
    await this.goto('/home/dashboards/library');
    await listing;
  }

  /** The card element for a given dashboard, located by its name. */
  card(name: string): Locator {
    return this.page
      .locator('[class*="dataTableItem"]')
      .filter({ hasText: name })
      .describe(`dashboard card "${name}"`);
  }

  async clickCreate(): Promise<void> {
    await test.step('Open the dashboard creation form', () =>
      this.createButton.click());
  }

  /** The card heading (dashboard name), used to disambiguate from descriptions. */
  private heading(name: string): Locator {
    return this.page.getByRole('heading', { exact: true, name });
  }

  /** Open a dashboard's detail page by clicking its name. */
  async openDashboard(name: string): Promise<void> {
    await test.step(`Open dashboard "${name}"`, () =>
      this.heading(name).click());
  }

  private async openMoreActions(name: string): Promise<void> {
    await this.card(name).getByRole('button', { name: 'More actions' }).click();
  }

  /** Open the "Edit properties" form for a dashboard via its actions menu. */
  async openProperties(name: string): Promise<void> {
    await test.step(`Open properties of "${name}"`, async () => {
      await this.openMoreActions(name);
      await this.page
        .getByRole('menuitem', { name: 'Edit properties' })
        .click();
    });
  }

  /** Open the delete confirmation for a dashboard via its actions menu. */
  async openDelete(name: string): Promise<void> {
    await test.step(`Delete "${name}"`, async () => {
      await this.openMoreActions(name);
      await this.page.getByRole('menuitem', { name: 'Delete' }).click();
    });
  }

  async expectVisible(name: string): Promise<void> {
    await expect(this.heading(name)).toBeVisible();
  }

  async expectNotVisible(name: string): Promise<void> {
    await expect(this.heading(name)).toHaveCount(0);
  }

  async expectEmptyState(): Promise<void> {
    await expect(this.createButton).toBeVisible();
  }
}
