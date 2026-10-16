import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

interface CreateRuleOptions {
  name: string;
  description?: string;
  /** Resource type label as shown in the "Select resource type" dropdown. */
  resourceType: string;
  /** Visible label of the resource to grant access to (e.g. a host name). */
  resource: string;
  /** Alias of the contact to grant the access to. */
  contact: string;
}

/**
 * Page Object for the modern React "Resource Access Management" page
 * (`/centreon/administration/resource-access/rules`).
 *
 * It wraps the listing (rows, add button, per-row delete with a confirmation
 * dialog, search field) and the creation modal (name/description, the
 * resource-type + resource dataset selectors, the contacts autocomplete and the
 * Save action) that the Cypress `Resources-Access-Management` feature drives.
 *
 * Selectors are taken straight from the React source:
 * - add button: `data-testid="createResourceAccessRule"`
 * - name field: label "Name" (also `data-testid="Name"`)
 * - resource type: SelectField with `aria-label="Select resource type"`
 * - resource value: autocomplete with `data-testid="Select resource"`
 * - contacts: autocomplete with `data-testid="Contacts"`
 * - save: `data-testid="submitForm"` (aria-label "Save")
 * - delete confirmation dialog (shared @centreon/ui ConfirmDialog):
 *   `data-testid="Confirm"` / `data-testid="Cancel"`
 */
export class ResourcesAccessManagementPage extends BasePage {
  readonly addButton: Locator;
  readonly nameInput: Locator;
  readonly descriptionInput: Locator;
  readonly resourceTypeSelect: Locator;
  readonly resourceValueAutocomplete: Locator;
  readonly contactsAutocomplete: Locator;
  readonly saveButton: Locator;
  readonly searchInput: Locator;
  readonly confirmDeleteButton: Locator;
  readonly cancelDeleteButton: Locator;

  constructor(page: Page) {
    super(page);
    this.addButton = page
      .getByTestId('createResourceAccessRule')
      .describe('Add resource access rule button');
    this.nameInput = page
      .getByLabel('Name', { exact: true })
      .describe('Rule name field');
    this.descriptionInput = page
      .getByLabel('Description', { exact: true })
      .describe('Rule description field');
    this.resourceTypeSelect = page
      .getByLabel('Select resource type')
      .first()
      .describe('Resource type select');
    this.resourceValueAutocomplete = page
      .getByTestId('Select resource')
      .first()
      .describe('Resource value autocomplete');
    this.contactsAutocomplete = page
      .getByTestId('Contacts')
      .describe('Contacts autocomplete');
    this.saveButton = page.getByTestId('submitForm').describe('Save button');
    this.searchInput = page
      .getByTestId('Search')
      .describe('Rules search field');
    this.confirmDeleteButton = page
      .getByTestId('Confirm')
      .describe('Confirm delete button');
    this.cancelDeleteButton = page
      .getByTestId('Cancel')
      .describe('Cancel delete button');
  }

  /** Wait for the rules listing GET request to settle. */
  private waitForRules(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response.url().includes('/administration/resource-access/rules') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the listing and wait for the rules request to settle. */
  async open(): Promise<void> {
    const rules = this.waitForRules();
    await this.goto('/centreon/administration/resource-access/rules');
    await rules;
    await expect(this.addButton).toBeVisible();
  }

  /** Pick an option (by visible text) from a just-opened autocomplete/select. */
  private async pickOption(text: string): Promise<void> {
    await this.page.getByRole('option', { exact: true, name: text }).click();
  }

  /**
   * Fill and submit the creation modal for a resource access rule.
   *
   * Mirrors the Cypress `fillFormRequiredFields` happy path: a single dataset
   * with one resource type + resource, and one contact. Waits for the success
   * snackbar so the caller knows the rule was persisted.
   */
  async createRule({
    name,
    description = '',
    resourceType,
    resource,
    contact
  }: CreateRuleOptions): Promise<void> {
    await test.step(`Create resource access rule "${name}"`, async () => {
      await this.addButton.click();

      const dialog = this.page.getByRole('dialog');
      await expect(dialog).toBeVisible();

      await this.nameInput.fill(name);
      if (description) {
        await this.descriptionInput.fill(description);
      }

      // Dataset: choose the resource type then the resource itself.
      await this.resourceTypeSelect.click();
      await this.pickOption(resourceType);
      await this.resourceValueAutocomplete.click();
      await this.pickOption(resource);

      // Contacts: open the autocomplete, pick the contact, then close the
      // dropdown with Escape (clicking the field again toggles the picker and
      // can leave the form invalid).
      await this.contactsAutocomplete.click();
      await this.pickOption(contact);
      await this.page.keyboard.press('Escape');

      await expect(this.saveButton).toBeEnabled();
      await this.saveButton.click();

      await expect(
        this.snackbar('The resource access rule was successfully created')
      ).toBeVisible();
    });
  }

  /** A listing row located by the rule name. */
  row(name: string): Locator {
    return this.page
      .getByRole('row')
      .filter({ hasText: name })
      .describe(`rule row "${name}"`);
  }

  /** Delete a rule from its row, confirming (or cancelling) the dialog. */
  async deleteRule(name: string, { confirm = true } = {}): Promise<void> {
    await test.step(`Delete rule "${name}"`, async () => {
      await this.row(name).getByLabel('Delete rule', { exact: true }).click();
      const dialog = this.page.getByRole('dialog');
      await expect(dialog).toBeVisible();
      if (confirm) {
        const rules = this.waitForRules();
        await this.confirmDeleteButton.click();
        await rules;
      } else {
        await this.cancelDeleteButton.click();
      }
    });
  }

  /** Type into the (debounced) search field and wait for the listing refresh. */
  async search(query: string): Promise<void> {
    await test.step(`Search rules by "${query}"`, async () => {
      const rules = this.waitForRules();
      await this.searchInput.fill(query);
      await rules;
    });
  }

  /** Snackbar shown after an action (creation/deletion confirmation). */
  snackbar(message: string): Locator {
    return this.page.getByText(message).describe(`snackbar "${message}"`);
  }
}
