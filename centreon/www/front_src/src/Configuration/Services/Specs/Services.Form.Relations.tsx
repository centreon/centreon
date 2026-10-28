import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelServiceGroups, labelServiceTraps } from '../translatedLabels';
import initialize from './initialize';

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

const getSaveButton = (): HTMLButtonElement =>
  document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

// Enabled once the form has validated what was picked last: a click before
// that is lost.
const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled());
  await userEvent.click(getSaveButton());
};

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: a name can also appear in the listing behind it.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const getChips = (label: string): Array<string> =>
  Array.from(
    (
      screen.getByLabelText(label).closest('.MuiAutocomplete-root') as Element
    ).querySelectorAll('.MuiChip-root')
  ).map((chip) => chip.textContent ?? '');

export default () => {
  describe('Relations: ', () => {
    it('opens a service on the groups and traps the detail endpoint returns', async () => {
      await initializeListing();

      await openRow('cpu');

      await waitForRequest('@getService0');

      // Found by label, so two swapped labels turn this red. A trap reads
      // `vendor - trap`, as in the legacy form.
      await waitFor(() =>
        expect(getChips(labelServiceGroups)).toEqual(['Web services'])
      );
      expect(getChips(labelServiceTraps)).toEqual(['Generic - linkDown']);
    });

    it('sends the picked groups and traps as ids', async () => {
      await initializeListing();

      await openRow('cpu');

      await waitForRequest('@getService0');
      await waitFor(() =>
        expect(getChips(labelServiceGroups)).toEqual(['Web services'])
      );

      await userEvent.click(screen.getByTestId('service-form-service-groups'));

      const { request: groupsRequest } = await waitForRequest(
        '@getFormServiceGroups'
      );

      expect(groupsRequest.url.pathname).toContain(
        '/api/configuration/services/service_groups'
      );
      expect(groupsRequest.url.pathname).not.toContain('/api/latest');

      await pickOption('Database services');
      // A multi-select stays open after a pick.
      await userEvent.keyboard('{Escape}');

      await userEvent.click(screen.getByTestId('service-form-traps'));

      const { request: trapsRequest } = await waitForRequest('@getFormTraps');

      expect(trapsRequest.url.pathname).toContain(
        '/api/configuration/services/traps'
      );

      await pickOption('Generic - linkUp');
      await userEvent.keyboard('{Escape}');

      await save();

      const { request } = await waitForRequest('@updateService0');

      expect(request.body).toMatchObject({
        service_group_ids: [1, 2],
        trap_ids: [21, 22]
      });
    });

    it('creates a service in no group and with no trap by default', async () => {
      await initializeListing();

      await openCreationForm();

      // Neither field is required, so nothing flags them.
      expect(
        await screen.findByTestId('service-form-service-groups')
      ).toBeInTheDocument();
      expect(getChips(labelServiceGroups)).toEqual([]);
      expect(getChips(labelServiceTraps)).toEqual([]);
    });

    it('disables the groups and traps for a user who may only look at services', async () => {
      await initializeListing({ hasWriteAccess: false });

      await openRow('cpu');

      await waitForRequest('@getService0');

      await waitFor(() =>
        expect(screen.getByTestId('service-form-service-groups')).toBeDisabled()
      );
      expect(screen.getByTestId('service-form-traps')).toBeDisabled();
    });

    it('drops the traps on a cloud platform and keeps the groups optional', async () => {
      await initializeListing({ isCloudPlatform: true });

      await openRow('cpu');

      await waitForRequest('@getService0');

      await waitFor(() =>
        expect(getChips(labelServiceGroups)).toEqual(['Web services'])
      );
      expect(
        screen.queryByTestId('service-form-traps')
      ).not.toBeInTheDocument();
      expect(
        screen.queryByLabelText(labelServiceTraps)
      ).not.toBeInTheDocument();

      // Emptied: a service needs no group on cloud, unlike a host.
      await userEvent.click(
        within(
          screen
            .getByLabelText(labelServiceGroups)
            .closest('.MuiAutocomplete-root') as HTMLElement
        ).getByTestId('CancelIcon')
      );

      await save();

      const { request } = await waitForRequest('@updateService0');

      expect(request.body).toHaveProperty('service_group_ids', []);
      expect(request.body).not.toHaveProperty('trap_ids');
    });
  });
};
