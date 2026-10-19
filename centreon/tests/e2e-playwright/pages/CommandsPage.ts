import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

export type CommandType =
  | 'Check'
  | 'Notification'
  | 'Discovery'
  | 'Miscellaneous';

interface CreateCommandOptions {
  name: string;
  type?: CommandType;
  commandLine?: string;
  comments?: string;
}

/**
 * Page Object for the modern React "Commands" configuration page
 * (`/centreon/configuration/commands`).
 *
 * It wraps the creation/modification dialog (name, command type radio group,
 * command line, comments) and the listing (name search bar, row inline Delete
 * icon with its confirmation dialog) that the Cypress `Commands` feature drives.
 *
 * Note: the Cypress flow builds the command line through three autocomplete
 * "insert macro / plugin" popovers backed by paginated API calls. To keep the
 * migrated happy paths fast and stable, this Page Object types the command line
 * directly into the textarea instead — the resulting command is still valid and
 * is what the listing/delete assertions exercise.
 */
export class CommandsPage extends BasePage {
  readonly addButton: Locator;
  readonly nameInput: Locator;
  readonly commandLineInput: Locator;
  readonly commentsInput: Locator;
  readonly saveButton: Locator;
  readonly searchBar: Locator;

  constructor(page: Page) {
    super(page);
    this.addButton = page
      .getByLabel('Add', { exact: true })
      .describe('Add command button');
    this.nameInput = page.locator('#Name').describe('Command name input');
    this.commandLineInput = page
      .locator('#Commandline')
      .describe('Command line textarea');
    this.commentsInput = page.locator('#Comments').describe('Comments input');
    this.saveButton = page
      .getByLabel('Save', { exact: true })
      .describe('Save command button');
    this.searchBar = page.locator('#searchbar').describe('Commands search bar');
  }

  private waitForCommandsList(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response.url().includes('/api/latest/configuration/commands') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the listing and wait for the commands request to settle. */
  async open(): Promise<void> {
    const commands = this.waitForCommandsList();
    await this.goto('/centreon/configuration/commands');
    await commands;
  }

  /** A listing row located by the command name. */
  row(name: string): Locator {
    return this.page
      .getByRole('row')
      .filter({ hasText: name })
      .describe(`command row "${name}"`);
  }

  /** Type a name in the search bar and wait for the filtered list. */
  async searchByName(name: string): Promise<void> {
    await test.step(`Search commands by name "${name}"`, async () => {
      const commands = this.waitForCommandsList();
      await this.searchBar.clear();
      await this.searchBar.fill(name);
      await commands;
    });
  }

  /** Fill and submit the creation dialog for a command. */
  async createCommand({
    name,
    type = 'Check',
    commandLine = '$CENTREONPLUGINS$/check_dummy',
    comments = 'pw command'
  }: CreateCommandOptions): Promise<void> {
    await test.step(`Create ${type} command "${name}"`, async () => {
      await this.addButton.click();
      await expect(
        this.page.getByText('Add a command', { exact: true })
      ).toBeVisible();

      await this.nameInput.fill(name);
      await this.page
        .locator(`input[value="${type}"]`)
        .describe(`command type "${type}" radio`)
        .click();
      await this.commandLineInput.fill(commandLine);
      await this.commentsInput.fill(comments);

      const commands = this.waitForCommandsList();
      await this.saveButton.click();
      await commands;
    });
  }

  /** Delete a command from its inline Delete icon, confirming the dialog. */
  async deleteCommand(name: string): Promise<void> {
    await test.step(`Delete command "${name}"`, async () => {
      await this.searchByName(name);
      // Inline action icon in the row (Cypress used `#Delete`).
      await this.page.locator('#Delete').first().click();
      const confirm = this.page
        .locator('button[type="submit"][aria-label="Delete"]')
        .describe('Confirm delete button');
      await expect(confirm).toBeVisible();
      const commands = this.waitForCommandsList();
      await confirm.click();
      await commands;
    });
  }
}
