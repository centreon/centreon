import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { selectedRowsAtom } from '../../ConfigurationBase/Listing/atoms';
import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import {
  labelActions,
  labelDelete,
  labelDuplicate
} from '../../ConfigurationBase/translatedLabels';
import { labelName } from '../translatedLabels';
import initialize, { getHeaderLabels } from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<ReturnType<typeof initialize>> => {
  const result = initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  return result;
};

const openMoreActions = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId(listingDataTestIds.moreActions));

  // The last one opened: a closing menu may still be in the page.
  return (await screen.findAllByRole('menu')).at(-1) as HTMLElement;
};

export default () => {
  describe('Actions: ', () => {
    it('offers duplicate and delete on an unlocked template', async () => {
      await initializeListing();

      [1, 2].forEach((id) => {
        expect(
          screen.getByTestId(listingDataTestIds.row.duplicate(id))
        ).toBeVisible();
        expect(
          screen.getByTestId(listingDataTestIds.row.delete(id))
        ).toBeVisible();
      });
    });

    describe('Locked template: ', () => {
      it('offers neither duplicate nor delete', async () => {
        await initializeListing();

        expect(
          screen.queryByTestId(listingDataTestIds.row.duplicate(3))
        ).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(listingDataTestIds.row.delete(3))
        ).not.toBeInTheDocument();
      });

      it('cannot be selected', async () => {
        await initializeListing();

        expect(screen.getByLabelText('Select row 3')).toBeDisabled();
        expect(screen.getByLabelText('Select row 1')).toBeEnabled();
        expect(screen.getByLabelText('Select row 2')).toBeEnabled();
      });

      // A disabled checkbox alone would not keep select-all off the row.
      it('is left out of select-all', async () => {
        const { store } = await initializeListing();

        await userEvent.click(screen.getByLabelText('Select all'));

        await waitFor(() => {
          expect(screen.getByLabelText('Select row 1')).toBeChecked();
        });
        expect(screen.getByLabelText('Select row 2')).toBeChecked();
        expect(screen.getByLabelText('Select row 3')).not.toBeChecked();

        expect(
          (store.get(selectedRowsAtom) as Array<{ id: number }>).map(
            ({ id }) => id
          )
        ).toEqual([1, 2]);
      });
    });

    it('offers duplicate and delete for a selection, and no activation', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText('Select row 1'));

      const menu = await openMoreActions();

      expect(
        within(menu)
          .getAllByRole('menuitem')
          .map((entry) => entry.textContent)
      ).toEqual([labelDuplicate, labelDelete]);
    });

    describe('Read-only user: ', () => {
      beforeEach(async () => {
        await initializeListing({ hasWriteAccess: false });
      });

      it('hides the add button and more actions', () => {
        expect(screen.queryByTestId('add-resource')).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(listingDataTestIds.moreActions)
        ).not.toBeInTheDocument();
      });

      it('offers no selection', () => {
        expect(screen.queryByLabelText('Select all')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Select row 1')).not.toBeInTheDocument();
      });

      it('offers no row action, nor their column', () => {
        expect(
          screen.queryByTestId(listingDataTestIds.row.duplicate(1))
        ).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(listingDataTestIds.row.delete(1))
        ).not.toBeInTheDocument();
        // Positive on the remaining headers, so an empty head cannot pass.
        const headers = getHeaderLabels();

        expect(headers).toContain(labelName);
        expect(headers).not.toContain(labelActions);
      });
    });
  });
};
