import { expect, type Locator, type Page } from '@playwright/test';

/** Component object for the `Dialog/Confirm` of @centreon/ui. */
export class ConfirmationDialog {
  readonly root: Locator;
  readonly confirmButton: Locator;
  readonly cancelButton: Locator;

  constructor(page: Page) {
    this.root = page.getByRole('dialog');
    // The buttons only carry test ids, their label depends on the caller.
    this.confirmButton = this.root.getByTestId('confirm');
    this.cancelButton = this.root.getByTestId('cancel');
  }

  async confirm(): Promise<void> {
    await this.confirmButton.click();
  }

  async cancel(): Promise<void> {
    await this.cancelButton.click();
  }

  async expectToBeClosed(): Promise<void> {
    await expect(this.root).toBeHidden();
  }
}
