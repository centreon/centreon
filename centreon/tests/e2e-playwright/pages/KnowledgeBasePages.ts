import { expect, type Locator, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object of the knowledge base parameters
 * (`Administration > Parameters > Knowledge Base`).
 */
export class KnowledgeBaseParametersPage extends BasePage {
  async configure({
    account,
    password,
    url
  }: {
    account: string;
    password: string;
    url: string;
  }): Promise<void> {
    await test.step(`Use the wiki at ${url}`, async () => {
      await this.goto('/centreon/main.get.php?p=50133&o=knowledgeBase');
      for (const [field, value] of [
        ['kb_wiki_url', url],
        ['kb_wiki_account', account],
        ['kb_wiki_password', password]
      ]) {
        await this.page.locator(`input[name="${field}"]`).fill(value);
      }
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page.locator('input[value="Save"]').first().click()
      ]);
    });
  }
}

/**
 * Page Object of the host and service procedure listings
 * (`Configuration > Knowledge Base`).
 */
export class KnowledgeListingPage extends BasePage {
  constructor(
    page: BasePage['page'],
    private readonly kind: 'host' | 'service'
  ) {
    super(page);
  }

  async open(search: string): Promise<void> {
    await this.goto(
      `/centreon/main.get.php?p=${this.kind === 'host' ? '61001' : '61002'}`
    );
    const field = this.page
      .locator(
        `input[name="${this.kind === 'host' ? 'searchHost' : 'searchService'}"]`
      )
      .first();
    await field.fill(search);
    await Promise.all([this.page.waitForEvent('load'), field.press('Enter')]);
  }

  row(name: string): Locator {
    return this.page
      .locator('tr.list_one, tr.list_two')
      .filter({ hasText: name });
  }

  async deletePage(name: string): Promise<void> {
    await test.step(`Delete the wiki page of ${name}`, async () => {
      await this.row(name)
        .getByText('Delete wiki page')
        .click();
      await expect(
        this.row(name).getByText('No wiki page defined')
      ).toBeVisible({
        timeout: 30_000
      });
    });
  }
}
