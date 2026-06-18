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
    return this.page
      .getByRole('row')
      .filter({ hasText: name })
      .describe(`listing row "${name}"`);
  }

  /** The enabled-only-when-a-row-is-selected toolbar "Acknowledge" button. */
  private get acknowledgeButton(): Locator {
    return this.page
      .getByTestId('mainAcknowledge')
      .describe('Acknowledge toolbar button');
  }

  /**
   * Tick the selection checkbox of a resource row. The listing is virtualized
   * and auto-refreshes, so the click can race with a re-render; retry until the
   * toolbar "Acknowledge" action becomes enabled (the signal a row is selected).
   */
  async selectResource(name: string): Promise<void> {
    await test.step(`Select resource "${name}"`, async () => {
      await expect(async () => {
        await this.listingRow(name).getByRole('checkbox').first().click();
        await expect(this.acknowledgeButton).toBeEnabled({ timeout: 2_000 });
      }).toPass({ timeout: 20_000 });
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

  /** Disacknowledge the current selection through the "More actions" menu. */
  async disacknowledgeSelected(): Promise<void> {
    await test.step('Disacknowledge the selected resource', async () => {
      await this.page
        .getByLabel('More actions', { exact: true })
        .last()
        .click();
      await this.page
        .getByTestId('Multiple Disacknowledge')
        .click({ force: true });

      // The "Disacknowledge" confirmation dialog shares the generic Confirm
      // button (data-testid="Confirm"), labelled "Disacknowledge".
      const disacknowledged = this.page.waitForResponse(
        (response) =>
          response.url().includes('/monitoring/resources/acknowledgements') &&
          response.request().method() === 'DELETE'
      );
      await this.page.getByTestId('Confirm').click();
      await disacknowledged;
    });
  }

  /** Snackbar shown after a command (e.g. "Acknowledge command sent"). */
  snackbar(message: string): Locator {
    return this.page.getByText(message);
  }
}
