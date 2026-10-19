import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

type BulkAction = 'Delete' | 'Duplicate';

/**
 * Page Object shared by the legacy PHP configuration listings.
 *
 * It opens `main.get.php?p=<topology page>`, the document the React shell
 * shows in its `#main-content` iframe, directly: the shell reloads the iframe
 * after each form submission, which detaches the elements a test acts on.
 *
 * The listings share the same markup: rows `tr.list_one` / `tr.list_two`
 * whose name link opens the object form, a `More actions` select (`o1`)
 * applying a bulk action to the ticked rows behind a `confirm()`, and a form
 * saved by its `submitA` (add) or `submitC` (change) button.
 */
export class LegacyListingPage extends BasePage {
  readonly addLink: Locator;
  private readonly bulkActions: Locator;
  private readonly saveButton: Locator;

  constructor(
    page: Page,
    private readonly topologyPage: string
  ) {
    super(page);
    this.addLink = page
      .getByRole('link', { exact: true, name: 'Add' })
      .first()
      .describe('Add link');
    this.bulkActions = page
      .locator('select[name="o1"]')
      .describe('More actions select');
    this.saveButton = page
      .locator('#validForm input[name^="submit"]')
      .first()
      .describe('Save button');
  }

  // Read-only users get the table without the toolbar actions.
  private async listingLoaded(): Promise<void> {
    await expect(this.page.locator('table.ListTable').first()).toBeVisible({
      timeout: 30_000
    });
  }

  /** Open the listing, narrowed to the objects matching `search`. */
  async open(search = ''): Promise<void> {
    await this.goto(`/centreon/main.get.php?p=${this.topologyPage}`);
    await this.listingLoaded();
    if (search) {
      // The first search field of each listing filters on the object name.
      const field = this.page.locator('input[name^="search"]').first();
      await field.fill(search);
      await Promise.all([this.page.waitForEvent('load'), field.press('Enter')]);
      await this.listingLoaded();
    }
  }

  /** The listing row whose name link reads exactly `name`. */
  row(name: string): Locator {
    return this.page
      .locator('tr.list_one, tr.list_two')
      .filter({ has: this.page.getByRole('link', { exact: true, name }) })
      .describe(`row "${name}"`);
  }

  /** Open the form of an object from its row link. */
  async openForm(name: string): Promise<void> {
    await this.row(name)
      .getByRole('link', { exact: true, name })
      .first()
      .click();
    await expect(this.saveButton).toBeVisible({ timeout: 30_000 });
  }

  /** A form field by its `name` attribute (help tooltips reuse the name). */
  field(name: string): Locator {
    return this.page
      .locator(`:is(input, select, textarea)[name="${name}"]`)
      .first();
  }

  /** Change text fields of an object and save its form. */
  async edit(name: string, fields: Record<string, string>): Promise<void> {
    await test.step(`Edit "${name}"`, async () => {
      await this.openForm(name);
      for (const [field, value] of Object.entries(fields)) {
        await this.field(field).fill(value);
      }
      await this.saveButton.click();
      await this.listingLoaded();
    });
  }

  /** Flip the status of an object from the icon of its row. */
  async toggleStatus(name: string): Promise<void> {
    await test.step(`Toggle the status of "${name}"`, async () => {
      const badge = this.row(name).locator('span.badge').first();
      const status = await badge.innerText();
      await this.row(name)
        .locator('img[alt="Disabled"], img[alt="Enabled"]')
        .first()
        .click();
      await expect(badge).not.toHaveText(status);
    });
  }

  /**
   * Post a listing action as the form would, with a CSRF token read from the
   * listing: a valid token makes sure the server reaches its ACL checks. The
   * token is single-use, so each call reads a fresh one.
   */
  async post(fields: Record<string, string>): Promise<number> {
    await this.open();
    const token = await this.page
      .locator('form[name="form"] input[name="centreon_token"]')
      .first()
      .inputValue();
    const response = await this.page.request.post(
      `/centreon/main.get.php?p=${this.topologyPage}`,
      { form: { centreon_token: token, ...fields } }
    );

    return response.status();
  }

  /** Tick rows and apply a bulk action, accepting its confirmation. */
  async bulk(action: BulkAction, names: Array<string>): Promise<void> {
    await test.step(`${action} ${names.join(', ')}`, async () => {
      for (const name of names) {
        await this.row(name)
          .locator('td.ListColPicker input[type="checkbox"]')
          // Drawn by an md-checkbox pseudo-element: the input has no box.
          .check({ force: true });
      }
      this.page.once('dialog', (dialog) => void dialog.accept());
      await Promise.all([
        this.page.waitForEvent('load'),
        this.bulkActions.selectOption({ label: action })
      ]);
      await this.listingLoaded();
    });
  }
}
