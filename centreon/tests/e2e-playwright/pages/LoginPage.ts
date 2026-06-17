import { expect, type Locator, type Page, test } from '@playwright/test';

import type { Credentials } from '../fixtures/credentials';
import { BasePage } from './BasePage';

/**
 * Page Object for the Centreon login form.
 *
 * All the knowledge about *how* to reach the login controls lives here, so the
 * tests can stay at the level of "log in as this user" without ever touching a
 * CSS selector. Locators mirror the accessibility attributes exposed by the
 * React form (`aria-label` / `data-testid`).
 */
export class LoginPage extends BasePage {
  readonly aliasInput: Locator;
  readonly passwordInput: Locator;
  readonly connectButton: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    super(page);
    // Prefer `data-testid` over `aria-label` for the inputs: the form translates
    // the aria-label at runtime (`t(labelAlias)`), whereas `data-testid` stays
    // "Alias"/"Password" regardless of `CENTREON_LANG`. The Connect button keeps
    // its aria-label, which the form exposes untranslated.
    this.aliasInput = page.locator('input[data-testid="Alias"]');
    this.passwordInput = page.locator('input[data-testid="Password"]');
    this.connectButton = page.locator('button[aria-label="Connect"]');
    // The error snackbar is an MUI Alert (role="alert"); targeting the role is
    // more robust than coupling to the internal `.MuiAlert-message` class.
    this.errorMessage = page.getByRole('alert');
  }

  /** Open the application root, which redirects to the login form when logged out. */
  async open(): Promise<void> {
    await this.goto('/');
    await this.expectVisible();
  }

  /** Assert the login form is displayed (used as a logged-out sentinel). */
  async expectVisible(): Promise<void> {
    await expect(this.aliasInput).toBeVisible();
    await expect(this.passwordInput).toBeVisible();
  }

  /** Fill in the credentials and submit the form. */
  async login({ login, password }: Credentials): Promise<void> {
    await test.step(`Log in as "${login}"`, async () => {
      await this.aliasInput.fill(login);
      await this.passwordInput.fill(password);
      await this.connectButton.click();
    });
  }

  /** Click the "Login with <provider>" external-provider button. */
  async loginWith(provider: string): Promise<void> {
    await test.step(`Log in with ${provider}`, async () => {
      await this.page.getByText(`Login with ${provider}`).click();
    });
  }

  /** Text of the snackbar/error banner shown after a failed attempt. */
  async getErrorMessage(): Promise<string> {
    await expect(this.errorMessage).toBeVisible();
    return (await this.errorMessage.textContent())?.trim() ?? '';
  }
}
