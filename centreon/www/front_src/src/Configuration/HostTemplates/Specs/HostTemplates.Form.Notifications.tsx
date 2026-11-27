import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelNone } from '../../ResourceForm/translatedLabels';
import {
  labelDown,
  labelDowntimeScheduled,
  labelFlapping,
  labelNotifications,
  labelRecovery,
  labelUnreachable
} from '../translatedLabels';
import initialize from './initialize';

const prefix = 'host-template-form';

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

const getChips = (testId: string): Array<string | null> =>
  Array.from(
    screen
      .getByTestId(testId)
      .closest('.MuiAutocomplete-root')
      ?.querySelectorAll('.MuiChip-root') ?? []
  ).map(({ textContent }) => textContent);

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

export default () => {
  describe('Notifications: ', () => {
    it('opens a template on its notification settings and sends them back', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(getChips(`${prefix}-notifications-contacts`)).toEqual(['admin'])
      );
      expect(getChips(`${prefix}-notifications-contact-groups`)).toEqual([
        'Supervisors'
      ]);

      const interval = await getInput(`${prefix}-notifications-interval`);

      expect(interval).toHaveValue(3);
      await userEvent.clear(interval);
      await userEvent.type(interval, '4');

      await save();

      const { request } = await waitForRequest('@updateHostTemplate1');

      expect((request.body as Record<string, unknown>).notifications).toEqual({
        contact_groups: [3],
        contacts: [1],
        enabled: 'false',
        first_delay: null,
        interval: 4,
        options: ['down', 'recovery'],
        recovery_delay: null,
        timeperiod_id: 1
      });
    });

    it('creates a template notifying contacts read from the host template routes', async () => {
      await openCreationForm();

      await userEvent.click(
        screen.getByTestId(`${prefix}-notifications-contacts`)
      );
      const { request: contactsRequest } =
        await waitForRequest('@getFormContacts');

      expect(contactsRequest.url.pathname).toContain(
        '/api/configuration/host_templates/contacts'
      );
      await pickOption('guest');

      await userEvent.click(
        screen.getByTestId(`${prefix}-notifications-contact-groups`)
      );
      const { request: contactGroupsRequest } = await waitForRequest(
        '@getFormContactGroups'
      );

      expect(contactGroupsRequest.url.pathname).toContain(
        '/api/configuration/host_templates/contact_groups'
      );
      await pickOption('Guests');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect((request.body as Record<string, unknown>).notifications).toEqual({
        contact_groups: [4],
        contacts: [2],
        enabled: 'use_default',
        first_delay: null,
        interval: null,
        options: [],
        recovery_delay: null,
        timeperiod_id: null
      });
    });

    it('shows no notification and sends none on a cloud platform', async () => {
      await openCreationForm({ isCloudPlatform: true });

      expect(
        screen.queryByTestId(`${labelNotifications}-header`)
      ).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(`${prefix}-notifications-contacts`)
      ).not.toBeInTheDocument();

      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect(request.body).not.toHaveProperty('notifications');
      expect(getRequestCalls('getFormContacts')).toHaveLength(0);
      expect(getRequestCalls('getFormContactGroups')).toHaveLength(0);
    });

    it('opens a template with its notifications read-only for a user who may only look at templates', async () => {
      initialize({ hasWriteAccess: false, search: '?mode=edit&id=1' });

      await waitForRequest('@getHostTemplate1');

      await waitFor(() =>
        expect(getChips(`${prefix}-notifications-contacts`)).toEqual(['admin'])
      );
      for (const testId of [
        `${prefix}-notifications-contacts`,
        `${prefix}-notifications-contact-groups`,
        `${prefix}-notifications-timeperiod`
      ]) {
        expect(screen.getByTestId(testId)).toBeDisabled();
      }
      for (const field of ['interval', 'firstDelay', 'recoveryDelay']) {
        expect(
          await getInput(`${prefix}-notifications-${field}`)
        ).toBeDisabled();
      }
      const enabledButtons = within(
        screen.getByTestId(`${prefix}-notifications-enabled`)
      ).getAllByRole('button');

      expect(enabledButtons).toHaveLength(3);
      enabledButtons.forEach((button) => {
        expect(button).toBeDisabled();
      });
      for (const option of [
        labelDown,
        labelUnreachable,
        labelRecovery,
        labelFlapping,
        labelDowntimeScheduled
      ]) {
        expect(
          screen.getByTestId(`${prefix}-notifications-options-${option}`)
        ).toHaveAttribute('aria-disabled', 'true');
      }
      // `None` is a switch, beside the chips.
      expect(
        within(
          screen.getByTestId(`${prefix}-notifications-options`)
        ).getByLabelText(labelNone)
      ).toBeDisabled();
    });

    it('opens a locked template with its notifications read-only', async () => {
      await openTemplate(3);

      await waitFor(() =>
        expect(
          screen.getByTestId(`${prefix}-notifications-contacts`)
        ).toBeDisabled()
      );
      expect(await getInput(`${prefix}-notifications-interval`)).toBeDisabled();
    });
  });
};
