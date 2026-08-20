import { expect, type Locator, type Page } from '@playwright/test';

import type { Credentials } from '../config/users';
import { BasePage } from './BasePage';

/** Labels come from www/front_src/src/Login/translatedLabels.ts. */
const labels = {
  alias: 'Alias',
  connect: 'Connect',
  password: 'Password'
} as const;

export class LoginPage extends BasePage {
  protected readonly path = '/login';

  readonly aliasField: Locator;
  readonly passwordField: Locator;
  readonly connectButton: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    super(page);

    this.aliasField = page.getByLabel(labels.alias, { exact: true });
    this.passwordField = page.getByLabel(labels.password, { exact: true });
    this.connectButton = page.getByRole('button', { name: labels.connect });
    this.errorMessage = page.getByRole('alert');
  }

  async fillCredentials({ alias, password }: Credentials): Promise<void> {
    await this.aliasField.fill(alias);
    await this.passwordField.fill(password);
  }

  async submit(): Promise<void> {
    await this.connectButton.click();
  }

  async login(credentials: Credentials): Promise<void> {
    await this.fillCredentials(credentials);
    await this.submit();
  }

  async expectFormToBeReady(): Promise<void> {
    await expect(this.aliasField).toBeVisible();
    await expect(this.passwordField).toBeVisible();
    await expect(this.connectButton).toBeDisabled();
  }

  async expectErrorMessage(message: string | RegExp): Promise<void> {
    await expect(this.errorMessage).toContainText(message);
  }
}
