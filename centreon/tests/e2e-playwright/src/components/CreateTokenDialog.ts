import { expect, type Locator, type Page } from '@playwright/test';

/** Labels come from www/front_src/src/AuthenticationTokens/translatedLabels.ts. */
const labels = {
  cancel: 'Cancel',
  createAuthenticationToken: 'Create authentication token',
  duration: 'Duration',
  generateToken: 'Generate token',
  name: 'Name',
  type: 'Type',
  user: 'User'
} as const;

export type TokenDuration =
  | '1 year'
  | '7 days'
  | '30 days'
  | '60 days'
  | '90 days'
  | 'Never expire';

export interface TokenForm {
  duration: TokenDuration;
  name: string;
  type?: 'API' | 'CMA';
  user: string;
}

/** Component object for the token creation modal and the generated token. */
export class CreateTokenDialog {
  readonly root: Locator;
  readonly nameField: Locator;
  readonly typeField: Locator;
  readonly durationField: Locator;
  readonly userField: Locator;
  readonly generateButton: Locator;
  readonly cancelButton: Locator;
  readonly closeButton: Locator;
  readonly tokenField: Locator;
  readonly toggleTokenVisibilityButton: Locator;
  readonly copyTokenButton: Locator;

  constructor(private readonly page: Page) {
    this.root = page.getByRole('dialog', {
      name: labels.createAuthenticationToken
    });
    this.nameField = this.root.getByRole('textbox', { name: labels.name });
    this.typeField = this.root.getByRole('combobox', { name: labels.type });
    this.durationField = this.root.getByRole('combobox', {
      name: labels.duration
    });
    this.userField = this.root.getByRole('combobox', { name: labels.user });
    this.generateButton = this.root.getByRole('button', {
      name: labels.generateToken
    });
    this.cancelButton = this.root.getByRole('button', { name: labels.cancel });
    // Lower case aria-label, not translated.
    this.closeButton = this.root.getByRole('button', { name: 'close' });
    // Displayed once the token has been generated.
    this.tokenField = this.root.getByTestId('tokenInput');
    this.toggleTokenVisibilityButton = this.root.getByRole('button', {
      name: 'toggle password visibility'
    });
    this.copyTokenButton = this.root.getByTestId('clipboard');
  }

  private option(name: string): Locator {
    return this.page.getByRole('option', { exact: true, name });
  }

  async fill({ duration, name, type, user }: TokenForm): Promise<void> {
    await this.nameField.fill(name);
    if (type) {
      await this.typeField.click();
      await this.option(type).click();
    }
    await this.durationField.click();
    await this.option(duration).click();
    // Autocomplete backed by the users endpoint: filter before picking.
    await this.userField.fill(user);
    await this.option(user).click();
  }

  async generate(): Promise<void> {
    await this.generateButton.click();
  }

  async close(): Promise<void> {
    await this.closeButton.click();
  }

  async expectTokenToBeGenerated(): Promise<void> {
    await expect(this.tokenField).toBeVisible();
    await expect(this.tokenField).not.toHaveValue('');
  }

  async expectTokenToBeMasked(masked: boolean): Promise<void> {
    await expect(this.tokenField).toHaveAttribute(
      'type',
      masked ? 'password' : 'text'
    );
  }
}
