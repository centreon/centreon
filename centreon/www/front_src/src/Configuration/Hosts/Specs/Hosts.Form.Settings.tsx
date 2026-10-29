import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelDown,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelNone,
  labelSnmpVersion,
  labelTimezone
} from '../translatedLabels';
import initialize from './initialize';
import {
  untouchedNotificationsPayload,
  untouchedSchedulingOptionsPayload
} from './utils';

interface RequestBody {
  check_options: { args: Array<string>; command_id: number | null };
  data_processing: object;
  notifications: { options: Array<string> };
  scheduling_options: object;
}

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper, as the Cypress test was.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const save = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector(
      `button[data-testid="${panelDataTestIds.save}"]`
    ) as HTMLButtonElement
  );
};

const openNewHost = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHosts');

  await userEvent.click(await screen.findByTestId('add-resource'));
};

const openHost = async (
  props: Parameters<typeof initialize>[0] = {},
  name = 'host 0',
  alias = '@getHost'
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHosts');

  await userEvent.click(await screen.findByText(name));

  await waitForRequest(alias);
};

const fillRequiredFields = async (): Promise<void> => {
  await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
  await userEvent.type(await getInput('host-form-address'), '10.0.0.42');

  await userEvent.click(screen.getByTestId('host-form-poller'));
  await pickOption('Poller EU');
};

// The clear indicator only shows on hover or focus: hovering the field first
// stands in for the Cypress `{ force: true }`.
const clearAutocomplete = async (testId: string): Promise<void> => {
  const root = (await screen.findByTestId(testId)).closest(
    '.MuiAutocomplete-root'
  ) as HTMLElement;

  await userEvent.hover(root);
  await userEvent.click(
    root.querySelector('.MuiAutocomplete-clearIndicator') as HTMLElement
  );
};

const blur = (element: HTMLElement): void => {
  act(() => {
    element.blur();
  });
};

const getRequestBody = async (alias: string): Promise<RequestBody> => {
  const { request } = await waitForRequest(alias);

  return request.body as RequestBody;
};

export default () => {
  it('sends None alone once it is chosen', async () => {
    await openNewHost();

    await fillRequiredFields();

    await userEvent.click(
      screen.getByTestId(`host-form-notifications-options-${labelDown}`)
    );
    await userEvent.click(screen.getByLabelText(labelNone));

    await save();

    const body = await getRequestBody('@createHost');

    expect(body.notifications.options).toEqual(['none']);
  });

  it('hides the additive inheritance toggles the platform does not use', async () => {
    await openNewHost();

    expect(await screen.findAllByTestId('host-form-name')).not.toHaveLength(0);
    expect(
      screen.queryByTestId(
        'host-form-notifications-contact-additive-inheritance'
      )
    ).not.toBeInTheDocument();
    expect(
      screen.queryByTestId(
        'host-form-notifications-contact-group-additive-inheritance'
      )
    ).not.toBeInTheDocument();
  });

  it('sends the additive inheritance toggles where the platform uses them', async () => {
    await openNewHost({ isAdditiveInheritanceEnabled: true });

    await fillRequiredFields();

    await userEvent.click(
      screen.getByTestId('host-form-notifications-contact-additive-inheritance')
    );

    await save();

    const body = await getRequestBody('@createHost');

    expect(body.notifications).toEqual({
      ...untouchedNotificationsPayload,
      contact_additive_inheritance: true,
      contact_group_additive_inheritance: false
    });
  });

  it('opens and saves back the additive inheritance a host carries', async () => {
    await openHost({ isAdditiveInheritanceEnabled: true });

    await waitFor(() =>
      expect(
        screen
          .getByTestId('host-form-notifications-contact-additive-inheritance')
          .querySelector('input')
      ).toBeChecked()
    );
    expect(
      screen
        .getByTestId(
          'host-form-notifications-contact-group-additive-inheritance'
        )
        .querySelector('input')
    ).not.toBeChecked();

    const address = await getInput('host-form-address');

    await userEvent.clear(address);
    await userEvent.type(address, '10.0.0.42');

    await save();

    const body = await getRequestBody('@patchHost');

    expect(body.notifications).toMatchObject({
      contact_additive_inheritance: true,
      contact_group_additive_inheritance: false
    });
  });

  it('opens an existing host on its SNMP, timezone and scheduling settings', async () => {
    await openHost();

    const snmpCommunity = await getInput('host-form-snmp-community');

    await waitFor(() => expect(snmpCommunity).toHaveValue(''));
    expect(snmpCommunity).toHaveAttribute('type', 'password');
    await waitFor(() =>
      expect(screen.getByLabelText(labelSnmpVersion)).toHaveValue('2c')
    );
    await waitFor(() =>
      expect(screen.getByTestId('host-form-timezone')).toHaveValue(
        'Europe/Paris'
      )
    );

    await waitFor(async () =>
      expect(
        await getInput('host-form-scheduling-options-maxCheckAttempts')
      ).toHaveValue(3)
    );
    expect(
      await getInput('host-form-scheduling-options-normalCheckInterval')
    ).toHaveValue(5);
    expect(
      await getInput('host-form-scheduling-options-retryCheckInterval')
    ).toHaveValue(null);

    expect(
      screen.getByTestId(
        'host-form-scheduling-options-activeCheckEnabled-false'
      )
    ).toHaveAttribute('aria-pressed', 'true');
    expect(
      screen.getByTestId(
        'host-form-scheduling-options-passiveCheckEnabled-true'
      )
    ).toHaveAttribute('aria-pressed', 'true');
  });

  it('clears the SNMP version, timezone and scheduling settings of a host', async () => {
    await openHost();

    await waitFor(() =>
      expect(screen.getByLabelText(labelSnmpVersion)).toHaveValue('2c')
    );
    await userEvent.click(screen.getByLabelText(labelSnmpVersion));
    await pickOption(labelNone);
    await waitFor(() =>
      expect(screen.getByLabelText(labelSnmpVersion)).toHaveValue('')
    );
    await clearAutocomplete('host-form-timezone');
    await clearAutocomplete('host-form-scheduling-options-checkPeriod');
    await userEvent.clear(
      await getInput('host-form-scheduling-options-maxCheckAttempts')
    );
    await userEvent.click(
      screen.getByTestId(
        'host-form-scheduling-options-activeCheckEnabled-use_default'
      )
    );

    await save();

    const body = await getRequestBody('@patchHost');

    expect(body).toMatchObject({
      snmp_version: null,
      timezone_id: null
    });
    expect(body.scheduling_options).toEqual({
      active_check_enabled: 'use_default',
      check_timeperiod_id: null,
      max_check_attempts: null,
      normal_check_interval: 5,
      passive_check_enabled: 'true',
      retry_check_interval: null
    });
  });

  it('creates a host with its SNMP, timezone and scheduling settings', async () => {
    await openNewHost();

    await fillRequiredFields();

    await userEvent.type(await getInput('host-form-snmp-community'), 'public');

    await userEvent.click(screen.getByLabelText(labelSnmpVersion));
    await pickOption('2c');

    await userEvent.click(screen.getByTestId('host-form-timezone'));
    const { request: timezonesRequest } =
      await waitForRequest('@getFormTimezones');

    expect(timezonesRequest.url.pathname).toContain(
      '/api/configuration/timezones'
    );
    expect(timezonesRequest.url.pathname).not.toContain('/api/latest');
    await pickOption('Europe/Paris');

    await userEvent.type(
      await getInput('host-form-scheduling-options-maxCheckAttempts'),
      '3'
    );
    await userEvent.type(
      await getInput('host-form-scheduling-options-retryCheckInterval'),
      '1'
    );

    expect(
      screen.getByTestId(
        'host-form-scheduling-options-activeCheckEnabled-use_default'
      )
    ).toHaveAttribute('aria-pressed', 'true');
    await userEvent.click(
      screen.getByTestId('host-form-scheduling-options-activeCheckEnabled-true')
    );
    await userEvent.click(
      screen.getByTestId(
        'host-form-scheduling-options-passiveCheckEnabled-false'
      )
    );

    await save();

    const body = await getRequestBody('@createHost');

    expect(body).toMatchObject({
      snmp_community: 'public',
      snmp_version: '2c',
      timezone_id: 7
    });
    expect(body.scheduling_options).toEqual({
      active_check_enabled: 'true',
      check_timeperiod_id: null,
      max_check_attempts: 3,
      normal_check_interval: null,
      passive_check_enabled: 'false',
      retry_check_interval: 1
    });
  });

  it('refuses a check interval below 1', async () => {
    await openNewHost();

    const normalCheckInterval = await getInput(
      'host-form-scheduling-options-normalCheckInterval'
    );

    await userEvent.type(normalCheckInterval, '0');
    blur(normalCheckInterval);

    expect(
      await screen.findByText(labelMustBeIntegerOfAtLeastOne)
    ).toBeVisible();
  });

  it('offers no check toggles on a cloud platform', async () => {
    await openNewHost({ isCloudPlatform: true });

    expect(
      await getInput('host-form-scheduling-options-maxCheckAttempts')
    ).toBeVisible();
    expect(
      screen.queryByTestId('host-form-scheduling-options-activeCheckEnabled')
    ).not.toBeInTheDocument();
    expect(
      screen.queryByTestId('host-form-scheduling-options-passiveCheckEnabled')
    ).not.toBeInTheDocument();
  });

  it('lets a user who may only look at hosts change none of them', async () => {
    await openHost({ hasWriteAccess: false });

    await waitFor(async () =>
      expect(await getInput('host-form-snmp-community')).toBeDisabled()
    );
    expect(screen.getByLabelText(labelSnmpVersion)).toBeDisabled();
    expect(screen.getByLabelText(labelTimezone)).toBeDisabled();
    expect(
      await getInput('host-form-scheduling-options-maxCheckAttempts')
    ).toBeDisabled();
    expect(
      screen.getByTestId('host-form-scheduling-options-activeCheckEnabled-true')
    ).toBeDisabled();
  });

  it('opens an existing host on its check command and check period', async () => {
    await openHost();

    await waitFor(() =>
      expect(screen.getByTestId('host-form-check-options-command')).toHaveValue(
        'check-host-alive'
      )
    );
    const args = await getInput('host-form-check-options-args');

    await waitFor(() => expect(args).toHaveValue('!3!80%'));
    expect(args).toBeEnabled();
    await waitFor(() =>
      expect(
        screen.getByTestId('host-form-scheduling-options-checkPeriod')
      ).toHaveValue('workhours')
    );
  });

  it('creates a host with its check command, arguments and check period', async () => {
    await openNewHost();

    await fillRequiredFields();

    // Arguments are refused without a command.
    expect(await getInput('host-form-check-options-args')).toBeDisabled();

    await userEvent.click(
      screen.getByTestId('host-form-check-options-command')
    );
    // Only active check commands, as legacy offers.
    const { request: commandsRequest } =
      await waitForRequest('@getFormCommands');

    expect(commandsRequest.url.pathname).toContain(
      '/api/configuration/hosts/commands'
    );
    expect(commandsRequest.url.searchParams.getAll('type[]')).toEqual([
      'Check'
    ]);
    expect(commandsRequest.url.searchParams.get('is_activated')).toBe('true');
    await pickOption('check-host-alive');

    const args = await getInput('host-form-check-options-args');

    await waitFor(() => expect(args).toBeEnabled());
    await userEvent.type(args, '3!80%');

    await userEvent.click(
      screen.getByTestId('host-form-scheduling-options-checkPeriod')
    );
    const { request: timePeriodsRequest } = await waitForRequest(
      '@getFormTimePeriods'
    );

    expect(timePeriodsRequest.url.pathname).toContain(
      '/api/configuration/hosts/timeperiods'
    );
    await pickOption('workhours');

    await save();

    const body = await getRequestBody('@createHost');

    expect(body.check_options).toEqual({
      args: ['3', '80%'],
      command_id: 9,
      macros: []
    });
    expect(body.scheduling_options).toEqual({
      ...untouchedSchedulingOptionsPayload,
      check_timeperiod_id: 2
    });
  });

  it('sends no arguments once the check command is removed', async () => {
    await openHost();

    await waitFor(() =>
      expect(screen.getByTestId('host-form-check-options-command')).toHaveValue(
        'check-host-alive'
      )
    );
    await clearAutocomplete('host-form-check-options-command');

    const args = await getInput('host-form-check-options-args');

    await waitFor(() => expect(args).toBeDisabled());

    await save();

    const body = await getRequestBody('@patchHost');

    expect(body.check_options.args).toEqual([]);
    expect(body.check_options.command_id).toBe(null);
  });

  it('offers the check command and check period on a cloud platform', async () => {
    await openNewHost({ isCloudPlatform: true });

    expect(
      await screen.findByTestId('host-form-check-options-command')
    ).toBeVisible();
    expect(await getInput('host-form-check-options-args')).toBeVisible();
    expect(
      screen.getByTestId('host-form-scheduling-options-checkPeriod')
    ).toBeVisible();
  });

  it('opens an existing host on its data processing settings', async () => {
    await openHost();

    await waitFor(() =>
      expect(
        screen.getByTestId('host-form-data-processing-checkFreshness-true')
      ).toHaveAttribute('aria-pressed', 'true')
    );
    expect(
      await getInput('host-form-data-processing-freshnessThreshold')
    ).toHaveValue(120);
    expect(
      await getInput('host-form-data-processing-acknowledgmentTimeout')
    ).toHaveValue(15);
    expect(
      screen.getByTestId('host-form-data-processing-flapDetectionEnabled-true')
    ).toHaveAttribute('aria-pressed', 'true');
    expect(
      await getInput('host-form-data-processing-lowFlapThreshold')
    ).toHaveValue(null);
    expect(
      await getInput('host-form-data-processing-highFlapThreshold')
    ).toHaveValue(50);
    expect(
      screen.getByTestId('host-form-data-processing-eventHandlerEnabled-false')
    ).toHaveAttribute('aria-pressed', 'true');
    await waitFor(() =>
      expect(
        screen.getByTestId('host-form-data-processing-eventHandler')
      ).toHaveValue('restart-httpd')
    );
    expect(
      await getInput('host-form-data-processing-eventHandlerArgs')
    ).toHaveValue('!80!graceful');
  });

  it('creates a host with its data processing settings', async () => {
    await openNewHost();

    await fillRequiredFields();

    expect(
      screen.getByTestId('host-form-data-processing-checkFreshness-use_default')
    ).toHaveAttribute('aria-pressed', 'true');
    await userEvent.click(
      screen.getByTestId('host-form-data-processing-checkFreshness-true')
    );
    await userEvent.type(
      await getInput('host-form-data-processing-freshnessThreshold'),
      '300'
    );
    await userEvent.type(
      await getInput('host-form-data-processing-acknowledgmentTimeout'),
      '10'
    );
    await userEvent.click(
      screen.getByTestId('host-form-data-processing-flapDetectionEnabled-false')
    );
    await userEvent.type(
      await getInput('host-form-data-processing-lowFlapThreshold'),
      '20'
    );
    await userEvent.type(
      await getInput('host-form-data-processing-highFlapThreshold'),
      '40'
    );
    await userEvent.click(
      screen.getByTestId('host-form-data-processing-eventHandlerEnabled-true')
    );

    await userEvent.click(
      screen.getByTestId('host-form-data-processing-eventHandler')
    );
    const { request: commandsRequest } =
      await waitForRequest('@getFormCommands');

    expect(commandsRequest.url.pathname).toContain(
      '/api/configuration/hosts/commands'
    );
    expect(commandsRequest.url.pathname).not.toContain('/api/latest');
    expect(commandsRequest.url.searchParams.get('is_activated')).toBe('true');
    await pickOption('restart-httpd');

    await userEvent.type(
      await getInput('host-form-data-processing-eventHandlerArgs'),
      '80!!graceful'
    );

    await save();

    const body = await getRequestBody('@createHost');

    expect(body.data_processing).toEqual({
      acknowledgment_timeout: 10,
      check_freshness: 'true',
      event_handler_args: ['80', '', 'graceful'],
      event_handler_command_id: 7,
      event_handler_enabled: 'true',
      flap_detection_enabled: 'false',
      freshness_threshold: 300,
      high_flap_threshold: 40,
      low_flap_threshold: 20
    });
  });

  it('refuses a negative flap threshold', async () => {
    await openNewHost();

    const highFlapThreshold = await getInput(
      'host-form-data-processing-highFlapThreshold'
    );

    await userEvent.type(highFlapThreshold, '-1');
    blur(highFlapThreshold);

    expect(
      await screen.findByText(labelMustBePositiveIntegerOrZero)
    ).toBeVisible();
  });

  it('offers only the cloud data processing settings on a cloud platform', async () => {
    await openNewHost({ isCloudPlatform: true });

    expect(
      await screen.findByTestId('host-form-data-processing-checkFreshness')
    ).toBeInTheDocument();
    expect(
      await getInput('host-form-data-processing-freshnessThreshold')
    ).toBeInTheDocument();
    expect(
      screen.getByTestId('host-form-data-processing-eventHandlerEnabled')
    ).toBeInTheDocument();
    expect(
      screen.getByTestId('host-form-data-processing-eventHandler')
    ).toBeInTheDocument();

    [
      'acknowledgmentTimeout',
      'flapDetectionEnabled',
      'lowFlapThreshold',
      'highFlapThreshold',
      'eventHandlerArgs'
    ].forEach((field) => {
      expect(
        screen.queryByTestId(`host-form-data-processing-${field}`)
      ).not.toBeInTheDocument();
    });
  });

  it('opens and saves back the data processing settings of a cloud host', async () => {
    await openHost({ isCloudPlatform: true }, 'host 2', '@getHost2');

    await waitFor(() =>
      expect(
        screen.getByTestId('host-form-data-processing-checkFreshness-false')
      ).toHaveAttribute('aria-pressed', 'true')
    );
    expect(
      await getInput('host-form-data-processing-freshnessThreshold')
    ).toHaveValue(60);
    expect(
      screen.getByTestId('host-form-data-processing-eventHandlerEnabled-true')
    ).toHaveAttribute('aria-pressed', 'true');
    expect(
      screen.getByTestId('host-form-data-processing-eventHandler')
    ).toHaveValue('');

    const address = await getInput('host-form-address');

    await userEvent.clear(address);
    await userEvent.type(address, '10.0.0.42');

    await save();

    const body = await getRequestBody('@patchHost2');

    expect(body.data_processing).toEqual({
      check_freshness: 'false',
      event_handler_command_id: null,
      event_handler_enabled: 'true',
      freshness_threshold: 60
    });
  });
};
