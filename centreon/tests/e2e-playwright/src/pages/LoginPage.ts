import {
  expect,
  type Locator,
  type Page,
  type Response
} from '@playwright/test';

import { Snackbar } from '../components/Snackbar';
import type { Credentials } from '../config/users';
import { BasePage } from './BasePage';

/** Labels come from www/front_src/src/Login/translatedLabels.ts. */
const labels = {
  alias: 'Alias',
  connect: 'Connect',
  password: 'Password'
} as const;

/** Error returned by the local authentication provider. */
const authenticationFailed = 'Authentication failed';

const loginEndpoint = '/authentication/providers/configurations/local';

export class LoginPage extends BasePage {
  protected readonly path = '/login';

  readonly aliasField: Locator;
  readonly passwordField: Locator;
  readonly connectButton: Locator;
  readonly snackbar: Snackbar;

  constructor(page: Page) {
    super(page);

    // exact: the "Display the password" button also matches 'Password'.
    this.aliasField = page.getByLabel(labels.alias, { exact: true });
    this.passwordField = page.getByLabel(labels.password, { exact: true });
    this.connectButton = page.getByRole('button', { name: labels.connect });
    this.snackbar = new Snackbar(page);
  }

  async fillCredentials({ alias, password }: Credentials): Promise<void> {
    await this.aliasField.fill(alias);
    await this.passwordField.fill(password);
  }

  /** Submits the form and resolves with the authentication API response. */
  async submit(): Promise<Response> {
    const authentication = this.page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        response.url().endsWith(loginEndpoint)
    );
    await this.connectButton.click();

    return authentication;
  }

  async login(credentials: Credentials): Promise<Response> {
    await this.fillCredentials(credentials);

    return this.submit();
  }

  async expectFormToBeReady(): Promise<void> {
    await expect(this.aliasField).toBeVisible();
    await expect(this.passwordField).toBeVisible();
    await expect(this.connectButton).toBeDisabled();
  }

  /**
   * Checks the response of this very attempt: the error alerts of previous
   * attempts stay displayed, so the alert alone proves nothing.
   */
  async expectAuthenticationFailed(response: Response): Promise<void> {
    expect(response.status()).toBe(401);
    await this.snackbar.expectMessage(authenticationFailed);
    await this.expectToBeDisplayed();
  }
}
