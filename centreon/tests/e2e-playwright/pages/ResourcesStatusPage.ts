import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the monitoring "Resources status" listing
 * (`/monitoring/resources`).
 *
 * It mirrors the Cypress `Resources-status/01-listing` selectors — the search
 * field (placeholder "Search"), the saved-filter select (`selectedFilter`) and
 * the listing rows located by resource name — but exposes them as intentful
 * methods so specs read as user actions, not selectors.
 */
export class ResourcesStatusPage extends BasePage {
  readonly searchInput: Locator;
  readonly selectedFilter: Locator;

  constructor(page: Page) {
    super(page);
    this.searchInput = page
      .getByPlaceholder('Search')
      .describe('Resources search field');
    this.selectedFilter = page
      .locator('[data-testid="selectedFilter"]')
      .describe('Saved filter select');
  }

  private waitForResources(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response.url().includes('/monitoring/resources') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the listing and wait for the first resources request to settle. */
  async open(): Promise<void> {
    const resources = this.waitForResources();
    await this.goto('/monitoring/resources');
    await resources;
    await this.disableAutoRefresh();
  }

  /**
   * Turn off the listing auto-refresh so a periodic re-render does not reset the
   * row selection mid-test (a real flake under full-suite load). Best-effort.
   */
  async disableAutoRefresh(): Promise<void> {
    const toggle = this.page.getByRole('button', {
      exact: true,
      name: 'Disable autorefresh'
    });
    if ((await toggle.count()) > 0) {
      await toggle
        .first()
        .click()
        .catch(() => undefined);
    }
  }

  /** Type a search expression, submit it and wait for the filtered listing. */
  async search(expression: string): Promise<void> {
    await test.step(`Search "${expression}"`, async () => {
      // Best-effort: a query already in the React Query cache may not re-hit the
      // network, so don't hard-fail on the response — the callers' row
      // assertions retry until the filtered listing is rendered.
      const resources = this.page
        .waitForResponse(
          (response) =>
            response.url().includes('/monitoring/resources') &&
            response.request().method() === 'GET',
          { timeout: 10_000 }
        )
        .catch(() => null);
      // Type real keystrokes (not `fill`) so the search field tokenizes the
      // query, then close the autocomplete popup and submit — mirrors the
      // Cypress `{selectall}{backspace}${text}{esc}{enter}` sequence.
      await this.searchInput.click();
      await this.searchInput.press('ControlOrMeta+a');
      await this.searchInput.press('Backspace');
      await this.searchInput.pressSequentially(expression);
      await this.searchInput.press('Escape');
      await this.searchInput.press('Enter');
      await resources;
    });
  }

  /** A listing entry located by its resource (service) name. */
  private row(name: string): Locator {
    return this.page.getByText(name, { exact: true });
  }

  async expectResourceVisible(name: string): Promise<void> {
    await expect(this.row(name)).toBeVisible();
  }

  async expectResourceHidden(name: string): Promise<void> {
    await expect(this.row(name)).toHaveCount(0);
  }

  async expectSelectedFilter(name: string): Promise<void> {
    await expect(this.selectedFilter).toContainText(name);
  }

  // --- Acknowledgement -----------------------------------------------------

  /** The listing row that contains a given resource name. */
  private listingRow(name: string): Locator {
    return (
      this.page
        .getByRole('row')
        .filter({ hasText: name })
        // The listing also renders rows out of sight to measure them.
        .filter({ visible: true })
        .describe(`listing row "${name}"`)
    );
  }

  /** The enabled-only-when-a-row-is-selected toolbar "Acknowledge" button. */
  private get acknowledgeButton(): Locator {
    return this.page
      .getByTestId('mainAcknowledge')
      .describe('Acknowledge toolbar button');
  }

  /**
   * Tick the selection checkbox of a resource row. The hovered row keeps its
   * previous render until the pointer leaves it, so the state is read with the
   * pointer away from the listing.
   */
  async selectResource(name: string): Promise<void> {
    await test.step(`Select resource "${name}"`, async () => {
      const checkbox = this.listingRow(name).getByRole('checkbox').first();
      const leaveListing = () => this.page.mouse.move(5, 5);
      await leaveListing();
      if (!(await checkbox.isChecked())) {
        await checkbox.click();
        await leaveListing();
      }
      await expect(checkbox).toBeChecked();
    });
  }

  /**
   * Open the acknowledge dialog for the current selection, fill the comment and
   * confirm with the default options (sticky, no notification). Waits for the
   * acknowledge request to be accepted.
   */
  async acknowledgeSelected(comment: string): Promise<void> {
    await test.step('Acknowledge the selected resource', async () => {
      await this.acknowledgeButton.click();

      const dialog = this.page.getByRole('dialog');
      await dialog.locator('textarea').first().fill(comment);

      const acknowledged = this.page.waitForResponse(
        (response) =>
          response.url().includes('/monitoring/resources/acknowledge') &&
          response.request().method() === 'POST'
      );
      await this.page.getByTestId('Confirm').click();
      await acknowledged;
    });
  }

  /** Open the toolbar "More actions" menu. */
  private async openMoreActions(): Promise<void> {
    await this.page.getByLabel('More actions', { exact: true }).first().click();
    // Its hover tooltip would cover the first item of the menu.
    await this.page.mouse.move(5, 5);
  }

  /** Disacknowledge the current selection through the "More actions" menu. */
  async disacknowledgeSelected(): Promise<void> {
    await test.step('Disacknowledge the selected resource', async () => {
      await this.openMoreActions();
      await this.page.getByTestId('Multiple Disacknowledge').click();
      const disacknowledged = this.page.waitForResponse(
        (response) =>
          response.url().includes('/monitoring/resources/acknowledgements') &&
          response.request().method() === 'DELETE'
      );
      await this.page
        .getByRole('dialog')
        .getByLabel('Disacknowledge', { exact: true })
        .click();
      await disacknowledged;
    });
  }

  /** Set a downtime with the default settings on the current selection. */
  async setDowntimeOnSelection(): Promise<void> {
    await test.step('Set a downtime on the selection', async () => {
      await this.page.getByTestId('mainSetDowntime').click();
      const sent = this.page.waitForResponse(
        (response) =>
          response.url().includes('/monitoring/resources/downtime') &&
          response.request().method() === 'POST'
      );
      await this.page
        .getByRole('dialog')
        .getByLabel('Set downtime', { exact: true })
        .click();
      await sent;
    });
  }

  /** Submit a passive result to the selected resource. */
  async submitStatusOnSelection(status: string, output: string): Promise<void> {
    await test.step(`Submit the "${status}" status`, async () => {
      await this.page
        .getByLabel('More actions', { exact: true })
        .last()
        .click();
      await this.page.getByTestId('Submit a status').click();
      const dialog = this.page.getByRole('dialog');
      await dialog.getByRole('combobox').first().click();
      await this.page
        .getByRole('option', { exact: true, name: status })
        .click();
      await dialog.getByLabel('Output').fill(output);
      const sent = this.page.waitForResponse(
        (response) =>
          response.url().includes('/monitoring/resources/submit') &&
          response.request().method() === 'POST'
      );
      await dialog.getByTestId('Confirm').click();
      await sent;
    });
  }

  /**
   * Export the listing to CSV (all columns, all pages) and return the file
   * the new tab receives.
   */
  async exportCsv(): Promise<string> {
    return test.step('Export the listing to CSV', async () => {
      await this.page.getByRole('button', { name: 'exportCsvButton' }).click();
      const dialog = this.page.getByRole('dialog');
      await expect(dialog.getByTestId('All pages')).toBeChecked();
      const exported = this.page
        .context()
        .waitForEvent('response', (response) =>
          response.url().includes('/monitoring/resources/export')
        );
      await dialog.getByRole('button', { name: 'Export' }).click();
      // The tab downloads the file: fetch the same URL with the session.
      const response = await exported;
      const file = await this.page.request.get(response.url());
      expect(file.ok()).toBeTruthy();

      return file.text();
    });
  }

  /** Snackbar shown after a command (e.g. "Acknowledge command sent"). */
  snackbar(message: string): Locator {
    return this.page.getByText(message);
  }
}
