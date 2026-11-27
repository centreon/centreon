import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { labelPortExpectedAtMost } from '../../VaultConfiguration/translatedLabels';
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
  labelCMA,
  labelCommand,
  labelCommandWarning,
  labelConfigurationServer,
  labelConnectionInitiated,
  labelDelete,
  labelDeleteAgent,
  labelDeletePoller,
  labelDNSIP,
  labelEnable,
  labelEncryptionLevel,
  labelExecuteTheScript,
  labelFilters,
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
  labelUpdateAgentConfiguration,
  labelWarningEncryptionLevelCMA,
  labelWarningEncryptionLevelTelegraf,
  labelWelcomeToTheAgentsConfigurationPage
} from '../translatedLabels';
import initialize, { linuxCommandLine, windowsCommandLine } from './initialize';

const defaultListingSearch =
  '?page=1&limit=10&sort_by={"name":"asc"}&search={"$and":[]}';

const waitForListing = async (): Promise<void> => {
  const { request } = await waitForRequest('@getAgentConfigurations');

  expect(decodeURIComponent(request.url.search)).toBe(defaultListingSearch);
  expect(await screen.findByText('AC 0')).toBeVisible();
};

const getListingCallCount = (): number =>
  getRequestCalls('@getAgentConfigurations').length;

// Every filter change refetches the listing at once, so the request a button
// sends is looked for among those sent since it was clicked.
const waitForListingSearch = async (
  search: unknown,
  { since }: { since: number }
): Promise<void> => {
  await waitFor(() => {
    const searches = getRequestCalls('@getAgentConfigurations')
      .slice(since)
      .map(({ request }) =>
        JSON.parse(request.url.searchParams.get('search') as string)
      );

    expect(searches).toContainEqual(search);
  });
};

const getDeleteButtons = (): Array<HTMLButtonElement> =>
  Array.from(
    document.querySelectorAll<HTMLButtonElement>(
      `button[data-testid="${labelDelete}"]`
    )
  );

const getDialog = (): Promise<HTMLElement> => screen.findByRole('dialog');

// MUI autocomplete options carry their position in the list.
const selectOption = async (index: number): Promise<void> => {
  await waitFor(() =>
    expect(
      document.querySelector(`[data-option-index="${index}"]`)
    ).toBeInTheDocument()
  );

  await userEvent.click(
    document.querySelector(`[data-option-index="${index}"]`) as HTMLElement
  );
};

const openAddModal = async (): Promise<void> => {
  await userEvent.click(await screen.findByRole('button', { name: labelAdd }));

  await screen.findByText(labelAddAgentConfiguration);
};

const selectAgentType = async (index: number): Promise<void> => {
  await userEvent.click(await screen.findByLabelText(labelAgentType));
  await selectOption(index);
};

const openRow = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
  await waitForRequest('@getAgentConfiguration');
  await waitFor(() =>
    expect(screen.getByLabelText(labelName)).toHaveValue('agent')
  );
};

const selectPoller = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByLabelText(labelPollers));
  await userEvent.click(await screen.findByText(name));
};

const selectToken = async (label: string): Promise<void> => {
  await userEvent.click(await screen.findByLabelText(label));
  await waitForRequest('@getTokens');
  await userEvent.click(await screen.findByText('token 1'));
};

const enablePollerInitiated = async (): Promise<void> => {
  await userEvent.click(await screen.findByText(labelByPoller));
  await userEvent.click(await screen.findByText(labelEnable));
};

const selectHost = async (): Promise<void> => {
  await userEvent.click(await screen.findByLabelText(labelSelectHost));
  await userEvent.click(await screen.findByText('central'));
  await waitFor(() =>
    expect(screen.getByLabelText(labelDNSIP)).toHaveValue('127.0.0.2')
  );
};

const touch = (element: HTMLElement): void => {
  act(() => {
    element.focus();
    element.blur();
  });
};

const getInput = (testId: string): HTMLInputElement =>
  screen
    .getAllByTestId(testId)
    .find((element) => element.tagName === 'INPUT') as HTMLInputElement;

const openCommandModal = async (): Promise<void> => {
  await waitForListing();

  await userEvent.click(screen.getByRole('button', { name: labelCommand }));

  await waitForRequest('@getCommandDetails');

  expect(
    await screen.findByText(labelGenerateInstallationCommand)
  ).toBeVisible();
};

describe('Agent configurations', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays a welcome label when the listing is empty', async () => {
    initialize({ isListingEmpty: true });

    await waitForRequest('@getEmptyAgentConfigurations');

    expect(await screen.findByText(labelAgentsConfigurations)).toBeVisible();
    expect(
      await screen.findByText(labelWelcomeToTheAgentsConfigurationPage)
    ).toBeVisible();
    expect(
      screen.getByText(labelAddAgentConfiguration).closest('button')
    ).toBeVisible();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('displays the listing', async () => {
    initialize();

    await waitForListing();

    expect(screen.getByText(labelAgentsConfigurations)).toBeVisible();
    expect(screen.getByRole('button', { name: labelAdd })).toBeVisible();
    expect(screen.getByRole('button', { name: labelCommand })).toBeVisible();
    expect(screen.getByText(labelName)).toBeVisible();
    expect(screen.getByText(labelAgentType)).toBeVisible();
    expect(screen.getByText(labelPoller)).toBeVisible();
    expect(screen.getByText(labelAction)).toBeVisible();
    expect(screen.getAllByText('Telegraf')[0]).toBeVisible();
    expect(screen.getAllByText(labelCMA)).toHaveLength(2);
    expect(screen.getAllByText('2 pollers')[0]).toBeVisible();
    expect(screen.getByText('0 pollers')).toBeVisible();
    expect(screen.getByText('1-10 of 20')).toBeVisible();
    expect(getDeleteButtons()).toHaveLength(10);

    const names = Array.from({ length: 10 }, (_, index) => `AC ${index}`);

    expect(
      screen.getAllByText(/^AC \d+$/).map((element) => element.textContent)
    ).toEqual(names);
  });

  it('sends a request when a column sort is clicked', async () => {
    initialize();

    await waitForListing();

    await userEvent.click(screen.getByText(labelName));

    const { request } = await waitForRequest('@getAgentConfigurations');

    expect(decodeURIComponent(request.url.search)).toBe(
      '?page=1&limit=10&sort_by={"name":"desc"}&search={"$and":[]}'
    );

    await waitFor(() =>
      expect(
        screen
          .getByLabelText(`Column ${labelName}`)
          .querySelector('.MuiTableSortLabel-iconDirectionDesc')
      ).toBeInTheDocument()
    );
  });

  it('sends a listing request with the search bar content when after a delay', async () => {
    initialize();

    await waitForRequest('@getAgentConfigurations');

    const [searchBar] = await screen.findAllByPlaceholderText(labelSearch);

    const since = getRequestCalls('@getAgentConfigurations').length;

    await userEvent.clear(searchBar);
    await userEvent.type(searchBar, 'My agent');

    // Every keystroke refetches the listing (the filters are in its query
    // key), so this waits for a request carrying the whole text.
    await waitForListingSearch(
      { $and: [{ $or: [{ name: { $rg: 'My agent' } }] }] },
      { since }
    );
  });

  it('sends a listing request when the filters are updated and search button clicked', async () => {
    initialize();

    await waitForRequest('@getAgentConfigurations');

    await screen.findByText('AC 0');
    await userEvent.click(screen.getByLabelText(labelFilters));

    await userEvent.click(await screen.findByLabelText(labelPoller));
    await waitForRequest('@getFilterPollers');
    await userEvent.click(await screen.findByText('poller6'));

    await userEvent.click(screen.getByLabelText(labelAgentType));
    await selectOption(1);

    const nameFilter = getInput(labelName);

    await userEvent.clear(nameFilter);
    await userEvent.type(nameFilter, 'My agent');

    const expectedSearch = {
      $and: [
        { $or: [{ name: { $rg: 'My agent' } }] },
        { $or: [{ type: { $in: ['telegraf'] } }] },
        {
          $or: [{ 'poller.id': { $in: [6] } }]
        }
      ]
    };

    // Typing refetches the listing on its own: wait until it has settled on
    // the whole filter before counting the calls, so the request matched
    // below can only come from the Search button.
    await waitForListingSearch(expectedSearch, { since: 0 });

    const callsBeforeSearch = getListingCallCount();

    await userEvent.click(screen.getByRole('button', { name: labelSearch }));

    await waitFor(() => {
      const calls = getRequestCalls('@getAgentConfigurations');

      expect(calls.length).toBeGreaterThan(callsBeforeSearch);
      expect(
        JSON.parse(calls.at(-1)?.request.url.searchParams.get('search') ?? '')
      ).toEqual(expectedSearch);
    });

    // Three filters differ from their default value.
    expect(screen.getByText('3')).toBeVisible();
    expect(screen.getAllByPlaceholderText(labelSearch)[0]).toHaveValue(
      'My agent'
    );
  });

  it('deletes a selected poller when the icon is clicked', async () => {
    initialize();

    await waitForListing();

    await userEvent.click(screen.getByLabelText(labelFilters));
    await userEvent.click(await screen.findByLabelText(labelPoller));

    await waitForRequest('@getFilterPollers');

    await userEvent.click(await screen.findByText('poller6'));

    expect(
      within(await screen.findByTestId('FilterContainer')).getByText('poller6')
    ).toBeVisible();
    expect(screen.getByTestId('tag-option-chip-6')).toHaveClass(
      'MuiChip-colorPrimary'
    );

    await userEvent.click(await screen.findByTestId('CancelIcon'));

    // The chip goes while the filters are still open, and no filter differs
    // from its default value any more.
    await waitFor(() => {
      expect(
        within(screen.getByTestId('FilterContainer')).queryByText('poller6')
      ).not.toBeInTheDocument();
    });
    expect(screen.getByRole('button', { name: labelClear })).toBeDisabled();

    await userEvent.click(screen.getByLabelText(labelFilters));

    await waitFor(() =>
      expect(screen.queryByText('poller6')).not.toBeInTheDocument()
    );
  });

  it('deletes a selected agent type when the icon is clicked', async () => {
    initialize();

    await waitForListing();

    await userEvent.click(screen.getByLabelText(labelFilters));
    await userEvent.click(await screen.findByLabelText(labelAgentType));
    await selectOption(1);

    const filterContainer = await screen.findByTestId('FilterContainer');

    expect(await within(filterContainer).findByText('Telegraf')).toBeVisible();

    await userEvent.click(await screen.findByTestId('CancelIcon'));

    // No filter differs from its default value any more.
    await waitFor(() =>
      expect(screen.getByRole('button', { name: labelClear })).toBeDisabled()
    );
    expect(
      within(filterContainer).queryByText('Telegraf')
    ).not.toBeInTheDocument();

    await userEvent.click(screen.getByLabelText(labelFilters));

    await waitFor(() =>
      expect(screen.queryByTestId('FilterContainer')).not.toBeInTheDocument()
    );
  });

  it('clears filters when filters are populated and the corresponding button is clicked', async () => {
    initialize();

    await waitForRequest('@getAgentConfigurations');
    await userEvent.click(await screen.findByLabelText(labelFilters));
    await userEvent.click(await screen.findByLabelText(labelAgentType));
    await selectOption(1);
    await userEvent.click(screen.getByLabelText(labelPoller));

    await waitForRequest('@getFilterPollers');
    await userEvent.click(await screen.findByText('poller6'));

    const nameFilter = getInput(labelName);

    await userEvent.clear(nameFilter);
    await userEvent.type(nameFilter, 'My agent');

    const callsBeforeClear = getListingCallCount();

    await userEvent.click(screen.getByRole('button', { name: labelClear }));

    await waitForListingSearch({ $and: [] }, { since: callsBeforeClear });

    await waitFor(() => expect(getInput(labelName)).toHaveValue(''));
    expect(screen.queryByText('poller6')).not.toBeInTheDocument();
    expect(
      within(screen.getByTestId('FilterContainer')).queryByText('Telegraf')
    ).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: labelClear })).toBeDisabled();
  });

  it('cancels a poller deletion when the corresponding icon is clicked and the corresponding is clicked', async () => {
    initialize();

    await waitForListing();

    await userEvent.click(getDeleteButtons()[0]);

    const dialog = await getDialog();

    expect(within(dialog).getByText(labelDeleteAgent)).toBeVisible();
    expect(dialog).toBeVisible();
    expect(dialog).toHaveTextContent('You are going to delete the');
    expect(dialog).toHaveTextContent('AC 0');
    expect(dialog).toHaveTextContent(
      'agent configuration. All parameters for this agent configuration will be deleted. This action cannot be undone.'
    );

    await userEvent.click(within(dialog).getByText(labelCancel));

    await waitFor(() =>
      expect(screen.queryByText(labelDeleteAgent)).not.toBeInTheDocument()
    );
    expect(getRequestCalls('@deleteAgent')).toHaveLength(0);
  });

  it('deletes an agent when the corresponding action icon is clicked and the corresponding button is clicked', async () => {
    initialize();

    await waitForListing();

    await userEvent.click(getDeleteButtons()[0]);

    const dialog = await getDialog();

    expect(within(dialog).getByText(labelDeleteAgent)).toBeVisible();
    expect(dialog).toBeVisible();
    expect(dialog).toHaveTextContent('You are going to delete the');
    expect(dialog).toHaveTextContent('AC 0');
    expect(dialog).toHaveTextContent(
      'agent configuration. All parameters for this agent configuration will be deleted. This action cannot be undone.'
    );

    await userEvent.click(within(dialog).getByText(/^Delete$/));

    await waitForRequest('@deleteAgent');

    await waitFor(() =>
      expect(screen.queryByText(labelDeleteAgent)).not.toBeInTheDocument()
    );
    await waitForRequest('@getAgentConfigurations');
  });

  it('deletes a poller when the corresponding icon is clicked and the corresponding is clicked', async () => {
    initialize();

    await waitForListing();

    await userEvent.click(screen.getByTestId('Expand 0'));

    expect(await screen.findByText('poller 1')).toBeVisible();
    expect(screen.getByText('poller 2')).toBeVisible();

    await userEvent.click(getDeleteButtons()[1]);

    const dialog = await getDialog();

    expect(within(dialog).getByText(labelDeletePoller)).toBeVisible();
    expect(dialog).toBeVisible();
    expect(dialog).toHaveTextContent('You are going to delete the');
    expect(dialog).toHaveTextContent('AC 0');
    expect(dialog).toHaveTextContent(
      'agent configuration. All configuration parameters for this poller will be deleted. This action cannot be undone.'
    );

    await userEvent.click(within(dialog).getByText(/^Delete$/));

    await waitForRequest('@deletePoller');

    await waitFor(() =>
      expect(screen.queryByText(labelDeletePoller)).not.toBeInTheDocument()
    );
    expect(screen.queryByText(labelDeleteAgent)).not.toBeInTheDocument();
    expect(getRequestCalls('@deleteAgent')).toHaveLength(0);
    await waitForRequest('@getAgentConfigurations');
  });
});

describe('Agent configurations modal', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('does not validate the form when fields contain errors', async () => {
    initialize();

    await openAddModal();

    expect(
      screen.getByRole('button', { hidden: true, name: labelAdd })
    ).toBeVisible();

    await selectAgentType(0);
    touch(screen.getByLabelText(labelName));
    touch(screen.getByLabelText(labelPollers));
    await userEvent.type(screen.getAllByLabelText(labelPort)[0], '123456');
    await userEvent.type(
      screen.getAllByLabelText(labelPublicCertificate)[0],
      './test.cer'
    );
    await userEvent.type(
      screen.getByLabelText(labelCaCertificate),
      '//test.crt'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[0],
      'test.abc'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[1],
      'test.xyz'
    );
    await userEvent.type(screen.getByLabelText(labelCaCertificate), 'test.cer');
    touch(screen.getByLabelText(labelCaCertificate));

    expect(screen.getByLabelText(labelAgentType)).toHaveValue('Telegraf');
    await waitFor(() =>
      expect(screen.getAllByText(labelRequired)).toHaveLength(2)
    );
    expect(screen.getAllByText(labelPortExpectedAtMost)).toHaveLength(1);
    expect(screen.getAllByText(labelRelativePathAreNotAllowed)).toHaveLength(1);
    expect(screen.getAllByText(labelInvalidPath)).toHaveLength(1);
    expect(screen.getAllByText(labelInvalidExtension)).toHaveLength(2);
    expect(screen.getByRole('button', { name: labelSave })).toBeDisabled();

    // What was typed is appended to the default port.
    expect(screen.getAllByLabelText(labelPort)[0]).toHaveValue(1443123456);
    expect(screen.getByLabelText(labelCaCertificate)).toHaveValue(
      '//test.crttest.cer'
    );
  });

  it('leaves the form when the "Leave" button of the popup is clicked', async () => {
    initialize();

    await openAddModal();

    await userEvent.type(screen.getByLabelText(labelName), 'agent');

    await userEvent.click(screen.getByRole('button', { name: labelCancel }));
    await userEvent.click(await screen.findByText('Leave'));

    await waitFor(() =>
      expect(screen.queryByLabelText(labelName)).not.toBeInTheDocument()
    );
    expect(
      screen.queryByText(labelAddAgentConfiguration)
    ).not.toBeInTheDocument();
    expect(getRequestCalls('@postAgentConfiguration')).toHaveLength(0);
  });

  it('backs to the form when the "Stay" button of the popup is clicked', async () => {
    initialize();

    await openAddModal();

    await userEvent.type(screen.getByLabelText(labelName), 'agent');

    await userEvent.click(screen.getByRole('button', { name: labelCancel }));
    await userEvent.click(await screen.findByText('Stay'));

    expect(
      screen.getByRole('button', { hidden: true, name: labelAdd })
    ).toBeInTheDocument();
    await waitFor(() =>
      expect(screen.queryByText('Stay')).not.toBeInTheDocument()
    );
    expect(screen.getByText(labelAddAgentConfiguration)).toBeVisible();
    expect(screen.getByLabelText(labelName)).toHaveValue('agent');
  });

  it('sends the form when fields are valid and the corresponding button is clicked', async () => {
    initialize();

    await openAddModal();

    await selectAgentType(0);
    await userEvent.type(screen.getByLabelText(labelName), 'agent');
    await selectPoller('poller1');
    await userEvent.click(screen.getAllByText(labelPollers)[0]);
    await userEvent.clear(screen.getAllByLabelText(labelPort)[0]);
    await userEvent.type(screen.getAllByLabelText(labelPort)[0], '1234');
    await userEvent.type(
      screen.getAllByLabelText(labelPublicCertificate)[0],
      'test.crt'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[0],
      '/sub/test.key'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[1],
      '/sub/test.key'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPublicCertificate)[1],
      'test.cer'
    );
    await userEvent.click(screen.getByRole('button', { name: labelSave }));

    const { request } = await waitForRequest('@postAgentConfiguration');

    expect(request.body).toEqual({
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

    expect(
      await screen.findByText(labelAgentConfigurationCreated)
    ).toBeVisible();
    await waitFor(() =>
      expect(
        screen.queryByText(labelAddAgentConfiguration)
      ).not.toBeInTheDocument()
    );
  });

  it('sends the form when a listing row is clicked, fields are valid and the corresponding button is clicked', async () => {
    initialize();

    await openRow('AC 1');

    await userEvent.type(screen.getByLabelText(labelName), ' updated');

    await userEvent.click(screen.getByRole('button', { name: labelSave }));

    const { request } = await waitForRequest('@patchAgentConfiguration');

    expect(request.body).toEqual({
      configuration: {
        conf_certificate: '/sub/test.crt',
        conf_private_key: 'test.key',
        conf_server_port: 9090,
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'test.key',
        otel_public_certificate: 'test.cer'
      },
      connection_mode: 'secure',
      name: 'agent updated',
      poller_ids: [1, 2]
    });

    expect(
      await screen.findByText(labelAgentConfigurationUpdated)
    ).toBeVisible();
    await waitFor(() =>
      expect(
        screen.queryByText(labelUpdateAgentConfiguration)
      ).not.toBeInTheDocument()
    );
  });

  it('sends the form when a listing row is clicked, fields are valid, the cancel button is clicked and the corresponding button is clicked', async () => {
    initialize();

    await openRow('AC 1');

    await userEvent.type(screen.getByLabelText(labelName), ' updated');
    await userEvent.click(screen.getByRole('button', { name: labelCancel }));
    await userEvent.click(await screen.findByTestId('confirm'));

    const { request } = await waitForRequest('@patchAgentConfiguration');

    expect(request.body).toEqual({
      configuration: {
        conf_certificate: '/sub/test.crt',
        conf_private_key: 'test.key',
        conf_server_port: 9090,
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'test.key',
        otel_public_certificate: 'test.cer'
      },
      connection_mode: 'secure',
      name: 'agent updated',
      poller_ids: [1, 2]
    });

    expect(
      await screen.findByText(labelAgentConfigurationUpdated)
    ).toBeVisible();
    await waitFor(() =>
      expect(
        screen.queryByText(labelUpdateAgentConfiguration)
      ).not.toBeInTheDocument()
    );
  });

  it('disables the agent type field when editing an existing configuration', async () => {
    initialize();

    await userEvent.click(await screen.findByText('AC 1'));

    await waitForRequest('@getAgentConfiguration');

    await waitFor(() =>
      expect(screen.getByLabelText(labelAgentType)).toBeDisabled()
    );

    // The form shows the configuration the API answered.
    expect(screen.getByText(labelUpdateAgentConfiguration)).toBeVisible();
    await waitFor(() =>
      expect(screen.getByLabelText(labelName)).toHaveValue('agent')
    );
    expect(screen.getByLabelText(labelAgentType)).toHaveValue('Telegraf');
    expect(screen.getByLabelText(labelEncryptionLevel)).toHaveValue(labelTLS);
    expect(screen.getByText('poller 1')).toBeVisible();
    expect(screen.getByText('poller 2')).toBeVisible();
    expect(screen.getAllByLabelText(labelPublicCertificate)[0]).toHaveValue(
      'test.cer'
    );
    expect(screen.getByLabelText(labelCaCertificate)).toHaveValue('test.crt');
    expect(screen.getAllByLabelText(labelPrivateKey)[0]).toHaveValue(
      'test.key'
    );
    expect(screen.getAllByLabelText(labelPort)[0]).toHaveValue(9090);
    expect(screen.getAllByLabelText(labelPublicCertificate)[1]).toHaveValue(
      '/sub/test.crt'
    );
    expect(screen.getAllByLabelText(labelPrivateKey)[1]).toHaveValue(
      'test.key'
    );
  });

  it('displays the CMA form when the CMA agent type is selected', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);

    expect(await screen.findByTestId('By agent selected')).toBeVisible();
    expect(screen.queryByTestId('By poller selected')).not.toBeInTheDocument();

    expect(screen.getByText(labelConnectionInitiated)).toBeInTheDocument();
    expect(screen.getByText(labelOTLPReceiver)).toBeVisible();
    expect(screen.getByLabelText(labelPublicCertificate)).toHaveValue('');
    expect(screen.getAllByLabelText(labelCaCertificate)[0]).toHaveValue('');
    expect(screen.getByLabelText(labelPrivateKey)).toHaveValue('');

    // Agent-initiated connections are on by default, on the default port.
    expect(screen.getByRole('checkbox', { name: labelEnable })).toBeChecked();
    expect(screen.getByTestId('portInput')).toHaveValue(4317);
    expect(screen.getByRole('button', { name: labelSave })).toBeDisabled();
  });

  it('resets the form when a different agent type is selected', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);
    await userEvent.type(screen.getByLabelText(labelName), 'My agent');
    await userEvent.type(
      screen.getByLabelText(labelPublicCertificate),
      'something'
    );
    await userEvent.clear(screen.getByLabelText(labelPublicCertificate));
    touch(screen.getByLabelText(labelPublicCertificate));
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[0],
      'filename.abc'
    );
    touch(screen.getAllByLabelText(labelPrivateKey)[0]);

    expect(await screen.findByText(labelInvalidExtension)).toBeVisible();

    await selectAgentType(0);

    expect(await screen.findByText(labelConfigurationServer)).toBeVisible();
    expect(screen.queryByText(labelMonitoredHosts)).not.toBeInTheDocument();
    expect(screen.queryByText(labelRequired)).not.toBeInTheDocument();
    expect(screen.queryByText(labelInvalidExtension)).not.toBeInTheDocument();
    expect(screen.getByLabelText(labelName)).toHaveValue('My agent');

    // The Telegraf fields start from their defaults.
    expect(screen.getByLabelText(labelAgentType)).toHaveValue('Telegraf');
    expect(screen.getAllByLabelText(labelPort)[0]).toHaveValue(1443);
    expect(
      screen
        .getAllByLabelText(labelPrivateKey)
        .map((field) => (field as HTMLInputElement).value)
    ).toEqual(['', '']);
  });

  it('does not validate the form when there mode "By poller" is enabled and there no host configuration', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);

    await userEvent.type(screen.getByLabelText(labelName), 'My agent');
    await selectPoller('poller1');

    await userEvent.click(screen.getByText(labelAddAgentConfiguration));
    expect(screen.getByRole('button', { name: labelSave })).toBeDisabled();

    await userEvent.type(
      screen.getByLabelText(labelPublicCertificate),
      'test.crt'
    );
    await userEvent.type(screen.getByLabelText(labelCaCertificate), 'test.crt');
    await userEvent.type(screen.getByLabelText(labelPrivateKey), 'test.key');

    await selectToken(labelSelectExistingCMATokens);

    await userEvent.click(screen.getByText(labelByPoller));

    await userEvent.click(await screen.findByText(labelEnable));
    await waitFor(() => expect(screen.getByTestId('submit')).toBeDisabled());

    await userEvent.click(screen.getByText(labelEnable));
    await waitFor(() => expect(screen.getByTestId('submit')).toBeEnabled());

    expect(screen.getByTestId('By agent selected')).toBeInTheDocument();
    expect(screen.queryByTestId('By poller selected')).not.toBeInTheDocument();
    expect(
      screen.getByRole('checkbox', { name: labelEnable })
    ).not.toBeChecked();
  });

  it('validates the form when fields are filled and the only "By agent" mode is unchecked', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);

    await userEvent.type(screen.getByLabelText(labelName), 'My agent');
    await selectPoller('poller1');
    await userEvent.click(screen.getByText(labelAddAgentConfiguration));

    await userEvent.type(
      screen.getByLabelText(labelPublicCertificate),
      '/certificate/test.crt'
    );
    await userEvent.type(screen.getByLabelText(labelCaCertificate), 'test.crt');
    await userEvent.type(
      screen.getByLabelText(labelPrivateKey),
      'privateKey.key'
    );

    await selectToken(labelSelectExistingCMATokens);

    await userEvent.click(screen.getByTestId('submit'));

    const { request } = await waitForRequest('@postAgentConfiguration');

    expect(request.body).toEqual({
      configuration: {
        agent_initiated: true,
        create_host_auto: false,
        hosts: [],
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'privateKey.key',
        otel_public_certificate: '/certificate/test.crt',
        poller_initiated: false,
        port: 4317,
        tokens: [
          {
            creator_id: 1,
            name: 'token 1'
          }
        ]
      },
      connection_mode: 'secure',
      name: 'My agent',
      poller_ids: [1],
      type: 'centreon-agent'
    });

    expect(
      await screen.findByText(labelAgentConfigurationCreated)
    ).toBeVisible();
  });

  it('configures the host address and port when a host is selected', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);

    await userEvent.click(screen.getByText(labelByPoller));

    await userEvent.click(await screen.findByText(labelEnable));
    await userEvent.click(await screen.findByLabelText(labelSelectHost));

    await waitForRequest('@getHosts');

    await userEvent.click(await screen.findByText('central'));

    await waitFor(() =>
      expect(screen.getByLabelText(labelDNSIP)).toHaveValue('127.0.0.2')
    );
    expect(getInput('portInput')).toHaveValue(4317);

    expect(screen.getByLabelText(labelSelectHost)).toHaveValue('central');
    expect(screen.getByTestId('By poller selected')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: labelSave })).toBeDisabled();
  });

  it('splits the address and the port when a full address is pasted in the address field', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);
    await userEvent.click(screen.getByText(labelByPoller));

    await userEvent.click(await screen.findByText(labelEnable));

    await userEvent.type(
      await screen.findByLabelText(labelDNSIP),
      '127.0.0.1:8'
    );
    await waitFor(() =>
      expect(screen.getByLabelText(labelDNSIP)).toHaveValue('127.0.0.1')
    );
    expect(getInput('portInput')).toHaveValue(8);

    expect(screen.getByLabelText(labelSelectHost)).toHaveValue('');
  });

  it('adds a new host configuration when the corresponding button is clicked', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);
    await userEvent.click(screen.getByText(labelByPoller));
    await userEvent.click(await screen.findByText(labelEnable));

    await userEvent.click(await screen.findByText(labelAddAHost));

    expect(
      await screen.findByTestId('delete-host-configuration-1')
    ).toBeInTheDocument();
    expect(screen.getByTestId('delete-host-configuration-0')).toBeVisible();

    // Both host configurations start empty.
    expect(
      screen
        .getAllByLabelText(labelSelectHost)
        .map((field) => (field as HTMLInputElement).value)
    ).toEqual(['', '']);
  });

  it('removes a host configuration when the corresponding button is clicked', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);

    await userEvent.click(screen.getByText(labelByPoller));
    await userEvent.click(await screen.findByText(labelEnable));

    await userEvent.click(await screen.findByText(labelAddAHost));

    expect(
      await screen.findByTestId('delete-host-configuration-1')
    ).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('delete-host-configuration-1'));

    await waitFor(() =>
      expect(screen.getByTestId('delete-host-configuration-0')).toBeDisabled()
    );
    expect(
      screen.queryByTestId('delete-host-configuration-1')
    ).not.toBeInTheDocument();
    expect(screen.getAllByLabelText(labelSelectHost)).toHaveLength(1);
  });

  it('sends the CMA agent type when the form is valid and the save button is clicked', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);
    await userEvent.type(screen.getByLabelText(labelName), 'My agent');
    await selectPoller('poller1');
    await userEvent.click(screen.getByText(labelConnectionInitiated));
    await userEvent.type(
      screen.getByLabelText(labelPublicCertificate),
      '/test.cer'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelCaCertificate)[0],
      'test.crt'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[0],
      'private.key'
    );
    await selectToken(labelSelectExistingCMATokens);

    await enablePollerInitiated();
    await selectHost();
    await userEvent.type(screen.getByLabelText(labelCaCertificate), 'test.crt');
    expect(screen.queryByLabelText(labelCACommonName)).not.toBeInTheDocument();

    await selectToken(labelSelectExistingCMAToken);

    await userEvent.click(screen.getByTestId('submit'));

    const { request } = await waitForRequest('@postAgentConfiguration');

    expect(request.body).toEqual({
      configuration: {
        agent_initiated: true,
        create_host_auto: false,
        hosts: [
          {
            address: '127.0.0.2',
            id: 1,
            poller_ca_certificate: 'test.crt',
            poller_ca_name: '',
            port: 4317,
            token: { creator_id: 1, name: 'token 1' }
          }
        ],
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'private.key',
        otel_public_certificate: '/test.cer',
        poller_initiated: true,
        port: 4317,
        tokens: [{ creator_id: 1, name: 'token 1' }]
      },
      connection_mode: 'secure',
      name: 'My agent',
      poller_ids: [1],
      type: 'centreon-agent'
    });

    expect(
      await screen.findByText(labelAgentConfigurationCreated)
    ).toBeVisible();
  });

  it('displays the encryption level field with TLS preselected as the default value', async () => {
    initialize();

    await openAddModal();

    expect(screen.getByLabelText(labelEncryptionLevel)).toHaveValue(labelTLS);

    // Nothing else is filled in until an agent type is chosen.
    expect(screen.getByLabelText(labelName)).toHaveValue('');
    expect(screen.getByLabelText(labelAgentType)).toHaveValue('');
    expect(screen.queryByLabelText(labelPollers)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: labelSave })).toBeDisabled();
  });

  it('displays a warning message when switching the encryption level to No TLS for Telegraf and CMA agents', async () => {
    initialize();

    await openAddModal();

    await selectAgentType(0);
    await userEvent.click(screen.getByLabelText(labelEncryptionLevel));
    await userEvent.click(await screen.findByText(labelNoTLS));

    expect(
      await screen.findByText(labelWarningEncryptionLevelTelegraf)
    ).toBeVisible();

    await selectAgentType(1);
    expect(
      await screen.findByText(labelWarningEncryptionLevelCMA)
    ).toBeVisible();

    expect(screen.getByLabelText(labelEncryptionLevel)).toHaveValue(labelNoTLS);
    expect(
      screen.queryByText(labelWarningEncryptionLevelTelegraf)
    ).not.toBeInTheDocument();
  });

  it('handle the form without certificate fields when setting encryption level to No TLS for Telegraf agent', async () => {
    initialize();

    await openAddModal();

    await selectAgentType(0);
    await userEvent.click(screen.getByLabelText(labelEncryptionLevel));
    await userEvent.click(await screen.findByText(labelNoTLS));

    await userEvent.type(screen.getByLabelText(labelName), 'My agent');
    await selectPoller('poller1');

    await userEvent.clear(screen.getAllByLabelText(labelPort)[0]);
    await userEvent.type(screen.getAllByLabelText(labelPort)[0], '1234');

    expect(screen.queryAllByLabelText(labelPublicCertificate)).toHaveLength(0);
    expect(screen.queryByLabelText(labelCaCertificate)).not.toBeInTheDocument();
    expect(screen.queryAllByLabelText(labelPrivateKey)).toHaveLength(0);
    expect(screen.queryAllByLabelText(labelPrivateKey)).toHaveLength(0);
    expect(screen.queryByLabelText(labelCaCertificate)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(labelCaCertificate)).not.toBeInTheDocument();

    expect(screen.getByText(labelConfigurationServer)).toBeVisible();
    await waitFor(() =>
      expect(screen.getByRole('button', { name: labelSave })).toBeEnabled()
    );

    await userEvent.click(screen.getByRole('button', { name: labelSave }));

    const { request } = await waitForRequest('@postAgentConfiguration');

    expect(request.body).toEqual({
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

    expect(
      await screen.findByText(labelAgentConfigurationCreated)
    ).toBeVisible();
  });

  it('handle the form without certificate fields when setting encryption level to No TLS for CMA agent', async () => {
    initialize();

    await openAddModal();

    await selectAgentType(1);

    await userEvent.type(screen.getByLabelText(labelName), 'My agent');
    await selectPoller('poller1');

    await userEvent.click(screen.getByLabelText(labelEncryptionLevel));
    await userEvent.click(await screen.findByText(labelNoTLS));

    await waitFor(() =>
      expect(
        screen.queryByLabelText(labelPublicCertificate)
      ).not.toBeInTheDocument()
    );
    expect(screen.queryAllByLabelText(labelCaCertificate)).toHaveLength(0);
    expect(screen.queryAllByLabelText(labelPrivateKey)).toHaveLength(0);
    await selectToken(labelSelectExistingCMATokens);

    await enablePollerInitiated();

    await selectHost();
    expect(screen.queryByLabelText(labelCACommonName)).not.toBeInTheDocument();
    await selectToken(labelSelectExistingCMAToken);

    // No certificate is asked for the monitored host either.
    expect(screen.queryAllByLabelText(labelCaCertificate)).toHaveLength(0);
    await waitFor(() => expect(screen.getByTestId('submit')).toBeEnabled());

    await userEvent.click(screen.getByTestId('submit'));

    const { request } = await waitForRequest('@postAgentConfiguration');

    expect(request.body).toEqual({
      configuration: {
        agent_initiated: true,
        create_host_auto: false,
        hosts: [
          {
            address: '127.0.0.2',
            id: 1,
            poller_ca_certificate: null,
            poller_ca_name: null,
            port: 4317,
            token: { creator_id: 1, name: 'token 1' }
          }
        ],
        otel_ca_certificate: null,
        otel_private_key: null,
        otel_public_certificate: null,
        poller_initiated: true,
        port: 4317,
        tokens: [{ creator_id: 1, name: 'token 1' }]
      },
      connection_mode: 'no-tls',
      name: 'My agent',
      poller_ids: [1],
      type: 'centreon-agent'
    });

    expect(
      await screen.findByText(labelAgentConfigurationCreated)
    ).toBeVisible();
  });

  it('saves the form with certificate fields when connection mode is insecure', async () => {
    initialize();

    await openAddModal();

    await selectAgentType(0);

    await userEvent.click(screen.getByLabelText(labelEncryptionLevel));
    await userEvent.click(await screen.findByText(labelInsecure));

    await userEvent.type(screen.getByLabelText(labelName), 'Insecure Agent');
    await selectPoller('poller1');
    await userEvent.click(screen.getByText(labelAddAgentConfiguration));
    await userEvent.clear(screen.getAllByLabelText(labelPort)[0]);
    await userEvent.type(screen.getAllByLabelText(labelPort)[0], '1234');

    expect(
      screen.getAllByLabelText(labelPublicCertificate)[0]
    ).toBeInTheDocument();
    expect(screen.getByLabelText(labelCaCertificate)).toBeInTheDocument();
    expect(screen.getAllByLabelText(labelPrivateKey)).toHaveLength(2);

    await userEvent.type(
      screen.getAllByLabelText(labelPublicCertificate)[0],
      'test.crt'
    );
    await userEvent.type(screen.getByLabelText(labelCaCertificate), 'ca.crt');
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[0],
      'test.key'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[1],
      'test.key'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPublicCertificate)[1],
      'test.cer'
    );

    expect(screen.getByLabelText(labelEncryptionLevel)).toHaveValue(
      labelInsecure
    );
    await waitFor(() =>
      expect(screen.getByRole('button', { name: labelSave })).toBeEnabled()
    );

    await userEvent.click(screen.getByRole('button', { name: labelSave }));

    const { request } = await waitForRequest('@postAgentConfiguration');

    expect(request.body).toEqual({
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

    expect(
      await screen.findByText(labelAgentConfigurationCreated)
    ).toBeVisible();
  });

  it('sends an update request with certificate fields when connection mode is insecure', async () => {
    initialize();

    await openRow('AC 1');

    await userEvent.click(screen.getByLabelText(labelEncryptionLevel));
    await userEvent.click(await screen.findByText(labelInsecure));

    await userEvent.clear(screen.getByLabelText(labelName));
    await userEvent.type(screen.getByLabelText(labelName), 'Insecure Agent');

    // The certificates of the configuration are kept.
    expect(screen.getByLabelText(labelEncryptionLevel)).toHaveValue(
      labelInsecure
    );
    expect(screen.getAllByLabelText(labelPublicCertificate)[0]).toHaveValue(
      'test.cer'
    );
    expect(screen.getByLabelText(labelCaCertificate)).toHaveValue('test.crt');
    expect(screen.getAllByLabelText(labelPublicCertificate)[1]).toHaveValue(
      '/sub/test.crt'
    );

    await userEvent.click(screen.getByRole('button', { name: labelSave }));

    const { request } = await waitForRequest('@patchAgentConfiguration');

    expect(request.body).toEqual({
      configuration: {
        conf_certificate: '/sub/test.crt',
        conf_private_key: 'test.key',
        conf_server_port: 9090,
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'test.key',
        otel_public_certificate: 'test.cer'
      },
      connection_mode: 'insecure',
      name: 'Insecure Agent',
      poller_ids: [1, 2]
    });

    expect(
      await screen.findByText(labelAgentConfigurationUpdated)
    ).toBeVisible();
  });

  it('displays the CA common name field when connection mode is set to insecure', async () => {
    initialize();

    await openAddModal();
    await selectAgentType(1);

    await userEvent.click(screen.getByLabelText(labelEncryptionLevel));
    await userEvent.click(await screen.findByText(labelInsecure));

    await userEvent.type(screen.getByLabelText(labelName), 'My agent');
    await selectPoller('poller1');
    await userEvent.click(screen.getByText(labelConnectionInitiated));
    await userEvent.type(
      screen.getByLabelText(labelPublicCertificate),
      '/test.cer'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelCaCertificate)[0],
      'test.crt'
    );
    await userEvent.type(
      screen.getAllByLabelText(labelPrivateKey)[0],
      'private.key'
    );
    await selectToken(labelSelectExistingCMATokens);

    await enablePollerInitiated();
    await selectHost();
    await userEvent.type(screen.getByLabelText(labelCaCertificate), 'test.crt');
    await userEvent.type(screen.getByLabelText(labelCACommonName), 'test.crt');

    await selectToken(labelSelectExistingCMAToken);

    await userEvent.click(screen.getByTestId('submit'));

    const { request } = await waitForRequest('@postAgentConfiguration');

    expect(request.body).toEqual({
      configuration: {
        agent_initiated: true,
        create_host_auto: false,
        hosts: [
          {
            address: '127.0.0.2',
            id: 1,
            poller_ca_certificate: 'test.crt',
            poller_ca_name: 'test.crt',
            port: 4317,
            token: { creator_id: 1, name: 'token 1' }
          }
        ],
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'private.key',
        otel_public_certificate: '/test.cer',
        poller_initiated: true,
        port: 4317,
        tokens: [{ creator_id: 1, name: 'token 1' }]
      },
      connection_mode: 'insecure',
      name: 'My agent',
      poller_ids: [1],
      type: 'centreon-agent'
    });

    expect(
      await screen.findByText(labelAgentConfigurationCreated)
    ).toBeVisible();
  });

  it('opens modal from global command button and shows values for central poller', async () => {
    initialize();

    await openCommandModal();

    expect(screen.getByText(labelAgentsConfigurations)).toBeVisible();
    expect(screen.getByText(labelCommandWarning)).toBeVisible();
    expect(screen.getByText(labelSelectPollerThatWillMonitor)).toBeVisible();

    expect(getInput('Select poller')).toHaveValue('Central');

    expect(screen.getByText(labelSelectOperatingSystem)).toBeVisible();

    expect(screen.getByTestId('Windows')).toHaveAttribute(
      'data-selected',
      'true'
    );
    expect(screen.getByTestId('Linux')).toBeVisible();

    expect(screen.getByText(labelExecuteTheScript)).toBeInTheDocument();

    expect((await screen.findByTestId(labelCommand)).textContent).toBe(
      windowsCommandLine
    );

    expect(screen.getByTestId('CloseIcon')).toBeInTheDocument();
  });

  it('opens modal from row command icon and display the values for the selected poller', async () => {
    initialize();

    await waitForListing();

    expect(screen.getByText(labelAgentsConfigurations)).toBeVisible();

    await userEvent.click(screen.getAllByTestId('ExpandMoreIcon')[2]);

    await waitFor(() =>
      expect(screen.getAllByTestId(labelCommand)).toHaveLength(2)
    );
    await userEvent.click(screen.getAllByTestId(labelCommand)[0]);

    await waitForRequest('@getCommandDetails');

    expect(
      await screen.findByText(labelGenerateInstallationCommand)
    ).toBeVisible();

    await waitFor(() =>
      expect(getInput('Select poller')).toHaveValue('Central')
    );

    expect(screen.getByTestId('CloseIcon')).toBeInTheDocument();
  });

  it('updates displayed command when changing operating system', async () => {
    initialize();

    await openCommandModal();

    expect(screen.getByText(labelAgentsConfigurations)).toBeVisible();
    expect(screen.getByText(labelSelectOperatingSystem)).toBeVisible();

    expect(screen.getByTestId('Windows')).toHaveAttribute(
      'data-selected',
      'true'
    );
    expect(screen.getByTestId('Linux')).toHaveAttribute(
      'data-selected',
      'false'
    );
    await waitFor(() =>
      expect(screen.getByTestId(labelCommand).textContent).toBe(
        windowsCommandLine
      )
    );

    await userEvent.click(screen.getByTestId('Linux'));
    await waitFor(() =>
      expect(screen.getByTestId('Linux')).toHaveAttribute(
        'data-selected',
        'true'
      )
    );
    expect(screen.getByTestId('Windows')).toHaveAttribute(
      'data-selected',
      'false'
    );
    expect(screen.getByTestId(labelCommand).textContent).toBe(linuxCommandLine);

    expect(getInput('Select poller')).toHaveValue('Central');
    expect(screen.getByTestId('CloseIcon')).toBeInTheDocument();
  });
});
