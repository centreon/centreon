import { expect, type Locator, type Page, test } from '@playwright/test';

import { ResourcesStatusPage } from './ResourcesStatusPage';

/**
 * Page Object for the "Set downtime" action on the monitoring "Resources
 * status" listing (`/monitoring/resources`).
 *
 * It extends {@link ResourcesStatusPage} so the spec reuses the shared
 * `open()` / `search()` / `selectResource()` / `snackbar()` building blocks, and
 * only adds the downtime-specific toolbar button and dialog confirmation,
 * mirroring the Cypress `Resources-status/03-downtime` step definitions:
 *   - toolbar button: `data-testid="mainSetDowntime"`
 *   - dialog confirm: the button labelled "Set downtime"
 *     (`labelConfirm={t(labelSetDowntime)}` on the shared Dialog)
 *   - feedback: the "Downtime command sent" snackbar
 *   - request: POST `/monitoring/resources/downtime`
 *
 * The downtime form pre-fills its required fields (start "now", a default
 * comment), so the happy path only needs to confirm — exactly like the Cypress
 * scenario which clicks confirm without typing anything.
 */
export class DowntimePage extends ResourcesStatusPage {
  private readonly setDowntimeButton: Locator;

  constructor(page: Page) {
    super(page);
    this.setDowntimeButton = page
      .getByTestId('mainSetDowntime')
      .describe('Set downtime toolbar button');
  }

  /** The confirm button of the downtime dialog (accessible name "Set downtime"). */
  private get confirmButton(): Locator {
    return this.page
      .getByRole('dialog')
      .getByLabel('Set downtime', { exact: true })
      .describe('Downtime dialog confirm button');
  }

  /**
   * Open the downtime dialog for the current selection and confirm with the
   * default settings (start "now", default duration and comment). Waits for the
   * downtime request to be accepted, mirroring the Cypress `@postSaveDowntime`
   * wait.
   */
  async setDowntimeOnSelection(): Promise<void> {
    await test.step('Set a downtime on the selected resource(s)', async () => {
      await expect(this.setDowntimeButton).toBeEnabled({ timeout: 20_000 });
      await this.setDowntimeButton.click();

      const downtimeSent = this.page.waitForResponse(
        (response) =>
          response.url().includes('/monitoring/resources/downtime') &&
          response.request().method() === 'POST'
      );
      await this.confirmButton.click();
      await downtimeSent;
    });
  }
}
