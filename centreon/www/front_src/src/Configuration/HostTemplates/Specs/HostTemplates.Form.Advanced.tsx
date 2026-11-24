import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelComments,
  labelMustBeAtMostCharacters,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero
} from '../../ResourceForm/translatedLabels';
import initialize from './initialize';

const prefix = 'host-template-form';
const dataProcessing = `${prefix}-data-processing`;

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: the listing behind the panel shows the same names.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const save = async (): Promise<void> => {
  const button = document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const openCreationForm = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  await userEvent.click(await screen.findByTestId('add-resource'));

  await userEvent.type(await getInput(`${prefix}-name`), 'linux');
  await userEvent.type(await getInput(`${prefix}-alias`), 'Linux');
};

const openTemplate = async (id: number): Promise<void> => {
  initialize({ search: `?mode=edit&id=${id}` });

  await waitForRequest(`@getHostTemplate${id}`);
};

const expectPressed = (testId: string): Promise<void> =>
  waitFor(() =>
    expect(screen.getByTestId(testId)).toHaveAttribute('aria-pressed', 'true')
  );

const leave = (element: HTMLElement): void => {
  act(() => {
    element.focus();
    element.blur();
  });
};

const getFieldError = (element: HTMLElement, message: string) =>
  within(element.closest('.MuiFormControl-root') as HTMLElement).findByText(
    message
  );

const getCreateBody = async (): Promise<Record<string, unknown>> => {
  await save();

  const { request } = await waitForRequest('@createHostTemplate');

  return request.body as Record<string, unknown>;
};

export default () => {
  describe('Advanced: ', () => {
    it('opens a template on its data processing settings and comment', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(
          screen.getByTestId(`${dataProcessing}-checkFreshness-true`)
        ).toHaveAttribute('aria-pressed', 'true')
      );
      expect(
        await getInput(`${dataProcessing}-freshnessThreshold`)
      ).toHaveValue(120);
      expect(
        await getInput(`${dataProcessing}-acknowledgmentTimeout`)
      ).toHaveValue(15);
      expect(screen.getByTestId(`${dataProcessing}-eventHandler`)).toHaveValue(
        'check-centreon-ping'
      );
      expect(await getInput(`${dataProcessing}-eventHandlerArgs`)).toHaveValue(
        '!80!graceful'
      );
      await expectPressed(`${dataProcessing}-eventHandlerEnabled-false`);
      await expectPressed(`${dataProcessing}-flapDetectionEnabled-true`);
      expect(await getInput(`${dataProcessing}-highFlapThreshold`)).toHaveValue(
        50
      );
      expect(await getInput(`${dataProcessing}-lowFlapThreshold`)).toHaveValue(
        null
      );
      expect(await getInput(`${prefix}-extended-infos-comment`)).toHaveValue(
        'Racked in room B'
      );
    });

    it('sends the advanced settings of a template back, a cleared field as null', async () => {
      await openTemplate(1);

      const freshnessThreshold = await getInput(
        `${dataProcessing}-freshnessThreshold`
      );
      const comment = await getInput(`${prefix}-extended-infos-comment`);

      await waitFor(() => expect(freshnessThreshold).toHaveValue(120));
      await userEvent.clear(freshnessThreshold);
      await userEvent.clear(comment);

      await save();

      const { request } = await waitForRequest('@updateHostTemplate1');
      const body = request.body as Record<string, unknown>;

      expect(body.data_processing).toEqual({
        acknowledgment_timeout: 15,
        check_freshness: 'true',
        event_handler_args: ['80', 'graceful'],
        event_handler_command_id: 8,
        event_handler_enabled: 'false',
        flap_detection_enabled: 'true',
        freshness_threshold: null,
        high_flap_threshold: 50,
        low_flap_threshold: null
      });
      expect(
        (body.extended_informations as Record<string, unknown>).comment
      ).toBeNull();
    });

    it('creates a template with its data processing settings and a comment', async () => {
      await openCreationForm();

      await userEvent.click(
        screen.getByTestId(`${dataProcessing}-checkFreshness-true`)
      );
      await userEvent.type(
        await getInput(`${dataProcessing}-freshnessThreshold`),
        '300'
      );
      await userEvent.type(
        await getInput(`${dataProcessing}-acknowledgmentTimeout`),
        '10'
      );
      await userEvent.click(
        screen.getByTestId(`${dataProcessing}-flapDetectionEnabled-false`)
      );
      await userEvent.type(
        await getInput(`${dataProcessing}-lowFlapThreshold`),
        '20'
      );
      await userEvent.type(
        await getInput(`${dataProcessing}-highFlapThreshold`),
        '40'
      );
      await userEvent.click(
        screen.getByTestId(`${dataProcessing}-eventHandlerEnabled-true`)
      );

      await userEvent.click(
        screen.getByTestId(`${dataProcessing}-eventHandler`)
      );
      const { request: commandsRequest } =
        await waitForRequest('@getFormCommands');

      expect(commandsRequest.url.pathname).toContain(
        '/api/configuration/host_templates/commands'
      );
      expect(commandsRequest.url.searchParams.get('is_activated')).toBe('true');
      await pickOption('check-centreon-ping');
      await userEvent.type(
        await getInput(`${dataProcessing}-eventHandlerArgs`),
        '80!!graceful'
      );
      await userEvent.type(
        await getInput(`${prefix}-extended-infos-comment`),
        'Racked in room A'
      );

      const body = await getCreateBody();

      expect(body.data_processing).toEqual({
        acknowledgment_timeout: 10,
        check_freshness: 'true',
        event_handler_args: ['80', '', 'graceful'],
        event_handler_command_id: 8,
        event_handler_enabled: 'true',
        flap_detection_enabled: 'false',
        freshness_threshold: 300,
        high_flap_threshold: 40,
        low_flap_threshold: 20
      });
      expect(
        (body.extended_informations as Record<string, unknown>).comment
      ).toBe('Racked in room A');
    });

    it('offers and sends only the cloud data processing settings on a cloud platform', async () => {
      await openCreationForm({ isCloudPlatform: true });

      await screen.findByTestId(`${dataProcessing}-checkFreshness`);
      [
        'acknowledgmentTimeout',
        'flapDetectionEnabled',
        'lowFlapThreshold',
        'highFlapThreshold',
        'eventHandlerArgs'
      ].forEach((field) => {
        expect(
          screen.queryByTestId(`${dataProcessing}-${field}`)
        ).not.toBeInTheDocument();
      });
      expect(
        screen.queryByTestId(`${prefix}-extended-infos-comment`)
      ).not.toBeInTheDocument();

      await userEvent.click(
        screen.getByTestId(`${dataProcessing}-checkFreshness-true`)
      );
      await userEvent.type(
        await getInput(`${dataProcessing}-freshnessThreshold`),
        '300'
      );
      await userEvent.click(
        screen.getByTestId(`${dataProcessing}-eventHandlerEnabled-true`)
      );
      await userEvent.click(
        screen.getByTestId(`${dataProcessing}-eventHandler`)
      );
      await pickOption('check-centreon-ping');

      const body = await getCreateBody();

      expect(body.data_processing).toEqual({
        check_freshness: 'true',
        event_handler_command_id: 8,
        event_handler_enabled: 'true',
        freshness_threshold: 300
      });
      expect(body.extended_informations).not.toHaveProperty('comment');
    });

    it('opens a template with its advanced settings read-only for a user who may only look at templates', async () => {
      initialize({ hasWriteAccess: false, search: '?mode=edit&id=1' });

      await waitForRequest('@getHostTemplate1');

      await waitFor(async () =>
        expect(await getInput(`${prefix}-extended-infos-comment`)).toHaveValue(
          'Racked in room B'
        )
      );
      expect(await getInput(`${prefix}-extended-infos-comment`)).toBeDisabled();
      expect(
        screen.getByTestId(`${dataProcessing}-eventHandler`)
      ).toBeDisabled();
      for (const field of [
        'eventHandlerArgs',
        'freshnessThreshold',
        'acknowledgmentTimeout',
        'lowFlapThreshold',
        'highFlapThreshold'
      ]) {
        expect(await getInput(`${dataProcessing}-${field}`)).toBeDisabled();
      }
      for (const field of [
        'eventHandlerEnabled',
        'checkFreshness',
        'flapDetectionEnabled'
      ]) {
        const buttons = within(
          screen.getByTestId(`${dataProcessing}-${field}`)
        ).getAllByRole('button');

        expect(buttons).toHaveLength(3);
        buttons.forEach((button) => {
          expect(button).toBeDisabled();
        });
      }
    });

    it('opens a locked template with its advanced settings read-only', async () => {
      await openTemplate(3);

      await waitFor(async () =>
        expect(await getInput(`${prefix}-name`)).toHaveValue(
          'OS-Linux-SNMP-custom'
        )
      );
      expect(
        await getInput(`${dataProcessing}-freshnessThreshold`)
      ).toBeDisabled();
      expect(await getInput(`${prefix}-extended-infos-comment`)).toBeDisabled();
    });

    it.each([
      ['freshnessThreshold', '-1', labelMustBePositiveIntegerOrZero],
      ['acknowledgmentTimeout', '0', labelMustBeIntegerOfAtLeastOne],
      ['lowFlapThreshold', '-1', labelMustBePositiveIntegerOrZero],
      ['highFlapThreshold', '-1', labelMustBePositiveIntegerOrZero]
    ])('refuses %s set to %s', async (field, value, message) => {
      await openCreationForm();

      const input = await getInput(`${dataProcessing}-${field}`);

      await userEvent.type(input, value);
      leave(input);

      expect(await getFieldError(input, message)).toBeVisible();
    });

    it('refuses a comment longer than 65535 characters', async () => {
      await openCreationForm();

      const comment = await getInput(`${prefix}-extended-infos-comment`);

      await userEvent.click(comment);
      await userEvent.paste('c'.repeat(65536));
      leave(comment);

      expect(
        await getFieldError(
          comment,
          labelMustBeAtMostCharacters
            .replace('{{label}}', labelComments)
            .replace('{{max}}', '65535')
        )
      ).toBeVisible();
    });
  });
};
