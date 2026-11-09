import { interceptRequest, Method, waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelRequired } from '../../ResourceForm/translatedLabels';
import { labelNameContainsForbiddenCharacters } from '../translatedLabels';
import initialize from './initialize';

interface RequestBody {
  activated?: boolean;
  check_options?: unknown;
  host_id?: number | null;
  name?: string;
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

      // The template and scheduling options are the other sections' specs.
      expect(await getRequestBody('@createService')).toMatchObject({
        check_options: { args: [], command_id: 8, macros: [] },
        host_id: 11,
        name: 'cpu load'
      });
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
      // The whole service is replaced, its activation included.
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

    it('leaves another opened service alone when a switch answers late', async () => {
      await openService();

      let answer: () => void = () => undefined;
      const answered = new Promise<void>((resolve) => {
        answer = resolve;
      });

      interceptRequest(
        Method.PATCH,
        '**/configuration/services/0',
        async () => {
          await answered;

          return HttpResponse.json({});
        },
        'latePatchService0'
      );

      const enableSwitch = (
        await screen.findByTestId(panelDataTestIds.enable)
      ).querySelector('input') as HTMLInputElement;

      await waitFor(() => expect(enableSwitch).toBeChecked());
      await userEvent.click(enableSwitch);

      await openRow('memory');
      await waitForRequest('@getService1');
      await expectHeader('memory');

      answer();
      await waitForRequest('@latePatchService0');

      await expectHeader('memory');
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
};
