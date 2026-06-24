import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

interface CreateTokenOptions {
  name: string;
  userAlias: string;
  duration?: string;
}

/**
 * Page Object for the authentication tokens administration page
 * (`/administration/authentication-token`).
 *
 * It wraps the React creation modal (name, type, user connected autocomplete,
 * duration, generated-token reveal/copy) and the listing (rows, delete with
 * confirmation, name filter) the Cypress `Api-Token` feature drives.
 */
export class ApiTokensPage extends BasePage {
  readonly addButton: Locator;
  readonly submitButton: Locator;
  readonly tokenInput: Locator;
  readonly revealButton: Locator;
  readonly copyButton: Locator;

  constructor(page: Page) {
    super(page);
    this.addButton = page.getByTestId('Add').describe('Add token button');
    this.submitButton = page
      .getByTestId('submit')
      .describe('Generate token button');
    this.tokenInput = page
      .getByTestId('tokenInput')
      .describe('Generated token field');
    this.revealButton = page
      .getByLabel('toggle password visibility')
      .describe('Reveal token button');
    this.copyButton = page
      .getByLabel('clipboard')
      .describe('Copy token button');
  }

  private waitForTokens(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response.url().includes('/administration/tokens') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the listing and wait for the tokens request to settle. */
  async open(): Promise<void> {
    const tokens = this.waitForTokens();
    await this.goto('/administration/authentication-token');
    await tokens;
  }

  /** Pick an option (by visible text) from a just-opened autocomplete/select. */
  private async pickOption(text: string): Promise<void> {
    await this.page.getByRole('option', { exact: true, name: text }).click();
  }

  /**
   * Fill and submit the creation modal for an API token. Waits for the
   * generated token to be displayed.
   */
  async createToken({
    name,
    userAlias,
    duration = '30 days'
  }: CreateTokenOptions): Promise<void> {
    await test.step(`Create API token "${name}"`, async () => {
      await this.addButton.click();

      const dialog = this.page.getByRole('dialog');
      await dialog.getByLabel('Name', { exact: true }).fill(name);

      // Type: ensure "API" so the user field is shown.
      await dialog.getByLabel('Type', { exact: true }).click();
      await this.pickOption('API');

      // User: connected autocomplete filtered by alias.
      await dialog.getByLabel('User', { exact: true }).fill(userAlias);
      await this.pickOption(userAlias);

      // Duration: preset select.
      await dialog.getByLabel('Duration', { exact: true }).click();
      await this.pickOption(duration);

      await this.submitButton.click();
      await expect(this.tokenInput).toBeVisible();
    });
  }

  /** Reveal the generated token then copy it to the clipboard. */
  async revealAndCopyToken(): Promise<void> {
    await test.step('Reveal and copy the generated token', async () => {
      await this.revealButton.click();
      await expect(this.tokenInput).toHaveAttribute('type', 'text');
      await this.copyButton.click();
    });
  }

  /** A listing row located by the token name. */
  row(name: string): Locator {
    return this.page
      .getByRole('row')
      .filter({ hasText: name })
      .describe(`token row "${name}"`);
  }

  /** Delete a token from its row, confirming (or cancelling) the dialog. */
  async deleteToken(name: string, { confirm = true } = {}): Promise<void> {
    await test.step(`Delete token "${name}"`, async () => {
      await this.row(name).getByLabel('Delete', { exact: true }).click();
      await this.page.getByTestId(confirm ? 'confirm' : 'cancel').click();
    });
  }

  /** Open the filter panel, search by token name and apply. */
  async filterByName(name: string): Promise<void> {
    await test.step(`Filter tokens by name "${name}"`, async () => {
      await this.page.getByTestId('Filters').click();
      // Several elements carry the "Name" test id (filter input + column); the
      // filter control is the text input.
      await this.page.locator('input[data-testid="Name"]').fill(name);
      const tokens = this.waitForTokens();
      await this.page.getByTestId('Search').click();
      await tokens;
    });
  }

  /**
   * Open the filter panel, pick a value in one of the connected autocomplete
   * filters (User / Creator) and apply. The field filters its options through a
   * connected request, so we type the value then pick the matching option.
   */
  private async filterByAutocomplete(
    field: 'User' | 'Creator',
    value: string
  ): Promise<void> {
    await test.step(`Filter tokens by ${field.toLowerCase()} "${value}"`, async () => {
      await this.page.getByTestId('Filters').click();
      // The connected autocomplete renders its `data-testid` on the input itself.
      await this.page.getByTestId(field).fill(value);
      await this.pickOption(value);
      const tokens = this.waitForTokens();
      await this.page.getByTestId('Search').click();
      await tokens;
    });
  }

  /** Filter the listing by the bound user (alias). */
  filterByUser(alias: string): Promise<void> {
    return this.filterByAutocomplete('User', alias);
  }

  /** Filter the listing by the token creator (name). */
  filterByCreator(name: string): Promise<void> {
    return this.filterByAutocomplete('Creator', name);
  }

  /** The sort field each sortable column header maps to in the listing API. */
  static readonly columnSortField: Record<string, string> = {
    'Creation date': 'creation_date',
    Creator: 'creator.name',
    'Expiration date': 'expiration_date',
    Name: 'token_name',
    User: 'user.name'
  };

  /**
   * Click a sortable column header and resolve with the `sort_by` object the
   * listing request carried (e.g. `{ token_name: 'desc' }`).
   *
   * The listing is virtualized and mixes the platform's own poller/cma tokens
   * with the seeded ones, so asserting the rendered row order is flaky; instead
   * we assert the request the UI issued — the same approach the OpenId listing
   * spec takes. A first click on an unsorted column sorts it descending.
   */
  async sortByColumn(label: string): Promise<Record<string, string>> {
    return test.step(`Sort by column "${label}"`, async () => {
      const request = this.page.waitForRequest(
        (response) =>
          response.url().includes('/administration/tokens') &&
          response.method() === 'GET'
      );
      await this.page.getByLabel(`Column ${label}`, { exact: true }).click();
      const match = (await request).url().match(/sort_by=([^&]+)/);

      return match
        ? (JSON.parse(decodeURIComponent(match[1])) as Record<string, string>)
        : {};
    });
  }

  /** Snackbar shown after an action (e.g. the copy confirmation). */
  snackbar(message: string): Locator {
    return this.page.getByText(message);
  }
}
