import {
  expect,
  type FrameLocator,
  type Locator,
  type Page,
  test
} from '@playwright/test';

import { BasePage } from './BasePage';

export interface SnmpTrapSeed {
  name: string;
  oid: string;
  vendor: string;
  output: string;
  ruleString: string;
  regexp: string;
  severity: string;
}

/**
 * Page Object for the legacy "SNMP traps" configuration page
 * (`main.php?p=61701`), rendered in the React shell's `#main-content` iframe and
 * driven through a Playwright frame locator (the `cy.getIframeBody()` equivalent).
 */
export class SnmpTrapsPage extends BasePage {
  private readonly frame: FrameLocator;
  readonly searchInput: Locator;
  readonly addButton: Locator;
  readonly nameInput: Locator;
  readonly oidInput: Locator;
  readonly outputInput: Locator;
  readonly vendorContainer: Locator;
  readonly matchingRulesAdd: Locator;
  readonly ruleStringInput: Locator;
  readonly ruleRegexpInput: Locator;
  readonly ruleStatusSelect: Locator;
  readonly submitButton: Locator;
  readonly bulkActionSelect: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.searchInput = this.frame
      .locator('input[name="searchT"]')
      .describe('SNMP traps listing search box');
    this.addButton = this.frame
      .locator('a.bt_success')
      .filter({ hasText: 'Add' })
      .first()
      .describe('Add SNMP trap button');
    this.nameInput = this.frame.locator('input[name="traps_name"]');
    this.oidInput = this.frame.locator('input[name="traps_oid"]');
    this.outputInput = this.frame.locator('input[name="traps_args"]');
    this.vendorContainer = this.frame
      .locator('span[id*="-manufacturer_id-container"]')
      .describe('Vendor select2 picker');
    this.matchingRulesAdd = this.frame.locator('div#matchingrules_add');
    this.ruleStringInput = this.frame.locator('input#rule_0');
    this.ruleRegexpInput = this.frame.locator('input#regexp_0');
    this.ruleStatusSelect = this.frame.locator('select#rulestatus_0');
    this.submitButton = this.frame.locator(
      '#validForm p.oreonbutton .btc.bt_success[name="submitA"]'
    );
    this.bulkActionSelect = this.frame.locator('select[name="o2"]');
  }

  /** A listing link located by the trap name. */
  trapLink(name: string): Locator {
    return this.frame
      .getByRole('link', { exact: true, name })
      .first()
      .describe(`SNMP trap link "${name}"`);
  }

  /** Open the SNMP traps listing and wait for it to be ready. */
  async open(): Promise<void> {
    await this.goto('/centreon/main.php?p=61701');
    await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
  }

  /** Open the creation form, fill it (incl. an advanced matching rule) and save. */
  async createTrap(seed: SnmpTrapSeed): Promise<void> {
    await test.step(`Create SNMP trap "${seed.name}"`, async () => {
      await this.addButton.click();
      await expect(this.nameInput).toBeVisible({ timeout: 30_000 });

      await this.nameInput.fill(seed.name);
      await this.oidInput.fill(seed.oid);

      // Vendor: select2 picker.
      await this.vendorContainer.click();
      await this.frame
        .locator('li.select2-results__option', { hasText: seed.vendor })
        .first()
        .click();

      await this.outputInput.fill(seed.output);

      // Advanced matching rule.
      await this.matchingRulesAdd.click();
      await this.ruleStringInput.fill(seed.ruleString);
      await this.ruleRegexpInput.fill(seed.regexp);
      await this.ruleStatusSelect.selectOption({ label: seed.severity });

      await this.submitButton.click();
      await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
    });
  }

  /** Delete a trap from the listing via the bulk-action select. */
  async deleteTrap(name: string): Promise<void> {
    await test.step(`Delete SNMP trap "${name}"`, async () => {
      const row = this.frame
        .locator('tr')
        .filter({ hasText: name })
        .first()
        .describe(`SNMP trap row "${name}"`);
      // The checkbox input is hidden behind a styled md-checkbox wrapper.
      await row
        .locator('input[type="checkbox"]')
        .first()
        .check({ force: true });

      // The legacy bulk-action select does not submit on its own: set "Delete"
      // and submit the form atomically (separate steps race with re-renders).
      await this.bulkActionSelect.evaluate((node) => {
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
        (window as unknown as { setO: (v: string) => void }).setO(
          form.elements.o2.value
        );
        form.submit();
      });
    });
  }
}
