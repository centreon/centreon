import { getRequestCalls } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelFailedToUpdateSomeResources,
  labelMassChange
} from '../../ConfigurationBase/translatedLabels';
import { labelReplacementOverwritesLists } from '../translatedLabels';
import initialize, { patchedServiceIds } from './initialize';
import { getSharedServiceListingResponse } from './utils';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await screen.findAllByText('host A');
};

// By its own checkbox: the Enable/Disable switches are checkboxes too. A row
// is keyed by its host and its service.
const tickRow = async (rowId: string): Promise<void> => {
  await userEvent.click(await screen.findByLabelText(`Select row ${rowId}`));
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

// The last one opened: a closing popper may still be in the page.
const getPopper = async (): Promise<HTMLElement> => {
  await waitFor(() =>
    expect(document.querySelector('.MuiAutocomplete-popper')).not.toBeNull()
  );

  return [...document.querySelectorAll('.MuiAutocomplete-popper')].at(
    -1
  ) as HTMLElement;
};

const pick = async (testId: string, option: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(testId));
  await userEvent.click(await within(await getPopper()).findByText(option));
  await userEvent.keyboard('{Escape}');
};

const setMode = async (
  field: string,
  mode: 'replacement' | 'removal'
): Promise<void> => {
  await userEvent.click(
    await screen.findByTestId(`service-form-update-mode-${field}-${mode}`)
  );
};

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

// Exactly these services were written to, as many times, each with this body
// when one is given.
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
    it('patches the selected services with what was added, nothing else', async () => {
      await initializeListing();

      await tickRow('10_0');
      await tickRow('10_2');
      await openMassChange();

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (2)`);
      // Nothing that identifies one service.
      await screen.findAllByTestId('service-form-service-groups');
      expect(screen.queryByTestId('service-form-name')).not.toBeInTheDocument();
      expect(screen.queryByTestId('service-form-host')).not.toBeInTheDocument();

      await pick('service-form-service-groups', 'Web services');

      await save();
      await expectConfirmation('The changes will be applied to 2 services.');
      expect(
        screen.queryByTestId('mass-change-warning')
      ).not.toBeInTheDocument();
      await confirm();

      await expectPatchedServices([0, 2], { service_group_ids_to_add: [1] });
      expect(await screen.findByText('Services updated')).toBeInTheDocument();
    });

    it('removes what a list set to Removal picks', async () => {
      await initializeListing();

      await tickRow('10_0');
      await openMassChange();

      await setMode('groups', 'removal');
      await pick('service-form-service-groups', 'Database services');
      await setMode('categories', 'removal');
      await pick('service-form-categories', 'Web');

      await save();
      // Removal overwrites nothing: no warning.
      await expectConfirmation('The changes will be applied to 1 service.');
      expect(
        screen.queryByTestId('mass-change-warning')
      ).not.toBeInTheDocument();
      await confirm();

      await expectPatchedServices([0], {
        category_ids_to_remove: [3],
        service_group_ids_to_remove: [2]
      });
    });

    it('warns before a list set to Replacement overwrites, or empties, those of the services', async () => {
      await initializeListing();

      await tickRow('10_0');
      await tickRow('10_2');
      await openMassChange();

      await setMode('groups', 'replacement');

      await save();

      expect(
        await screen.findByTestId('mass-change-warning')
      ).toHaveTextContent(new RegExp(`^${labelReplacementOverwritesLists}$`));

      await confirm();

      await expectPatchedServices([0, 2], { service_group_ids: [] });
    });

    it('patches a service linked to several hosts once', async () => {
      await initializeListing({
        listingResponse: getSharedServiceListingResponse()
      });

      await userEvent.click(await screen.findByLabelText('Select all'));
      await openMassChange();

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (1)`);

      await pick('service-form-service-groups', 'Web services');
      await save();
      await expectConfirmation('The changes will be applied to 1 service.');
      await confirm();

      await expectPatchedServices([5], { service_group_ids_to_add: [1] });
    });

    it('names the services that failed and keeps the panel open on them alone', async () => {
      await initializeListing({ patchFails: [2] });

      await tickRow('10_0');
      await tickRow('10_2');
      await openMassChange();

      await pick('service-form-service-groups', 'Web services');
      await save();
      await confirm();

      await expectPatchedServices([0, 2]);

      expect(
        await screen.findByText(`${labelFailedToUpdateSomeResources}: disk`)
      ).toBeInTheDocument();
      await waitFor(async () =>
        expect(await getHeader()).toHaveTextContent(`${labelMassChange} (1)`)
      );

      await save();
      await expectConfirmation('The changes will be applied to 1 service.');
      await confirm();

      // Service 0 is not written to again.
      await expectPatchedServices([0, 2, 2]);
    });
  });
};
