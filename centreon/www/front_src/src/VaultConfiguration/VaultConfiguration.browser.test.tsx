import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { I18nextProvider, initReactI18next } from 'react-i18next';

import { vaultConfigurationEndpoint } from './api/endpoints';
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
} from './translatedLabels';
import VaultConfiguration from './VaultConfiguration';

const initialize = (): void => {
  i18next.use(initReactI18next).init({
    fallbackLng: 'en',
    lng: 'en',
    resources: { en: { translationsNS: {} } }
  });

  const store = createStore();

  interceptAPIRequest({
    alias: 'getVaultConfiguration',
    method: Method.GET,
    path: `./api/latest${vaultConfigurationEndpoint}`,
    response: {
      address: 'localhost',
      port: 1024,
      root_path: '/path'
    }
  });

  interceptAPIRequest({
    alias: 'putVaultConfiguration',
    method: Method.PUT,
    path: `./api/latest${vaultConfigurationEndpoint}`,
    statusCode: 204
  });

  render(
    <Provider store={store}>
      <I18nextProvider i18n={i18next}>
        <TestQueryProvider>
          <SnackbarProvider>
            <VaultConfiguration />
          </SnackbarProvider>
        </TestQueryProvider>
      </I18nextProvider>
    </Provider>
  );
};

// The form is shown once the configuration is loaded.
const waitForConfiguration = async (): Promise<void> => {
  await waitForRequest('@getVaultConfiguration');

  await waitFor(() =>
    expect(screen.getByLabelText(labelVaultAddress)).toHaveValue('localhost')
  );
};

const getResetButton = (): HTMLElement =>
  screen.getByRole('button', { name: labelReset });

const getSaveButton = (): HTMLElement =>
  screen.getByRole('button', { name: labelSave });

const typeAddress = async (address: string): Promise<void> => {
  const input = screen.getByLabelText(labelVaultAddress);

  await userEvent.clear(input);
  await userEvent.type(input, address);
  await userEvent.tab();
};

describe('Vault configuration', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays an error when the URL field is not correctly formatted', async () => {
    initialize();

    await waitForConfiguration();

    await typeAddress('127.0.');

    expect(await screen.findByText(labelAddressIsNotAnUrl)).toBeVisible();
    expect(screen.getByLabelText(labelVaultAddress)).toHaveAttribute(
      'aria-invalid',
      'true'
    );

    // A modified form can be reset, an invalid one cannot be saved.
    expect(getResetButton()).toBeEnabled();
    expect(getSaveButton()).toBeDisabled();
  });

  it('displays an error when the port field is greater than 65535', async () => {
    initialize();

    await waitForConfiguration();

    const port = screen.getByLabelText(labelPort);

    await userEvent.clear(port);
    await userEvent.type(port, '-1');
    await userEvent.tab();

    expect(await screen.findByText(labelPortMustStartFrom1)).toBeVisible();

    await userEvent.clear(port);
    await userEvent.type(port, '4526121');
    await userEvent.tab();

    expect(await screen.findByText(labelPortExpectedAtMost)).toBeVisible();
    expect(port).toHaveValue(4526121);
    expect(port).toHaveAttribute('aria-invalid', 'true');
    expect(screen.queryByText(labelPortMustStartFrom1)).not.toBeInTheDocument();

    expect(getResetButton()).toBeEnabled();
    expect(getSaveButton()).toBeDisabled();
  });

  it('sanitizes the URL when the field receives an URL that starts with a protocol', async () => {
    initialize();

    await waitForConfiguration();

    await typeAddress('http://example.com');

    await waitFor(() =>
      expect(screen.getByLabelText(labelVaultAddress)).toHaveValue(
        'example.com'
      )
    );
    expect(screen.getByLabelText(labelPort)).toHaveValue(1024);
    expect(screen.queryByText(labelAddressIsNotAnUrl)).not.toBeInTheDocument();

    // The role and secret IDs are still missing.
    expect(getResetButton()).toBeEnabled();
    expect(getSaveButton()).toBeDisabled();
  });

  it('sanitizes URL and port field when URL field receives an URL and a port', async () => {
    initialize();

    await waitForConfiguration();

    await typeAddress('http://example.com:1');

    await waitFor(() =>
      expect(screen.getByLabelText(labelVaultAddress)).toHaveValue(
        'example.com'
      )
    );
    await waitFor(() =>
      expect(screen.getByLabelText(labelPort)).toHaveValue(1)
    );
    expect(screen.getByLabelText(labelRootPath)).toHaveValue('/path');
    expect(screen.queryByText(labelAddressIsNotAnUrl)).not.toBeInTheDocument();
    expect(screen.queryByText(labelPortMustStartFrom1)).not.toBeInTheDocument();

    expect(getResetButton()).toBeEnabled();
    expect(getSaveButton()).toBeDisabled();
  });

  it('displays the retrieved Vault configuration', async () => {
    initialize();

    await waitForConfiguration();

    expect(screen.getByText(labelVaultConfiguration)).toBeVisible();
    expect(screen.getByLabelText(labelVaultAddress)).toHaveValue('localhost');
    expect(screen.getByLabelText(labelPort)).toHaveValue(1024);
    expect(screen.getByLabelText(labelRootPath)).toHaveValue('/path');
    expect(screen.getByLabelText(labelRoleID)).toHaveValue('');
    expect(screen.getByLabelText(labelSecretID)).toHaveValue('');

    // The role and secret IDs are hidden by default.
    expect(screen.getByLabelText(labelRoleID)).toHaveAttribute(
      'type',
      'password'
    );
    expect(screen.getByLabelText(labelSecretID)).toHaveAttribute(
      'type',
      'password'
    );

    expect(getResetButton()).toBeDisabled();
    expect(getSaveButton()).toBeDisabled();
    expect(screen.getByRole('button', { name: labelMigrate })).toBeEnabled();
  });

  it('sends the updated Vault configuration', async () => {
    initialize();

    await waitForConfiguration();

    await typeAddress('http://example.com');

    await waitFor(() =>
      expect(screen.getByLabelText(labelVaultAddress)).toHaveValue(
        'example.com'
      )
    );

    await userEvent.type(screen.getByLabelText(labelRoleID), 'role');
    await userEvent.type(screen.getByLabelText(labelSecretID), 'Secret');

    await waitFor(() => expect(getSaveButton()).toBeEnabled());

    // The stored configuration, once saved. It comes late, to leave time to
    // check the form while it is loaded again.
    interceptAPIRequest({
      alias: 'getVaultConfiguration',
      delay: 300,
      method: Method.GET,
      path: `./api/latest${vaultConfigurationEndpoint}`,
      response: {
        address: 'example.com',
        port: 1024,
        root_path: '/path'
      }
    });

    await userEvent.click(getSaveButton());

    const { request } = await waitForRequest('@putVaultConfiguration');

    expect(request.body).toEqual({
      address: 'example.com',
      port: 1024,
      role_id: 'role',
      root_path: '/path',
      secret_id: 'Secret'
    });

    expect(
      await screen.findByText(labelVaultConfigurationUpdate)
    ).toBeVisible();

    // While the configuration is loaded again, the form keeps what was typed
    // until the new configuration arrives. Checked twice, as a reset would
    // come after a render.
    expect(screen.getByLabelText(labelVaultAddress)).toHaveValue('example.com');
    expect(screen.getByLabelText(labelRoleID)).toHaveValue('role');
    await new Promise((resolve) => setTimeout(resolve, 100));
    expect(screen.getByLabelText(labelVaultAddress)).toHaveValue('example.com');
    expect(screen.getByLabelText(labelRoleID)).toHaveValue('role');

    // The configuration is loaded again, and the form starts from it: the
    // saved values, without the role and secret IDs.
    await waitForRequest('@getVaultConfiguration');

    await waitFor(() =>
      expect(screen.getByLabelText(labelRoleID)).toHaveValue('')
    );
    expect(screen.getByLabelText(labelSecretID)).toHaveValue('');
    expect(screen.getByLabelText(labelVaultAddress)).toHaveValue('example.com');
    expect(screen.getByLabelText(labelPort)).toHaveValue(1024);
    expect(getResetButton()).toBeDisabled();
    expect(getSaveButton()).toBeDisabled();
  });

  it('resets the form when the configuration has changed and the reset button is clicked', async () => {
    initialize();

    await waitForConfiguration();

    await typeAddress('http://example.com');

    await waitFor(() =>
      expect(screen.getByLabelText(labelVaultAddress)).toHaveValue(
        'example.com'
      )
    );

    await userEvent.type(screen.getByLabelText(labelRoleID), 'role');
    await userEvent.type(screen.getByLabelText(labelSecretID), 'Secret');

    await userEvent.click(getResetButton());

    const dialog = await screen.findByRole('dialog');

    expect(within(dialog).getByText(labelFormWillBeCleared)).toBeVisible();
    expect(within(dialog).getByText(labelResetConfiguration)).toBeVisible();

    await userEvent.click(within(dialog).getByLabelText(labelReset));

    await waitFor(() =>
      expect(screen.getByLabelText(labelRoleID)).toHaveValue('')
    );
    expect(screen.getByLabelText(labelSecretID)).toHaveValue('');
    expect(screen.getByLabelText(labelVaultAddress)).toHaveValue('localhost');
    expect(screen.getByLabelText(labelPort)).toHaveValue(1024);
    expect(screen.getByLabelText(labelRootPath)).toHaveValue('/path');

    await waitFor(() =>
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    );
    expect(getResetButton()).toBeDisabled();
    expect(getSaveButton()).toBeDisabled();
  });

  it('displays a modal when the vault configuration is valid and the button is clicked', async () => {
    initialize();

    await waitForConfiguration();

    await userEvent.click(screen.getByRole('button', { name: labelMigrate }));

    const dialog = await screen.findByRole('dialog');

    expect(within(dialog).getByText(labelMigrationScript)).toBeVisible();
    expect(
      within(dialog).getByText(labelMigrationScriptExportCredentials)
    ).toBeVisible();
    expect(
      within(dialog).getByText(
        /By executing this script, your platform will be in a locked mode and you will not be able to do/
      )
    ).toBeVisible();
    expect(
      within(dialog).getByText(labelMigrationCanTakeSeveralMinutes)
    ).toBeVisible();
    expect(
      within(dialog).getByText(`# ${labelExecuteThisCommandAsRoot}`, {
        exact: false
      })
    ).toBeVisible();
    expect(
      within(dialog).getByText(
        '/usr/share/centreon/bin/migrateCredentials.php',
        {
          exact: false
        }
      )
    ).toBeVisible();
  });
});
