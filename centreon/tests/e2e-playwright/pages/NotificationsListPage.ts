import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the cloud notifications listing
 * (`/configuration/notifications`).
 *
 * It wraps the MUI table-pagination controls the Cypress
 * `Cloud-notifications/05-notification-listing` suite drives directly
 * (`.MuiTablePagination-*`, "Previous/Next page" buttons, the rows-per-page
 * select) so the spec can talk in terms of pages and totals.
 */
export class NotificationsListPage extends BasePage {
  readonly noResult: Locator;
  readonly displayedRows: Locator;
  readonly previousPageButton: Locator;
  readonly nextPageButton: Locator;
  readonly rowsPerPageSelect: Locator;

  constructor(page: Page) {
    super(page);
    this.noResult = page
      .getByText('No result found')
      .describe('Empty listing message');
    this.displayedRows = page
      .locator('.MuiTablePagination-displayedRows')
      .describe('Pagination "x–y of z" label');
    this.previousPageButton = page
      .getByLabel('Previous page', { exact: true })
      .describe('Previous page button');
    this.nextPageButton = page
      .getByLabel('Next page', { exact: true })
      .describe('Next page button');
    this.rowsPerPageSelect = page
      .locator('.MuiTablePagination-toolbar .MuiInputBase-root')
      .describe('Rows per page select');
  }

  /** Open the listing and wait for the notifications request to settle. */
  async open(): Promise<void> {
    const listing = this.page.waitForResponse(
      (response) =>
        response.url().includes('/configuration/notifications') &&
        response.request().method() === 'GET'
    );
    await this.goto('/configuration/notifications');
    await listing;
  }

  /** Total number of rules, parsed from the "x–y of z" pagination label. */
  async totalCount(): Promise<number> {
    const label = (await this.displayedRows.textContent()) ?? '';
    const total = label.split('of').at(1)?.trim();
    return Number(total);
  }

  async setRowsPerPage(rows: number): Promise<void> {
    await test.step(`Show ${rows} rows per page`, async () => {
      await this.rowsPerPageSelect.click();
      await this.page.locator(`[data-value="${rows}"]`).click();
    });
  }

  async goToNextPage(): Promise<void> {
    await this.nextPageButton.click();
  }

  async goToPreviousPage(): Promise<void> {
    await this.previousPageButton.click();
  }

  /** The listing row of a rule. */
  row(name: string): Locator {
    return this.page
      .locator('.MuiTableBody-root .MuiTableRow-root')
      .filter({ hasText: name })
      .describe(`rule "${name}"`);
  }

  snackbar(text: string): Locator {
    return this.page.locator('.MuiAlert-message').filter({ hasText: text });
  }

  private async pick(field: Locator, options: Array<string>): Promise<void> {
    await field.click();
    for (const option of options) {
      await this.page.getByRole('option', { exact: true, name: option }).click();
    }
    await this.page.keyboard.press('Escape');
  }

  /** Create a rule on a host group and its services, sent by email. */
  async create(rule: {
    body: string;
    contacts: Array<string>;
    hostGroup: string;
    name: string;
    subject: string;
    timePeriod: string;
  }): Promise<void> {
    await test.step(`Create the notification rule "${rule.name}"`, async () => {
      await this.page.getByRole('button', { exact: true, name: 'Add' }).click();
      await this.page.locator('#Notificationname').fill(rule.name);
      await this.pick(this.page.locator('#Searchhostgroups'), [rule.hostGroup]);
      await this.page.getByText('Include services for these hosts').click();
      for (const event of await this.page
        .locator('[data-testid="Extra events services"] > *')
        .all()) {
        await event.click();
      }
      await this.pick(this.page.locator('#Searchcontacts'), rule.contacts);
      await this.page.locator('[aria-label="Select time period"]').click();
      await this.page
        .getByRole('option', { exact: true, name: rule.timePeriod })
        .click();
      await this.page.locator('[aria-label="Subject"]').fill(rule.subject);
      await this.page.locator('[aria-label="EmailBody"]').fill(rule.body);
      await this.page.locator('#Save').click();
      await expect(this.row(rule.name)).toBeVisible();
    });
  }

  async duplicate(name: string, copy: string): Promise<void> {
    await test.step(`Duplicate "${name}" as "${copy}"`, async () => {
      await this.row(name).locator('#Duplicate').click();
      const dialog = this.page.getByRole('dialog');
      await dialog.locator('#Newnotificationname').fill(copy);
      // The name is checked when the field loses the focus.
      await dialog.locator('#Newnotificationname').blur();
      const confirm = dialog.getByRole('button', { name: 'Duplicate' });
      await expect(confirm).toBeEnabled();
      await confirm.click();
      await expect(this.snackbar('Notification duplicated')).toBeVisible();
    });
  }

  async remove(name: string): Promise<void> {
    await test.step(`Delete "${name}"`, async () => {
      await this.row(name).locator('#Deletenotificationrule').click();
      await this.page.getByTestId('Confirm').click();
      await expect(
        this.snackbar('Notification deleted successfully')
      ).toBeVisible();
      await expect(this.row(name)).toHaveCount(0);
    });
  }
}
