import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelEventHandlerArgumentsNeedAnEventHandler,
  labelMustBeIntegerOfAtLeastOne
} from '../../ResourceForm/translatedLabels';
import { labelMustBeIntegerBetweenZeroAndOneHundred } from '../translatedLabels';
import initialize from './initialize';

interface RequestBody {
  advanced: Record<string, unknown>;
}

const getTestId = (field: string): string =>
  `service-form-data-processing-${field}`;

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

const pickOption = async (testId: string, option: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(testId));
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
  await waitFor(() => expect(screen.getByTestId(testId)).toHaveValue(option));
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (field: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(getTestId(field)))[1] as HTMLInputElement;

const getCheckbox = (option: string): HTMLInputElement =>
  within(screen.getByTestId(getTestId(`stalkingOptions-${option}`))).getByRole(
    'checkbox'
  ) as HTMLInputElement;

const getTriStateButton = (field: string, value: string): HTMLElement =>
  screen.getByTestId(getTestId(`${field}-${value}`));

const leave = (element: HTMLElement): void => {
  act(() => {
    element.focus();
    element.blur();
  });
};

const save = async (): Promise<void> => {
  const button = await waitFor(() => {
    const element = document.querySelector<HTMLButtonElement>(
      `button[data-testid="${panelDataTestIds.save}"]`
    );

    expect(element).toBeEnabled();

    return element as HTMLButtonElement;
  });

  await userEvent.click(button);
};

const getRequestBody = async (alias: string): Promise<RequestBody> => {
  const { request } = await waitForRequest(alias);

  return request.body as RequestBody;
};

const openNewService = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');

  await userEvent.click(await screen.findByTestId('add-resource'));
  // Without a name and a host the form cannot be saved.
  await userEvent.type(
    (await screen.findAllByTestId('service-form-name'))[1],
    'http'
  );
  await pickOption('service-form-host', 'host A');
  // Required on a cloud platform; elsewhere, it brings the check command a
  // service needs.
  await pickOption('service-form-template', 'generic-active-service');
};

const openService = async ({
  alias = '@getService0',
  name = 'cpu',
  props = {}
}: {
  alias?: string;
  name?: string;
  props?: Parameters<typeof initialize>[0];
} = {}): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');

  await userEvent.click(await screen.findByText(name));

  await waitForRequest(alias);
};

const expectPressed = async (field: string, value: string): Promise<void> => {
  await waitFor(() =>
    expect(getTriStateButton(field, value)).toHaveAttribute(
      'aria-pressed',
      'true'
    )
  );
};

const onPremiseOnlyFields = [
  'acknowledgmentTimeout',
  'flapDetectionEnabled',
  'lowFlapThreshold',
  'highFlapThreshold',
  'eventHandlerArgs',
  'obsessOverService',
  'retainStatusInformation',
  'retainNonStatusInformation',
  'stalkingOptions-ok'
];

export default () => {
  describe('Data Processing: ', () => {
    it('opens an existing service on its data processing settings', async () => {
      await openService();

      await expectPressed('obsessOverService', 'false');
      expect(await getInput('acknowledgmentTimeout')).toHaveValue(15);
      await expectPressed('checkFreshness', 'true');
      expect(await getInput('freshnessThreshold')).toHaveValue(120);
      await expectPressed('flapDetectionEnabled', 'true');
      expect(await getInput('lowFlapThreshold')).toHaveValue(null);
      expect(await getInput('highFlapThreshold')).toHaveValue(50);
      await expectPressed('retainStatusInformation', 'true');
      await expectPressed('retainNonStatusInformation', 'use_default');
      expect(getCheckbox('ok')).not.toBeChecked();
      expect(getCheckbox('warning')).toBeChecked();
      expect(getCheckbox('unknown')).not.toBeChecked();
      expect(getCheckbox('critical')).toBeChecked();
      await expectPressed('eventHandlerEnabled', 'false');
      await waitFor(() =>
        expect(screen.getByTestId(getTestId('eventHandler'))).toHaveValue(
          'restart-httpd'
        )
      );
      expect(await getInput('eventHandlerArgs')).toHaveValue('!80!graceful');
    });

    it('creates a service with its data processing settings', async () => {
      await openNewService();

      // Every Yes/No/Default field starts on Default.
      await expectPressed('obsessOverService', 'use_default');
      await expectPressed('checkFreshness', 'use_default');

      await userEvent.click(getTriStateButton('obsessOverService', 'true'));
      await userEvent.type(await getInput('acknowledgmentTimeout'), '10');
      await userEvent.click(getTriStateButton('checkFreshness', 'false'));
      await userEvent.type(await getInput('freshnessThreshold'), '0');
      await userEvent.click(getTriStateButton('flapDetectionEnabled', 'true'));
      // No cross-check: the low threshold may exceed the high one.
      await userEvent.type(await getInput('lowFlapThreshold'), '60');
      await userEvent.type(await getInput('highFlapThreshold'), '40');
      await userEvent.click(
        getTriStateButton('retainStatusInformation', 'false')
      );
      await userEvent.click(
        getTriStateButton('retainNonStatusInformation', 'true')
      );
      // Checked out of order, sent in the API order.
      await userEvent.click(getCheckbox('critical'));
      await userEvent.click(getCheckbox('ok'));
      await userEvent.click(getTriStateButton('eventHandlerEnabled', 'true'));

      await userEvent.click(screen.getByTestId(getTestId('eventHandler')));
      const { request: commandsRequest } =
        await waitForRequest('@getFormCommands');

      expect(commandsRequest.url.pathname).toContain(
        '/api/configuration/services/commands'
      );
      expect(commandsRequest.url.pathname).not.toContain('/api/latest');
      await waitFor(() => expect(getPopper()).not.toBeNull());
      await userEvent.click(
        await within(getPopper()).findByText('restart-httpd')
      );

      await userEvent.type(await getInput('eventHandlerArgs'), '80!!graceful');

      await save();

      const body = await getRequestBody('@createService');

      expect(body.advanced).toEqual({
        acknowledgment_timeout: 10,
        check_freshness: 'false',
        // The comment shares the group.
        comment: null,
        event_handler_args: ['80', '', 'graceful'],
        event_handler_command_id: 7,
        event_handler_enabled: 'true',
        flap_detection_enabled: 'true',
        freshness_threshold: 0,
        high_flap_threshold: 40,
        low_flap_threshold: 60,
        obsess_over_service: 'true',
        retain_non_status_information: 'true',
        retain_status_information: 'false',
        stalking_options: ['ok', 'critical']
      });
    });

    it('saves back the data processing settings of an existing service', async () => {
      await openService();

      const highFlapThreshold = await getInput('highFlapThreshold');

      await waitFor(() => expect(highFlapThreshold).toHaveValue(50));
      await userEvent.clear(highFlapThreshold);
      await userEvent.type(highFlapThreshold, '70');

      await save();

      const body = await getRequestBody('@updateService0');

      expect(body.advanced).toEqual({
        acknowledgment_timeout: 15,
        check_freshness: 'true',
        comment: 'Checked by the web team',
        event_handler_args: ['80', 'graceful'],
        event_handler_command_id: 7,
        event_handler_enabled: 'false',
        flap_detection_enabled: 'true',
        freshness_threshold: 120,
        high_flap_threshold: 70,
        low_flap_threshold: null,
        obsess_over_service: 'false',
        retain_non_status_information: 'use_default',
        retain_status_information: 'true',
        stalking_options: ['warning', 'critical']
      });
    });

    it('forces the freshness check of a Centreon Monitoring Agent command', async () => {
      await openService({ alias: '@getService1', name: 'memory' });

      await waitFor(() =>
        expect(getTriStateButton('checkFreshness', 'true')).toBeDisabled()
      );
      expect(await getInput('freshnessThreshold')).toBeDisabled();

      await userEvent.type(await getInput('acknowledgmentTimeout'), '5');
      await save();

      const body = await getRequestBody('@updateService1');

      expect(body.advanced).toMatchObject({
        acknowledgment_timeout: 5,
        check_freshness: 'true',
        freshness_threshold: 500
      });
    });

    it('lets the freshness check of another command be set', async () => {
      await openService();

      await waitFor(() =>
        expect(getTriStateButton('checkFreshness', 'true')).toBeEnabled()
      );
      expect(await getInput('freshnessThreshold')).toBeEnabled();
    });

    it('refuses a flap threshold above 100', async () => {
      await openNewService();

      const lowFlapThreshold = await getInput('lowFlapThreshold');

      await userEvent.type(lowFlapThreshold, '101');
      leave(lowFlapThreshold);

      expect(
        await screen.findByText(labelMustBeIntegerBetweenZeroAndOneHundred)
      ).toBeVisible();
    });

    it('refuses an acknowledgement timeout below 1', async () => {
      await openNewService();

      const acknowledgementTimeout = await getInput('acknowledgmentTimeout');

      await userEvent.type(acknowledgementTimeout, '0');
      leave(acknowledgementTimeout);

      expect(
        await screen.findByText(labelMustBeIntegerOfAtLeastOne)
      ).toBeVisible();
    });

    it('refuses event handler arguments without an event handler', async () => {
      await openNewService();

      const args = await getInput('eventHandlerArgs');

      await userEvent.type(args, '!80');
      leave(args);

      expect(
        await screen.findByText(labelEventHandlerArgumentsNeedAnEventHandler)
      ).toBeVisible();
    });

    it('refuses event handler arguments holding a line break, tab or carriage return', async () => {
      await openService();

      const args = await getInput('eventHandlerArgs');

      await waitFor(() => expect(args).toHaveValue('!80!graceful'));
      await userEvent.type(args, '!a#BR#b!c#T#');
      leave(args);

      // The message names the offending arguments only.
      expect(
        await screen.findByText(
          'The following arguments are not valid: a#BR#b, c#T#'
        )
      ).toBeVisible();
    });

    it('opens the data processing settings read-only without write access', async () => {
      await openService({ props: { hasWriteAccess: false } });

      await waitFor(() =>
        expect(getTriStateButton('obsessOverService', 'false')).toBeDisabled()
      );
      expect(getTriStateButton('checkFreshness', 'true')).toBeDisabled();
      expect(await getInput('freshnessThreshold')).toBeDisabled();
      expect(await getInput('acknowledgmentTimeout')).toBeDisabled();
      expect(await getInput('lowFlapThreshold')).toBeDisabled();
      expect(await getInput('eventHandlerArgs')).toBeDisabled();
      expect(screen.getByTestId(getTestId('eventHandler'))).toBeDisabled();
      // Disabled, the stalking options still show what the service sets.
      expect(getCheckbox('warning')).toBeDisabled();
      expect(getCheckbox('warning')).toBeChecked();
    });

    it('offers only the cloud data processing settings on a cloud platform', async () => {
      await openNewService({ isCloudPlatform: true });

      expect(
        await screen.findByTestId(getTestId('checkFreshness'))
      ).toBeInTheDocument();
      expect(await getInput('freshnessThreshold')).toBeInTheDocument();
      expect(
        screen.getByTestId(getTestId('eventHandlerEnabled'))
      ).toBeInTheDocument();
      expect(screen.getByTestId(getTestId('eventHandler'))).toBeInTheDocument();

      onPremiseOnlyFields.forEach((field) => {
        expect(screen.queryByTestId(getTestId(field))).not.toBeInTheDocument();
      });
    });

    it('opens and saves back the data processing settings of a cloud service', async () => {
      await openService({
        alias: '@getService2',
        name: 'disk',
        props: { isCloudPlatform: true }
      });

      await expectPressed('checkFreshness', 'false');
      expect(await getInput('freshnessThreshold')).toHaveValue(60);
      await expectPressed('eventHandlerEnabled', 'true');
      expect(screen.getByTestId(getTestId('eventHandler'))).toHaveValue('');

      await userEvent.click(getTriStateButton('checkFreshness', 'true'));

      await save();

      const body = await getRequestBody('@updateService2');

      expect(body.advanced).toEqual({
        check_freshness: 'true',
        event_handler_command_id: null,
        event_handler_enabled: 'true',
        freshness_threshold: 60
      });
    });
  });
};
