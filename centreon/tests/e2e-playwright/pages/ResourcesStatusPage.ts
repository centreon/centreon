import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the monitoring "Resources status" listing
 * (`/monitoring/resources`).
 *
 * It mirrors the Cypress `Resources-status/01-listing` selectors — the search
 * field (placeholder "Search"), the saved-filter select (`selectedFilter`) and
 * the listing rows located by resource name — but exposes them as intentful
 * methods so specs read as user actions, not selectors.
 */
export class ResourcesStatusPage extends BasePage {
  readonly searchInput: Locator;
  readonly selectedFilter: Locator;

  constructor(page: Page) {
    super(page);
    this.searchInput = page
      .getByPlaceholder('Search')
      .describe('Resources search field');
    this.selectedFilter = page
      .locator('[data-testid="selectedFilter"]')
      .describe('Saved filter select');
  }

  private waitForResources(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response.url().includes('/monitoring/resources') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the listing and wait for the first resources request to settle. */
  async open(): Promise<void> {
    const resources = this.waitForResources();
    await this.goto('/monitoring/resources');
    await resources;
  }

  /** Type a search expression, submit it and wait for the filtered listing. */
  async search(expression: string): Promise<void> {
    await test.step(`Search "${expression}"`, async () => {
      const resources = this.waitForResources();
      await this.searchInput.fill(expression);
      await this.searchInput.press('Enter');
      await resources;
    });
  }

  /** A listing entry located by its resource (service) name. */
  private row(name: string): Locator {
    return this.page.getByText(name, { exact: true });
  }

  async expectResourceVisible(name: string): Promise<void> {
    await expect(this.row(name)).toBeVisible();
  }

  async expectResourceHidden(name: string): Promise<void> {
    await expect(this.row(name)).toHaveCount(0);
  }

  async expectSelectedFilter(name: string): Promise<void> {
    await expect(this.selectedFilter).toContainText(name);
  }
}
