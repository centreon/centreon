import { expect, type Locator, type Page } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for a single dashboard detail page (`/home/dashboards/library/:id`).
 */
export class DashboardDetailPage extends BasePage {
  readonly title: Locator;
  readonly description: Locator;
  readonly editButton: Locator;
  readonly addWidgetButton: Locator;
  readonly quickAccessButton: Locator;

  constructor(page: Page) {
    super(page);
    this.title = page.locator('[aria-label="page header title"]');
    this.description = page.locator('[aria-label="page header description"]');
    this.editButton = page.locator('[data-testid="edit_dashboard"]');
    this.addWidgetButton = page.getByRole('button', { name: 'Add a widget' });
    this.quickAccessButton = page.locator('[data-testid="quickaccess"]');
  }

  /** Switch the dashboard to edit mode and wait for the editor to be ready. */
  async enterEditMode(): Promise<void> {
    await this.editButton.click();
    await this.expectInEditMode();
    await expect(this.addWidgetButton).toBeVisible();
  }

  /** From the edit view, create a brand-new dashboard via the quick-access menu. */
  async openQuickAccessCreate(): Promise<void> {
    await this.quickAccessButton.click();
    await this.page.getByText('Create a dashboard').click();
  }

  /** Assert the URL points at a dashboard detail page (numeric id). */
  async expectOnDetailPage(): Promise<void> {
    await expect(this.page).toHaveURL(/\/home\/dashboards\/library\/\d+/);
  }

  /** Assert the dashboard opened in edit mode (`?edit=true`). */
  async expectInEditMode(): Promise<void> {
    await expect(this.page).toHaveURL(/edit=true/);
  }

  async expectTitle(name: string): Promise<void> {
    // In edit mode the quick-access panel renders an extra page header, so the
    // title locator can match several nodes; assert the one carrying the name.
    await expect(this.title.filter({ hasText: name })).toBeVisible();
  }

  async expectDescription(description: string): Promise<void> {
    await expect(
      this.description.filter({ hasText: description })
    ).toBeVisible();
  }
}
