import { expect, type Locator, type Page, test } from '@playwright/test';

type ShareRole = 'Editor' | 'Viewer';

/**
 * Page Object of the "Manage access rights" dialog of a dashboard, opened
 * from its card in the library.
 */
export class DashboardSharingDialog {
  private readonly dialog: Locator;

  constructor(private readonly page: Page) {
    this.dialog = page.getByRole('dialog');
  }

  /** Open the dialog of a dashboard of the library. */
  async open(dashboard: string): Promise<void> {
    await this.page
      .locator('[class*="dataTableItem"]')
      .filter({ hasText: dashboard })
      .getByTestId('Share with contacts')
      .click();
    await expect(this.dialog.getByText('Manage access rights')).toBeVisible();
  }

  // The role testids sit on hidden inputs: their select takes the click.
  private async pickRole(select: Locator, role: ShareRole): Promise<void> {
    await select.locator('..').click();
    await this.page
      .getByRole('listbox')
      .getByText(role, { exact: true })
      .click();
  }

  /** Add a contact, or a contact group, with a role. */
  async add(
    name: string,
    role: ShareRole,
    { group = false } = {}
  ): Promise<void> {
    await test.step(`Share with ${name} as ${role}`, async () => {
      if (group) {
        await this.dialog.getByText('Contact group', { exact: true }).click();
      }
      await this.dialog
        .getByLabel(group ? 'Add a contact group' : 'Add a contact', {
          exact: true
        })
        .click();
      await this.page.getByRole('option', { name }).click();
      await this.pickRole(this.dialog.getByTestId('add_role'), role);
      await this.dialog.getByTestId('add').click();
      await expect(this.dialog.getByTestId(`role-${name}`)).toBeVisible();
    });
  }

  /** Change the role of a contact already in the list. */
  async setRole(name: string, role: ShareRole): Promise<void> {
    await test.step(`Make ${name} ${role}`, () =>
      this.pickRole(this.dialog.getByTestId(`role-${name}`), role));
  }

  /** Remove a contact from the list. */
  async remove(name: string): Promise<void> {
    await test.step(`Remove ${name}`, async () => {
      await this.dialog.getByTestId(`remove-${name}`).click();
      await expect(this.dialog.locator('[data-state="removed"]')).toBeVisible();
    });
  }

  /** Save the access rights and wait for the dialog to close. */
  async save(): Promise<void> {
    const saved = this.page.waitForResponse(
      (response) =>
        response.url().includes('/shares') &&
        response.request().method() === 'PUT'
    );
    await this.dialog.getByLabel('Save', { exact: true }).click();
    expect((await saved).ok()).toBeTruthy();
    await this.dialog
      .getByLabel('close', { exact: true })
      .click()
      .catch(() => undefined);
    await expect(this.dialog).toHaveCount(0);
  }
}
