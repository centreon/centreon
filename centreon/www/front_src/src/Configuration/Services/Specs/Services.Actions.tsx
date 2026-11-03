import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import {
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelEnable
} from '../../ConfigurationBase/translatedLabels';
import initialize from './initialize';

const selectAll = async (): Promise<void> => {
  await userEvent.click(screen.getByLabelText('Select all'));
};

const openMoreActions = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId(listingDataTestIds.moreActions));

  // The last one opened: a closing menu may still be in the page.
  return (await screen.findAllByRole('menu')).at(-1) as HTMLElement;
};

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
  await screen.findByText('cpu');
};

export default () => {
  describe('Actions: ', () => {
    it('deletes a service through the confirmation modal', async () => {
      await initializeListing();

      await userEvent.click(
        screen.getByTestId(listingDataTestIds.row.delete(0))
      );
      await userEvent.click(await screen.findByTestId('confirm'));

      const { request } = await waitForRequest('@deleteService0');

      expect(request.url.pathname).toContain('/api/configuration/services/0');
      expect(request.url.pathname).not.toContain('/api/latest');

      await waitFor(() =>
        expect(screen.queryByTestId('confirm')).not.toBeInTheDocument()
      );
    });

    it('duplicates a service through the confirmation modal', async () => {
      await initializeListing();

      await userEvent.click(
        screen.getByTestId(listingDataTestIds.row.duplicate(0))
      );
      await userEvent.click(await screen.findByTestId('confirm'));

      // The route names the service and takes no body.
      const { request } = await waitForRequest('@duplicateService0');

      expect(request.url.pathname).toContain(
        '/api/configuration/services/0/_duplicate'
      );
    });

    it('disables an activated service from the row toggle', async () => {
      await initializeListing();

      await userEvent.click(
        screen.getByTestId(listingDataTestIds.row.enableDisable(0))
      );

      const { request } = await waitForRequest('@patchService0');

      expect(request.body).toEqual({ activated: false });
    });

    it('enables a deactivated service from the row toggle', async () => {
      await initializeListing();

      await userEvent.click(
        screen.getByTestId(listingDataTestIds.row.enableDisable(1))
      );

      const { request } = await waitForRequest('@patchService1');

      expect(request.body).toEqual({ activated: true });
    });

    it('offers the massive actions the ticket lists', async () => {
      await initializeListing();

      await selectAll();

      const menu = await openMoreActions();

      expect(
        within(menu)
          .getAllByRole('menuitem')
          .map((entry) => entry.textContent)
      ).toEqual([labelDuplicate, labelEnable, labelDisable, labelDelete]);
    });

    it('deletes every selected service, one request each', async () => {
      await initializeListing();

      await selectAll();

      const menu = await openMoreActions();

      await userEvent.click(within(menu).getByText(labelDelete));
      await userEvent.click(await screen.findByTestId('confirm'));

      // Sequentially, so waiting on the last one proves the whole fan-out ran.
      await waitForRequest('@deleteService0');
      const { request } = await waitForRequest('@deleteService2');

      expect(request.url.pathname).toContain('/api/configuration/services/2');
    });

    describe('Read-only ACL user: ', () => {
      beforeEach(async () => {
        await initializeListing({ hasWriteAccess: false });
      });

      it('keeps the toggle, disabled', () => {
        expect(
          within(
            screen.getByTestId(listingDataTestIds.row.enableDisable(0))
          ).getByRole('checkbox')
        ).toBeDisabled();
      });

      it('hides the add button, more actions and the row write actions', () => {
        expect(
          screen.queryByTestId(listingDataTestIds.moreActions)
        ).not.toBeInTheDocument();
        expect(screen.queryByTestId('add-resource')).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(listingDataTestIds.row.delete(0))
        ).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(listingDataTestIds.row.duplicate(0))
        ).not.toBeInTheDocument();
      });
    });
  });
};
