import { expect, type Locator, type Page } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the dashboard deletion confirmation dialog.
 */
export class DeleteDashboardDialog extends BasePage {
  readonly dialog: Locator;
  readonly confirmButton: Locator;
  readonly cancelButton: Locator;

  constructor(page: Page) {
    super(page);
    this.dialog = page.getByRole('dialog');
    this.confirmButton = this.dialog.locator('button[aria-label="Delete"]');
    this.cancelButton = this.dialog.locator('button[aria-label="Cancel"]');
  }

  /** Assert the confirmation dialog warns about the given dashboard. */
  async expectVisibleFor(name: string): Promise<void> {
    await expect(this.cancelButton).toBeVisible();
    await expect(
      this.dialog.getByText(
        `The ${name} dashboard will be permanently deleted.`
      )
    ).toBeVisible();
  }

  async confirm(): Promise<void> {
    await this.confirmButton.click();
  }

  async cancel(): Promise<void> {
    await this.cancelButton.click();
  }
}
