import {
  expect,
  type FrameLocator,
  type Locator,
  type Page,
  test
} from '@playwright/test';

import { BasePage } from './BasePage';

/** A single host macro (name + value), mirroring the Cypress `Macro` shape. */
export interface Macro {
  name: string;
  value: string;
}

/**
 * Page Object for the legacy "Configuration > Hosts" macros form
 * (`main.php?p=60101`).
 *
 * This is a legacy PHP page rendered inside the React shell's `#main-content`
 * iframe, so — exactly like {@link CustomViewsPage} — it is driven through a
 * Playwright frame locator (the Cypress `cy.getIframeBody()` equivalent).
 *
 * It ports the host-macros custom commands from the Cypress feature
 * (`features/Macros/commands.ts` + `features/Macros/01-hosts-macros/index.ts`):
 * `fillHostBasicsInfos`, `fillMacros` and `checkMacrosFieldsValues`.
 *
 * NOTE: saving a legacy iframe form can momentarily freeze the iframe; the
 * helpers wait on the resulting listing/edit state rather than on navigation.
 */
export class MacrosPage extends BasePage {
  private readonly frame: FrameLocator;
  readonly addLink: Locator;
  readonly nameInput: Locator;
  readonly aliasInput: Locator;
  readonly addressInput: Locator;
  readonly addMacroButton: Locator;
  readonly submitButton: Locator;

  constructor(page: Page) {
    super(page);
    this.frame = page.frameLocator('iframe#main-content');
    this.addLink = this.frame
      .locator('a')
      .filter({ hasText: 'Add' })
      .first()
      .describe('Add host link');
    this.nameInput = this.frame
      .locator('input[name="host_name"]')
      .describe('Host name input');
    this.aliasInput = this.frame
      .locator('input[name="host_alias"]')
      .describe('Host alias input');
    this.addressInput = this.frame
      .locator('input[name="host_address"]')
      .describe('Host address input');
    this.addMacroButton = this.frame
      .locator('#macro_add')
      .describe('Add macro button');
    this.submitButton = this.frame
      .locator('input.btc.bt_success[name^="submit"]')
      .first()
      .describe('Save button');
  }

  /** The macro name input at a given row index (`#macroInput_<i>`). */
  macroNameInput(index: number): Locator {
    return this.frame
      .locator(`#macroInput_${index}`)
      .describe(`macro name input #${index}`);
  }

  /** The macro value input at a given row index (`#macroValue_<i>`). */
  macroValueInput(index: number): Locator {
    return this.frame
      .locator(`#macroValue_${index}`)
      .describe(`macro value input #${index}`);
  }

  /** The "is password" toggle for a macro row (`#macroPassword_<i>`). */
  macroPasswordToggle(index: number): Locator {
    return this.frame
      .locator(`#macroPassword_${index}`)
      .describe(`macro password toggle #${index}`);
  }

  /** A host listing link, located by its name (rendered as an `<a>` link). */
  hostListLink(name: string): Locator {
    return this.frame
      .locator('a')
      .filter({ hasText: name })
      .first()
      .describe(`host listing link "${name}"`);
  }

  /** Open the legacy hosts listing page and wait for the Add link. */
  async open(): Promise<void> {
    await this.goto('/centreon/main.php?p=60101');
    await expect(this.addLink).toBeVisible({ timeout: 30_000 });
  }

  /** Click "Add" and wait for the host creation form. */
  async clickAddHost(): Promise<void> {
    await test.step('Open the new-host form', async () => {
      await this.addLink.click();
      await expect(this.nameInput).toBeVisible({ timeout: 30_000 });
    });
  }

  /** Fill the mandatory host fields (name, alias, address). */
  async fillHostBasics(
    name: string,
    alias: string,
    address: string
  ): Promise<void> {
    await test.step(`Fill host basics for "${name}"`, async () => {
      await this.nameInput.fill(name);
      await this.aliasInput.fill(alias);
      await this.addressInput.fill(address);
    });
  }

  /**
   * Add one normal macro and one password macro, mirroring Cypress `fillMacros`
   * with `isUpdate = false`: two macro rows are created, both names + values are
   * filled, then row 1 is flagged as a password macro.
   */
  async addNormalAndPasswordMacros(
    normalMacro: Macro,
    passwordMacro: Macro
  ): Promise<void> {
    await test.step('Add one normal macro and one password macro', async () => {
      await this.addMacroButton.click();
      await this.addMacroButton.click();
      await this.macroNameInput(0).fill(normalMacro.name);
      await this.macroNameInput(1).fill(passwordMacro.name);
      await this.macroValueInput(0).fill(normalMacro.value);
      await this.macroValueInput(1).fill(passwordMacro.value);
      // Flag the second macro as a password macro.
      await this.macroPasswordToggle(1).click({ force: true });
    });
  }

  /** Submit the host form. */
  async save(): Promise<void> {
    await test.step('Save the host', async () => {
      await this.submitButton.click();
    });
  }

  /** Open an existing host from the listing for editing. */
  async openHostForEditing(name: string): Promise<void> {
    await test.step(`Open host "${name}" for editing`, async () => {
      await expect(this.hostListLink(name)).toBeVisible({ timeout: 30_000 });
      await this.hostListLink(name).click();
      await expect(this.nameInput).toBeVisible({ timeout: 30_000 });
    });
  }

  /**
   * Assert the saved macro fields, mirroring Cypress `checkMacrosFieldsValues`:
   * the normal macro keeps its name + value, the password macro keeps its name
   * and its value is masked (only `*` characters).
   */
  async expectSavedMacros(
    normalMacro: Macro,
    passwordMacro: Macro
  ): Promise<void> {
    await test.step('Verify the saved macros on the host form', async () => {
      await expect(this.macroNameInput(0)).toHaveValue(normalMacro.name);
      await expect(this.macroValueInput(0)).toHaveValue(normalMacro.value);
      await expect(this.macroNameInput(1)).toHaveValue(passwordMacro.name);
      await expect(this.macroValueInput(1)).toHaveValue(/^\*+$/);
    });
  }
}
