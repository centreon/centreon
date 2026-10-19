import { expect, type Locator, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object of the legacy LDAP configuration form
 * (`Administration > Authentication > LDAP`), opened at `main.get.php` like
 * `LegacyListingPage`.
 */
export class LdapConfigurationPage extends BasePage {
  /** Open the form of an LDAP configuration from the listing. */
  async openForm(name: string): Promise<void> {
    await this.goto('/centreon/main.get.php?p=50113&o=ldap');
    // Name and description both link to the form.
    await this.page.getByRole('link', { exact: true, name }).first().click();
    await expect(this.page.locator('input[name="ar_name"]')).toBeVisible({
      timeout: 30_000
    });
  }

  private choose(field: string, choice: 'No' | 'Yes'): Promise<void> {
    return this.page
      .locator(`input[name="${field}[${field}]"]`)
      .locator('..')
      .locator('label', { hasText: choice })
      .first()
      .click();
  }

  /** Enable or disable an LDAP configuration and its users auto import. */
  async configure(
    name: string,
    { autoImport, enabled }: { autoImport: boolean; enabled: boolean }
  ): Promise<void> {
    await test.step(`Configure the "${name}" LDAP`, async () => {
      await this.openForm(name);
      await this.choose('ldap_auth_enable', enabled ? 'Yes' : 'No');
      await this.choose('ldap_auto_import', autoImport ? 'Yes' : 'No');
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page
          .locator('input.btc.bt_success[name^="submit"]')
          .first()
          .click()
      ]);
    });
  }
}

/**
 * Page Object of the legacy "LDAP Import" page of the contacts
 * (`main.get.php?p=60301&o=li`).
 */
export class LdapImportPage extends BasePage {
  /** Rows of the users the search found. */
  get results(): Locator {
    return this.page
      .locator('table.ListTable')
      .nth(1)
      .locator('tbody tr.list_one, tbody tr.list_two');
  }

  /** Search the LDAP directories with a filter. */
  async search(filter: string): Promise<void> {
    await test.step(`Search ${filter}`, async () => {
      await this.goto('/centreon/main.get.php?p=60301&o=li');
      await this.page
        .locator('input[name^="ldap_search_filter"]')
        .first()
        .fill(filter);
      const searched = this.page.waitForResponse(
        (response) => response.request().method() === 'POST'
      );
      await this.page.locator('input[name="ldap_search_button"]').click();
      await searched;
      await expect(this.results.first()).toBeVisible();
    });
  }

  /** Import every user the search found. */
  async importAll(): Promise<void> {
    await test.step('Import the found users', async () => {
      await this.page.locator('#checkall').check({ force: true });
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page.locator('input[value="Import"]').click()
      ]);
    });
  }
}
