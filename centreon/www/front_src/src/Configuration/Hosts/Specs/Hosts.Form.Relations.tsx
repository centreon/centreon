import { getRequestCalls, waitForRequest } from '@test-setup/api';
import {
  act,
  fireEvent,
  screen,
  waitFor,
  within
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelChildHosts,
  labelDown,
  labelDowntimeScheduled,
  labelFlapping,
  labelHostCategories,
  labelHostGroups,
  labelHostNotFound,
  labelInheritFromTemplate,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelNo,
  labelParentHosts,
  labelRecovery,
  labelRequired,
  labelUnreachable,
  labelYes
} from '../translatedLabels';
import initialize, { pollersForbiddenMessage } from './initialize';
import {
  resolvedAddressResponse,
  untouchedCheckOptionsPayload,
  untouchedDataProcessingPayload,
  untouchedExtendedInformationsPayload,
  untouchedNotificationsPayload,
  untouchedSchedulingOptionsPayload
} from './utils';

// `@centreon/ui` text fields put the same test id on the wrapper and the
// input: the input is the second one.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getSaveButton = (): HTMLButtonElement =>
  document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

// Enabled once the form has validated what was typed last: a click before
// that is lost (Cypress retried it until the button was actionable).
const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled());
  await userEvent.click(getSaveButton());
};

const openCreationForm = async (): Promise<void> => {
  // A click before the rows are listed is lost: the panel does not open.
  await screen.findByText('host 0');
  await userEvent.click(
    document.querySelector('[data-testid="add-resource"]') as HTMLElement
  );
};

const openHost = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
};

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: a name can also appear in the listing behind it.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

// A response with nothing visible to mark its arrival: once the mock has
// answered, leave the page the time to apply it.
const letResponseLand = (): Promise<void> =>
  new Promise((resolve) => {
    setTimeout(resolve, 500);
  });

// The chips of the autocomplete found by its label.
const expectSingleChip = async (label: string, chip: string): Promise<void> => {
  await waitFor(() => {
    const chips = (
      screen.getByLabelText(label).closest('.MuiAutocomplete-root') as Element
    ).querySelectorAll('.MuiChip-root');

    expect(chips).toHaveLength(1);
    expect(chips[0]).toHaveTextContent(new RegExp(`^${chip}$`));
  });
};

export default () => {
  it('opens the creation form on the default monitoring server', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    // The form's own selector, the one the poller field reads.
    const { request: pollersRequest } = await waitForRequest('@getFormPollers');

    expect(pollersRequest.url.pathname).toContain(
      '/api/configuration/hosts/pollers'
    );
    expect(pollersRequest.url.pathname).not.toContain('/api/latest');

    // The default is held until a creation form opens, and one opened before
    // it is applied goes without it: nothing on the listing shows it, so
    // leave the page the time to apply the answer.
    await letResponseLand();

    await openCreationForm();

    await waitFor(() =>
      expect(screen.getByTestId('host-form-poller')).toHaveValue('Poller US')
    );

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
    await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

    await save();

    const { request } = await waitForRequest('@createHost');

    expect(request.body).toHaveProperty('poller_id', 3);
  });

  it('keeps what was typed when the default monitoring server arrives late', async () => {
    initialize({ pollersDelay: 1500 });

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');

    // The request went out with the page, and nothing visible changes when
    // its response lands: wait for the mock to answer, then for the page to
    // apply the answer.
    await waitForRequest('@getFormPollers');
    await letResponseLand();

    // A late default would reinitialise the form; this one goes without.
    expect(await getInput('host-form-name')).toHaveValue('srv-apache-02');
    expect(screen.getByTestId('host-form-poller')).toHaveValue('');
  });

  it('opens the creation form with no monitoring server when none is the default', async () => {
    initialize({ hasDefaultPoller: false });

    await waitForRequest('@getAllHosts');
    await waitForRequest('@getFormPollers');

    await openCreationForm();

    expect(
      (await screen.findAllByTestId('host-form-name'))[0]
    ).toBeInTheDocument();
    await waitFor(() =>
      expect(screen.getByTestId('host-form-poller')).toHaveValue('')
    );
  });

  it('does not look the default monitoring server up for a user who may only look at hosts', async () => {
    initialize({ hasWriteAccess: false });

    await waitForRequest('@getAllHosts');

    await openHost('host 0');

    await waitForRequest('@getHost');

    // The selector would answer 403, and the listing would say so.
    expect(
      (await screen.findAllByTestId('host-form-name'))[0]
    ).toBeInTheDocument();
    expect(screen.queryByText(pollersForbiddenMessage)).not.toBeInTheDocument();
    // Not even asked: the lookup would have gone out when the page mounted,
    // long before the host detail answered.
    expect(getRequestCalls('@getFormPollers')).toHaveLength(0);
  });

  it('resolves an address once while a lookup is running', async () => {
    // Unresolved, so the address stays a name: only the running lookup
    // can disable the button. Delayed as the Cypress intercept was by
    // default, so the lookup is still running when the button is checked.
    initialize({ addressResolution: 'unresolved', resolveDelay: 500 });

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(
      await getInput('host-form-address'),
      resolvedAddressResponse.hostname
    );

    await userEvent.click(screen.getByTestId('host-form-address-resolve'));

    const resolveButton = screen.getByTestId('host-form-address-resolve');

    await waitFor(() => expect(resolveButton).toBeDisabled());
    // Dispatched on the disabled button anyway, as a forced click would:
    // `userEvent` would not even try.
    fireEvent.click(resolveButton);

    await waitForRequest('@resolveAddress');

    // Counted once the lookup is over (its answer reported, the button back),
    // so a request sent as it ends is counted too.
    expect(await screen.findByText(labelHostNotFound)).toBeVisible();
    await waitFor(() => expect(resolveButton).toBeEnabled());
    expect(getRequestCalls('@resolveAddress')).toHaveLength(1);
  });

  it('keeps an address edited while its previous value was resolving', async () => {
    initialize({ resolveDelay: 1500 });

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(
      await getInput('host-form-address'),
      resolvedAddressResponse.hostname
    );

    await userEvent.click(screen.getByTestId('host-form-address-resolve'));

    const address = await getInput('host-form-address');

    await userEvent.clear(address);
    await userEvent.type(address, 'srv-b');

    await waitForRequest('@resolveAddress');
    // The request went out at the click; nothing visible marks its answer.
    await letResponseLand();

    expect(await getInput('host-form-address')).toHaveValue('srv-b'); // Nor is a result it did not apply reported.
    expect(
      screen.queryByText(resolvedAddressResponse.ip)
    ).not.toBeInTheDocument();
  });

  it('creates a host with its alias, trimmed', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');
    await waitForRequest('@getFormPollers');

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
    await userEvent.type(await getInput('host-form-alias'), '  Apache front  ');
    await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

    await save();

    const { request } = await waitForRequest('@createHost');

    expect(request.body).toHaveProperty('alias', 'Apache front');
  });

  it('sends no alias for a host whose alias was cleared', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    await openHost('host 0');

    await waitForRequest('@getHost');

    const alias = await getInput('host-form-alias');

    await waitFor(() =>
      expect(alias).toHaveValue(
        'alias of host 0 as the detail endpoint spells it'
      )
    );
    await userEvent.clear(alias);

    await save();

    const { request } = await waitForRequest('@patchHost');

    expect(request.body).toHaveProperty('alias', null);
  });

  it('leaves host groups optional on an onPrem platform', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
    await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

    await userEvent.click(screen.getByTestId('host-form-poller'));
    await pickOption('Poller EU');

    // Nothing picked in Relations, and the form is still saveable.
    expect(screen.getByTestId('host-form-groups')).not.toHaveAttribute(
      'required'
    );
    await waitFor(() => expect(getSaveButton()).toBeEnabled());

    await save();

    // Trimmed on the way out: the schema validates the trimmed value, so an
    // untrimmed one would pass `max` here and fail it server side.
    const { request } = await waitForRequest('@createHost');

    expect(request.body).toEqual({
      address: '10.0.0.42',
      alias: null,
      category_ids: [],
      check_options: untouchedCheckOptionsPayload,
      child_host_ids: [],
      create_services_linked_to_templates: true,
      data_processing: untouchedDataProcessingPayload,
      extended_informations: untouchedExtendedInformationsPayload,
      host_group_ids: [],
      name: 'srv-apache-02',
      notifications: untouchedNotificationsPayload,
      parent_host_ids: [],
      poller_id: 2,
      scheduling_options: untouchedSchedulingOptionsPayload,
      severity_id: null,
      snmp_version: null,
      template_ids: [],
      timezone_id: null
    });
  });

  it('requires a host group before saving on a cloud platform', async () => {
    initialize({ isCloudPlatform: true });

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
    await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

    await userEvent.click(screen.getByTestId('host-form-poller'));
    await pickOption('Poller EU');
    await waitFor(() =>
      expect(screen.getByTestId('host-form-poller')).toHaveValue('Poller EU')
    );

    // The button is disabled before the pick too: the group's error, shown
    // once the field is left, is what proves the form validated the values
    // as they are now, and found the group missing.
    const groups = screen.getByTestId('host-form-groups');

    act(() => {
      groups.focus();
      groups.blur();
    });
    expect(
      await within(
        groups.closest('.MuiFormControl-root') as HTMLElement
      ).findByText(labelRequired)
    ).toBeVisible();

    // The same three fields that suffice onPrem leave cloud incomplete.
    await waitFor(() => expect(getSaveButton()).toBeDisabled());

    // The field says so too: without this the rule and the input could be
    // pinned to two flags that merely happen to agree.
    expect(screen.getByTestId('host-form-groups')).toHaveAttribute('required');

    await userEvent.click(screen.getByTestId('host-form-groups'));

    const { request: hostGroupsRequest } =
      await waitForRequest('@getFormHostGroups');

    expect(hostGroupsRequest.url.pathname).toContain(
      '/api/configuration/hosts/host_groups'
    );
    expect(hostGroupsRequest.url.pathname).not.toContain('/api/latest');

    await pickOption('Linux servers');

    await save();

    const { request } = await waitForRequest('@createHost');

    expect(request.body).toEqual({
      address: '10.0.0.42',
      alias: null,
      category_ids: [],
      check_options: untouchedCheckOptionsPayload,
      child_host_ids: [],
      data_processing: {
        check_freshness: 'use_default',
        event_handler_command_id: null,
        event_handler_enabled: 'use_default',
        freshness_threshold: null
      },
      // Alt icon and comments are refused on cloud.
      extended_informations: {
        action_url: null,
        geo_coordinates: null,
        icon_id: null,
        note: null,
        note_url: null
      },
      host_group_ids: [1],
      name: 'srv-apache-02',
      parent_host_ids: [],
      poller_id: 2,
      scheduling_options: {
        check_timeperiod_id: null,
        max_check_attempts: null,
        normal_check_interval: null,
        retry_check_interval: null
      },
      severity_id: null,
      snmp_version: null,
      template_ids: [],
      timezone_id: null
    });
  });

  it('creates a host from the three mandatory fields', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
    await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

    await userEvent.click(screen.getByTestId('host-form-poller'));

    // The form's own selector, not the generic one the listing filter reads:
    // it is granted by host write access and offers only active pollers.
    const { request: pollersRequest } = await waitForRequest('@getFormPollers');

    expect(pollersRequest.url.pathname).toContain(
      '/api/configuration/hosts/pollers'
    );
    expect(pollersRequest.url.pathname).not.toContain('/api/latest');

    await pickOption('Poller EU');

    await save();

    // API Platform, so the payload is snake_case and the poller is an id.
    // The glob matches both bases, so the base itself needs asserting.
    const { request } = await waitForRequest('@createHost');

    expect(request.url.pathname).toContain('/api/configuration/hosts');
    expect(request.url.pathname).not.toContain('/api/latest');
    expect(request.body).toEqual({
      address: '10.0.0.42',
      alias: null,
      category_ids: [],
      check_options: untouchedCheckOptionsPayload,
      child_host_ids: [],
      create_services_linked_to_templates: true,
      data_processing: untouchedDataProcessingPayload,
      extended_informations: untouchedExtendedInformationsPayload,
      host_group_ids: [],
      name: 'srv-apache-02',
      notifications: untouchedNotificationsPayload,
      parent_host_ids: [],
      poller_id: 2,
      scheduling_options: untouchedSchedulingOptionsPayload,
      severity_id: null,
      snmp_version: null,
      template_ids: [],
      timezone_id: null
    });
  });

  it('opens an existing host on the relations the detail endpoint returns', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    await openHost('host 0');

    await waitForRequest('@getHost');

    // Found by label, so two swapped labels turn this red.
    for (const [label, chip] of [
      [labelHostGroups, 'Linux servers'],
      [labelHostCategories, 'Virtual'],
      [labelParentHosts, 'host 1'],
      [labelChildHosts, 'host 2']
    ]) {
      await expectSingleChip(label, chip);
    }
  });

  it('creates a host with its categories, parent and child hosts', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
    await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

    await userEvent.click(screen.getByTestId('host-form-poller'));
    await pickOption('Poller EU');

    await userEvent.click(screen.getByTestId('host-form-categories'));

    const { request: categoriesRequest } = await waitForRequest(
      '@getFormHostCategories'
    );

    expect(categoriesRequest.url.pathname).toContain(
      '/api/configuration/hosts/host_categories'
    );
    expect(categoriesRequest.url.pathname).not.toContain('/api/latest');

    await pickOption('Physical');
    // A multi-select stays open after a pick.
    await userEvent.keyboard('{Escape}');

    await userEvent.click(screen.getByTestId('host-form-parent-hosts'));
    await pickOption('host 1');
    await userEvent.keyboard('{Escape}');

    await userEvent.click(screen.getByTestId('host-form-child-hosts'));
    await pickOption('host 2');
    await userEvent.keyboard('{Escape}');

    await save();

    const { request } = await waitForRequest('@createHost');

    expect(request.body).toEqual({
      address: '10.0.0.42',
      alias: null,
      category_ids: [3],
      check_options: untouchedCheckOptionsPayload,
      child_host_ids: [2],
      create_services_linked_to_templates: true,
      data_processing: untouchedDataProcessingPayload,
      extended_informations: untouchedExtendedInformationsPayload,
      host_group_ids: [],
      name: 'srv-apache-02',
      notifications: untouchedNotificationsPayload,
      parent_host_ids: [1],
      poller_id: 2,
      scheduling_options: untouchedSchedulingOptionsPayload,
      severity_id: null,
      snmp_version: null,
      template_ids: [],
      timezone_id: null
    });
  });

  it('opens an existing host on its notification settings', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    await openHost('host 0');

    await waitForRequest('@getHost');

    await waitFor(() =>
      expect(
        within(screen.getByTestId('host-form-notifications-enabled')).getByRole(
          'button',
          { name: labelNo }
        )
      ).toHaveAttribute('aria-pressed', 'true')
    );

    for (const [label, chip] of [
      [labelLinkedContacts, 'admin'],
      [labelLinkedContactGroups, 'Supervisors']
    ]) {
      await expectSingleChip(label, chip);
    }

    expect(await getInput('host-form-notifications-interval')).toHaveValue(3);
    expect(await getInput('host-form-notifications-firstDelay')).toHaveValue(1);
    // Left out of the response, so still empty: an empty number input has no
    // value.
    expect(await getInput('host-form-notifications-recoveryDelay')).toHaveValue(
      null
    );
    expect(
      screen.getByTestId('host-form-notifications-timeperiod')
    ).toHaveValue('24x7');

    expect(
      screen.getByTestId(`host-form-notifications-options-${labelDown}`)
    ).toHaveAttribute('aria-pressed', 'true');
    expect(
      screen.getByTestId(`host-form-notifications-options-${labelRecovery}`)
    ).toHaveAttribute('aria-pressed', 'true');
    expect(
      screen.getByTestId(`host-form-notifications-options-${labelUnreachable}`)
    ).toHaveAttribute('aria-pressed', 'false');
  });

  it('opens a host with no notification settings on their defaults', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    // Host 1's detail response has no `notifications` block at all.
    await openHost('host 1');

    await waitForRequest('@getHost1');

    // The defaults are what an empty form shows too: checked once host 1's
    // detail is in the form.
    await waitFor(async () =>
      expect(await getInput('host-form-address')).toHaveValue(
        'host-1.example.com'
      )
    );

    await waitFor(() =>
      expect(
        within(screen.getByTestId('host-form-notifications-enabled')).getByRole(
          'button',
          { name: labelInheritFromTemplate }
        )
      ).toHaveAttribute('aria-pressed', 'true')
    );
    expect(
      screen.getByTestId(
        'host-form-scheduling-options-activeCheckEnabled-use_default'
      )
    ).toHaveAttribute('aria-pressed', 'true');

    const address = await getInput('host-form-address');

    await userEvent.clear(address);
    await userEvent.type(address, '10.0.0.42');

    await save();

    const { request } = await waitForRequest('@patchHost1');

    expect(request.body).toHaveProperty(
      'notifications',
      untouchedNotificationsPayload
    );
    expect(request.body).toHaveProperty('template_ids', []);
    expect(request.body).toHaveProperty(
      'scheduling_options',
      untouchedSchedulingOptionsPayload
    );
    expect(request.body).toMatchObject({
      snmp_version: null,
      timezone_id: null
    });
    expect(request.body).toHaveProperty(
      'data_processing',
      untouchedDataProcessingPayload
    );
    expect(request.body).toHaveProperty(
      'check_options',
      untouchedCheckOptionsPayload
    );
  });

  it('creates a host with its notification settings', async () => {
    initialize({});

    await waitForRequest('@getAllHosts');

    await openCreationForm();

    await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
    await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

    await userEvent.click(screen.getByTestId('host-form-poller'));
    await pickOption('Poller EU');

    const notificationsEnabled = screen.getByTestId(
      'host-form-notifications-enabled'
    );

    // The inherited state is preselected, and is not No.
    expect(
      within(notificationsEnabled).getByRole('button', {
        name: labelInheritFromTemplate
      })
    ).toHaveAttribute('aria-pressed', 'true');
    await userEvent.click(
      within(notificationsEnabled).getByRole('button', { name: labelYes })
    );

    await userEvent.click(
      screen.getByTestId('host-form-notifications-contacts')
    );
    const { request: contactsRequest } =
      await waitForRequest('@getFormContacts');

    expect(contactsRequest.url.pathname).toContain(
      '/api/configuration/hosts/contacts'
    );
    await pickOption('admin');
    // A multi-select stays open after a pick.
    await userEvent.keyboard('{Escape}');

    await userEvent.click(
      screen.getByTestId('host-form-notifications-contact-groups')
    );
    const { request: contactGroupsRequest } = await waitForRequest(
      '@getFormContactGroups'
    );

    expect(contactGroupsRequest.url.pathname).toContain(
      '/api/configuration/hosts/contact_groups'
    );
    await pickOption('Supervisors');
    await userEvent.keyboard('{Escape}');

    await userEvent.type(
      await getInput('host-form-notifications-interval'),
      '5'
    );

    await userEvent.click(
      screen.getByTestId('host-form-notifications-timeperiod')
    );
    const { request: timePeriodsRequest } = await waitForRequest(
      '@getFormTimePeriods'
    );

    expect(timePeriodsRequest.url.pathname).toContain(
      '/api/configuration/hosts/timeperiods'
    );
    await pickOption('workhours');

    await userEvent.click(
      screen.getByTestId(`host-form-notifications-options-${labelDown}`)
    );
    await userEvent.click(
      screen.getByTestId(`host-form-notifications-options-${labelFlapping}`)
    );
    await userEvent.click(
      screen.getByTestId(`host-form-notifications-options-${labelUnreachable}`)
    );
    await userEvent.click(
      screen.getByTestId(
        `host-form-notifications-options-${labelDowntimeScheduled}`
      )
    );

    await userEvent.type(
      await getInput('host-form-notifications-firstDelay'),
      '0'
    );

    await save();

    // `enabled` as the string the API takes, not a boolean.
    const { request } = await waitForRequest('@createHost');

    expect(request.body).toHaveProperty('notifications', {
      contact_groups: [3],
      contacts: [1],
      enabled: 'true',
      first_delay: 0,
      interval: 5,
      options: ['down', 'flapping', 'unreachable', 'downtime_scheduled'],
      recovery_delay: null,
      timeperiod_id: 2
    });
  });
};
