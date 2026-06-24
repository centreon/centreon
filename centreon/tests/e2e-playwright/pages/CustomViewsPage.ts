import {
  expect,
  type FrameLocator,
  type Locator,
  type Page,
  test
} from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the legacy "Custom views" home page (`main.php?p=103`).
 *
 * Like the proxy parameters page, this is a legacy PHP page rendered inside the
 * React shell's `#main-content` iframe, so it is driven through a Playwright
 * frame locator (the Cypress `cy.getIframeBody()` equivalent).
 */
export class CustomViewsPage extends BasePage {
  private readonly frame: FrameLocator;
  readonly editModeToggle: Locator;
  readonly addViewButton: Locator;
  readonly nameInput: Locator;
  readonly submitButton: Locator;
  readonly deleteViewButton: Locator;
  readonly confirmDeleteButton: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.editModeToggle = this.frame
      .locator('a[title="Show/Hide edit mode"]')
      .describe('Show/Hide edit mode toggle');
    this.addViewButton = this.frame
      .getByRole('button', { name: 'Add view' })
      .describe('Add view button');
    this.nameInput = this.frame.locator('input[name="name"]').first();
    this.submitButton = this.frame.locator('input[name="submit"]').first();
    this.deleteViewButton = this.frame.locator('button.deleteView');
    this.confirmDeleteButton = this.frame.locator(
      '#deleteViewConfirm .bt_danger'
    );
  }

  /** A view tab, located by its name (rendered as an `<a>` link). */
  viewTab(name: string): Locator {
    return this.frame
      .locator('a')
      .filter({ hasText: name })
      .describe(`custom view tab "${name}"`);
  }

  /** Open the custom views page and wait for the edit-mode toggle. */
  async open(): Promise<void> {
    await this.goto('/centreon/main.php?p=103');
    await expect(this.editModeToggle).toBeVisible({ timeout: 30_000 });
  }

  async enterEditMode(): Promise<void> {
    await test.step('Enter edit mode', async () => {
      await this.editModeToggle.click();
      await expect(this.addViewButton).toBeVisible({ timeout: 15_000 });
    });
  }

  /** Create a new (private) custom view and wait for its tab to appear. */
  async addView(name: string): Promise<void> {
    await test.step(`Add custom view "${name}"`, async () => {
      await this.addViewButton.click({ force: true });
      await this.nameInput.fill(name);
      await this.submitButton.click();
      await expect(this.viewTab(name)).toBeVisible({ timeout: 15_000 });
    });
  }

  /** Delete the currently selected view, confirming the popup. */
  async deleteCurrentView(): Promise<void> {
    await test.step('Delete the current view', async () => {
      await this.deleteViewButton.click({ force: true });
      await this.confirmDeleteButton.click();
    });
  }
}
