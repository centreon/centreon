import { waitForRequest } from '@test-setup/api';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { labelAlias, labelName } from '../translatedLabels';
import initialize from './initialize';

export default () => {
  describe('Filters: ', () => {
    it('displays and interacts with filters', async () => {
      initialize();

      await waitForRequest('@getAllHostGroups');

      expect((await screen.findAllByTestId('search-bar'))[0]).toBeVisible();

      await userEvent.click(screen.getByTestId('Filters'));

      const advancedFilters = await screen.findByTestId('advanced-filters');

      expect(advancedFilters).toBeVisible();

      expect(within(advancedFilters).getAllByText(labelName)[0]).toBeVisible();
      expect(within(advancedFilters).getAllByText(labelAlias)[0]).toBeVisible();

      // The status filter, unchecked, and the clear and search actions.
      expect(within(advancedFilters).getByText('Status')).toBeVisible();
      expect(
        within(advancedFilters).getByRole('checkbox', { name: 'Enabled' })
      ).not.toBeChecked();
      expect(
        within(advancedFilters).getByRole('checkbox', { name: 'Disabled' })
      ).not.toBeChecked();
      expect(
        within(advancedFilters).getByRole('button', { name: 'Clear' })
      ).toBeVisible();
      expect(
        within(advancedFilters).getByRole('button', { name: 'Search' })
      ).toBeVisible();
    });
  });
};
