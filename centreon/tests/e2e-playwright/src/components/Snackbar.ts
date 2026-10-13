import { expect, type Locator, type Page } from '@playwright/test';

/** Component object for the notistack snackbars (success and error alerts). */
export class Snackbar {
  readonly alerts: Locator;

  constructor(page: Page) {
    // includeHidden: an open MUI modal sets aria-hidden on the snackbar container.
    this.alerts = page.getByRole('alert', { includeHidden: true });
  }

  async expectMessage(message: string | RegExp): Promise<void> {
    // Repeated actions stack identical alerts.
    await expect(
      this.alerts.filter({ hasText: message }).first()
    ).toBeVisible();
  }
}
