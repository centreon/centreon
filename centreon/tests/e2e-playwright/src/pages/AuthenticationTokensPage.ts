import { expect, type Locator, type Page } from '@playwright/test';

import { ConfirmationDialog } from '../components/ConfirmationDialog';
import { CreateTokenDialog } from '../components/CreateTokenDialog';
import { Snackbar } from '../components/Snackbar';
import { BasePage } from './BasePage';

/** Labels come from www/front_src/src/AuthenticationTokens/translatedLabels.ts. */
const labels = {
  add: 'Add',
  authenticationTokens: 'Authentication tokens',
  delete: 'Delete',
  refresh: 'Refresh',
  search: 'Search',
  tokenCopiedToTheClipboard: 'Authentication token copied to the clipboard'
} as const;

export class AuthenticationTokensPage extends BasePage {
  protected readonly path = '/administration/authentication-token';

  readonly title: Locator;
  readonly addButton: Locator;
  readonly refreshButton: Locator;
  readonly searchField: Locator;
  readonly rows: Locator;
  readonly createTokenDialog: CreateTokenDialog;
  readonly confirmationDialog: ConfirmationDialog;
  readonly snackbar: Snackbar;

  constructor(page: Page) {
    super(page);

    this.title = page.getByRole('heading', { level: 1 });
    this.addButton = page.getByRole('button', {
      exact: true,
      name: labels.add
    });
    this.refreshButton = page.getByRole('button', { name: labels.refresh });
    this.searchField = page.getByRole('textbox', { name: labels.search });
    // Second rowgroup: the first one holds the column headers.
    this.rows = page
      .getByRole('table')
      .getByRole('rowgroup')
      .nth(1)
      .getByRole('row');
    this.createTokenDialog = new CreateTokenDialog(page);
    this.confirmationDialog = new ConfirmationDialog(page);
    this.snackbar = new Snackbar(page);
  }

  row(tokenName: string): Locator {
    return this.rows.filter({
      has: this.page.getByText(tokenName, { exact: true })
    });
  }

  async openCreateTokenDialog(): Promise<CreateTokenDialog> {
    await this.addButton.click();

    return this.createTokenDialog;
  }

  async requestDeletion(tokenName: string): Promise<ConfirmationDialog> {
    await this.row(tokenName)
      .getByRole('button', { name: labels.delete })
      .click();

    return this.confirmationDialog;
  }

  async expectToBeLoaded(): Promise<void> {
    await this.expectToBeDisplayed();
    await expect(this.title).toHaveText(labels.authenticationTokens);
    await expect(this.page.getByRole('table')).toBeVisible();
  }

  async expectTokenToBeListed(tokenName: string, listed = true): Promise<void> {
    await expect(this.row(tokenName)).toHaveCount(listed ? 1 : 0);
  }

  async expectTokenCopied(): Promise<void> {
    await this.snackbar.expectMessage(labels.tokenCopiedToTheClipboard);
  }
}
