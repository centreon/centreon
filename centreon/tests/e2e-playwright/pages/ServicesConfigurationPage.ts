import { expect, type Locator, type Page, test } from '@playwright/test';

import { LegacyListingPage } from './LegacyListingPage';

/**
 * Page Object of the legacy "Services by host" configuration listing
 * (`main.get.php?p=60201`), with its creation form.
 *
 * Its first search field filters on the host name, so `open(host)` lists the
 * services of that host.
 */
export class ServicesConfigurationPage extends LegacyListingPage {
  constructor(page: Page) {
    super(page, '60201');
  }

  /** A select2 option of the open dropdown. */
  private select2Option(text: string): Locator {
    return this.page
      .locator('li.select2-results__option', { hasText: text })
      .first()
      .describe(`select2 option "${text}"`);
  }

  /** Create a service on an existing host through the legacy form. */
  async createService({
    host,
    name,
    template
  }: {
    host: string;
    name: string;
    template: string;
  }): Promise<void> {
    await test.step(`Create service "${name}" on host "${host}"`, async () => {
      await this.goto('/centreon/main.get.php?p=60201&o=a');
      await expect(this.field('service_description')).toBeVisible({
        timeout: 30_000
      });
      await this.page
        .locator('select[name="service_hPars[]"] + span.select2-container')
        .click();
      await this.select2Option(host).click();
      await this.page
        .locator('span#select2-service_template_model_stm_id-container')
        .click();
      await this.select2Option(template).click();
      await this.field('service_description').fill(name);
      await this.page
        .locator('#validForm input[name^="submit"]')
        .first()
        .click();
      await expect(this.addLink).toBeVisible({ timeout: 30_000 });
    });
  }
}
