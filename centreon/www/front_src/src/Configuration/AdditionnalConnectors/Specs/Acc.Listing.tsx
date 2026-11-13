import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelCreationDate,
  labelCreator,
  labelLastUpdate,
  labelName,
  labelType,
  labelUpdateBy
} from '../translatedLabels';
import initialize from './initialize';

const columnToSort = [
  { id: 'name', label: labelName },
  { id: 'type', label: labelType },
  { id: 'created_by', label: labelCreator },
  { id: 'created_at', label: labelCreationDate },
  { id: 'updated_by', label: labelUpdateBy },
  { id: 'updated_at', label: labelLastUpdate }
];

const getSearchParameter = (url: URL, name: string): unknown =>
  JSON.parse(url.searchParams.get(name) as string);

export default (): void => {
  describe('Listing', () => {
    beforeEach(() => {
      initialize();
    });

    it('displays the first page of the ACC listing', async () => {
      await waitForRequest('@getConnectors');

      expect(await screen.findByText('VMWare1')).toBeVisible();
      expect(screen.getByText('Description for VMWare1')).toBeVisible();

      // The ten connectors of the page in order, with their columns, and the
      // total.
      const rows = screen.getAllByRole('row').slice(1);

      expect(rows).toHaveLength(10);
      rows.forEach((row, index) => {
        expect(row).toHaveTextContent(`VMWare${index + 1}`);
      });
      expect(rows[0]).toHaveTextContent(
        /^VMWare1VMWare 6\/7Description for VMWare1Alice07\/23\/2024Bob07\/23\/2024$/
      );
      expect(screen.getByText('1-10 of 44')).toBeVisible();
      const headerCells = Array.from(
        screen.getAllByRole('row')[0].children
      ).slice(0, 8);

      expect(headerCells.map((cell) => cell.textContent)).toEqual([
        labelName,
        labelType,
        'Description',
        labelCreator,
        labelCreationDate,
        labelUpdateBy,
        labelLastUpdate,
        'Actions'
      ]);
      // Sorted by name, ascending.
      expect(screen.getByLabelText(`Column ${labelName}`)).toHaveClass(
        'Mui-active',
        'MuiTableSortLabel-directionAsc'
      );
    });
    it('sends a listing request with the selected limit when the corresponding button is clicked', async () => {
      await waitForRequest('@getConnectors');

      expect(await screen.findByText('VMWare1')).toBeVisible();
      expect(screen.getByText('Description for VMWare1')).toBeVisible();

      await userEvent.click(
        document.querySelector('#Rows\\ per\\ page') as HTMLElement
      );
      await userEvent.click(
        within(await screen.findByRole('listbox')).getByText(/^20$/)
      );

      const { request } = await waitForRequest('@getConnectors');

      expect(getSearchParameter(request.url, 'limit')).toBe(20);
    });

    it('sends a listing request with the selected page when the corresponding button is clicked', async () => {
      await waitForRequest('@getConnectors');
      expect(await screen.findByText('VMWare1')).toBeVisible();
      expect(screen.getByText('Description for VMWare1')).toBeVisible();

      await userEvent.click(screen.getByLabelText('Next page'));

      const { request } = await waitForRequest('@getConnectors');

      expect(getSearchParameter(request.url, 'page')).toBe(2);
    });
    it('executes a listing request with sort parameter when a sortable column is clicked`', async () => {
      await waitForRequest('@getConnectors');

      for (const { label, id } of columnToSort) {
        expect(await screen.findByText('VMWare1')).toBeVisible();
        expect(screen.getByText('VMWare2')).toBeVisible();

        await userEvent.click(screen.getByLabelText(`Column ${label}`));

        const { request } = await waitForRequest('@getConnectors');

        expect(getSearchParameter(request.url, 'sort_by')).toEqual({
          [id]: 'desc'
        });

        // The clicked column is the sorted one.
        await waitFor(() =>
          expect(screen.getByLabelText(`Column ${label}`)).toHaveClass(
            'Mui-active',
            'MuiTableSortLabel-directionDesc'
          )
        );
      }
    });
  });
};
