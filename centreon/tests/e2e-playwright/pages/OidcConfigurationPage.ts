import { expect, type Locator, type Page, test } from '@playwright/test';

import { oidcConfig } from '../fixtures/oidc';
import { BasePage } from './BasePage';

/**
 * Page Object for the OpenID Connect configuration form
 * (Administration > Authentication > OpenID Connect tab).
 */
export class OidcConfigurationPage extends BasePage {
  readonly oidcTab: Locator;
  readonly identityProviderSection: Locator;
  readonly baseUrlInput: Locator;
  readonly authorizationEndpointInput: Locator;
  readonly tokenEndpointInput: Locator;
  readonly introspectionEndpointInput: Locator;
  readonly clientIdInput: Locator;
  readonly clientSecretInput: Locator;
  readonly scopesInput: Locator;
  readonly loginAttributeInput: Locator;
  readonly useBasicAuthCheckbox: Locator;
  readonly disableVerifyPeerCheckbox: Locator;
  readonly enableOidcCheckbox: Locator;
  readonly mixedModeRadio: Locator;
  readonly saveButton: Locator;

  constructor(page: Page) {
    super(page);
    this.oidcTab = page.locator('div[role="tablist"] button:nth-child(2)');
    // The label "Identity provider" is shared by a tab and a collapsible
    // section; target the collapsible section (role="button" div).
    this.identityProviderSection = page.locator(
      'div[role="button"][aria-label="Identity provider"]'
    );
    this.baseUrlInput = page.locator('input[aria-label="Base URL"]');
    this.authorizationEndpointInput = page.locator(
      'input[aria-label="Authorization endpoint"]'
    );
    this.tokenEndpointInput = page.locator(
      'input[aria-label="Token endpoint"]'
    );
    this.introspectionEndpointInput = page.locator(
      'input[aria-label="Introspection token endpoint"]'
    );
    this.clientIdInput = page.locator('input[aria-label="Client ID"]');
    this.clientSecretInput = page.locator('input[aria-label="Client secret"]');
    this.scopesInput = page.locator('input[aria-label="Scopes"]');
    this.loginAttributeInput = page.locator(
      'input[aria-label="Login attribute path"]'
    );
    this.useBasicAuthCheckbox = page.locator(
      'input[aria-label="Use basic authentication for token endpoint authentication"]'
    );
    this.disableVerifyPeerCheckbox = page.locator(
      'input[aria-label="Disable verify peer"]'
    );
    this.enableOidcCheckbox = page.locator(
      'input[aria-label="Enable OpenID Connect authentication"]'
    );
    this.mixedModeRadio = page.locator('input[aria-label="Mixed"]');
    this.saveButton = page.locator('button[aria-label="save button"]');
  }

  /** Open the authentication page and select the OpenID Connect tab. */
  async open(): Promise<void> {
    await test.step('Open the OpenID Connect configuration', async () => {
      const providerLoaded = this.page.waitForResponse((response) =>
        response.url().includes('/authentication/providers/openid')
      );
      await this.goto('/administration/authentication');
      await this.oidcTab.click();
      await providerLoaded;
    });
  }

  /** Expand the "Identity provider" section if it is collapsed. */
  private async expandIdentityProvider(): Promise<void> {
    if (!(await this.baseUrlInput.isVisible())) {
      await this.identityProviderSection.click();
    }
    await expect(this.baseUrlInput).toBeVisible();
  }

  /** Fill the identity-provider settings with valid values. */
  async fillProviderConfiguration(): Promise<void> {
    await test.step('Fill the identity provider settings', async () => {
      await this.expandIdentityProvider();
      await this.baseUrlInput.fill(oidcConfig.baseUrl);
      await this.authorizationEndpointInput.fill(
        oidcConfig.authorizationEndpoint
      );
      await this.tokenEndpointInput.fill(oidcConfig.tokenEndpoint);
      await this.introspectionEndpointInput.fill(
        oidcConfig.introspectionTokenEndpoint
      );
      await this.clientIdInput.fill(oidcConfig.clientId);
      await this.clientSecretInput.fill(oidcConfig.clientSecret);
      await this.loginAttributeInput.fill(oidcConfig.loginAttributePath);
      await this.ensureScope(oidcConfig.scopes);
      await this.useBasicAuthCheckbox.uncheck();
    });
  }

  /**
   * Make sure the given connection scope is present. The `openid` scope is
   * required for the provider to return an id_token; without it the login
   * fails with "Request for connection token to external provider has failed".
   */
  private scopeChip(scope: string): Locator {
    return this.page.locator('.MuiChip-root', { hasText: scope });
  }

  private async ensureScope(scope: string): Promise<void> {
    if ((await this.scopeChip(scope).count()) > 0) {
      return;
    }
    // It is a creatable autocomplete: type the value (fill() does not trigger
    // the chip creation) and press Enter to accept it. Use the keyboard after
    // focusing — typing re-renders the combobox, so the input locator goes
    // stale and must not be re-queried for the Enter press.
    await this.scopesInput.click();
    await this.page.keyboard.type(scope);
    await this.page.keyboard.press('Enter');
    await expect(this.scopeChip(scope).first()).toBeVisible();
  }

  /** Enable OpenID Connect authentication if it is not already enabled. */
  async enableOpenIdConnect(): Promise<void> {
    await test.step('Enable OpenID Connect authentication', async () => {
      if (!(await this.enableOidcCheckbox.isChecked())) {
        await this.enableOidcCheckbox.check();
      }
    });
  }

  /** Save the form and wait for the provider update to succeed (HTTP 204). */
  async save(): Promise<void> {
    await test.step('Save the OpenID Connect configuration', async () => {
      if (await this.saveButton.isDisabled()) {
        return;
      }
      const updated = this.page.waitForResponse(
        (response) =>
          response.url().includes('/authentication/providers/openid') &&
          response.request().method() === 'PUT'
      );
      await this.saveButton.click();
      const response = await updated;
      expect(response.status()).toBe(204);
    });
  }

  async expectClientSecretHidden(): Promise<void> {
    await expect(this.clientSecretInput).toHaveAttribute('type', 'password');
  }

  async expectMixedModeIsDefault(): Promise<void> {
    await expect(this.mixedModeRadio).toBeChecked();
  }
}
