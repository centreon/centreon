import { capitalize } from '@mui/material';

import { waitForRequest } from '@test-setup/api';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { equals } from 'ramda';

import {
  labelDisabledHosts,
  labelEnabledHosts,
  labelNoDisabledHosts,
  labelNoEnabledHosts
} from '../translatedLabels';
import initialize from './initialize';

// The first row is the header: its cells' labels, the selection one aside.
const getColumnHeaders = (): Array<string> =>
  Array.from(screen.getAllByRole('row')[0].querySelectorAll('p')).map(
    (label) => label.textContent ?? ''
  );

const getDataRows = (): Array<HTMLElement> =>
  screen.getAllByRole('row').slice(1);

export default () => {
  describe('Listing: ', () => {
    it('hides all actions and action columns when the user does not have write access', async () => {
      initialize({ hasWriteAccess: false });

      await waitForRequest('@getAllHostGroups');
      expect(await screen.findByText('host group 0')).toBeVisible();

      // The data columns only, no row selection, no creation nor bulk actions.
      expect(getColumnHeaders()).toEqual([
        'Name',
        'Alias',
        'Enabled hosts',
        'Disabled hosts'
      ]);
      expect(screen.queryByTestId('add-resource')).not.toBeInTheDocument();
      expect(screen.queryByText('More actions')).not.toBeInTheDocument();
      expect(getDataRows()).toHaveLength(8);
      expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
      expect(screen.queryByLabelText('Duplicate')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('Delete')).not.toBeInTheDocument();
    });

    it('renders the Host group page with the ConfigurationBase layout', async () => {
      initialize();

      await waitForRequest('@getAllHostGroups');

      expect(await screen.findByText('Host groups')).toBeVisible();

      // The creation and bulk actions, the search bar, the columns, the eight
      // host groups and their status.
      expect(await screen.findByText('host group 7')).toBeVisible();
      expect(screen.getByTestId('add-resource')).toBeEnabled();
      expect(screen.getByText('More actions')).toBeVisible();
      expect(screen.getAllByTestId('search-bar')[0]).toBeVisible();
      expect(screen.getByText('1-8 of 8')).toBeVisible();
      expect(getColumnHeaders()).toEqual([
        'Name',
        'Alias',
        'Enabled hosts',
        'Disabled hosts',
        'Actions',
        'Enable/Disable'
      ]);

      const rows = getDataRows();

      expect(rows).toHaveLength(8);
      rows.forEach((row, index) => {
        const [select, enable] = within(row).getAllByRole('checkbox');

        expect(select).not.toBeChecked();
        // Odd host groups are the activated ones.
        expect(enable).toHaveProperty('checked', index % 2 === 1);
        expect(within(row).getByLabelText('Duplicate')).toBeVisible();
        expect(within(row).getByLabelText('Delete')).toBeVisible();
      });
      expect(within(rows[0]).getByRole('img')).toHaveAttribute(
        'alt',
        'cypress_logo'
      );
    });

    ['name', 'alias'].forEach((column) => {
      it(`sorts the ${column} column when clicked`, async () => {
        initialize();

        await waitForRequest('@getAllHostGroups');

        await userEvent.click(await screen.findByText(capitalize(column)));

        const { request } = await waitForRequest('@getAllHostGroups');

        expect(
          JSON.parse(request.url.searchParams.get('sort_by') ?? 'null')
        ).toEqual({
          [column]: 'desc'
        });
      });
    });

    it('truncates the name and alias fields when their length exceeds 50 characters', async () => {
      initialize();

      expect(
        await screen.findByText(`${'hostGroup0'.repeat(5)}...`)
      ).toBeVisible();
      expect(await screen.findByText(`${'alias'.repeat(10)}...`)).toBeVisible();
    });

    ['enabled host groups', 'disabled host groups'].forEach((column, i) => {
      const isEnabledHost = equals(i, 0);

      it(`displays all hosts of the host group when hovering over the ${column} column`, async () => {
        initialize();

        await waitForRequest('@getAllHostGroups');

        await userEvent.hover(
          await screen.findByText(isEnabledHost ? '12' : '15')
        );

        const tooltip = await screen.findByRole('tooltip');

        expect(
          within(tooltip).getByText(
            isEnabledHost ? labelEnabledHosts : labelDisabledHosts
          )
        ).toBeVisible();
        expect(await within(tooltip).findByText('host 1')).toBeVisible();

        const { request } = await waitForRequest('@getHosts');

        expect(
          JSON.parse(request.url.searchParams.get('search') ?? 'null')
        ).toEqual({
          $and: [
            {
              $or: [
                {
                  'group.name': {
                    $in: [isEnabledHost ? 'host group 4' : 'host group 3']
                  }
                }
              ]
            },
            { $or: [{ is_activated: { $eq: String(isEnabledHost) } }] }
          ]
        });

        // The hosts of that host group with that status, in a list.
        ['host 0', 'host 2', 'host 3', 'host 4', 'host 6', 'host 7'].forEach(
          (host) => {
            expect(within(tooltip).getByText(host)).toBeVisible();
          }
        );
      });

      it(`displays a 'Not found' message when hovering over the ${column} column with no hosts`, async () => {
        initialize({ isEmptyHostGroup: true });

        await waitForRequest('@getAllHostGroups');

        await userEvent.hover(
          (await screen.findAllByText('0'))[isEnabledHost ? 8 : 7]
        );

        const tooltip = await screen.findByRole('tooltip');

        // The tooltip shows the column's title and its empty message.
        expect(
          within(tooltip).getByText(
            isEnabledHost ? labelEnabledHosts : labelDisabledHosts
          )
        ).toBeVisible();
        expect(
          await within(tooltip).findByText(
            isEnabledHost ? labelNoEnabledHosts : labelNoDisabledHosts
          )
        ).toBeVisible();
      });
    });
  });
};
