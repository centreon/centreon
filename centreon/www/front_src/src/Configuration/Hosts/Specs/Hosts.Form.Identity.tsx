import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelDataProcessing,
  labelDown,
  labelHostConfiguration,
  labelHostExtendedInfos,
  labelHostNotFound,
  labelInvalidAddress,
  labelNameMustNotStartWithModule,
  labelNotification,
  labelRelations,
  labelRequired,
  labelResolve,
  labelYes
} from '../translatedLabels';
import initialize from './initialize';
import { refusedAddressResponse, resolvedAddressResponse } from './utils';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHosts');
};

const openRow = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
};

const openCreationForm = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('add-resource'));
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getAddress = (): Promise<HTMLInputElement> =>
  getInput('host-form-address');

const getResolveButton = (): Promise<HTMLElement> =>
  screen.findByTestId('host-form-address-resolve');

const getHeader = (): Promise<HTMLElement> =>
  screen.findByTestId(panelDataTestIds.header);

const getSaveButton = (): HTMLButtonElement | null =>
  document.querySelector<HTMLButtonElement>(
    `button[data-testid="${panelDataTestIds.save}"]`
  );

const expectHeader = async (text: string): Promise<void> => {
  const header = await getHeader();

  await waitFor(() =>
    expect(header).toHaveTextContent(new RegExp(`^${text}$`))
  );
};

export default () => {
  it('opens the form of a host in a side panel rather than a modal', async () => {
    await initializeListing();

    await openRow('host 0');

    expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
    expect(screen.queryByTestId('Modal')).not.toBeInTheDocument();

    // Named by the listing row, with no detail endpoint on this module.
    await expectHeader('host 0');
  });

  it('opens the creation form in the same panel', async () => {
    await initializeListing();

    await openCreationForm();

    expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
    expect(screen.queryByTestId('Modal')).not.toBeInTheDocument();

    await expectHeader('Add a host');
  });

  it('declares the five sections the US defines', async () => {
    await initializeListing();

    await openCreationForm();

    for (const section of [
      labelHostConfiguration,
      labelNotification,
      labelRelations,
      labelDataProcessing,
      labelHostExtendedInfos
    ]) {
      // The title shows more than once: the first, as `cy.contains` took.
      expect((await screen.findAllByText(section))[0]).toBeVisible();
    }
  });

  it('opens an existing host on the values the detail endpoint returns', async () => {
    await initializeListing();

    await openRow('host 0');

    const { request } = await waitForRequest('@getHost');

    // API Platform, not the legacy detail endpoint under `api/latest`.
    expect(request.url.pathname).toContain('/api/configuration/hosts/0');
    expect(request.url.pathname).not.toContain('/api/latest');

    // Values of the detail response, none of which the listing row carries.
    const name = await getInput('host-form-name');
    const address = await getAddress();
    const alias = await getInput('host-form-alias');

    await waitFor(() =>
      expect(name).toHaveValue('host 0 as the detail endpoint spells it')
    );
    await waitFor(() => expect(address).toHaveValue('10.10.10.10'));
    await waitFor(() =>
      expect(alias).toHaveValue(
        'alias of host 0 as the detail endpoint spells it'
      )
    );
    // Once the default is known too, so it cannot be what this checks.
    await waitForRequest('@getFormPollers');
    await waitFor(() =>
      expect(screen.getByTestId('host-form-poller')).toHaveValue('Poller EU')
    );

    // The header keeps naming the row, which is what the listing showed.
    await expectHeader('host 0');
  });

  it('saves an edited host back to the host it was opened on', async () => {
    await initializeListing();

    await openRow('host 0');

    await waitForRequest('@getHost');

    const address = await getAddress();

    // The detail values must be in the form before the field is edited, or
    // they would overwrite the edit (Cypress retried `.clear()` until then).
    await waitFor(() => expect(address).toHaveValue('10.10.10.10'));

    await userEvent.clear(address);
    await userEvent.type(address, '10.0.0.42');

    // Enabled once the edit is validated (Cypress retried the click).
    await waitFor(() => expect(getSaveButton()).toBeEnabled());
    await userEvent.click(getSaveButton() as HTMLButtonElement);

    // The same PATCH route enable and disable use, on host 0 and no other.
    const { request } = await waitForRequest('@patchHost');

    expect(request.url.pathname).toContain('/api/configuration/hosts/0');
    expect(request.body).toEqual({
      address: '10.0.0.42',
      alias: 'alias of host 0 as the detail endpoint spells it',
      category_ids: [4],
      // The arguments go back as the list they came as.
      check_options: {
        args: ['3', '80%'],
        command_id: 9,
        // A null value keeps the stored password; the untouched
        // inherited macros are left out.
        macros: [
          {
            id: 1,
            is_password: false,
            name: 'SNMPPORT',
            parent: null,
            value: '161'
          },
          {
            id: 2,
            is_password: true,
            name: 'API_TOKEN',
            parent: null,
            value: null
          }
        ]
      },
      child_host_ids: [2],
      // Never read back, so off as legacy opens an existing host.
      create_services_linked_to_templates: false,
      data_processing: {
        acknowledgment_timeout: 15,
        check_freshness: 'true',
        event_handler_args: ['80', 'graceful'],
        event_handler_command_id: 7,
        event_handler_enabled: 'false',
        flap_detection_enabled: 'true',
        freshness_threshold: 120,
        high_flap_threshold: 50,
        low_flap_threshold: null
      },
      extended_informations: {
        action_url: 'https://example.com/actions/host-0',
        alt_icon: null,
        comment: 'Racked in room B',
        geo_coordinates: '48.8566,2.3522',
        icon_id: 12,
        note: 'Front web server',
        note_url: null
      },
      host_group_ids: [1],
      name: 'host 0 as the detail endpoint spells it',
      notifications: {
        contact_groups: [3],
        contacts: [1],
        enabled: 'false',
        first_delay: 1,
        interval: 3,
        options: ['down', 'recovery'],
        recovery_delay: null,
        timeperiod_id: 1
      },
      parent_host_ids: [1],
      poller_id: 2,
      scheduling_options: {
        active_check_enabled: 'false',
        check_timeperiod_id: 2,
        max_check_attempts: 3,
        normal_check_interval: 5,
        passive_check_enabled: 'true',
        retry_check_interval: null
      },
      severity_id: 2,
      snmp_version: '2c',
      template_ids: [6, 5],
      timezone_id: 7
    });
  });

  it('names a host that has no icon of its own', async () => {
    await initializeListing();

    // `skip_null_values` leaves host 1 without an `icon` key at all.
    await openRow('host 1');

    await expectHeader('host 1');

    expect(
      (await getHeader()).parentElement?.querySelector('img')
    ).not.toBeInTheDocument();
  });

  it('shows the host icon the listing carried into the panel', async () => {
    await initializeListing();

    await openRow('host 0');

    const header = await getHeader();

    await waitFor(() =>
      expect(header.parentElement?.querySelector('img')).toHaveAttribute(
        'alt',
        'server.png'
      )
    );
  });

  it('makes the form read-only for a user who may only look at hosts', async () => {
    await initializeListing({ hasWriteAccess: false });

    await openRow('host 0');

    // The host is still loaded and shown: read-only, not empty.
    await waitForRequest('@getHost');

    const name = await getInput('host-form-name');

    await waitFor(() =>
      expect(name).toHaveValue('host 0 as the detail endpoint spells it')
    );
    expect(name).toBeDisabled();
    expect(await getAddress()).toBeDisabled();

    // The autocomplete is a different rendering path from a text field, and
    // the one that can read as greyed while still offering its options.
    expect(await screen.findByTestId('host-form-poller')).toBeDisabled();
    for (const testId of [
      'host-form-groups',
      'host-form-categories',
      'host-form-parent-hosts',
      'host-form-child-hosts',
      'host-form-notifications-contacts',
      'host-form-notifications-contact-groups',
      'host-form-notifications-timeperiod',
      'host-form-check-options-command',
      'host-form-scheduling-options-checkPeriod'
    ]) {
      expect(await screen.findByTestId(testId)).toBeDisabled();
    }
    // Host 0 has a command, so only read-only mode keeps its args disabled.
    expect(await getInput('host-form-check-options-args')).toBeDisabled();
    expect(await getInput('host-form-notifications-interval')).toBeDisabled();
    expect(
      within(
        await screen.findByTestId('host-form-notifications-enabled')
      ).getByRole('button', { name: labelYes })
    ).toBeDisabled();
    expect(
      await screen.findByTestId(`host-form-notifications-options-${labelDown}`)
    ).toHaveAttribute('aria-disabled', 'true');

    expect(getSaveButton()).not.toBeInTheDocument();
  });

  it('drops the Notification section on a cloud platform', async () => {
    await initializeListing({ isCloudPlatform: true });

    await openCreationForm();

    for (const section of [
      labelHostConfiguration,
      labelRelations,
      labelDataProcessing,
      labelHostExtendedInfos
    ]) {
      // The title shows more than once: the first, as `cy.contains` took.
      expect((await screen.findAllByText(section))[0]).toBeVisible();
    }

    // Checked once the other sections are rendered, so it cannot pass only
    // because the form is not there yet. `cy.contains` matched a substring.
    expect(screen.queryAllByText(new RegExp(labelNotification))).toHaveLength(
      0
    );
  });

  it('refuses to save until the mandatory fields are filled', async () => {
    await initializeListing();

    await openCreationForm();

    await waitFor(() => expect(getSaveButton()).toBeDisabled());

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');

    // Disabled before the name was typed too: the address's error, shown once
    // the field is left, proves the form validated the name as typed and
    // still found a mandatory field missing.
    const address = await getAddress();

    act(() => {
      address.focus();
      address.blur();
    });
    expect(
      await within(
        address.closest('.MuiFormControl-root') as HTMLElement
      ).findByText(labelRequired)
    ).toBeVisible();

    expect(getSaveButton()).toBeDisabled();
  });

  it('reports a name the API reserves for modules', async () => {
    await initializeListing();

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), '_Module_BAM');
    await userEvent.tab();

    expect(
      await screen.findByText(labelNameMustNotStartWithModule)
    ).toBeVisible();
  });

  it('reports an address that is neither an IP nor a resolvable name', async () => {
    await initializeListing();

    await openCreationForm();

    await userEvent.type(await getAddress(), 'not a host!');
    await userEvent.tab();

    expect(await screen.findByText(labelInvalidAddress)).toBeVisible();
  });

  it('replaces the address with the IP its name resolves to', async () => {
    await initializeListing();

    await openCreationForm();

    const address = await getAddress();

    await userEvent.type(address, ` ${resolvedAddressResponse.hostname} `);

    const resolve = await getResolveButton();

    expect(resolve).toHaveTextContent(new RegExp(`^${labelResolve}$`));
    await userEvent.click(resolve);

    // The glob matches both bases, so the base itself needs asserting.
    const { request } = await waitForRequest('@resolveAddress');

    expect(request.url.pathname).toContain('/api/configuration/hosts/_resolve');
    expect(request.url.pathname).not.toContain('/api/latest');
    expect(request.url.searchParams.get('hostname')).toBe(
      resolvedAddressResponse.hostname
    );

    await waitFor(() =>
      expect(address).toHaveValue(resolvedAddressResponse.ip)
    );
    expect(
      await screen.findByText(
        `${resolvedAddressResponse.hostname} → ${resolvedAddressResponse.ip}`
      )
    ).toBeVisible();
  });

  it('keeps an address that does not resolve and says so', async () => {
    await initializeListing({ addressResolution: 'unresolved' });

    await openCreationForm();

    const address = await getAddress();

    await userEvent.type(address, resolvedAddressResponse.hostname);

    await userEvent.click(await getResolveButton());

    await waitForRequest('@resolveAddress');

    expect(await screen.findByText(labelHostNotFound)).toBeVisible();
    expect(address).toHaveValue(resolvedAddressResponse.hostname);
  });

  it('shows why the API refuses to resolve an address', async () => {
    await initializeListing({ addressResolution: 'refused' });

    await openCreationForm();

    const address = await getAddress();

    await userEvent.type(address, 'srv_01');

    await userEvent.click(await getResolveButton());

    await waitForRequest('@resolveAddress');

    expect(
      await screen.findByText(refusedAddressResponse.message.trim())
    ).toBeVisible();
    expect(address).toHaveValue('srv_01');
  });

  it('offers to resolve the address only once there is one', async () => {
    await initializeListing();

    await openCreationForm();

    const resolve = await getResolveButton();
    const address = await getAddress();

    expect(resolve).toBeDisabled();

    // Blanks are trimmed before they are sent, so they count as nothing.
    await userEvent.type(address, '   ');
    expect(resolve).toBeDisabled();

    await userEvent.type(address, 'srv-apache-02');
    await waitFor(() => expect(resolve).toBeEnabled());
  });

  it('offers no resolution for an address that already is an IP', async () => {
    await initializeListing();

    await openCreationForm();

    const address = await getAddress();
    const resolve = await getResolveButton();

    await userEvent.type(address, '10.0.0.42');
    expect(resolve).toBeDisabled();

    await userEvent.clear(address);
    await userEvent.type(address, 'fe80::1');
    expect(resolve).toBeDisabled();

    await userEvent.clear(address);
    await userEvent.type(address, '10.0.0.42');

    await userEvent.type(address, '.example.com');
    await waitFor(() => expect(resolve).toBeEnabled());
  });

  it('does not let a user who may only look at hosts resolve an address', async () => {
    await initializeListing({ hasWriteAccess: false });

    await openRow('host 1');

    await waitForRequest('@getHost1');

    // A name, so only the missing write access disables it.
    const address = await getAddress();

    await waitFor(() => expect(address).toHaveValue('host-1.example.com'));
    expect(await getResolveButton()).toBeDisabled();
  });

  it('offers no resolve button on a cloud platform', async () => {
    await initializeListing({ isCloudPlatform: true });

    await openCreationForm();

    await userEvent.type(await getAddress(), 'srv-apache-02');

    expect(
      screen.queryByTestId('host-form-address-resolve')
    ).not.toBeInTheDocument();
  });
};
