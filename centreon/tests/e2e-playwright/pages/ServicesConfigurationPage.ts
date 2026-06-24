import {
  expect,
  type FrameLocator,
  type Locator,
  type Page,
  test
} from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object for the legacy "Services by host" configuration page
 * (`main.php?p=60201`).
 *
 * This is a legacy PHP page rendered inside the React shell's `#main-content`
 * iframe (the Cypress `cy.getIframeBody()` / `cy.waitForElementInIframe(...)`
 * equivalent), so it is driven through a Playwright frame locator exactly like
 * `CustomViewsPage`. The host is expected to exist already (seeded via CLAPI);
 * this page only creates and deletes the service through the legacy form and
 * listing.
 *
 * Note: saving these legacy forms reloads the iframe and can be slow; the helper
 * methods wait on the resulting listing/form state rather than on network.
 */
export class ServicesConfigurationPage extends BasePage {
  private static readonly listRoute = '/centreon/main.php?p=60201';
  private static readonly addRoute = '/centreon/main.php?p=60201&o=a';

  private readonly frame: FrameLocator;
  readonly addButton: Locator;
  readonly descriptionInput: Locator;
  readonly hostSelectContainer: Locator;
  readonly templateSelectContainer: Locator;
  readonly saveButton: Locator;
  readonly searchHostInput: Locator;
  readonly searchButton: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.addButton = this.frame
      .locator('a', { hasText: 'Add' })
      .first()
      .describe('Add service button');
    this.descriptionInput = this.frame
      .locator('input[name="service_description"]')
      .describe('Service description input');
    // The host field is a multi-select (`service_hPars[]`), so target its
    // select2 widget via the select's adjacent container rather than a
    // single-select container id.
    this.hostSelectContainer = this.frame
      .locator('select[name="service_hPars[]"] + span.select2-container')
      .describe('Host select2 picker');
    this.templateSelectContainer = this.frame
      .locator('span#select2-service_template_model_stm_id-container')
      .describe('Service template select2 picker');
    this.saveButton = this.frame
      .locator('input.btc.bt_success[name^="submit"]')
      .first()
      .describe('Save service button');
    this.searchHostInput = this.frame
      .locator('input[name="searchH"]')
      .describe('Host search input');
    this.searchButton = this.frame
      .locator('input.btc.bt_success[name="Search"]')
      .describe('Search button');
  }

  /**
   * A select2 result option, rendered in the dropdown after a select2 picker is
   * opened. Lives at the document level inside the iframe body.
   */
  private select2Option(text: string): Locator {
    return this.frame
      .locator('li.select2-results__option', { hasText: text })
      .first()
      .describe(`select2 option "${text}"`);
  }

  /** A listing row link located by its visible service description text. */
  serviceListLink(name: string): Locator {
    return this.frame
      .locator('a', { hasText: name })
      .first()
      .describe(`service listing link "${name}"`);
  }

  /** Open the "Services by host" listing and wait for the iframe to render. */
  async open(): Promise<void> {
    await this.goto(ServicesConfigurationPage.listRoute);
    await expect(this.searchHostInput).toBeVisible({ timeout: 30_000 });
  }

  /** Open the "Add a service by host" form and wait for the form to render. */
  async openAddForm(): Promise<void> {
    await this.goto(ServicesConfigurationPage.addRoute);
    await expect(this.descriptionInput).toBeVisible({ timeout: 30_000 });
  }

  /**
   * Create a service attached to an existing host, picking the host and a
   * service template, then saving the legacy form. Waits for the listing to
   * show the new service after the save-triggered reload.
   */
  async createService({
    host,
    name,
    template = 'Ping-LAN'
  }: {
    host: string;
    name: string;
    template?: string;
  }): Promise<void> {
    await test.step(`Create service "${name}" on host "${host}"`, async () => {
      await this.openAddForm();

      // Host: select2 autocomplete.
      await this.hostSelectContainer.click();
      await this.select2Option(host).click();

      // Service template: select2 autocomplete.
      await this.templateSelectContainer.click();
      await this.select2Option(template).click();

      await this.descriptionInput.fill(name);

      await this.saveButton.click();

      // After the save the page returns to the listing.
      await expect(this.searchHostInput).toBeVisible({ timeout: 30_000 });
    });
  }

  /** Filter the listing by host so the host's services are displayed. */
  async filterByHost(host: string): Promise<void> {
    await test.step(`Filter services by host "${host}"`, async () => {
      await this.searchHostInput.fill(host);
      await this.searchButton.click();
      await expect(this.searchHostInput).toHaveValue(host, {
        timeout: 15_000
      });
    });
  }

  /**
   * Delete a service from the listing through the bottom bulk-action selector.
   *
   * The legacy listing tags the per-row checkbox with the resource id; the
   * Cypress flow checks every row whose left column contains the host name then
   * fires the "Delete" bulk action. Here we tick the row matching the service
   * name and run the same bulk action.
   */
  async deleteService(name: string): Promise<void> {
    await test.step(`Delete service "${name}"`, async () => {
      const row = this.frame
        .locator('tr.list_one, tr.list_two')
        .filter({ hasText: name })
        .first()
        .describe(`service listing row "${name}"`);
      // The checkbox input is hidden behind a styled md-checkbox wrapper.
      await row
        .locator('input[type="checkbox"]')
        .first()
        .check({ force: true });

      // Bottom toolbar "More actions" select. The legacy select does not submit
      // on its own, so set "Delete" and submit the form atomically in one
      // evaluate (separate steps race with the iframe re-rendering).
      const bulkSelect = this.frame
        .locator('td.Toolbar_TDSelectAction_Bottom select')
        .first()
        .describe('bulk action select (bottom)');
      await bulkSelect.evaluate((node) => {
        const select = node as HTMLSelectElement;
        const option = Array.from(select.options).find(
          (o) => o.text.trim() === 'Delete'
        );
        if (option) {
          select.value = option.value;
        }
        const form = select.form as HTMLFormElement & {
          elements: Record<string, HTMLInputElement>;
        };
        // `setO` is a legacy global that records the chosen bulk operation.
        (window as unknown as { setO: (v: string) => void }).setO(
          form.elements.o2.value
        );
        form.submit();
      });

      await expect(this.serviceListLink(name)).toHaveCount(0, {
        timeout: 30_000
      });
    });
  }
}
