import {
  expect,
  type FrameLocator,
  type Locator,
  type Page,
  test
} from '@playwright/test';

import { BasePage } from './BasePage';

/** Properties needed to fill the legacy contact creation form. */
export interface ContactInput {
  alias: string;
  name: string;
  email: string;
}

/**
 * Page Object for the legacy "Contacts / Users" configuration page
 * (`main.php?p=60301`).
 *
 * Like the custom-views page, this is a legacy PHP page rendered inside the
 * React shell's `#main-content` iframe, so it is driven through a Playwright
 * frame locator (the Cypress `cy.getIframeBody()` equivalent). It mirrors the
 * Cypress `Contacts` feature: open the listing, add a contact through the
 * General Information form, then delete it via the listing's "More actions"
 * dropdown.
 */
export class ContactsPage extends BasePage {
  private readonly frame: FrameLocator;
  readonly addButton: Locator;
  readonly aliasInput: Locator;
  readonly nameInput: Locator;
  readonly emailInput: Locator;
  readonly submitButton: Locator;
  readonly moreActions: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.addButton = this.frame
      .getByRole('link', { exact: true, name: 'Add' })
      .describe('Add contact link');
    this.aliasInput = this.frame
      .locator('input#contact_alias')
      .describe('Contact alias input');
    this.nameInput = this.frame
      .locator('input#contact_name')
      .describe('Contact full name input');
    this.emailInput = this.frame
      .locator('input#contact_email')
      .describe('Contact email input');
    this.submitButton = this.frame
      .locator('input.btc.bt_success[name^="submit"]')
      .first()
      .describe('Save contact button');
    this.moreActions = this.frame
      .locator('select[name="o1"]')
      .first()
      .describe('More actions dropdown');
  }

  /** A listing row (table line) located by the contact alias/name text. */
  contactLink(text: string): Locator {
    return this.frame
      .getByRole('link', { exact: true, name: text })
      .describe(`contact link "${text}"`);
  }

  /** The selection checkbox of the listing row carrying the given text. */
  private contactRowCheckbox(text: string): Locator {
    return this.frame
      .locator('tr')
      .filter({ hasText: text })
      .locator('input[type="checkbox"]')
      .first()
      .describe(`row checkbox for "${text}"`);
  }

  /** Open the contacts listing and wait for the "Add" link to be visible. */
  async open(): Promise<void> {
    await this.goto('/centreon/main.php?p=60301');
    await expect(this.addButton).toBeVisible({ timeout: 30_000 });
  }

  /** Open the creation form and fill the required General Information fields. */
  async createContact(contact: ContactInput): Promise<void> {
    await test.step(`Create contact "${contact.alias}"`, async () => {
      await this.addButton.click();
      await expect(this.aliasInput).toBeVisible({ timeout: 30_000 });

      await this.aliasInput.fill(contact.alias);
      await this.nameInput.fill(contact.name);
      await this.emailInput.fill(contact.email);

      await this.submitButton.click();
      // Back on the listing, the new contact appears as a link.
      await expect(this.contactLink(contact.alias)).toBeVisible({
        timeout: 30_000
      });
    });
  }

  /**
   * Delete a contact from the listing: tick its row checkbox then trigger the
   * "Delete" entry of the "More actions" dropdown (mirrors the Cypress flow,
   * which forces the form's `onchange` to submit the bulk action).
   */
  async deleteContact(alias: string): Promise<void> {
    await test.step(`Delete contact "${alias}"`, async () => {
      await this.contactRowCheckbox(alias).check();

      // The legacy listing wires the bulk action through the select's
      // onchange handler; selecting "Delete" submits the form.
      this.page.once('dialog', (dialog) => {
        void dialog.accept();
      });
      await this.moreActions.selectOption({ label: 'Delete' });

      await expect(this.contactLink(alias)).toHaveCount(0, {
        timeout: 30_000
      });
    });
  }
}
