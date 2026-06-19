import {
  expect,
  type FrameLocator,
  type Locator,
  type Page,
  test
} from '@playwright/test';

import { BasePage } from './BasePage';

export interface HostFormValues {
  name: string;
  alias?: string;
  address?: string;
}

/**
 * Page Object for the legacy "Configuration > Hosts" listing and form
 * (`main.php?p=60101`).
 *
 * Like the Custom views page, this is a legacy PHP page rendered inside the
 * React shell's `#main-content` iframe, so every locator is anchored on a
 * Playwright frame locator (the Cypress `cy.getIframeBody()` /
 * `cy.waitForElementInIframe('#main-content', ...)` equivalent). The selectors
 * are ported verbatim from the Cypress `Hosts/01-host-configuration` step
 * definitions (`input[name="host_name"]`, the green submit button, the listing
 * search field, ...).
 *
 * Saving the form posts back to the iframe and reloads the listing; we wait for
 * the listing search field to reappear rather than for a SPA transition.
 */
export class HostsPage extends BasePage {
  private readonly frame: FrameLocator;
  readonly addLink: Locator;
  readonly searchInput: Locator;
  readonly nameInput: Locator;
  readonly aliasInput: Locator;
  readonly addressInput: Locator;
  readonly submitButton: Locator;
  readonly moreActionsSelect: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.addLink = this.frame
      .getByRole('link', { name: 'Add' })
      .first()
      .describe('Add host link');
    this.searchInput = this.frame
      .locator('input[name="searchH"]')
      .describe('Hosts listing search field');
    this.nameInput = this.frame
      .locator('input[name="host_name"]')
      .describe('Host name field');
    this.aliasInput = this.frame
      .locator('input[name="host_alias"]')
      .describe('Host alias field');
    this.addressInput = this.frame
      .locator('input[name="host_address"]')
      .describe('Host address field');
    this.submitButton = this.frame
      .locator('input.btc.bt_success[name^="submit"]')
      .first()
      .describe('Save host button');
    // The listing's "More actions" <select> (Duplicate / Delete / Enable ...).
    // It is the 5th select on the page in the legacy layout (Cypress used
    // `.find('select').eq(4)`).
    this.moreActionsSelect = this.frame
      .locator('select')
      .nth(4)
      .describe('More actions select');
  }

  /** The selection checkbox of a listing row, located by its host name. */
  rowCheckbox(name: string): Locator {
    return this.frame
      .locator('tr')
      .filter({ hasText: name })
      .locator('input[type="checkbox"]')
      .first()
      .describe(`row checkbox for host "${name}"`);
  }

  /** A listing row link located by the host name. */
  hostLink(name: string): Locator {
    return this.frame
      .getByRole('link', { exact: true, name })
      .first()
      .describe(`host listing link "${name}"`);
  }

  /** Open the hosts listing and wait for the search field to be ready. */
  async open(): Promise<void> {
    await this.goto('/centreon/main.php?p=60101');
    await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
  }

  /** Open the creation form from the listing. */
  async openCreateForm(): Promise<void> {
    await test.step('Open the host creation form', async () => {
      await this.addLink.click();
      await expect(this.nameInput).toBeVisible({ timeout: 30_000 });
    });
  }

  /** Open the edit form for an existing host by clicking its listing link. */
  async openEditForm(name: string): Promise<void> {
    await test.step(`Open the edit form for host "${name}"`, async () => {
      await this.hostLink(name).click();
      await expect(this.nameInput).toBeVisible({ timeout: 30_000 });
    });
  }

  /** Fill the host form fields that are provided. */
  private async fillForm({
    name,
    alias,
    address
  }: HostFormValues): Promise<void> {
    await this.nameInput.fill(name);
    if (alias !== undefined) {
      await this.aliasInput.fill(alias);
    }
    if (address !== undefined) {
      await this.addressInput.fill(address);
    }
  }

  /**
   * Create a host through the form and wait for the listing to show it again.
   */
  async createHost(values: HostFormValues): Promise<void> {
    await test.step(`Create host "${values.name}"`, async () => {
      await this.openCreateForm();
      await this.fillForm({
        address: '127.0.0.1',
        alias: values.name,
        ...values
      });
      await this.submitButton.click();
      await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
      await expect(this.hostLink(values.name)).toBeVisible({
        timeout: 30_000
      });
    });
  }

  /**
   * Rename an existing host (name + alias) and save. Returns once the listing
   * shows the new name.
   */
  async renameHost(currentName: string, newName: string): Promise<void> {
    await test.step(`Rename host "${currentName}" to "${newName}"`, async () => {
      await this.openEditForm(currentName);
      await this.nameInput.fill(newName);
      await this.aliasInput.fill(newName);
      await this.submitButton.click();
      await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
      await expect(this.hostLink(newName)).toBeVisible({ timeout: 30_000 });
    });
  }

  /**
   * Delete a host from the listing: tick its row checkbox, then pick "Delete"
   * in the "More actions" select. The native `change` event re-submits the
   * legacy form (mirroring the Cypress flow). Resolves once the listing is back
   * without the host.
   */
  async deleteHost(name: string): Promise<void> {
    await test.step(`Delete host "${name}"`, async () => {
      // The real checkbox input is hidden behind a styled md-checkbox wrapper,
      // so force the check past the visibility actionability gate.
      await this.rowCheckbox(name).check({ force: true });
      // The legacy "More actions" select does not submit on its own; wire its
      // onchange to submit the form, exactly like the Cypress flow does.
      await this.moreActionsSelect.evaluate((select) => {
        (select as HTMLSelectElement).setAttribute(
          'onchange',
          "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
        );
      });
      await this.moreActionsSelect.selectOption({ label: 'Delete' });
      await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
      await expect(this.hostLink(name)).toHaveCount(0);
    });
  }
}
