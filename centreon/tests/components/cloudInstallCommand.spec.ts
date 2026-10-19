import type { Page } from '@playwright/test';

import tokens from '../../cypress/fixtures/authenticationTokens/listTokens.json';
import {
  labelCancel,
  labelCentralAddress,
  labelClickToGenerate,
  labelConfigurationExportedAndReloaded,
  labelContainer,
  labelCopyTheFollowingCommand,
  labelCreateNewPoller,
  labelEnterPollerNameAndAddress,
  labelExportConfiguration,
  labelFailedToCreatePoller,
  labelFailedToExportAndReloadConfiguration,
  labelGenerateInstallationCommand,
  labelPleaseWait,
  labelPollerAddress,
  labelPollerName,
  labelSelectPollerEnvironment,
  labelSelectToken,
  labelSelectTokenPlaceholder,
  labelVMOrPhysical
} from '../../www/front_src/src/Header/Poller/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Header/Poller/PollerSubMenu/CloudInstallCommand.
const storyId = 'CloudInstallCommand/CloudInstallCommand/Default';
const installationCommand =
  'installcma.ps /FINGERPRINT=lllllll  /COMPONENTS=agent,plugins /HOST=host_1 /ENDPOINT=https://central/centreon:4317';
const createPollerSuccessResponse = {
  '@context': '/centreon/api/latest/contexts/Poller',
  '@id': '/centreon/api/latest/pollers/11',
  '@type': 'Poller',
  address: '192.0.0.98',
  id: 11,
  installation_command: installationCommand,
  name: 'poller-docker_07',
  poller_type: 'docker',
  uuid: '019dcf18-bdb1-7f89-a61f-45b8fcfb27e6'
};

interface Mocks {
  createPoller: MockedEndpoint;
  exportConfig: MockedEndpoint;
}

const mockRequests = async (
  mockApi: MockApi,
  {
    createPollerResponse = createPollerSuccessResponse as object,
    createPollerStatus = 200,
    exportConfigStatus = 204
  } = {}
): Promise<Mocks> => {
  await mockApi({ endpoint: 'administration/tokens', response: tokens });

  return {
    createPoller: await mockApi({
      endpoint: 'configuration/pollers',
      method: 'POST',
      response: createPollerResponse,
      status: createPollerStatus
    }),
    // Slow enough to see the pending label.
    exportConfig: await mockApi({
      delay: 500,
      endpoint: 'configuration/monitoring-servers/11/generate-and-reload',
      status: exportConfigStatus
    })
  };
};

const dialog = (page: Page) => page.getByRole('dialog');
const textField = (page: Page, label: string) =>
  page.getByLabel(`${label} *`, { exact: true });
const generateButton = (page: Page) =>
  page.getByTestId('Install command').locator('xpath=ancestor::button[1]');
const exportButton = (page: Page) => page.getByTestId('generate-command');
const environment = (page: Page, label: string) =>
  page
    .getByLabel(label, { exact: true })
    .locator('xpath=ancestor-or-self::*[@data-selected][1]');

const fillForm = async (
  page: Page,
  {
    centralAddress = '192.168.1.1',
    name = 'my-poller',
    pollerAddress = '192.168.1.1'
  }: {
    centralAddress?: string | null;
    name?: string;
    pollerAddress?: string;
  } = {}
) => {
  await textField(page, labelPollerName).fill(name);
  await textField(page, labelPollerAddress).fill(pollerAddress);
  if (centralAddress) {
    await textField(page, labelCentralAddress).fill(centralAddress);
  }
};
const pickToken = async (page: Page) => {
  await page.getByLabel(labelSelectTokenPlaceholder, { exact: true }).click();
  await page.getByRole('option', { exact: true, name: 'a-token' }).click();
};

test.describe('button', () => {
  test('opens the modal and closes the sub menu', async ({
    mockApi,
    mount,
    page
  }) => {
    await mockRequests(mockApi);
    const component = await mount(storyId);
    const button = component.getByTestId(labelCreateNewPoller);
    await expect(button).toContainText(labelCreateNewPoller);

    await button.click();
    await expect(page.getByTestId('close-sub-menu-calls')).toHaveValue('1');
    await expect(dialog(page)).toBeVisible();
  });
});

test.describe('modal', () => {
  let mocks: Mocks;

  test.beforeEach(async ({ mockApi }) => {
    mocks = await mockRequests(mockApi);
  });

  test('displays the title, the form sections and its fields', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(storyId, { isModalOpen: true });

    await expect(dialog(page).getByText(labelCreateNewPoller)).toBeVisible();
    for (const text of [
      labelEnterPollerNameAndAddress,
      labelSelectPollerEnvironment,
      labelGenerateInstallationCommand
    ]) {
      await expect(dialog(page).getByText(text)).toBeVisible();
    }
    await expect(
      dialog(page).getByText(labelSelectToken, { exact: true })
    ).toHaveCount(3);
    for (const testId of [
      'cloud-poller-name',
      'cloud-poller-address',
      'centreon-central-address'
    ]) {
      await expect(page.getByTestId(testId)).toBeVisible();
    }
    await expect(
      page.getByLabel(labelSelectTokenPlaceholder, { exact: true })
    ).toBeVisible();
    await expect(page.getByText(labelClickToGenerate)).toBeVisible();
    await expect(page.getByTestId('Install command')).toBeVisible();
    await expect(dialog(page).getByText(labelCancel)).toBeVisible();
    await expect(
      dialog(page).getByText(labelExportConfiguration)
    ).toBeVisible();
    await expectThemedScreenshot(dialog(page), 'cloud-install-command-modal');
  });

  test('takes a poller name and addresses', async ({ mount, page }) => {
    await mount(storyId, { isModalOpen: true });

    for (const [label, value] of [
      [labelPollerName, 'my-poller'],
      [labelPollerAddress, '192.168.1.1'],
      [labelCentralAddress, '192.168.1.1']
    ]) {
      await textField(page, label).fill(value);
      await expect(textField(page, label)).toHaveValue(value);
    }
  });

  test('hides the central address on a cloud platform', async ({
    mount,
    page
  }) => {
    await mount(storyId, { isCloudPlatform: true, isModalOpen: true });

    await expect(page.getByTestId('cloud-poller-name')).toBeVisible();
    await expect(page.getByTestId('centreon-central-address')).toHaveCount(0);
  });

  test('selects the VM environment by default, and Docker on demand', async ({
    mount,
    page
  }) => {
    await mount(storyId, { isModalOpen: true });
    await expect(environment(page, labelVMOrPhysical)).toHaveAttribute(
      'data-selected',
      'true'
    );

    await page.getByLabel(labelContainer, { exact: true }).click();
    await expect(environment(page, labelContainer)).toHaveAttribute(
      'data-selected',
      'true'
    );
    await expect(environment(page, labelVMOrPhysical)).toHaveAttribute(
      'data-selected',
      'false'
    );
  });

  test('disables generation and export until the form is complete', async ({
    mount,
    page
  }) => {
    await mount(storyId, { isModalOpen: true });

    await expect(generateButton(page)).toBeDisabled();
    await expect(exportButton(page)).toBeDisabled();
  });

  test('closes from Cancel', async ({ mount, page }) => {
    await mount(storyId, { isModalOpen: true });
    await dialog(page).getByText(labelCancel).click();

    await expect(dialog(page)).toHaveCount(0);
  });

  test.describe('with a generated command', () => {
    const generated = { generatedCommand: 'some-command', isModalOpen: true };

    test('shows the command and locks the form', async ({ mount, page }) => {
      await mount(storyId, {
        generatedCommand: installationCommand,
        isModalOpen: true
      });

      await expect(page.getByText(labelCopyTheFollowingCommand)).toBeVisible();
      await expect(page.getByTestId('Command')).toContainText(
        installationCommand
      );
      await expect(textField(page, labelPollerName)).toBeDisabled();
      await expect(textField(page, labelPollerAddress)).toBeDisabled();
      for (const label of [labelVMOrPhysical, labelContainer]) {
        await expect(
          page
            .getByLabel(label, { exact: true })
            .locator('xpath=ancestor-or-self::button[1]')
        ).toBeDisabled();
      }
      await expect(
        page.getByLabel(labelSelectTokenPlaceholder, { exact: true })
      ).toBeDisabled();
    });

    test('enables the export only once the poller id is known', async ({
      mount,
      page
    }) => {
      await mount(storyId, { ...generated, pollerId: null });
      await expect(exportButton(page)).toBeDisabled();

      await mount(storyId, { ...generated, pollerId: 11 });
      await expect(exportButton(page)).toBeEnabled();
    });

    test('exports the configuration, showing progress, then closes', async ({
      mount,
      page
    }) => {
      await mount(storyId, { ...generated, pollerId: 11 });
      await expect(exportButton(page)).toContainText(labelExportConfiguration);
      await exportButton(page).click();

      await expect(page.getByText(labelPleaseWait)).toBeVisible();
      await expect.poll(() => mocks.exportConfig.urls.length).toBe(1);
      await expect(
        page.getByText(labelConfigurationExportedAndReloaded)
      ).toBeVisible();
      await expect(dialog(page)).toHaveCount(0);
    });

    test('reports a failed export', async ({ mockApi, mount, page }) => {
      await mockRequests(mockApi, { exportConfigStatus: 500 });
      await mount(storyId, { ...generated, pollerId: 11 });
      await exportButton(page).click();

      await expect(
        page.getByText(labelFailedToExportAndReloadConfiguration)
      ).toBeVisible();
    });

    test('forgets the command once the modal is closed', async ({
      mount,
      page
    }) => {
      const component = await mount(storyId, generated);
      await expect(page.getByText(labelCopyTheFollowingCommand)).toBeVisible();
      await dialog(page).getByText(labelCancel).click();
      await expect(dialog(page)).toHaveCount(0);

      await component.getByTestId(labelCreateNewPoller).click();
      await expect(dialog(page)).toBeVisible();
      await expect(page.getByText(labelCopyTheFollowingCommand)).toHaveCount(0);
      await expect(page.getByText(labelClickToGenerate)).toBeVisible();
    });
  });

  test.describe('command generation', () => {
    test('creates the poller and shows its command', async ({
      mount,
      page
    }) => {
      await mount(storyId, { isModalOpen: true });
      await fillForm(page);
      await pickToken(page);
      await expect(generateButton(page)).toBeEnabled();
      await generateButton(page).click();

      await expect(page.getByText(labelCopyTheFollowingCommand)).toBeVisible();
      await expect(page.getByTestId('Command')).toContainText(
        installationCommand
      );
      expect(mocks.createPoller.bodies[0]).toEqual(
        expect.objectContaining({
          address: '192.168.1.1',
          central_address: '192.168.1.1',
          name: 'my-poller'
        })
      );
    });

    test('reports a failed creation', async ({ mockApi, mount, page }) => {
      await mockRequests(mockApi, {
        createPollerResponse: { message: 'Internal server error' },
        createPollerStatus: 500
      });
      await mount(storyId, { isModalOpen: true });
      await fillForm(page);
      await pickToken(page);
      await generateButton(page).click();

      await expect(page.getByText(labelFailedToCreatePoller)).toBeVisible();
    });

    test('sends the web URL as the central address on a cloud platform', async ({
      mount,
      page
    }) => {
      await mount(storyId, {
        centralWebAddress: 'staging.euwest1.centreon.click/funky-donkey',
        isCloudPlatform: true,
        isModalOpen: true
      });
      await fillForm(page, { centralAddress: null });
      await pickToken(page);
      await generateButton(page).click();

      await expect.poll(() => mocks.createPoller.bodies.length).toBe(1);
      expect(mocks.createPoller.bodies[0]).toEqual(
        expect.objectContaining({
          address: '192.168.1.1',
          central_address: 'staging.euwest1.centreon.click/funky-donkey',
          name: 'my-poller'
        })
      );
    });

    test('strips the scheme but keeps the base path of the central address', async ({
      mount,
      page
    }) => {
      await mount(storyId, { isModalOpen: true });
      await fillForm(page, {
        centralAddress: 'https://central.example.com/centreon'
      });
      await pickToken(page);
      await generateButton(page).click();

      await expect.poll(() => mocks.createPoller.bodies.length).toBe(1);
      expect(mocks.createPoller.bodies[0]).toEqual(
        expect.objectContaining({
          central_address: 'central.example.com/centreon'
        })
      );
    });

    test('sends the Docker environment when selected', async ({
      mount,
      page
    }) => {
      await mount(storyId, { isModalOpen: true });
      await fillForm(page, {
        name: 'docker-poller',
        pollerAddress: '10.0.0.1'
      });
      await page.getByLabel(labelContainer, { exact: true }).click();
      await pickToken(page);
      await generateButton(page).click();

      await expect.poll(() => mocks.createPoller.bodies.length).toBe(1);
      expect(mocks.createPoller.bodies[0]).toEqual(
        expect.objectContaining({
          name: 'docker-poller',
          poller_type: 'docker'
        })
      );
    });
  });
});
