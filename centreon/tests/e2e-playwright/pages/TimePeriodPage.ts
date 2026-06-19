import {
  expect,
  type FrameLocator,
  type Locator,
  type Page,
  test
} from '@playwright/test';

import type {
  TimePeriodException,
  TimePeriodSeed
} from '../fixtures/time-period';
import { BasePage } from './BasePage';

/**
 * Page Object for the legacy "Time periods" configuration page
 * (`main.php?p=60304`).
 *
 * Like {@link CustomViewsPage}, this is a legacy PHP page rendered inside the
 * React shell's `#main-content` iframe, so it is driven through a Playwright
 * frame locator (the Cypress `cy.getIframeBody()` equivalent).
 *
 * NOTE: saving a legacy Smarty form reloads the whole iframe; the page object
 * waits for the listing search box (`input[name="searchTP"]`) to reappear as
 * the "save succeeded" signal.
 */
export class TimePeriodPage extends BasePage {
  private readonly frame: FrameLocator;
  readonly searchInput: Locator;
  readonly addButton: Locator;
  readonly nameInput: Locator;
  readonly aliasInput: Locator;
  readonly exceptionsTab: Locator;
  readonly submitButton: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.searchInput = this.frame
      .locator('input[name="searchTP"]')
      .describe('time periods listing search box');
    this.addButton = this.frame
      .locator('a.bt_success')
      .filter({ hasText: 'Add' })
      .describe('Add time period button');
    this.nameInput = this.frame
      .locator('input[name="tp_name"]')
      .describe('time period name input');
    this.aliasInput = this.frame
      .locator('input[name="tp_alias"]')
      .describe('time period alias input');
    this.exceptionsTab = this.frame.locator('li#c2').describe('exceptions tab');
    this.submitButton = this.frame
      .locator('#validForm p.oreonbutton .btc.bt_success[name="submitA"]')
      .describe('save time period button');
  }

  /** Day time-range input, located by its form field name (`tp_<day>`). */
  dayInput(day: string): Locator {
    return this.frame
      .locator(`input[name="tp_${day}"]`)
      .describe(`time range input for ${day}`);
  }

  /** The "+ Add new entry" link inside the exceptions tab. */
  private get addExceptionLink(): Locator {
    return this.frame
      .locator('a, span, button')
      .filter({ hasText: '+ Add new entry' })
      .first()
      .describe('add exception entry link');
  }

  private exceptionDateInput(index: number): Locator {
    return this.frame
      .locator(`input#exceptionInput_${index}`)
      .describe(`exception date input ${index}`);
  }

  private exceptionTimeRangeInput(index: number): Locator {
    return this.frame
      .locator(`input#exceptionTimerange_${index}`)
      .describe(`exception time range input ${index}`);
  }

  /** A row in the listing, located by the time period name it contains. */
  row(name: string): Locator {
    return this.frame
      .locator('tr[class*="list_"]')
      .filter({ hasText: name })
      .describe(`time period row "${name}"`);
  }

  /** Open the listing page and wait for the search box. */
  async open(): Promise<void> {
    await this.goto('/centreon/main.php?p=60304');
    await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
  }

  /** Click "Add" and wait for the creation form. */
  async startCreation(): Promise<void> {
    await test.step('Open the time period creation form', async () => {
      await this.addButton.click();
      await expect(this.nameInput).toBeVisible({ timeout: 30_000 });
    });
  }

  /** Fill name, alias, every weekday time range and the exceptions. */
  async fillForm(seed: TimePeriodSeed): Promise<void> {
    await test.step(`Fill time period "${seed.name}"`, async () => {
      await this.nameInput.fill(seed.name);
      await this.aliasInput.fill(seed.alias);

      for (const { day, timeRange } of seed.days) {
        await this.dayInput(day).fill(timeRange);
      }

      await this.exceptionsTab.click();
      await this.addExceptions(seed.exceptions);
    });
  }

  private async addExceptions(
    exceptions: Array<TimePeriodException>
  ): Promise<void> {
    for (const [index, exception] of exceptions.entries()) {
      // A fresh entry must be added before each exception row exists.
      await this.addExceptionLink.click();
      await this.exceptionDateInput(index).fill(exception.date);
      await this.exceptionTimeRangeInput(index).fill(exception.timeRange);
    }
  }

  /** Submit the form and wait for the listing to come back. */
  async submit(): Promise<void> {
    await test.step('Save the time period', async () => {
      await this.submitButton.click();
      await expect(this.searchInput).toBeVisible({ timeout: 30_000 });
    });
  }
}
