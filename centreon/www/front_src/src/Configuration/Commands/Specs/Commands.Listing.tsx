import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { labelName, labelType } from '../translatedLabels';
import initialize from './initialize';

const columnToSort = [
  { id: 'name', label: labelName },
  { id: 'type', label: labelType }
];

// The listing is displayed: the page has applied the first answer.
const initializeAndWaitForListing = async (): Promise<void> => {
  initialize();

  await waitForRequest('@getCommands');

  await screen.findByText('check_host_alive');
};

const getSortLabel = (label: string): HTMLElement =>
  screen.getByLabelText(`Column ${label}`);

export default (): void => {
  describe('Listing', () => {
    it('displays the first page of the Commands listing', async () => {
      await initializeAndWaitForListing();

      expect(await screen.findByText('check_host_alive')).toBeVisible();

      // The first ten of the eleven commands, in the order of the answer,
      // sorted by name.
      const rows = screen.getAllByRole('row').slice(1);

      expect(rows).toHaveLength(10);
      expect(rows[0]).toHaveTextContent('check_host_alive');
      expect(rows[9]).toHaveTextContent('check_hpjd');
      expect(screen.getByText('1-10 of 11')).toBeVisible();
      expect(getSortLabel(labelName)).toHaveClass('Mui-active');
      expect(getSortLabel(labelType)).not.toHaveClass('Mui-active');
    });
    it('sends a listing request with the selected limit when the corresponding button is clicked', async () => {
      await initializeAndWaitForListing();

      await userEvent.click(
        document.querySelector('#Rows\\ per\\ page') as HTMLElement
      );
      await userEvent.click(
        (await screen.findAllByRole('option')).find(
          (option) => option.textContent === '20'
        ) as HTMLElement
      );

      expect(await screen.findByText('check_host_alive')).toBeVisible();

      const { request } = await waitForRequest('@getCommands');

      expect(request.url.href).toContain('itemsPerPage=20');
    });

    it('sends a listing request with the selected page when the corresponding button is clicked', async () => {
      await initializeAndWaitForListing();

      await userEvent.click(screen.getByLabelText('Next page'));

      const { request } = await waitForRequest('@getCommands');

      expect(JSON.parse(request.url.searchParams.get('page') ?? '')).toBe(2);
    });
    it('executes a listing request with sort parameter when a sortable column is clicked`', async () => {
      await initializeAndWaitForListing();

      for (const { label, id } of columnToSort) {
        await userEvent.click(getSortLabel(label));

        const { request } = await waitForRequest('@getCommands');

        expect(request.url.href).toContain(`sort[${id}]=desc`);

        // The clicked column is the sorted one, and in descending order.
        await waitFor(() =>
          expect(
            getSortLabel(label).querySelector(
              '.MuiTableSortLabel-iconDirectionDesc'
            )
          ).not.toBeNull()
        );
        expect(getSortLabel(label)).toHaveClass('Mui-active');
        expect(await screen.findByText('check_host_alive')).toBeVisible();
      }
    });
  });
};
