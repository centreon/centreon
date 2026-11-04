import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelRequired } from '../../ResourceForm/translatedLabels';
import { labelNameContainsForbiddenCharacters } from '../translatedLabels';
import initialize from './initialize';
import {
  openedNotificationsPayload,
  untouchedNotificationsPayload
} from './utils';

interface RequestBody {
  activated?: boolean;
  check_options?: unknown;
  host_id?: number | null;
  name?: string;
  notifications?: unknown;
}

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
};

const openRow = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
};

const openCreationForm = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('add-resource'));
};

const openService = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  await initializeListing(props);
  await openRow('cpu');
  await waitForRequest('@getService0');
};

const getSaveButton = (): HTMLButtonElement | null =>
  document.querySelector<HTMLButtonElement>(
    `button[data-testid="${panelDataTestIds.save}"]`
  );

const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled());
  await userEvent.click(getSaveButton() as HTMLButtonElement);
};

const expectHeader = async (text: string): Promise<void> => {
  const header = await screen.findByTestId(panelDataTestIds.header);

  await waitFor(() =>
    expect(header).toHaveTextContent(new RegExp(`^${text}$`))
  );
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

const pickOption = async (testId: string, option: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(testId));
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
  await waitFor(() => expect(screen.getByTestId(testId)).toHaveValue(option));
};

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

const getRequestBody = async (alias: string): Promise<RequestBody> => {
  const { request } = await waitForRequest(alias);

  return request.body as RequestBody;
};

export default () => {
  describe('Panel: ', () => {
    it('opens the creation form in a side panel rather than a modal', async () => {
      await initializeListing();

      await openCreationForm();

      expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
      expect(screen.queryByTestId('Modal')).not.toBeInTheDocument();

      await expectHeader('Add a service');
    });

    it('opens a service from its row on the detail endpoint', async () => {
      await initializeListing();

      await openRow('cpu');

      const { request } = await waitForRequest('@getService0');

      // API Platform, not the legacy detail endpoint under `api/latest`.
      expect(request.url.pathname).toContain('/api/configuration/services/0');
      expect(request.url.pathname).not.toContain('/api/latest');

      expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
      await expectHeader('cpu');
      await waitFor(() => expect(getSaveButton()).toBeInTheDocument());
    });

    it('opens a service read-only for a user who may only look at services', async () => {
      await openService({ hasWriteAccess: false });

      expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
      await expectHeader('cpu');
      expect(getSaveButton()).not.toBeInTheDocument();

      await waitFor(async () =>
        expect(await getInput('service-form-name')).toBeDisabled()
      );
      expect(screen.getByTestId('service-form-host')).toBeDisabled();
      // Shown, not just locked.
      expect(await getInput('service-form-name')).toHaveValue('cpu');
      expect(screen.getByTestId('service-form-host')).toHaveValue('host A');
    });

    it('opens the service a deep link names, in edit mode', async () => {
      initialize({ searchParams: '?mode=edit&id=0' });

      const { request } = await waitForRequest('@getService0');

      expect(request.url.pathname).toContain('/api/configuration/services/0');

      expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
      // Named by the detail response, there being no row to take it from.
      await expectHeader('cpu');
      await waitFor(() => expect(getSaveButton()).toBeInTheDocument());
    });
  });

  describe('General information: ', () => {
    it('opens a service on its name and host', async () => {
      await openService();

      await waitFor(async () =>
        expect(await getInput('service-form-name')).toHaveValue('cpu')
      );
      expect(screen.getByTestId('service-form-host')).toHaveValue('host A');
    });

    it('creates a service on a host, with its name as the server stores it', async () => {
      await initializeListing();
      await openCreationForm();

      // Empty, even though the page could guess one.
      await waitFor(() =>
        expect(screen.getByTestId('service-form-host')).toHaveValue('')
      );

      await userEvent.type(
        await getInput('service-form-name'),
        '  cpu   load '
      );
      await pickOption('service-form-host', 'host B');
      // Required without a template.
      await pickOption(
        'service-form-check-options-command',
        'check-centreon-ping'
      );

      await save();

      // The other sections' keys are their own specs'; the notifications
      // here, as never touched.
      const body = await getRequestBody('@createService');

      expect(body).toMatchObject({
        check_options: { args: [], command_id: 8, macros: [] },
        host_id: 11,
        name: 'cpu load'
      });
      expect(body.notifications).toEqual(untouchedNotificationsPayload);
    });

    it('updates a service with the whole form', async () => {
      await openService();

      const name = await getInput('service-form-name');

      await waitFor(() => expect(name).toHaveValue('cpu'));
      await userEvent.clear(name);
      await userEvent.type(name, 'cpu load');

      await save();

      const { request } = await waitForRequest('@updateService0');

      expect(request.method).toBe('PUT');
      // The whole service is replaced, its activation included. The other
      // sections' keys are their own specs'; the notifications here, as
      // opened.
      expect(request.body).toMatchObject({
        activated: true,
        check_options: {
          args: ['80', '90'],
          command_id: 9,
          macros: [
            {
              id: 1,
              is_password: false,
              name: 'WARNING',
              parent: null,
              value: '80'
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
        host_id: 10,
        name: 'cpu load'
      });
      expect((request.body as RequestBody).notifications).toEqual(
        openedNotificationsPayload
      );
    });

    it('keeps the activation the panel switch set when the service is saved', async () => {
      await openService();

      const name = await getInput('service-form-name');

      await waitFor(() => expect(name).toHaveValue('cpu'));

      const enableSwitch = (
        await screen.findByTestId(panelDataTestIds.enable)
      ).querySelector('input') as HTMLInputElement;

      await waitFor(() => expect(enableSwitch).toBeChecked());
      await userEvent.click(enableSwitch);
      await waitForRequest('@patchService0');
      await waitFor(() => expect(enableSwitch).not.toBeChecked());

      await userEvent.clear(name);
      await userEvent.type(name, 'cpu load');

      await save();

      expect(await getRequestBody('@updateService0')).toMatchObject({
        activated: false,
        host_id: 10,
        name: 'cpu load'
      });
    });

    it('asks for a name and a host', async () => {
      await initializeListing();
      await openCreationForm();

      const name = await getInput('service-form-name');

      leave(name);
      leave(screen.getByTestId('service-form-host'));

      expect(await getFieldError(name, labelRequired)).toBeVisible();
      expect(
        await getFieldError(
          screen.getByTestId('service-form-host'),
          labelRequired
        )
      ).toBeVisible();
      expect(getSaveButton()).toBeDisabled();
    });

    it('refuses a name with a character the engine forbids', async () => {
      await initializeListing();
      await openCreationForm();

      const name = await getInput('service-form-name');

      await userEvent.type(name, 'cpu|load');
      leave(name);

      expect(
        await getFieldError(name, labelNameContainsForbiddenCharacters)
      ).toBeVisible();
    });
  });

  // Every key of the body, so that a key a block should not send, or sends
  // misnamed, fails here.
  describe('Whole payload: ', () => {
    it('creates a service from its required fields only, every other key on its default', async () => {
      await initializeListing();
      await openCreationForm();

      await userEvent.type(await getInput('service-form-name'), 'cpu load');
      await pickOption('service-form-host', 'host B');
      // Brings the check command a service needs.
      await pickOption('service-form-template', 'generic-active-service');

      await save();

      const { request } = await waitForRequest('@createService');

      expect(request.method).toBe('POST');
      expect(request.body).toEqual({
        category_ids: [],
        check_options: { args: [], command_id: null, macros: [] },
        data_processing: {
          acknowledgment_timeout: null,
          check_freshness: 'use_default',
          event_handler_args: [],
          event_handler_command_id: null,
          event_handler_enabled: 'use_default',
          flap_detection_enabled: 'use_default',
          freshness_threshold: null,
          high_flap_threshold: null,
          low_flap_threshold: null,
          obsess_over_service: 'use_default',
          retain_non_status_information: 'use_default',
          retain_status_information: 'use_default',
          stalking_options: []
        },
        extended_informations: {
          action_url: null,
          alt_icon: null,
          comment: null,
          geo_coordinates: null,
          icon_id: null,
          note: null,
          note_url: null
        },
        graph_template_id: null,
        host_id: 11,
        name: 'cpu load',
        notifications: untouchedNotificationsPayload,
        scheduling_options: {
          active_check_enabled: 'use_default',
          check_timeperiod_id: null,
          is_volatile: 'use_default',
          max_check_attempts: null,
          normal_check_interval: null,
          passive_check_enabled: 'use_default',
          retry_check_interval: null
        },
        service_group_ids: [],
        service_template_id: 30,
        severity_id: null,
        trap_ids: []
      });
    });

    // The save button waits for a change: the name is the only one.
    it('updates a service with every key as it opened, the renamed name aside', async () => {
      await openService();

      const name = await getInput('service-form-name');

      await waitFor(() => expect(name).toHaveValue('cpu'));
      await userEvent.clear(name);
      await userEvent.type(name, 'cpu load');

      await save();

      const { request } = await waitForRequest('@updateService0');

      expect(request.method).toBe('PUT');
      expect(request.body).toEqual({
        category_ids: [3],
        // The macro inherited from the template is not the service's own.
        check_options: {
          args: ['80', '90'],
          command_id: 9,
          macros: [
            {
              id: 1,
              is_password: false,
              name: 'WARNING',
              parent: null,
              value: '80'
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
        data_processing: {
          acknowledgment_timeout: 15,
          check_freshness: 'true',
          event_handler_args: ['80', 'graceful'],
          event_handler_command_id: 7,
          event_handler_enabled: 'false',
          flap_detection_enabled: 'true',
          freshness_threshold: 120,
          high_flap_threshold: 50,
          low_flap_threshold: null,
          obsess_over_service: 'false',
          retain_non_status_information: 'use_default',
          retain_status_information: 'true',
          stalking_options: ['w', 'c']
        },
        extended_informations: {
          action_url: 'https://example.com/actions/cpu',
          alt_icon: null,
          comment: 'Checked by the web team',
          geo_coordinates: '48.8566,2.3522',
          icon_id: 12,
          note: 'CPU load',
          note_url: null
        },
        graph_template_id: 7,
        host_id: 10,
        name: 'cpu load',
        notifications: openedNotificationsPayload,
        scheduling_options: {
          active_check_enabled: 'false',
          check_timeperiod_id: 21,
          is_volatile: 'true',
          max_check_attempts: 3,
          normal_check_interval: 5,
          passive_check_enabled: 'use_default',
          retry_check_interval: null
        },
        service_group_ids: [1],
        service_template_id: 30,
        severity_id: 1,
        trap_ids: [21]
      });
    });
  });
};
