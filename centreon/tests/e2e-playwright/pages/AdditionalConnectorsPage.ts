import { expect, type Locator, type Page, test } from '@playwright/test';

import type { AdditionalConnectorSeed } from '../fixtures/additional-connectors';
import { BasePage } from './BasePage';

/**
 * Page Object for the Additional Connector Configuration (ACC) page
 * (`/configuration/additional-connector-configurations`).
 *
 * This is a modern React page (MUI form in a side panel + a listing), so it is
 * driven through role/label/test-id locators — no legacy `#main-content`
 * iframe. It wraps the creation/edit form (name, description, type, poller
 * autocomplete, and the vCenter/ESX parameter group) and the listing rows the
 * Cypress `Additional-connectors` feature drives.
 *
 * The vCenter parameter inputs keep their legacy element ids
 * (`#Usernamevalue`, `#Passwordvalue`, `#vCenternamevalue`, `#URLvalue`,
 * `#Portvalue`) from the Cypress step definitions.
 */
export class AdditionalConnectorsPage extends BasePage {
  readonly createButton: Locator;
  readonly addButton: Locator;
  readonly saveButton: Locator;
  readonly cancelButton: Locator;
  readonly nameInput: Locator;
  readonly descriptionInput: Locator;
  readonly pollerInput: Locator;
  readonly typeSelect: Locator;
  readonly usernameInput: Locator;
  readonly passwordInput: Locator;
  readonly vCenterNameInput: Locator;
  readonly urlInput: Locator;
  readonly portInput: Locator;
  readonly listing: Locator;

  constructor(page: Page) {
    super(page);

    // Empty-state CTA (no connector yet).
    this.createButton = page
      .getByLabel('create', { exact: true })
      .describe('Add additional configuration (empty state) button');
    // Header action once at least one connector exists (shared AddAction).
    this.addButton = page
      .getByTestId('add-resource')
      .describe('Add additional configuration button');
    this.saveButton = page
      .locator('button[data-testid="submit"]')
      .describe('Save connector button');
    this.cancelButton = page
      .getByLabel('Cancel', { exact: true })
      .describe('Cancel connector form button');

    this.nameInput = page
      .getByLabel('Name', { exact: true })
      .describe('Connector name input');
    this.descriptionInput = page
      .getByLabel('Description', { exact: true })
      .describe('Connector description input');
    this.pollerInput = page
      .getByLabel('Select poller(s)', { exact: true })
      .describe('Poller autocomplete input');
    this.typeSelect = page
      .locator('#mui-component-select-type')
      .describe('Connector type select');

    this.usernameInput = page
      .locator('#Usernamevalue')
      .describe('vCenter username input');
    this.passwordInput = page
      .locator('#Passwordvalue')
      .describe('vCenter password input');
    this.vCenterNameInput = page
      .locator('#vCenternamevalue')
      .describe('vCenter name input');
    this.urlInput = page.locator('#URLvalue').describe('vCenter URL input');
    this.portInput = page.locator('#Portvalue').describe('vCenter port input');

    this.listing = page
      .locator('[role="rowgroup"]')
      .describe('Additional connectors listing');
  }

  private waitForListing(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response
          .url()
          .includes('/configuration/additional-connector-configurations') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the ACC listing and wait for the connectors request to settle. */
  async open(): Promise<void> {
    const listing = this.waitForListing();
    await this.goto(
      '/centreon/configuration/additional-connector-configurations'
    );
    await listing;
  }

  /** A listing row located by the connector name. */
  row(name: string): Locator {
    return this.listing
      .getByText(name, { exact: true })
      .describe(`connector row "${name}"`);
  }

  /** Open the creation form, handling both the empty-state and header buttons. */
  async openCreationForm(): Promise<void> {
    await test.step('Open the creation form', async () => {
      // Empty-state CTA or header button, whichever the listing shows.
      await this.createButton.or(this.addButton).first().click();
      await expect(this.page.getByTestId('Modal')).toBeVisible();
    });
  }

  /** Pick an option (by visible text) from a just-opened autocomplete. */
  private async pickOption(text: string): Promise<void> {
    await this.page.getByRole('option', { exact: true, name: text }).click();
  }

  /** Fill the vCenter/ESX parameter group (first/only group). */
  private async fillParameters(
    connector: AdditionalConnectorSeed
  ): Promise<void> {
    await this.usernameInput.fill(connector.username);
    await this.passwordInput.fill(connector.password);
    await this.vCenterNameInput.fill(connector.vCenterName);
    await this.urlInput.fill(connector.url);
  }

  /** Fill the whole creation form with the mandatory information. */
  async fillForm(connector: AdditionalConnectorSeed): Promise<void> {
    await test.step(`Fill the connector form for "${connector.name}"`, async () => {
      await this.nameInput.fill(connector.name);
      await expect(this.typeSelect).toHaveText(connector.type);

      await this.pollerInput.click();
      await this.pickOption(connector.poller);

      await this.fillParameters(connector);
    });
  }

  /** Submit the form (Create/Update) and wait for the listing to refresh. */
  async save(): Promise<void> {
    await test.step('Save the connector', async () => {
      const listing = this.waitForListing();
      await this.saveButton.click();
      await listing;
    });
  }

  /** Create a connector end to end and assert it shows up in the listing. */
  async createConnector(connector: AdditionalConnectorSeed): Promise<void> {
    await this.openCreationForm();
    await this.fillForm(connector);
    await this.save();
    await expect(this.row(connector.name)).toBeVisible();
  }

  /** Open the edit form for an existing connector (row click). */
  async openConnector(name: string): Promise<void> {
    await test.step(`Open connector "${name}"`, async () => {
      await this.row(name).click();
      await expect(
        this.page.getByText('Modify an additional configuration')
      ).toBeVisible();
    });
  }

  /**
   * Replace the connector's password through the dedicated edit (pen) control.
   *
   * On the edit form the password field is disabled and masked; clicking the
   * edit icon enables an empty field, then the inner save commits it.
   */
  private async editPassword(value: string): Promise<void> {
    await this.page.getByTestId('button_edit').click();
    await expect(this.passwordInput).toBeEnabled();
    await this.passwordInput.fill(value);
    await this.page.getByTestId('button_save').click();
  }

  /** Update an existing connector's fields with new values. */
  async updateConnector(connector: AdditionalConnectorSeed): Promise<void> {
    await test.step(`Update the connector to "${connector.name}"`, async () => {
      await this.nameInput.fill(connector.name);

      await this.usernameInput.fill(connector.username);
      await this.editPassword(connector.password);
      await this.vCenterNameInput.fill(connector.vCenterName);
      await this.urlInput.fill(connector.url);
      await this.portInput.fill(connector.port);
    });
  }

  /**
   * Delete a connector from its row, then confirm (or cancel) the dialog.
   *
   * The "Delete" label is reused by both the row action and the confirmation
   * dialog button, so the first match is the row action and the second is the
   * dialog confirmation (mirroring the Cypress `.eq(0)` / `.eq(1)` usage).
   */
  async deleteConnector(name: string, { confirm = true } = {}): Promise<void> {
    await test.step(`Delete connector "${name}"`, async () => {
      await this.page.getByLabel('Delete', { exact: true }).first().click();
      if (confirm) {
        const listing = this.waitForListing();
        await this.page.getByLabel('Delete', { exact: true }).nth(1).click();
        await listing;
      } else {
        await this.cancelButton.click();
      }
    });
  }
}
