import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelFailedToUpdateSomeResources,
  labelMassChange
} from '../../ConfigurationBase/translatedLabels';
import { labelReplacementOverwritesLists } from '../translatedLabels';
import initialize, {
  interceptNotificationsRequests,
  patchedServiceIds
} from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
  await screen.findByText('cpu');
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

const confirm = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('confirm'));
};

const expectConfirmation = async (text: string): Promise<void> => {
  expect(
    await screen.findByTestId('mass-change-confirmation')
  ).toHaveTextContent(new RegExp(`^${text}$`));
};

const getMaxCheckAttempts = async (): Promise<HTMLInputElement> =>
  (
    await screen.findAllByTestId('service-form-max-check-attempts')
  )[1] as HTMLInputElement;

const typeMaxCheckAttempts = async (value: string): Promise<void> => {
  await userEvent.type(await getMaxCheckAttempts(), value);
};

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: a name can also appear in the listing behind it.
const pickOption = async (testId: string, option: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(testId));
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

// Exactly these services were written to, as many times, with a partial
// update of each, carrying this body when one is given.
const expectPatchedServices = async (
  expectedIds: Array<number>,
  body?: object
): Promise<void> => {
  await waitFor(() => {
    expect([...patchedServiceIds].sort()).toEqual([...expectedIds].sort());

    [...new Set(expectedIds)].forEach((id) => {
      const calls = getRequestCalls(`patchService${id}`);
      const { request } = calls[calls.length - 1];

      expect(request.url.pathname).toMatch(
        new RegExp(`/api/configuration/services/${id}$`)
      );
      expect(request.url.pathname).not.toContain('/api/latest');

      if (body) {
        expect(request.body).toEqual(body);
      }

      expect(calls.every(({ answered }) => answered)).toBe(true);
      // Never the full replacement the edit sends.
      expect(getRequestCalls(`updateService${id}`)).toHaveLength(0);
    });
  });
};

export default () => {
  describe('Mass change: ', () => {
    it('applies the changes to the services ticked, sending only what changed', async () => {
      await initializeListing();

      await tickRow(0);
      await tickRow(2);

      await openMassChange();

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (2)`);
      // Nothing that identifies one service, and no macros.
      await getMaxCheckAttempts();
      expect(screen.queryByTestId('service-form-name')).not.toBeInTheDocument();
      expect(screen.queryByTestId('service-form-host')).not.toBeInTheDocument();
      expect(
        screen.queryByTestId('service-form-macros')
      ).not.toBeInTheDocument();

      // Incremental, the default: added to each service's own.
      await pickOption('service-form-service-groups', 'Web services');

      await typeMaxCheckAttempts('3');

      await save();

      await expectConfirmation('The changes will be applied to 2 services.');
      expect(
        screen.queryByTestId('mass-change-warning')
      ).not.toBeInTheDocument();

      await confirm();

      await expectPatchedServices([0, 2], {
        scheduling_options: { max_check_attempts: 3 },
        service_group_ids_to_add: [1]
      });

      expect(await screen.findByText('Services updated')).toBeInTheDocument();
      await waitFor(() =>
        expect(
          screen.queryByTestId(panelDataTestIds.header)
        ).not.toBeInTheDocument()
      );
    });

    it('preselects no tri-state value', async () => {
      await initializeListing();

      await tickRow(0);
      await openMassChange();

      await getMaxCheckAttempts();

      ['true', 'false', 'use_default'].forEach((value) => {
        expect(
          screen.getByTestId(`service-form-active-checks-enabled-${value}`)
        ).toHaveAttribute('aria-pressed', 'false');
      });
    });

    it('replaces a list with nothing picked, after warning about it', async () => {
      await initializeListing();

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await userEvent.click(
        await screen.findByTestId(
          'service-form-update-mode-categories-replacement'
        )
      );

      await save();

      await expectConfirmation('The changes will be applied to 2 services.');

      const warning = await screen.findByTestId('mass-change-warning');

      expect(warning).toBeVisible();
      expect(warning).toHaveTextContent(
        new RegExp(`^${labelReplacementOverwritesLists}$`)
      );

      await confirm();

      await expectPatchedServices([0, 2], { category_ids: [] });
    });

    it('removes the picked traps from each service', async () => {
      await initializeListing();

      await tickRow(0);
      await openMassChange();

      await userEvent.click(
        await screen.findByTestId('service-form-update-mode-traps-removal')
      );
      await pickOption('service-form-traps', 'Generic - linkDown');

      await save();

      await expectConfirmation('The changes will be applied to 1 service.');
      expect(
        screen.queryByTestId('mass-change-warning')
      ).not.toBeInTheDocument();

      await confirm();

      await expectPatchedServices([0], { trap_ids_to_remove: [21] });
    });

    it('removes the picked contacts from each service', async () => {
      await initializeListing();
      interceptNotificationsRequests();

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await userEvent.click(
        await screen.findByTestId('service-form-update-mode-contacts-removal')
      );
      await pickOption('service-form-notifications-contacts', 'admin');

      await save();
      await confirm();

      await expectPatchedServices([0, 2], {
        notifications: { contacts_to_remove: [1] }
      });
    });

    it('sends a field emptied on purpose as null', async () => {
      await initializeListing();

      await tickRow(0);
      await openMassChange();

      await typeMaxCheckAttempts('3');
      await userEvent.clear(await getMaxCheckAttempts());

      await save();
      await confirm();

      await expectPatchedServices([0], {
        scheduling_options: { max_check_attempts: null }
      });
    });

    it('names the services that failed and keeps the panel open on them alone', async () => {
      await initializeListing({ patchFails: [2] });

      await tickRow(0);
      await tickRow(2);
      await openMassChange();

      await typeMaxCheckAttempts('3');
      await save();
      await confirm();

      await expectPatchedServices([0, 2]);

      expect(
        await screen.findByText(`${labelFailedToUpdateSomeResources}: disk`)
      ).toBeInTheDocument();

      // Still open on what was typed, now aimed at the failed service only.
      await waitFor(async () =>
        expect(await getHeader()).toHaveTextContent(`${labelMassChange} (1)`)
      );
      expect(await getMaxCheckAttempts()).toHaveValue(3);

      await save();
      await expectConfirmation('The changes will be applied to 1 service.');
      await confirm();

      // Service 0 is not written to again.
      await expectPatchedServices([0, 2, 2]);
    });
  });
};
