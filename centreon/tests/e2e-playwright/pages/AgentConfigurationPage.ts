import { expect, type Locator, type Page, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Minimal description of a Telegraf agent configuration, mirroring the
 * `telegraf*` fixtures used by the Cypress `Agent-configuration` feature.
 *
 * The Telegraf type is the happy path that needs no CMA authentication token:
 * a poller plus the OTLP receiver certificates/keys are enough.
 */
export interface TelegrafAgentSeed {
  name: string;
  pollerName: string;
  /** Connector (poller-side) public certificate, CA and private key. */
  publicCertificateFileName: string;
  caFileName: string;
  privateKeyFileName: string;
  /** OTLP receiver (server-side) certificate. */
  certificateFileName: string;
}

/**
 * Page Object for the modern React "Agents Configuration" page
 * (`/configuration/pollers/agent-configurations`).
 *
 * It wraps the creation modal (agent type, name, pollers autocomplete, the two
 * connector/receiver certificate groups), the listing rows and the delete flow
 * with its confirmation modal that the Cypress `Agent-configuration` feature
 * drives. Only the reliable single-user Telegraf flow is modelled here.
 */
export class AgentConfigurationPage extends BasePage {
  readonly addButton: Locator;
  readonly submitButton: Locator;
  readonly dialog: Locator;
  readonly agentTypeInput: Locator;
  readonly nameInput: Locator;
  readonly pollersInput: Locator;
  readonly pollersHeading: Locator;
  readonly portInput: Locator;

  constructor(page: Page) {
    super(page);
    this.addButton = page
      .getByRole('button', { exact: true, name: 'Add' })
      .first()
      .describe('Add agent configuration button');
    this.submitButton = page
      .getByTestId('submit')
      .describe('Create agent configuration button');
    this.dialog = page.getByRole('dialog').describe('agent configuration form');
    this.agentTypeInput = this.dialog
      .getByLabel('Agent type', { exact: true })
      .describe('Agent type select');
    this.nameInput = this.dialog
      .getByLabel('Name', { exact: true })
      .describe('Agent name field');
    this.pollersInput = this.dialog
      .getByLabel('Pollers', { exact: true })
      .describe('Pollers autocomplete');
    this.pollersHeading = this.dialog
      .getByRole('heading', { name: 'Pollers' })
      .describe('Pollers section heading');
    this.portInput = this.dialog
      .getByLabel('Port', { exact: true })
      .describe('OTLP receiver port field');
  }

  private waitForListing(): Promise<unknown> {
    return this.page.waitForResponse(
      (response) =>
        response.url().includes('/configuration/agent-configurations') &&
        response.request().method() === 'GET'
    );
  }

  /** Open the listing and wait for the page to be ready. */
  async open(): Promise<void> {
    await this.goto('/centreon/configuration/pollers/agent-configurations');
    await expect(this.addButton).toBeVisible({ timeout: 30_000 });
  }

  /** Pick an option (by visible text) from a just-opened autocomplete/select. */
  private async pickOption(text: string): Promise<void> {
    await this.page.getByRole('option', { exact: true, name: text }).click();
  }

  /**
   * The two certificate-group fields (Public certificate / CA / Private key)
   * appear twice in the Telegraf form: index 0 is the connector (poller) group,
   * index 1 is the OTLP receiver group. Locate them by label + position.
   */
  private fieldAt(label: string, index: number): Locator {
    return this.dialog.getByLabel(label, { exact: true }).nth(index);
  }

  /**
   * Fill and submit the creation modal for a Telegraf agent configuration.
   * Waits for the new row to appear in the listing.
   */
  async createTelegrafAgent(seed: TelegrafAgentSeed): Promise<void> {
    await test.step(`Create Telegraf agent "${seed.name}"`, async () => {
      await this.addButton.click();
      await expect(this.dialog).toBeVisible();
      await expect(
        this.dialog.getByText('Add agent configuration')
      ).toBeVisible();

      await this.agentTypeInput.click();
      await this.pickOption('Telegraf');

      await this.nameInput.fill(seed.name);

      await this.pollersInput.click();
      await this.pickOption(seed.pollerName);
      // Click the section heading to close the pollers dropdown.
      await this.pollersHeading.click();

      // Connector (poller-side) certificate group — index 0.
      await this.fieldAt('Public certificate (.crt, .cert, .cer)', 0).fill(
        seed.publicCertificateFileName
      );
      await this.fieldAt('CA (.crt, .cert, .cer)', 0).fill(seed.caFileName);
      await this.fieldAt('Private key (.key)', 0).fill(seed.privateKeyFileName);

      // Port defaults to 1443 for the OTLP receiver.
      await expect(this.portInput).toHaveValue('1443');

      // OTLP receiver (server-side) certificate group — index 1.
      await this.fieldAt('Public certificate (.crt, .cert, .cer)', 1).fill(
        seed.certificateFileName
      );
      await this.fieldAt('Private key (.key)', 1).fill(seed.privateKeyFileName);

      await this.submitButton.click();
      await expect(this.dialog).toHaveCount(0);
    });
  }

  /** A listing row located by the agent configuration name. */
  row(name: string): Locator {
    return this.page
      .getByRole('row')
      .filter({ hasText: name })
      .describe(`agent configuration row "${name}"`);
  }

  /**
   * Delete an agent configuration from its row, confirming (or cancelling) the
   * confirmation modal.
   */
  async deleteAgent(name: string, { confirm = true } = {}): Promise<void> {
    await test.step(`Delete agent configuration "${name}"`, async () => {
      await this.row(name).getByLabel('Delete', { exact: true }).click();

      const confirmationDialog = this.page
        .getByRole('dialog')
        .filter({ hasText: 'Delete agent' })
        .describe('delete confirmation modal');
      await expect(confirmationDialog).toBeVisible();

      if (confirm) {
        await confirmationDialog
          .getByRole('button', { exact: true, name: 'Delete' })
          .click();
      } else {
        await confirmationDialog
          .getByRole('button', { exact: true, name: 'Cancel' })
          .click();
      }
    });
  }
}
