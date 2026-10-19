import { expect, type Locator, type Page } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Base Page Object for the dashboard properties dialog (shared by the creation
 * and the update flows). The name/description fields are identical; only the
 * confirmation control differs, so subclasses provide it.
 */
abstract class DashboardFormDialog extends BasePage {
  readonly dialog: Locator;
  readonly nameInput: Locator;
  readonly descriptionTextarea: Locator;
  abstract readonly confirmButton: Locator;
  abstract readonly cancelButton: Locator;

  protected constructor(page: Page) {
    super(page);
    this.dialog = page.getByRole('dialog');
    this.nameInput = this.dialog.locator('input[aria-label="Name"]');
    this.descriptionTextarea = this.dialog.locator(
      'textarea[aria-label="Description"]'
    );
  }

  async expectVisible(title: string): Promise<void> {
    await expect(this.dialog.getByText(title)).toBeVisible();
  }

  async setName(name: string): Promise<void> {
    await this.nameInput.fill(name);
  }

  async setDescription(description: string): Promise<void> {
    await this.descriptionTextarea.fill(description);
  }

  async clearName(): Promise<void> {
    await this.nameInput.clear();
  }

  async clearDescription(): Promise<void> {
    await this.descriptionTextarea.clear();
  }

  async confirm(): Promise<void> {
    await this.confirmButton.click();
  }

  async cancel(): Promise<void> {
    await this.cancelButton.click();
  }
}

/** Creation dialog ("Create dashboard") — submit/cancel via data-testid. */
export class DashboardCreationDialog extends DashboardFormDialog {
  readonly confirmButton: Locator;
  readonly cancelButton: Locator;

  constructor(page: Page) {
    super(page);
    this.confirmButton = this.dialog.locator('[data-testid="submit"]');
    this.cancelButton = this.dialog.locator('[data-testid="cancel"]');
  }
}

/** Update dialog ("Update dashboard") — confirm/cancel via aria-label. */
export class DashboardPropertiesDialog extends DashboardFormDialog {
  readonly confirmButton: Locator;
  readonly cancelButton: Locator;

  constructor(page: Page) {
    super(page);
    this.confirmButton = this.dialog.locator('button[aria-label="Update"]');
    this.cancelButton = this.dialog.locator('button[aria-label="Cancel"]');
  }
}
