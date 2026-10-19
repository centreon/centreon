import { expect, type Locator, test } from '@playwright/test';

import { BasePage } from './BasePage';

export interface SamlFormValues {
  certificate: string;
  entityId: string;
  remoteLoginUrl: string;
  userIdAttribute: string;
}

/**
 * Page Object of the SAMLv2 configuration form
 * (Administration > Authentication > SAML tab).
 */
export class SamlConfigurationPage extends BasePage {
  private input(label: string): Locator {
    return this.page.locator(
      `:is(input, textarea)[aria-label="${label}"]:visible`
    );
  }

  get mixedMode(): Locator {
    return this.page.locator('input[aria-label="Mixed"]');
  }

  get certificate(): Locator {
    return this.input('Copy/paste x.509 certificate');
  }

  async open(): Promise<void> {
    await test.step('Open the SAML configuration', async () => {
      const loaded = this.page.waitForResponse((response) =>
        response.url().includes('/authentication/providers/saml')
      );
      await this.goto('/administration/authentication');
      await this.page
        .locator('div[role="tablist"] button:nth-child(4)')
        .click();
      await loaded;
      await expect(
        this.page.getByText('Enable SAMLv2 authentication')
      ).toBeVisible();
    });
  }

  /** Enable the provider with the given identity provider settings. */
  async enable(values: SamlFormValues): Promise<void> {
    await test.step('Enable SAMLv2 with a valid identity provider', async () => {
      await this.page
        .locator('input[aria-label="Enable SAMLv2 authentication"]')
        .check();
      if (!(await this.input('Remote login URL').isVisible())) {
        await this.page
          .locator('div[role="button"][aria-label="Identity provider"]')
          .click();
      }
      await this.input('Remote login URL').fill(values.remoteLoginUrl);
      await this.input('Issuer (Entity ID) URL').fill(values.entityId);
      await this.certificate.fill(values.certificate);
      await this.input('User ID (login) attribute for Centreon user').fill(
        values.userIdAttribute
      );
    });
  }

  async save(): Promise<void> {
    await test.step('Save the SAML configuration', async () => {
      const updated = this.page.waitForResponse(
        (response) =>
          response.url().includes('/authentication/providers/saml') &&
          response.request().method() === 'PUT'
      );
      await this.page.locator('button[aria-label="save button"]').click();
      expect((await updated).status()).toBe(204);
    });
  }
}
