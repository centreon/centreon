import { waitForRequest } from '@test-setup/api';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelEventHandlerArgumentsNeedAnEventHandler,
  labelMustBeIntegerBetweenZeroAndOneHundred,
  labelMustBeIntegerOfAtLeastOne
} from '../translatedLabels';
import { fillRequiredServiceFields } from './formHelpers';
import initialize from './initialize';

interface RequestBody {
  data_processing: Record<string, unknown>;
}

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getCheckbox = (testId: string): HTMLInputElement =>
  within(screen.getByTestId(testId)).getByRole('checkbox') as HTMLInputElement;

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
  await fillRequiredServiceFields();
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

const expectPressed = async (testId: string): Promise<void> => {
  await waitFor(() =>
    expect(screen.getByTestId(testId)).toHaveAttribute('aria-pressed', 'true')
  );
};

const onPremiseOnlyFields = [
  'service-form-acknowledgment-timeout',
  'service-form-flap-detection-enabled',
  'service-form-low-flap-threshold',
  'service-form-high-flap-threshold',
  'service-form-event-handler-args',
  'service-form-obsess-over-service',
  'service-form-retain-status-information',
  'service-form-retain-non-status-information',
  'service-form-stalking-options-o'
];

export default () => {
  describe('Data Processing: ', () => {
    it('opens an existing service on its data processing settings', async () => {
      await openService();

      await expectPressed('service-form-obsess-over-service-false');
      expect(await getInput('service-form-acknowledgment-timeout')).toHaveValue(
        15
      );
      await expectPressed('service-form-check-freshness-true');
      expect(await getInput('service-form-freshness-threshold')).toHaveValue(
        120
      );
      await expectPressed('service-form-flap-detection-enabled-true');
      expect(await getInput('service-form-low-flap-threshold')).toHaveValue(
        null
      );
      expect(await getInput('service-form-high-flap-threshold')).toHaveValue(
        50
      );
      await expectPressed('service-form-retain-status-information-true');
      await expectPressed(
        'service-form-retain-non-status-information-use_default'
      );
      expect(getCheckbox('service-form-stalking-options-o')).not.toBeChecked();
      expect(getCheckbox('service-form-stalking-options-w')).toBeChecked();
      expect(getCheckbox('service-form-stalking-options-u')).not.toBeChecked();
      expect(getCheckbox('service-form-stalking-options-c')).toBeChecked();
      await expectPressed('service-form-event-handler-enabled-false');
      await waitFor(() =>
        expect(screen.getByTestId('service-form-event-handler')).toHaveValue(
          'restart-httpd'
        )
      );
      expect(await getInput('service-form-event-handler-args')).toHaveValue(
        '!80!graceful'
      );
    });

    it('creates a service with its data processing settings', async () => {
      await openNewService();

      // Every Yes/No/Default field starts on Default.
      await expectPressed('service-form-obsess-over-service-use_default');
      await expectPressed('service-form-check-freshness-use_default');

      await userEvent.click(
        screen.getByTestId('service-form-obsess-over-service-true')
      );
      await userEvent.type(
        await getInput('service-form-acknowledgment-timeout'),
        '10'
      );
      await userEvent.click(
        screen.getByTestId('service-form-check-freshness-false')
      );
      await userEvent.type(
        await getInput('service-form-freshness-threshold'),
        '0'
      );
      await userEvent.click(
        screen.getByTestId('service-form-flap-detection-enabled-true')
      );
      // No cross-check: the low threshold may exceed the high one.
      await userEvent.type(
        await getInput('service-form-low-flap-threshold'),
        '60'
      );
      await userEvent.type(
        await getInput('service-form-high-flap-threshold'),
        '40'
      );
      await userEvent.click(
        screen.getByTestId('service-form-retain-status-information-false')
      );
      await userEvent.click(
        screen.getByTestId('service-form-retain-non-status-information-true')
      );
      // Checked out of order, sent in the API order.
      await userEvent.click(getCheckbox('service-form-stalking-options-c'));
      await userEvent.click(getCheckbox('service-form-stalking-options-o'));
      await userEvent.click(
        screen.getByTestId('service-form-event-handler-enabled-true')
      );

      await userEvent.click(screen.getByTestId('service-form-event-handler'));
      const { request: commandsRequest } =
        await waitForRequest('@getFormCommands');

      expect(commandsRequest.url.pathname).toContain(
        '/api/configuration/services/commands'
      );
      expect(commandsRequest.url.pathname).not.toContain('/api/latest');
      await pickOption('restart-httpd');

      await userEvent.type(
        await getInput('service-form-event-handler-args'),
        '80!!graceful'
      );

      await save();

      const body = await getRequestBody('@createService');

      expect(body.data_processing).toEqual({
        acknowledgment_timeout: 10,
        check_freshness: 'false',
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
        stalking_options: ['o', 'c']
      });
    });

    it('saves back the data processing settings of an existing service', async () => {
      await openService();

      const highFlapThreshold = await getInput(
        'service-form-high-flap-threshold'
      );

      await waitFor(() => expect(highFlapThreshold).toHaveValue(50));
      await userEvent.clear(highFlapThreshold);
      await userEvent.type(highFlapThreshold, '70');

      await save();

      const body = await getRequestBody('@updateService0');

      expect(body.data_processing).toEqual({
        acknowledgment_timeout: 15,
        check_freshness: 'true',
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
        stalking_options: ['w', 'c']
      });
    });

    it('refuses a flap threshold above 100', async () => {
      await openNewService();

      const lowFlapThreshold = await getInput(
        'service-form-low-flap-threshold'
      );

      await userEvent.type(lowFlapThreshold, '101');
      fireEvent.blur(lowFlapThreshold);

      expect(
        await screen.findByText(labelMustBeIntegerBetweenZeroAndOneHundred)
      ).toBeVisible();
    });

    it('refuses an acknowledgement timeout below 1', async () => {
      await openNewService();

      const acknowledgementTimeout = await getInput(
        'service-form-acknowledgment-timeout'
      );

      await userEvent.type(acknowledgementTimeout, '0');
      fireEvent.blur(acknowledgementTimeout);

      expect(
        await screen.findByText(labelMustBeIntegerOfAtLeastOne)
      ).toBeVisible();
    });

    it('refuses event handler arguments without an event handler', async () => {
      await openNewService();

      const args = await getInput('service-form-event-handler-args');

      await userEvent.type(args, '!80');
      fireEvent.blur(args);

      expect(
        await screen.findByText(labelEventHandlerArgumentsNeedAnEventHandler)
      ).toBeVisible();
    });

    it('refuses event handler arguments holding a line break, tab or carriage return', async () => {
      await openService();

      const args = await getInput('service-form-event-handler-args');

      await waitFor(() => expect(args).toHaveValue('!80!graceful'));
      await userEvent.type(args, '!a#BR#b!c#T#');
      fireEvent.blur(args);

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
        expect(
          screen.getByTestId('service-form-obsess-over-service-false')
        ).toBeDisabled()
      );
      expect(
        screen.getByTestId('service-form-check-freshness-true')
      ).toBeDisabled();
      expect(await getInput('service-form-freshness-threshold')).toBeDisabled();
      expect(
        await getInput('service-form-acknowledgment-timeout')
      ).toBeDisabled();
      expect(await getInput('service-form-low-flap-threshold')).toBeDisabled();
      expect(await getInput('service-form-event-handler-args')).toBeDisabled();
      expect(screen.getByTestId('service-form-event-handler')).toBeDisabled();
      // Disabled, the stalking options still show what the service sets.
      expect(getCheckbox('service-form-stalking-options-w')).toBeDisabled();
      expect(getCheckbox('service-form-stalking-options-w')).toBeChecked();
    });

    it('offers only the cloud data processing settings on a cloud platform', async () => {
      await openNewService({ isCloudPlatform: true });

      expect(
        await screen.findByTestId('service-form-check-freshness')
      ).toBeInTheDocument();
      expect(
        await getInput('service-form-freshness-threshold')
      ).toBeInTheDocument();
      expect(
        screen.getByTestId('service-form-event-handler-enabled')
      ).toBeInTheDocument();
      expect(
        screen.getByTestId('service-form-event-handler')
      ).toBeInTheDocument();

      onPremiseOnlyFields.forEach((testId) => {
        expect(screen.queryByTestId(testId)).not.toBeInTheDocument();
      });
    });

    it('opens and saves back the data processing settings of a cloud service', async () => {
      await openService({
        alias: '@getService2',
        name: 'disk',
        props: { isCloudPlatform: true }
      });

      await expectPressed('service-form-check-freshness-false');
      expect(await getInput('service-form-freshness-threshold')).toHaveValue(
        60
      );
      await expectPressed('service-form-event-handler-enabled-true');
      expect(screen.getByTestId('service-form-event-handler')).toHaveValue('');

      await userEvent.click(
        screen.getByTestId('service-form-check-freshness-true')
      );

      await save();

      const body = await getRequestBody('@updateService2');

      expect(body.data_processing).toEqual({
        check_freshness: 'true',
        event_handler_command_id: null,
        event_handler_enabled: 'true',
        freshness_threshold: 60
      });
    });
  });
};
