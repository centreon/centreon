import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelClose,
  labelFailedToUpdateSomeResources,
  labelMassChange
} from '../../ConfigurationBase/translatedLabels';
import {
  labelDown,
  labelTemplateReplacementDeletesServices
} from '../translatedLabels';
import initialize, { patchedHostIds } from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHosts');
  await screen.findByText('host 0');
};

// By its own checkbox: the Enable/Disable switches are checkboxes too.
const tickRow = async (id: number): Promise<void> => {
  await userEvent.click(screen.getByLabelText(`Select row ${id}`));
};

const openMassChange = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId(listingDataTestIds.moreActions));
  await userEvent.click(
    within(
      (await screen.findAllByRole('menu')).at(-1) as HTMLElement
    ).getByText(labelMassChange)
  );
};

const getHeader = (): Promise<HTMLElement> =>
  screen.findByTestId(panelDataTestIds.header);

const save = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector(
      `button[data-testid="${panelDataTestIds.save}"]`
    ) as HTMLButtonElement
  );
};

// No host written to, counting a request the mock has received but not
// answered yet.
const expectNoPatch = (): void => {
  expect(patchedHostIds).toEqual([]);
  [0, 1, 2].forEach((id) => {
    expect(
      getRequestCalls(id === 0 ? 'patchHost' : `patchHost${id}`)
    ).toHaveLength(0);
  });
};

const confirm = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('confirm'));
};

const getMaxCheckAttempts = async (): Promise<HTMLInputElement> =>
  (
    await screen.findAllByTestId(
      'host-form-scheduling-options-maxCheckAttempts'
    )
  )[1] as HTMLInputElement;

const typeMaxCheckAttempts = async (value: string): Promise<void> => {
  await userEvent.type(await getMaxCheckAttempts(), value);
};

const expectConfirmation = async (text: string): Promise<void> => {
  expect(
    await screen.findByTestId('mass-change-confirmation')
  ).toHaveTextContent(new RegExp(`^${text}$`));
};

// Exactly these hosts were written to, as many times, and through API
// Platform, each with this body when one is given.
const expectPatchedHosts = async (
  expectedIds: Array<number>,
  body?: object
): Promise<void> => {
  await waitFor(() => {
    expect([...patchedHostIds].sort()).toEqual([...expectedIds].sort());

    [...new Set(expectedIds)].forEach((id) => {
      const calls = getRequestCalls(id === 0 ? 'patchHost' : `patchHost${id}`);
      const { request } = calls[calls.length - 1];

      expect(request.url.pathname).toMatch(
        new RegExp(`/api/configuration/hosts/${id}$`)
      );
      expect(request.url.pathname).not.toContain('/api/latest');

      if (body) {
        expect(request.body).toEqual(body);
      }

      // Answered too, not only sent: requests are logged on arrival.
      expect(calls.every(({ answered }) => answered)).toBe(true);
    });
  });
};

export default () => {
  describe('Mass change: ', () => {
    it('applies the changes to the hosts ticked one by one, sending only what changed', async () => {
      await initializeListing();

      await tickRow(0);
      await tickRow(2);

      await openMassChange();

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (2)`);
      // Nothing that identifies one host, and no macros yet.
      await getMaxCheckAttempts();
      expect(screen.queryByTestId('host-form-name')).not.toBeInTheDocument();
      expect(screen.queryByTestId('host-form-address')).not.toBeInTheDocument();
      expect(
        screen.queryByTestId('host-form-check-options-macros')
      ).not.toBeInTheDocument();

      // Incremental, the default: added to each host's own.
      await userEvent.click(screen.getByTestId('host-form-groups'));
      await waitFor(() =>
        expect(document.querySelector('.MuiAutocomplete-popper')).not.toBeNull()
      );
      await userEvent.click(
        await within(
          document.querySelector('.MuiAutocomplete-popper') as HTMLElement
        ).findByText('Linux servers')
      );
      await userEvent.click(
        screen.getByTestId(`host-form-notifications-options-${labelDown}`)
      );

      // Replacement with nothing picked clears the categories.
      await userEvent.click(
        screen.getByTestId('host-form-update-mode-categories-replacement')
      );

      await typeMaxCheckAttempts('3');

      await save();

      await expectConfirmation('The changes will be applied to 2 hosts.');
      expect(
        screen.queryByTestId('mass-change-warning')
      ).not.toBeInTheDocument();

      await confirm();

      await expectPatchedHosts([0, 2], {
        category_ids: [],
        host_group_ids_to_add: [1],
        notifications: { options_to_add: ['down'] },
        scheduling_options: { max_check_attempts: 3 }
      });

      expect(await screen.findByText('Hosts updated')).toBeInTheDocument();
      await waitFor(() =>
        expect(
          screen.queryByTestId(panelDataTestIds.header)
        ).not.toBeInTheDocument()
      );
    });

    it('applies the changes to every host the check-all selects', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText('Select all'));

      await openMassChange();

      // The deactivated host 1 is selected too, although its own checkbox is
      // greyed: known, and out of this ticket.
      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (3)`);

      await typeMaxCheckAttempts('3');

      await save();

      await expectConfirmation('The changes will be applied to 3 hosts.');
      await confirm();

      await expectPatchedHosts([0, 1, 2], {
        scheduling_options: { max_check_attempts: 3 }
      });
    });

    it('writes nothing until the confirmation is accepted', async () => {
      await initializeListing();

      await tickRow(0);
      await openMassChange();

      await typeMaxCheckAttempts('3');
      await save();

      await userEvent.click(await screen.findByTestId('cancel'));

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (1)`);
      // Checked once the confirmation has left the page, well after a request
      // sent from it would have reached the mock.
      await waitFor(() =>
        expect(
          screen.queryByTestId('mass-change-confirmation')
        ).not.toBeInTheDocument()
      );
      expectNoPatch();

      // Then accepted: a single write proves none went out before, whether
      // on the first Save or on Cancel, however late it would have landed.
      await save();
      await expectConfirmation('The changes will be applied to 1 host.');
      await confirm();

      expect(await screen.findByText('Host updated')).toBeInTheDocument();
      expect(getRequestCalls('patchHost')).toHaveLength(1);
      expect(patchedHostIds).toEqual([0]);
    });

    it('warns that replacing the templates deletes their services', async () => {
      await initializeListing();

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await typeMaxCheckAttempts('3');
      await save();

      // Adding templates deletes nothing.
      await screen.findByTestId('mass-change-confirmation');
      expect(
        screen.queryByTestId('mass-change-warning')
      ).not.toBeInTheDocument();
      await userEvent.click(screen.getByTestId('cancel'));

      await userEvent.click(
        screen.getByTestId('host-form-update-mode-templates-replacement')
      );
      await save();

      const warning = await screen.findByTestId('mass-change-warning');

      // What the screenshot showed: a warning alert, with its icon and this
      // text alone.
      expect(warning).toBeVisible();
      expect(warning).toHaveAttribute('role', 'alert');
      expect(warning).toHaveTextContent(
        new RegExp(`^${labelTemplateReplacementDeletesServices}$`)
      );
      expect(
        within(warning).getByTestId('ReportProblemOutlinedIcon')
      ).toBeInTheDocument();

      await confirm();

      await expectPatchedHosts([0, 2], {
        scheduling_options: { max_check_attempts: 3 },
        template_ids: []
      });
    });

    it('leaves the additive inheritance alone unless Yes or No is picked', async () => {
      await initializeListing({ isAdditiveInheritanceEnabled: true });

      await tickRow(0);
      await openMassChange();

      const yes = await screen.findByTestId(
        'host-form-notifications-contact-additive-inheritance-true'
      );
      const no = screen.getByTestId(
        'host-form-notifications-contact-additive-inheritance-false'
      );

      expect(yes).toHaveAttribute('aria-pressed', 'false');
      expect(no).toHaveAttribute('aria-pressed', 'false');

      // Off on purpose, which a switch could not tell from "unchanged".
      await userEvent.click(no);

      await save();
      await confirm();

      await expectPatchedHosts([0], {
        notifications: { contact_additive_inheritance: false }
      });
    });

    it('names the hosts that failed and keeps the panel open on them alone', async () => {
      await initializeListing({ patchFails: [2] });

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await typeMaxCheckAttempts('3');
      await save();
      await confirm();

      await expectPatchedHosts([0, 2]);

      expect(
        await screen.findByText(`${labelFailedToUpdateSomeResources}: host 2`)
      ).toBeInTheDocument();

      // Still open on what was typed, now aimed at the failed host only.
      await waitFor(async () =>
        expect(await getHeader()).toHaveTextContent(`${labelMassChange} (1)`)
      );
      expect(await getMaxCheckAttempts()).toHaveValue(3);

      await save();
      await expectConfirmation('The changes will be applied to 1 host.');
      await confirm();

      // Host 0 is not written to again.
      await expectPatchedHosts([0, 2, 2]);
    });

    it('keeps the panel open on every host when they all fail', async () => {
      await initializeListing({ patchFails: [0, 2] });

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await typeMaxCheckAttempts('3');
      await save();
      await confirm();

      await expectPatchedHosts([0, 2]);

      expect(
        await screen.findByText('Failed to update the hosts')
      ).toBeInTheDocument();
      expect(screen.queryByText('Hosts updated')).not.toBeInTheDocument();
      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (2)`);
      expect(await getMaxCheckAttempts()).toHaveValue(3);
    });

    it('asks for the confirmation when the changes are saved on closing', async () => {
      await initializeListing();

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await userEvent.click(
        await screen.findByTestId('host-form-update-mode-templates-replacement')
      );

      await userEvent.click(screen.getByLabelText(labelClose));
      // The unsaved changes dialog's own Save.
      await confirm();

      // Not written yet: the count, the hosts and the warning come first.
      await expectConfirmation('The changes will be applied to 2 hosts.');
      expect(screen.getByTestId('mass-change-warning')).toBeVisible();
      expectNoPatch();

      await confirm();

      await expectPatchedHosts([0, 2], { template_ids: [] });

      // Once the change is reported done, each host was written to once: a
      // write sent before the confirmation, even one still on its way when
      // checked above, lands before the confirmed ones and makes two.
      expect(await screen.findByText('Hosts updated')).toBeInTheDocument();
      expect(getRequestCalls('patchHost')).toHaveLength(1);
      expect(getRequestCalls('patchHost2')).toHaveLength(1);
    });

    it('requires no host group on a cloud platform', async () => {
      await initializeListing({ isCloudPlatform: true });

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await typeMaxCheckAttempts('3');

      await waitFor(() =>
        expect(
          document.querySelector(
            `button[data-testid="${panelDataTestIds.save}"]`
          )
        ).toBeEnabled()
      );
      await save();
      await confirm();

      await expectPatchedHosts([0, 2], {
        scheduling_options: { max_check_attempts: 3 }
      });
    });
  });
};
