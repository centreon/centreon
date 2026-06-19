import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the React poller "quick export" flow exposed in the top
 * navigation header.
 *
 * This mirrors the Cypress `Poller-configuration` step that opens the header
 * "Pollers" menu and triggers the one-click "Export configuration" /
 * "Export & reload" action (`@TEST_MON-22138`). Unlike the legacy
 * `main.php?p=60902` generate page, this whole flow is a modern React surface
 * (the `Header/Poller` submenu + an MUI confirm dialog + a snackbar), so it is
 * driven through role/test-id locators with no iframe.
 *
 * Source references:
 *  - `www/front_src/src/Header/Poller/index.tsx` (header Pollers button,
 *    `title={data.buttonLabel}` => accessible name "Pollers")
 *  - `www/front_src/src/Header/Poller/PollerSubMenu/ExportConfiguration/index.tsx`
 *    (`data-testid="Export configuration"`, confirm label "Export & reload",
 *    success snackbar "Configuration exported and reloaded")
 */
export class PollerConfigurationPage extends BasePage {
  readonly pollerMenuButton: Locator;
  readonly pollerSubMenu: Locator;
  readonly exportConfigurationButton: Locator;
  readonly exportAndReloadButton: Locator;

  constructor(page: Page) {
    super(page);
    // The header Pollers counter renders an MUI button whose accessible name is
    // the `buttonLabel` ("Pollers"). The Cypress flow selects it with
    // `cy.get('header').getByLabel({ label: 'Pollers', tag: 'button' })`.
    this.pollerMenuButton = page
      .locator('header')
      .getByRole('button', { name: 'Pollers' })
      .describe('Header Pollers menu button');
    this.pollerSubMenu = page
      .getByTestId('poller-menu')
      .describe('Pollers submenu');
    // `data-testid` equals the (English) label "Export configuration".
    this.exportConfigurationButton = page
      .getByTestId('Export configuration')
      .describe('Export configuration button');
    this.exportAndReloadButton = page
      .getByRole('button', { name: 'Export & reload' })
      .describe('Export & reload confirm button');
  }

  /**
   * Open the authenticated shell (the default monitoring landing page) and wait
   * for the header Pollers button. The header — and thus the Pollers menu — is
   * present on every authenticated page, so any route works; we use the default
   * landing page to keep the test fast.
   */
  async open(): Promise<void> {
    await this.goto('/centreon/monitoring/resources');
    await expect(this.pollerMenuButton).toBeVisible({ timeout: 30_000 });
  }

  /** Open the header Pollers submenu. */
  async openPollerMenu(): Promise<void> {
    await test.step('Open the Pollers header menu', async () => {
      await this.pollerMenuButton.click();
      await expect(this.exportConfigurationButton).toBeVisible({
        timeout: 15_000
      });
    });
  }

  /**
   * Trigger the one-click export & reload on all pollers. The caller asserts
   * the success snackbar, which is the deterministic completion signal.
   */
  async exportAndReloadAllPollers(): Promise<void> {
    await test.step('Export and reload the configuration on all pollers', async () => {
      await this.exportConfigurationButton.click();
      await this.exportAndReloadButton.click();
    });
  }

  /** Snackbar shown after an action (e.g. the export confirmation). */
  snackbar(message: string | RegExp): Locator {
    return this.page.getByText(message).describe(`snackbar "${message}"`);
  }
}
