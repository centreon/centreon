import { expect, type Locator, type Page, test } from '@playwright/test';

import type { Credentials } from '../fixtures/credentials';
import { BasePage } from './BasePage';

/**
 * Page Object for the Keycloak login form reached after clicking
 * "Login with openid" on the Centreon login page (realm `Centreon_SSO`).
 */
export class KeycloakLoginPage extends BasePage {
  readonly usernameInput: Locator;
  readonly passwordInput: Locator;
  readonly submitButton: Locator;

  constructor(page: Page) {
    super(page);
    this.usernameInput = page
      .locator('#username')
      .describe('Keycloak username');
    this.passwordInput = page
      .locator('#password')
      .describe('Keycloak password');
    this.submitButton = page.locator('#kc-login').describe('Keycloak sign-in');
  }

  /** Assert the browser has been redirected to the Keycloak realm. */
  async expectVisible(): Promise<void> {
    await expect(this.page).toHaveURL(/\/realms\/Centreon_SSO/);
    await expect(this.usernameInput).toBeVisible();
  }

  async login({ login, password }: Credentials): Promise<void> {
    await test.step(`Sign in on Keycloak as "${login}"`, async () => {
      await this.usernameInput.fill(login);
      await this.passwordInput.fill(password);
      await this.submitButton.click();
    });
  }
}
