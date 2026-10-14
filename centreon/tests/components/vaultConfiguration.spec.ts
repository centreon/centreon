import {
  labelAddressIsNotAnUrl,
  labelExecuteThisCommandAsRoot,
  labelFormWillBeCleared,
  labelMigrate,
  labelMigrationCanTakeSeveralMinutes,
  labelMigrationScript,
  labelMigrationScriptExportCredentials,
  labelPort,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelReset,
  labelResetConfiguration,
  labelRoleID,
  labelRootPath,
  labelSave,
  labelSecretID,
  labelVaultAddress,
  labelVaultConfiguration,
  labelVaultConfigurationUpdate
} from '../../www/front_src/src/VaultConfiguration/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from VaultConfiguration/VaultConfiguration.
const storyId = 'VaultConfiguration/VaultConfiguration/Default';
const endpoint = './api/latest/administration/vaults/configurations';

test.beforeEach(async ({ mockApi }) => {
  await mockApi({
    endpoint,
    response: { address: 'localhost', port: 1024, root_path: '/path' }
  });
});

const loaded = async (
  component: import('@playwright/test').Locator
): Promise<void> => {
  await expect(component.getByLabel(labelVaultAddress)).toHaveValue(
    'localhost'
  );
};

test('displays the saved configuration', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);
  await loaded(component);

  await expect(
    component.getByText(labelVaultConfiguration).first()
  ).toBeVisible();
  await expect(component.getByLabel(labelPort)).toHaveValue('1024');
  await expect(component.getByLabel(labelRootPath)).toHaveValue('/path');
  await expect(component.getByLabel(labelRoleID)).toHaveValue('');
  await expect(component.getByLabel(labelSecretID)).toHaveValue('');
  await expect(
    component.getByRole('button', { name: labelReset })
  ).toBeDisabled();
  await expect(
    component.getByRole('button', { name: labelSave })
  ).toBeDisabled();

  await expectThemedScreenshot(component, 'vault-configuration');
});

test('rejects an address that is not an URL', async ({ mount }) => {
  const component = await mount(storyId);
  await loaded(component);

  await component.getByLabel(labelVaultAddress).fill('127.0.');
  await component.getByLabel(labelVaultAddress).blur();

  await expect(component.getByText(labelAddressIsNotAnUrl)).toBeVisible();
});

const invalidPorts = [
  { error: labelPortMustStartFrom1, port: '-1' },
  { error: labelPortExpectedAtMost, port: '4526121' }
];

for (const { error, port } of invalidPorts) {
  test(`rejects the port ${port}`, async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    await component.getByLabel(labelPort).fill(port);
    await component.getByLabel(labelPort).blur();

    await expect(component.getByText(error)).toBeVisible();
  });
}

test('extracts the host and the port from a typed URL', async ({ mount }) => {
  const component = await mount(storyId);
  await loaded(component);
  const address = component.getByLabel(labelVaultAddress);

  await address.fill('http://example.com');
  await address.blur();
  await expect(address).toHaveValue('example.com');

  await address.fill('http://example.com:1');
  await address.blur();
  await expect(address).toHaveValue('example.com');
  await expect(component.getByLabel(labelPort)).toHaveValue('1');
});

test('saves the updated configuration', async ({ mockApi, mount }) => {
  const save = await mockApi({ endpoint, method: 'PUT', status: 204 });
  const component = await mount(storyId);
  await loaded(component);
  const address = component.getByLabel(labelVaultAddress);

  await address.fill('http://example.com');
  await address.blur();
  await component.getByLabel(labelRoleID).fill('role');
  await component.getByLabel(labelSecretID).fill('Secret');
  await component.getByRole('button', { name: labelSave }).click();

  await expect
    .poll(() => save.bodies)
    .toEqual([
      {
        address: 'example.com',
        port: 1024,
        role_id: 'role',
        root_path: '/path',
        secret_id: 'Secret'
      }
    ]);
  await expect(
    component.page().getByText(labelVaultConfigurationUpdate)
  ).toBeVisible();
  await expect(
    component.getByRole('button', { name: labelSave })
  ).toBeDisabled();
  await expect(component.getByLabel(labelSecretID)).toHaveValue('');
  // The form is reset to the saved values (useUpdateVaultConfiguration), not
  // to the previous configuration the Cypress spec still expected.
  await expect(address).toHaveValue('example.com');
});

test('resets the form after confirmation', async ({ mount, page }) => {
  const component = await mount(storyId);
  await loaded(component);

  await component.getByLabel(labelVaultAddress).fill('http://example.com');
  await component.getByLabel(labelVaultAddress).blur();
  await component.getByLabel(labelRoleID).fill('role');
  await component.getByLabel(labelSecretID).fill('Secret');
  await component.getByRole('button', { name: labelReset }).click();

  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText(labelFormWillBeCleared)).toBeVisible();
  await expect(dialog.getByText(labelResetConfiguration)).toBeVisible();
  await dialog.getByLabel(labelReset).click();

  await expect(component.getByLabel(labelRoleID)).toHaveValue('');
  await expect(component.getByLabel(labelSecretID)).toHaveValue('');
  await expect(component.getByLabel(labelVaultAddress)).toHaveValue(
    'localhost'
  );
});

test('explains the credentials migration', async ({ mount, page }) => {
  const component = await mount(storyId);
  await loaded(component);

  await component.getByText(labelMigrate).click();

  for (const text of [
    labelMigrationScript,
    labelMigrationScriptExportCredentials,
    'By executing this script, your platform will be in a locked mode and you will not be able to do',
    labelMigrationCanTakeSeveralMinutes,
    `# ${labelExecuteThisCommandAsRoot}`,
    '/usr/share/centreon/bin/migrateCredentials.php'
  ]) {
    await expect(page.getByText(text).first()).toBeVisible();
  }
});
