import type { Page } from '@playwright/test';

import pollers from '../../cypress/fixtures/ACC/pollers-vmware.json';
import installationCommand from '../../cypress/fixtures/AgentConfigurations/installation-command.json';
import listing from '../../cypress/fixtures/AgentConfigurations/listing.json';
import {
  labelAction,
  labelAdd,
  labelAddAgentConfiguration,
  labelAddAHost,
  labelAgentConfigurationCreated,
  labelAgentConfigurationUpdated,
  labelAgentsConfigurations,
  labelAgentType,
  labelByPoller,
  labelCACommonName,
  labelCaCertificate,
  labelCancel,
  labelClear,
  labelCommand,
  labelCommandWarning,
  labelConfigurationServer,
  labelConnectionInitiated,
  labelDelete,
  labelDeleteAgent,
  labelDeletePoller,
  labelDNSIP,
  labelEncryptionLevel,
  labelExecuteTheScript,
  labelGenerateInstallationCommand,
  labelInsecure,
  labelInvalidExtension,
  labelInvalidPath,
  labelMonitoredHosts,
  labelName,
  labelNoTLS,
  labelOTLPReceiver,
  labelPoller,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelRelativePathAreNotAllowed,
  labelRequired,
  labelSave,
  labelSearch,
  labelSelectExistingCMAToken,
  labelSelectExistingCMATokens,
  labelSelectHost,
  labelSelectOperatingSystem,
  labelSelectPollerThatWillMonitor,
  labelTLS,
  labelWarningEncryptionLevelCMA,
  labelWarningEncryptionLevelTelegraf,
  labelWelcomeToTheAgentsConfigurationPage
} from '../../www/front_src/src/AgentConfiguration/translatedLabels';
import { labelPortExpectedAtMost } from '../../www/front_src/src/VaultConfiguration/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from AgentConfiguration/Specs/AgentConfigurations.
const storyId = 'AgentConfiguration/Page/Default';
const endpoint = './api/latest/configuration/agent-configurations';

const windowsCommandLine =
  'installcma.ps /FINGERPRINT=wwwww  /COMPONENTS=agent,plugins /HOST=host_1 /ENDPOINT=https://central/centreon:4318';
const linuxCommandLine =
  'installcma.ps /FINGERPRINT=lllllll  /COMPONENTS=agent,plugins /HOST=host_1 /ENDPOINT=https://central/centreon:4317';

const agentConfiguration = {
  configuration: {
    conf_certificate: '/sub/test.crt',
    conf_private_key: 'test.key',
    conf_server_port: 9090,
    otel_ca_certificate: 'test.crt',
    otel_private_key: 'test.key',
    otel_public_certificate: 'test.cer',
    otel_server_address: '127.0.0.1',
    otel_server_port: 8080,
    tokens: [
      { creator_id: 1, name: 'token 1' },
      { creator_id: 2, name: 'token 2' }
    ]
  },
  connection_mode: 'secure',
  id: 1,
  name: 'agent',
  pollers: [
    { id: 1, name: 'poller 1' },
    { id: 2, name: 'poller 2' }
  ],
  type: 'telegraf'
};

interface Mocks {
  create: MockedEndpoint;
  deleteAgent: MockedEndpoint;
  deletePoller: MockedEndpoint;
  listing: MockedEndpoint;
  update: MockedEndpoint;
}

let mocks: Mocks;

const mockApis = async (
  mockApi: MockApi,
  isListingEmpty = false
): Promise<Mocks> => {
  const listingMock = await mockApi({
    endpoint,
    response: isListingEmpty
      ? { meta: { limit: 10, page: 1, total: 0 }, result: [] }
      : listing
  });
  for (const pollersEndpoint of [
    './api/latest/configuration/monitoring-servers',
    `${endpoint}/pollers`
  ]) {
    await mockApi({ endpoint: pollersEndpoint, response: pollers });
  }
  await mockApi({ endpoint: `${endpoint}/1`, response: agentConfiguration });
  await mockApi({
    endpoint: './api/latest/configuration/hosts',
    response: {
      meta: { limit: 10, page: 1, total: 1 },
      result: [{ address: '127.0.0.2', id: 1, name: 'central' }]
    }
  });
  await mockApi({
    endpoint: 'administration/tokens',
    response: {
      meta: { limit: 10, page: 1, total: 2 },
      result: [
        { creator: { id: 1, name: 'Admin' }, name: 'token 1' },
        { creator: { id: 1, name: 'Admin' }, name: 'token 2' }
      ]
    }
  });
  await mockApi({
    endpoint: `${endpoint}/installation-command/1`,
    response: installationCommand
  });

  return {
    create: await mockApi({ endpoint, method: 'POST' }),
    deleteAgent: await mockApi({ endpoint: `${endpoint}/0`, method: 'DELETE' }),
    deletePoller: await mockApi({
      endpoint: `${endpoint}/0/pollers/1`,
      method: 'DELETE'
    }),
    listing: listingMock,
    update: await mockApi({ endpoint: `${endpoint}/1`, method: 'PUT' })
  };
};

test.use({ viewport: { height: 900, width: 1280 } });

const setUp = async (
  { mockApi, mount }: { mockApi: MockApi; mount: (id: string) => unknown },
  isListingEmpty = false
): Promise<void> => {
  mocks = await mockApis(mockApi, isListingEmpty);
  await mount(storyId);
};

const listingQuery = (index = -1): string =>
  decodeURIComponent(new URL(mocks.listing.urls.at(index) ?? '').search);
const listingSearch = (): unknown =>
  JSON.parse(
    new URL(mocks.listing.urls.at(-1) ?? '').searchParams.get('search') ?? ''
  );
const initialQuery =
  '?page=1&limit=10&sort_by={"name":"asc"}&search={"$and":[]}';

const deleteButtons = (page: Page) =>
  page.locator(`button[data-testid="${labelDelete}"]`);
const filtersButton = (page: Page) => page.getByLabel('Filters');
const nameFilter = (page: Page) => page.getByTestId(labelName).nth(1);

const pickFilterPoller = async (page: Page): Promise<void> => {
  await page.getByTestId(labelPoller).click({ force: true });
  await page.getByText('poller6').click();
};
const pickFilterAgentType = async (page: Page): Promise<void> => {
  await page.getByTestId(labelAgentType).click({ force: true });
  await page.locator('[data-option-index="1"]').click();
};

const expectListingLoaded = async (page: Page): Promise<void> => {
  await expect(page.getByText('AC 0', { exact: true })).toBeVisible();
};

test.describe('Agent configurations', () => {
  test('displays a welcome label when the listing is empty', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount }, true);
    await expect(page.getByText(labelAgentsConfigurations)).toBeVisible();
    await expect(
      page.getByText(labelWelcomeToTheAgentsConfigurationPage)
    ).toBeVisible();
    await expect(
      page.locator('button', { hasText: labelAddAgentConfiguration })
    ).toBeVisible();
    await expectThemedScreenshot(page, 'agent-configurations-empty');
  });

  test('displays the listing', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    expect(listingQuery(0)).toBe(initialQuery);
    await expect(
      page.getByRole('button', { exact: true, name: labelAdd })
    ).toBeVisible();
    for (const value of [
      labelName,
      labelAgentType,
      labelPoller,
      labelAction,
      'Telegraf',
      '2 pollers',
      '0 poller'
    ]) {
      await expect(page.getByText(value).first()).toBeVisible();
    }
    await expect(deleteButtons(page)).toHaveCount(10);
    await expectThemedScreenshot(page, 'agent-configurations-listing');
  });

  test('sorts the listing when a column header is clicked', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await page.getByText(labelName, { exact: true }).first().click();
    await expect
      .poll(() => listingQuery())
      .toBe('?page=1&limit=10&sort_by={"name":"desc"}&search={"$and":[]}');
  });

  test('searches the typed name after a delay', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await page.getByPlaceholder(labelSearch).first().fill('My agent');
    await expect
      .poll(listingSearch)
      .toEqual({ $and: [{ $or: [{ name: { $rg: 'My agent' } }] }] });
  });

  test('searches with the filters when the search button is clicked', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await filtersButton(page).click();
    await pickFilterPoller(page);
    await pickFilterAgentType(page);
    await nameFilter(page).fill('My agent');
    await expectThemedScreenshot(page, 'agent-configurations-filters');
    await page.getByText(labelSearch, { exact: true }).click();
    await expect.poll(listingSearch).toEqual({
      $and: [
        { $or: [{ name: { $rg: 'My agent' } }] },
        { $or: [{ type: { $in: ['telegraf'] } }] },
        { $or: [{ 'poller.id': { $in: [6] } }] }
      ]
    });
  });

  test('removes a poller filter when its chip icon is clicked', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await filtersButton(page).click();
    await pickFilterPoller(page);
    await page.getByTestId('CancelIcon').click();
    await filtersButton(page).click();
    await expect(page.getByText('poller6')).toHaveCount(0);
  });

  test('removes an agent type filter when its chip icon is clicked', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await filtersButton(page).click();
    await pickFilterAgentType(page);
    await page.getByTestId('CancelIcon').click();
    await expect(page.getByTestId('CancelIcon')).toHaveCount(0);
  });

  test('clears the filters', async ({ mockApi, mount, page }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await filtersButton(page).click();
    // The agent type list would cover the poller field.
    await pickFilterPoller(page);
    await pickFilterAgentType(page);
    await nameFilter(page).fill('My agent');
    const before = mocks.listing.urls.length;
    await page.getByText(labelClear, { exact: true }).click();
    // Known app race: the debounced name search then resends the old filters,
    // so only the request that follows the click is checked, as in Cypress.
    await expect
      .poll(() =>
        mocks.listing.urls
          .slice(before)
          .map((url) => new URL(url).searchParams.get('search'))
      )
      .toContain('{"$and":[]}');
    await expect(nameFilter(page)).toHaveValue('');
    await expect(page.getByText('poller6')).toHaveCount(0);
  });

  const deletionMessage = (kind: 'agent' | 'poller') =>
    kind === 'agent'
      ? 'agent configuration. All parameters for this agent configuration will be deleted. This action cannot be undone.'
      : 'agent configuration. All configuration parameters for this poller will be deleted. This action cannot be undone.';

  test('cancels an agent deletion', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await deleteButtons(page).first().click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByText(labelDeleteAgent)).toBeVisible();
    await expect(dialog).toContainText('You are going to delete the');
    await expect(dialog).toContainText('AC 0');
    await expect(dialog).toContainText(deletionMessage('agent'));
    await expectThemedScreenshot(dialog, 'agent-configurations-delete-agent');
    await dialog.getByText(labelCancel).click();
    await expect(page.getByText(labelDeleteAgent)).toHaveCount(0);
    expect(mocks.deleteAgent.urls).toHaveLength(0);
  });

  test('deletes an agent once confirmed', async ({ mockApi, mount, page }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await deleteButtons(page).first().click();
    await expect(page.getByRole('dialog')).toContainText(
      deletionMessage('agent')
    );
    const calls = mocks.listing.urls.length;
    await page.getByRole('button', { exact: true, name: 'Delete' }).click();
    await expect.poll(() => mocks.deleteAgent.urls.length).toBe(1);
    await expect(page.getByText(labelDeleteAgent)).toHaveCount(0);
    await expect.poll(() => mocks.listing.urls.length).toBeGreaterThan(calls);
  });

  test('deletes a poller of an agent once confirmed', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
    await page.getByTestId('Expand 0').click();
    await expect(page.getByText('poller 1', { exact: true })).toBeVisible();
    await expect(page.getByText('poller 2', { exact: true })).toBeVisible();
    await deleteButtons(page).nth(1).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByText(labelDeletePoller)).toBeVisible();
    await expect(dialog).toContainText(deletionMessage('poller'));
    await page.getByRole('button', { exact: true, name: 'Delete' }).click();
    await expect.poll(() => mocks.deletePoller.urls.length).toBe(1);
    await expect(page.getByText(labelDeletePoller)).toHaveCount(0);
  });
});

// Fields of the modal; collapsed sections keep hidden fields with the same label.
const field = (page: Page, label: string, index = 0) =>
  page
    .getByRole('dialog')
    .getByLabel(label, { exact: true })
    .filter({ visible: true })
    .nth(index);

const openCreation = async (page: Page, agentType?: 0 | 1): Promise<void> => {
  await page.getByRole('button', { exact: true, name: labelAdd }).click();
  if (agentType !== undefined) {
    await field(page, labelAgentType).click();
    await page.locator(`[data-option-index="${agentType}"]`).click();
  }
};

const pickPoller = async (page: Page): Promise<void> => {
  await field(page, labelPollers).click();
  await page.getByText('poller1', { exact: true }).click();
  // Closes the list, which would catch the next clicks.
  await page.getByText(labelAddAgentConfiguration, { exact: true }).click();
};

const pickToken = async (page: Page, label: string): Promise<void> => {
  await field(page, label).click();
  await page.getByText('token 1', { exact: true }).click();
};

const enableByPoller = async (page: Page): Promise<void> => {
  await page.getByText(labelByPoller).click();
  await page.getByText('Enable', { exact: true }).click();
};

const pickHost = async (page: Page): Promise<void> => {
  await field(page, labelSelectHost).click();
  await page.getByText('central', { exact: true }).click();
};

const expectCreated = async (page: Page, body: unknown): Promise<void> => {
  await expect.poll(() => mocks.create.bodies).toEqual([body]);
  await expect(page.getByText(labelAgentConfigurationCreated)).toBeVisible();
};

const telegrafUpdate = (name: string, connectionMode = 'secure') => ({
  configuration: {
    conf_certificate: '/sub/test.crt',
    conf_private_key: 'test.key',
    conf_server_port: 9090,
    otel_ca_certificate: 'test.crt',
    otel_private_key: 'test.key',
    otel_public_certificate: 'test.cer'
  },
  connection_mode: connectionMode,
  name,
  poller_ids: [1, 2]
});

const cmaHost = (caCertificate: string | null, caName: string | null) => ({
  address: '127.0.0.2',
  id: 1,
  poller_ca_certificate: caCertificate,
  poller_ca_name: caName,
  port: 4317,
  token: { creator_id: 1, name: 'token 1' }
});

test.describe('Agent configurations modal', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
  });

  test('does not validate the form when fields contain errors', async ({
    expectThemedScreenshot,
    page
  }) => {
    await openCreation(page, 0);
    await field(page, labelName).focus();
    await field(page, labelName).blur();
    await field(page, labelPollers).focus();
    await field(page, labelPollers).blur();
    await field(page, labelPort).fill('123456');
    await field(page, labelPublicCertificate).fill('./test.cer');
    await field(page, labelCaCertificate).fill('//test.crt');
    await field(page, labelPrivateKey).fill('test.abc');
    await field(page, labelPrivateKey, 1).fill('test.xyz');
    await field(page, labelCaCertificate).pressSequentially('test.cer');
    await field(page, labelCaCertificate).blur();
    await expect(field(page, labelAgentType)).toHaveValue('Telegraf');
    await expect(page.getByText(labelRequired, { exact: true })).toHaveCount(2);
    await expect(page.getByText(labelPortExpectedAtMost)).toHaveCount(1);
    await expect(page.getByText(labelRelativePathAreNotAllowed)).toHaveCount(1);
    await expect(page.getByText(labelInvalidPath)).toHaveCount(1);
    await expect(page.getByText(labelInvalidExtension)).toHaveCount(2);
    await expect(
      page.getByRole('button', { exact: true, name: labelSave })
    ).toBeDisabled();
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'agent-configurations-form-errors'
    );
  });

  test('leaves the form from the cancel confirmation', async ({ page }) => {
    await openCreation(page);
    await field(page, labelName).fill('agent');
    await page.getByText(labelCancel, { exact: true }).click();
    await page.getByText('Leave', { exact: true }).click();
    await expect(field(page, labelName)).toHaveCount(0);
  });

  test('stays in the form from the cancel confirmation', async ({ page }) => {
    await openCreation(page);
    await field(page, labelName).fill('agent');
    await page.getByText(labelCancel, { exact: true }).click();
    await page.getByText('Stay', { exact: true }).click();
    await expect(page.getByText('Stay', { exact: true })).toHaveCount(0);
    await expect(field(page, labelName)).toHaveValue('agent');
  });

  test('creates a Telegraf agent configuration', async ({ page }) => {
    await openCreation(page, 0);
    await field(page, labelName).fill('agent');
    await pickPoller(page);
    await page.getByText('Pollers', { exact: true }).click();
    await field(page, labelPort).fill('1234');
    await field(page, labelPublicCertificate).fill('test.crt');
    await field(page, labelPrivateKey).fill('/sub/test.key');
    await field(page, labelPrivateKey, 1).fill('/sub/test.key');
    await field(page, labelPublicCertificate, 1).fill('test.cer');
    await page.getByRole('button', { exact: true, name: labelSave }).click();
    await expectCreated(page, {
      configuration: {
        conf_certificate: 'test.cer',
        conf_private_key: '/sub/test.key',
        conf_server_port: 1234,
        otel_ca_certificate: null,
        otel_private_key: '/sub/test.key',
        otel_public_certificate: 'test.crt'
      },
      connection_mode: 'secure',
      name: 'agent',
      poller_ids: [1],
      type: 'telegraf'
    });
  });

  test('updates an agent configuration opened from the listing', async ({
    expectThemedScreenshot,
    page
  }) => {
    await page.getByText('AC 1', { exact: true }).click();
    await expect(field(page, labelName)).toHaveValue('agent');
    await expect(field(page, labelAgentType)).toBeDisabled();
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'agent-configurations-edition'
    );
    await field(page, labelName).press('End');
    await field(page, labelName).pressSequentially(' updated');
    await page.getByRole('button', { exact: true, name: labelSave }).click();
    await expect
      .poll(() => mocks.update.bodies)
      .toEqual([telegrafUpdate('agent updated')]);
    await expect(page.getByText(labelAgentConfigurationUpdated)).toBeVisible();
  });

  test('saves the changes confirmed when the edition is cancelled', async ({
    page
  }) => {
    await page.getByText('AC 1', { exact: true }).click();
    await expect(field(page, labelName)).toHaveValue('agent');
    await field(page, labelName).press('End');
    await field(page, labelName).pressSequentially(' updated');
    await page.getByText(labelCancel, { exact: true }).click();
    await page.getByTestId('confirm').click();
    await expect
      .poll(() => mocks.update.bodies)
      .toEqual([telegrafUpdate('agent updated')]);
    await expect(page.getByText(labelAgentConfigurationUpdated)).toBeVisible();
  });

  test('displays the CMA form when the CMA agent type is selected', async ({
    expectThemedScreenshot,
    page
  }) => {
    await openCreation(page, 1);
    await expect(page.getByTestId('By agent selected')).toBeVisible();
    await expect(page.getByTestId('By poller selected')).toHaveCount(0);
    await expect(page.getByText(labelConnectionInitiated)).toBeAttached();
    await page.getByText(labelOTLPReceiver).scrollIntoViewIfNeeded();
    await expect(page.getByText(labelOTLPReceiver)).toBeVisible();
    await expect(field(page, labelPublicCertificate)).toHaveValue('');
    await expect(field(page, labelCaCertificate)).toHaveValue('');
    await expect(field(page, labelPrivateKey)).toHaveValue('');
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'agent-configurations-cma-form'
    );
  });

  test('resets the form when a different agent type is selected', async ({
    page
  }) => {
    await openCreation(page, 1);
    await field(page, labelName).fill('My agent');
    await field(page, labelPublicCertificate).fill('something');
    await field(page, labelPublicCertificate).clear();
    await field(page, labelPublicCertificate).blur();
    await field(page, labelPrivateKey).fill('filename.abc');
    await field(page, labelPrivateKey).blur();
    await expect(page.getByText(labelInvalidExtension)).toBeVisible();
    await field(page, labelAgentType).click();
    await page.locator('[data-option-index="0"]').click();
    await expect(page.getByText(labelMonitoredHosts)).toHaveCount(0);
    await expect(page.getByText(labelRequired)).toHaveCount(0);
    await expect(page.getByText(labelInvalidExtension)).toHaveCount(0);
    await expect(field(page, labelName)).toHaveValue('My agent');
    await expect(page.getByText(labelConfigurationServer)).toBeVisible();
  });

  test('requires a host configuration once the "By poller" mode is enabled', async ({
    page
  }) => {
    await openCreation(page, 1);
    await field(page, labelName).fill('My agent');
    await pickPoller(page);
    await page.getByText('Add agent configuration').click();
    await expect(
      page.getByRole('button', { exact: true, name: labelSave })
    ).toBeDisabled();
    await field(page, labelPublicCertificate).fill('test.crt');
    await field(page, labelCaCertificate).fill('test.crt');
    await field(page, labelPrivateKey).fill('test.key');
    await pickToken(page, labelSelectExistingCMATokens);
    await enableByPoller(page);
    await expect(page.getByTestId('submit')).toBeDisabled();
    await page.getByText('Enable', { exact: true }).click();
    await expect(page.getByTestId('submit')).toBeEnabled();
  });

  test('creates a CMA configuration initiated by the agent only', async ({
    page
  }) => {
    await openCreation(page, 1);
    await field(page, labelName).fill('My agent');
    await pickPoller(page);
    await page.getByText('Add agent configuration').click();
    await field(page, labelPublicCertificate).fill('/certificate/test.crt');
    await field(page, labelCaCertificate).fill('test.crt');
    await field(page, labelPrivateKey).fill('privateKey.key');
    await pickToken(page, labelSelectExistingCMATokens);
    await page.getByTestId('submit').click();
    await expect
      .poll(() => mocks.create.bodies)
      .toEqual([
        {
          configuration: {
            agent_initiated: true,
            create_host_auto: false,
            hosts: [],
            otel_ca_certificate: 'test.crt',
            otel_private_key: 'privateKey.key',
            otel_public_certificate: '/certificate/test.crt',
            poller_initiated: false,
            port: 4317,
            tokens: [{ creator_id: 1, name: 'token 1' }]
          },
          connection_mode: 'secure',
          name: 'My agent',
          poller_ids: [1],
          type: 'centreon-agent'
        }
      ]);
  });

  test('fills the host address and port when a host is selected', async ({
    expectThemedScreenshot,
    page
  }) => {
    await openCreation(page, 1);
    await enableByPoller(page);
    await pickHost(page);
    await expect(field(page, labelDNSIP)).toHaveValue('127.0.0.2');
    await expect(page.getByTestId('portInput')).toHaveValue('4317');
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'agent-configurations-host'
    );
  });

  test('splits a full address pasted in the address field', async ({
    page
  }) => {
    await openCreation(page, 1);
    await enableByPoller(page);
    await field(page, labelDNSIP).pressSequentially('127.0.0.1:8');
    await expect(field(page, labelDNSIP)).toHaveValue('127.0.0.1');
    await expect(page.getByTestId('portInput')).toHaveValue('8');
  });

  test('adds then removes a host configuration', async ({ page }) => {
    await openCreation(page, 1);
    await enableByPoller(page);
    await page.getByText(labelAddAHost).click();
    await expect(page.getByTestId('delete-host-configuration-0')).toBeVisible();
    await page.getByTestId('delete-host-configuration-1').click();
    await expect(
      page.getByTestId('delete-host-configuration-0')
    ).toBeDisabled();
    await expect(page.getByTestId('delete-host-configuration-1')).toHaveCount(
      0
    );
  });

  const fillCmaForm = async (page: Page, caName?: string): Promise<void> => {
    await field(page, labelName).fill('My agent');
    await pickPoller(page);
    await page.getByText(labelConnectionInitiated).click({ force: true });
    await field(page, labelPublicCertificate).fill('/test.cer');
    await field(page, labelCaCertificate).fill('test.crt');
    await field(page, labelPrivateKey).fill('private.key');
    await pickToken(page, labelSelectExistingCMATokens);
    await enableByPoller(page);
    await pickHost(page);
    // The OTLP section collapses: the visible CA certificate is the host one.
    await field(page, labelCaCertificate).fill('test.crt');
    if (caName) {
      await field(page, labelCACommonName).fill(caName);
    } else {
      await expect(page.getByLabel(labelCACommonName)).toHaveCount(0);
    }
    await pickToken(page, labelSelectExistingCMAToken);
  };

  const cmaBody = (connectionMode: string, host: object) => ({
    configuration: {
      agent_initiated: true,
      create_host_auto: false,
      hosts: [host],
      otel_ca_certificate: 'test.crt',
      otel_private_key: 'private.key',
      otel_public_certificate: '/test.cer',
      poller_initiated: true,
      port: 4317,
      tokens: [{ creator_id: 1, name: 'token 1' }]
    },
    connection_mode: connectionMode,
    name: 'My agent',
    poller_ids: [1],
    type: 'centreon-agent'
  });

  test('creates a CMA configuration initiated by the poller', async ({
    page
  }) => {
    await openCreation(page, 1);
    await fillCmaForm(page);
    await page.getByTestId('submit').click();
    await expectCreated(page, cmaBody('secure', cmaHost('test.crt', '')));
  });

  test('preselects the TLS encryption level', async ({ page }) => {
    await openCreation(page);
    await expect(field(page, labelEncryptionLevel)).toHaveValue(labelTLS);
  });

  test('warns when the encryption level is set to No TLS', async ({
    expectThemedScreenshot,
    page
  }) => {
    await openCreation(page, 0);
    await field(page, labelEncryptionLevel).click();
    await page.getByText(labelNoTLS, { exact: true }).click();
    await expect(
      page.getByText(labelWarningEncryptionLevelTelegraf)
    ).toBeVisible();
    await field(page, labelAgentType).click();
    await page.locator('[data-option-index="1"]').click();
    await expect(page.getByText(labelWarningEncryptionLevelCMA)).toBeVisible();
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'agent-configurations-no-tls-warning'
    );
  });

  const expectNoCertificateFields = async (page: Page): Promise<void> => {
    for (const label of [
      labelPublicCertificate,
      labelCaCertificate,
      labelPrivateKey
    ]) {
      await expect(page.getByLabel(label, { exact: true })).toHaveCount(0);
    }
  };

  test('creates a Telegraf configuration without TLS nor certificates', async ({
    page
  }) => {
    await openCreation(page, 0);
    await field(page, labelEncryptionLevel).click();
    await page.getByText(labelNoTLS, { exact: true }).click();
    await field(page, labelName).fill('My agent');
    await pickPoller(page);
    await field(page, labelPort).fill('1234');
    await expectNoCertificateFields(page);
    await page.getByRole('button', { exact: true, name: labelSave }).click();
    await expectCreated(page, {
      configuration: {
        conf_certificate: null,
        conf_private_key: null,
        conf_server_port: 1234,
        otel_ca_certificate: null,
        otel_private_key: null,
        otel_public_certificate: null
      },
      connection_mode: 'no-tls',
      name: 'My agent',
      poller_ids: [1],
      type: 'telegraf'
    });
  });

  test('creates a CMA configuration without TLS nor certificates', async ({
    page
  }) => {
    await openCreation(page, 1);
    await field(page, labelName).fill('My agent');
    await pickPoller(page);
    await field(page, labelEncryptionLevel).click();
    await page.getByText(labelNoTLS, { exact: true }).click();
    await expectNoCertificateFields(page);
    await pickToken(page, labelSelectExistingCMATokens);
    await enableByPoller(page);
    await pickHost(page);
    await expect(page.getByLabel(labelCACommonName)).toHaveCount(0);
    await pickToken(page, labelSelectExistingCMAToken);
    await page.getByTestId('submit').click();
    await expectCreated(page, {
      ...cmaBody('no-tls', cmaHost(null, null)),
      configuration: {
        ...cmaBody('no-tls', cmaHost(null, null)).configuration,
        otel_ca_certificate: null,
        otel_private_key: null,
        otel_public_certificate: null
      }
    });
  });

  test('creates an insecure Telegraf configuration with its certificates', async ({
    page
  }) => {
    await openCreation(page, 0);
    await field(page, labelEncryptionLevel).click();
    await page.getByText(labelInsecure, { exact: true }).click();
    await field(page, labelName).fill('Insecure Agent');
    await pickPoller(page);
    await page.getByText('Add agent configuration').click();
    await field(page, labelPort).fill('1234');
    await expect(page.getByLabel(labelPrivateKey, { exact: true })).toHaveCount(
      2
    );
    await field(page, labelPublicCertificate).fill('test.crt');
    await field(page, labelCaCertificate).fill('ca.crt');
    await field(page, labelPrivateKey).fill('test.key');
    await field(page, labelPrivateKey, 1).fill('test.key');
    await field(page, labelPublicCertificate, 1).fill('test.cer');
    await page.getByRole('button', { exact: true, name: labelSave }).click();
    await expectCreated(page, {
      configuration: {
        conf_certificate: 'test.cer',
        conf_private_key: 'test.key',
        conf_server_port: 1234,
        otel_ca_certificate: 'ca.crt',
        otel_private_key: 'test.key',
        otel_public_certificate: 'test.crt'
      },
      connection_mode: 'insecure',
      name: 'Insecure Agent',
      poller_ids: [1],
      type: 'telegraf'
    });
  });

  test('updates a configuration to the insecure encryption level', async ({
    page
  }) => {
    await page.getByText('AC 1', { exact: true }).click();
    await expect(field(page, labelName)).toHaveValue('agent');
    await field(page, labelEncryptionLevel).click();
    await page.getByText(labelInsecure, { exact: true }).click();
    await field(page, labelName).fill('Insecure Agent');
    await page.getByRole('button', { exact: true, name: labelSave }).click();
    await expect
      .poll(() => mocks.update.bodies)
      .toEqual([telegrafUpdate('Insecure Agent', 'insecure')]);
    await expect(page.getByText(labelAgentConfigurationUpdated)).toBeVisible();
  });

  test('sends the CA common name of an insecure CMA configuration', async ({
    page
  }) => {
    await openCreation(page, 1);
    await field(page, labelEncryptionLevel).click();
    await page.getByText(labelInsecure, { exact: true }).click();
    await fillCmaForm(page, 'test.crt');
    await page.getByTestId('submit').click();
    await expectCreated(
      page,
      cmaBody('insecure', cmaHost('test.crt', 'test.crt'))
    );
  });
});

test.describe('Installation command', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await setUp({ mockApi, mount });
    await expectListingLoaded(page);
  });

  test('opens the command of the central poller from the page button', async ({
    expectThemedScreenshot,
    page
  }) => {
    await page.getByRole('button', { exact: true, name: labelCommand }).click();
    for (const value of [
      labelGenerateInstallationCommand,
      labelCommandWarning,
      labelSelectPollerThatWillMonitor,
      labelSelectOperatingSystem,
      labelExecuteTheScript
    ]) {
      await expect(page.getByText(value)).toBeVisible();
    }
    await expect(page.getByTestId('Select poller')).toHaveValue('Central');
    await expect(page.getByTestId('Windows')).toHaveAttribute(
      'data-selected',
      'true'
    );
    await expect(page.getByTestId('Linux')).toBeVisible();
    await expect(page.getByTestId(labelCommand)).toHaveText(windowsCommandLine);
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'agent-configurations-command'
    );
  });

  test('opens the command of a poller from its row', async ({ page }) => {
    await page.getByTestId('ExpandMoreIcon').nth(2).click();
    await expect(page.getByTestId(labelCommand)).toHaveCount(2);
    await page.getByTestId(labelCommand).first().click();
    await expect(
      page.getByText(labelGenerateInstallationCommand)
    ).toBeVisible();
    await expect(page.getByTestId('Select poller')).toHaveValue('Central');
  });

  test('updates the command when the operating system changes', async ({
    page
  }) => {
    await page.getByRole('button', { exact: true, name: labelCommand }).click();
    await expect(page.getByTestId('Linux')).toHaveAttribute(
      'data-selected',
      'false'
    );
    await expect(page.getByTestId(labelCommand)).toHaveText(windowsCommandLine);
    await page.getByTestId('Linux').click();
    await expect(page.getByTestId('Linux')).toHaveAttribute(
      'data-selected',
      'true'
    );
    await expect(page.getByTestId(labelCommand)).toHaveText(linuxCommandLine);
  });
});
