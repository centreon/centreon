import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

interface CreateHostGroupOptions {
  name: string;
  alias?: string;
}

/**
 * Page Object for the modern React "Host groups" configuration page
 * (`/configuration/hosts/groups`), built on the shared `ConfigurationBase`
 * listing + modal.
 *
 * It wraps the creation modal (name/alias fields, submit) and the listing
 * (row located by name, inline delete with confirmation) driven by the Cypress
 * `HostGroups/01-host-group-configuration` feature. Selectors are taken from
 * the React source: the add button carries `data-testid="add-resource"`, form
 * fields expose `data-testid-suffix="test-<label>"`, the inline delete icon is
 * `Delete_<id>` (aria-label "Delete") and the confirmation button is
 * `data-testid="confirm"`.
 */
export class HostGroupsPage extends BasePage {
  readonly addButton: Locator;
  readonly nameInput: Locator;
  readonly aliasInput: Locator;
  readonly submitButton: Locator;
  readonly confirmButton: Locator;

  constructor(page: Page) {
    super(page);
    this.addButton = page
      .getByTestId('add-resource')
      .describe('Add host group button');
    this.nameInput = page
      .locator('[data-testid-suffix="test-Name"] input')
      .describe('Host group name field');
    this.aliasInput = page
      .locator('[data-testid-suffix="test-Alias"] input')
      .describe('Host group alias field');
    this.submitButton = page
      .locator('button[data-testid="submit"]')
      .describe('Save host group button');
    this.confirmButton = page
      .getByTestId('confirm')
      .describe('Confirm deletion button');
  }

  private waitForGroups(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response.url().includes('/configuration/hosts/groups') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the listing and wait for the host groups request to settle. */
  async open(): Promise<void> {
    const groups = this.waitForGroups();
    await this.goto('/centreon/configuration/hosts/groups');
    await groups;
  }

  /** A listing row located by the host group name. */
  row(name: string): Locator {
    return this.page
      .getByRole('row')
      .filter({ hasText: name })
      .describe(`host group row "${name}"`);
  }

  /** Open the creation modal, fill the required fields and submit. */
  async createHostGroup({
    name,
    alias = name
  }: CreateHostGroupOptions): Promise<void> {
    await test.step(`Create host group "${name}"`, async () => {
      await this.addButton.click();

      const dialog = this.page.getByTestId('Modal');
      await expect(dialog).toBeVisible();

      await this.nameInput.fill(name);
      await this.aliasInput.fill(alias);

      const groups = this.waitForGroups();
      await this.submitButton.click();
      await groups;
    });
  }

  /** Delete a host group from its row, confirming (or cancelling) the dialog. */
  async deleteHostGroup(name: string, { confirm = true } = {}): Promise<void> {
    await test.step(`Delete host group "${name}"`, async () => {
      await this.row(name).getByLabel('Delete', { exact: true }).click();

      if (!confirm) {
        await this.page.getByTestId('cancel').click();
        return;
      }

      const groups = this.waitForGroups();
      await this.confirmButton.click();
      await groups;
    });
  }

  /** Snackbar shown after an action (e.g. "Host group created"). */
  snackbar(message: string): Locator {
    return this.page.getByText(message);
  }
}
