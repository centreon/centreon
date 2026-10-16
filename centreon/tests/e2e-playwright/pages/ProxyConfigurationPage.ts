import {
  type FrameLocator,
  type Locator,
  type Page,
  expect,
  test
} from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the legacy "Centreon UI" parameters page
 * (`main.php?p=50110&o=general`), which hosts the proxy configuration.
 *
 * This is a legacy PHP page rendered inside the React shell's `#main-content`
 * iframe, so every control is reached through a Playwright `frameLocator` — the
 * equivalent of the Cypress `cy.getIframeBody()` helper. It demonstrates that
 * legacy iframe pages are migratable to Playwright.
 *
 * Note: the "Test" button POSTs the **current** field values (no save needed).
 * Saving the form freezes it into a read-only view (and removes the Test
 * button), so the test deliberately never saves.
 */
export class ProxyConfigurationPage extends BasePage {
  private readonly frame: FrameLocator;
  readonly proxyUrlInput: Locator;
  readonly proxyPortInput: Locator;
  readonly testButton: Locator;
  readonly modifyButton: Locator;
  readonly errorMessage: Locator;
  readonly successMessage: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.proxyUrlInput = this.frame.locator('input[name="proxy_url"]');
    this.proxyPortInput = this.frame.locator('input[name="proxy_port"]');
    this.testButton = this.frame
      .locator('input[name="test_proxy"]')
      .describe('Test internet connection button');
    // Shown only when the form is frozen (after a previous save) — switches the
    // page back to edit mode.
    this.modifyButton = this.frame.locator('input[name="change"]');
    this.errorMessage = this.frame
      .locator('span.msg-field.error')
      .describe('Proxy test error message');
    this.successMessage = this.frame
      .locator('span.msg-field.success2')
      .describe('Proxy test success message');
  }

  /**
   * Open the legacy Centreon UI parameters page in edit mode. If the form comes
   * up frozen (read-only, e.g. after a previous save left a "Modify" button),
   * click "Modify" to get the editable form back.
   */
  async open(): Promise<void> {
    await this.goto('/centreon/main.php?p=50110&o=general');
    await this.frame.locator('form').first().waitFor({ timeout: 30_000 });

    if ((await this.testButton.count()) === 0) {
      await this.modifyButton.click();
    }
    await expect(this.testButton).toBeVisible({ timeout: 30_000 });
  }

  /** Type the proxy address/port (without saving — the Test button reads them). */
  async setProxy(url: string, port: string): Promise<void> {
    await test.step(`Enter proxy ${url}:${port}`, async () => {
      await this.proxyUrlInput.fill(url);
      await this.proxyPortInput.fill(port);
    });
  }

  /** Click "Test internet connection"; the result shows in a popin. */
  async testConnection(): Promise<void> {
    await test.step('Test the proxy configuration', () =>
      this.testButton.click());
  }
}
