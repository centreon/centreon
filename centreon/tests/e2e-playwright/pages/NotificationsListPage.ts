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
}
