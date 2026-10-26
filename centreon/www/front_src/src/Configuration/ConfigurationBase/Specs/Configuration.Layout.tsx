import { capitalize } from '@mui/material';

import { waitForRequest } from '@test-setup/api';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import pluralize from 'pluralize';

import {
  labelActions,
  labelAlias,
  labelClear,
  labelDelete,
  labelDisabled,
  labelDuplicate,
  labelEnableDisable,
  labelEnabled,
  labelFilters,
  labelMoreActions,
  labelName,
  labelSearch
} from '../translatedLabels';
import initialize, { toResource } from './initialize';
import { getToggle } from './utils';

export default (resourceType: string): void => {
  const capitalizedResourceType = capitalize(resourceType);
  const pluralizedResourceType = pluralize(capitalizedResourceType);
  const resource = toResource(resourceType);

  describe('Layout', () => {
    beforeEach(async () => {
      initialize({ resourceType });

      await waitForRequest('@getAll');
      await screen.findByText(`${resource} 0`);
    });

    it('renders the layout with all components', async () => {
      expect(await screen.findByText(pluralizedResourceType)).toBeVisible();

      // What the screenshot showed: the columns in order, every row of the
      // response, each toggle reflecting its row, and the pagination.
      const columnLabels = [
        labelName,
        labelAlias,
        labelActions,
        labelEnableDisable
      ];

      // Left to right, in the order declared.
      const columnLefts = columnLabels.map(
        (label) => screen.getByText(label).getBoundingClientRect().left
      );

      expect(columnLefts).toEqual([...columnLefts].sort((a, b) => a - b));

      Array.from({ length: 12 }, (_, id) => id).forEach((id) => {
        expect(screen.getByText(`${resource} ${id}`)).toBeVisible();
        expect(screen.getByText(`alias for ${resource} ${id}`)).toBeVisible();

        const toggle = getToggle(id);

        if (id % 2) {
          expect(toggle).toBeChecked();
        } else {
          expect(toggle).not.toBeChecked();
        }
      });

      expect(screen.getByText('1-10 of 12')).toBeVisible();
      expect(screen.getByTestId('add-resource')).toBeEnabled();
      expect(screen.getByTestId(labelMoreActions)).toBeDisabled();
    });

    it('displays configuration static columns', async () => {
      expect(await screen.findByText(labelEnableDisable)).toBeVisible();
      expect(screen.getByText(labelActions)).toBeVisible();

      expect(screen.getByTestId(`${labelDuplicate}_1`)).toBeVisible();
      expect(screen.getByTestId(`${labelDelete}_1`)).toBeVisible();
      expect(screen.getByTestId(`${labelEnableDisable}_1`)).toBeVisible();
    });

    it('displays resource-specific additional columns', async () => {
      expect(await screen.findByText(labelName)).toBeVisible();
      expect(screen.getByText(labelAlias)).toBeVisible();
    });

    it('displays more actions', async () => {
      expect(await screen.findByTestId(labelMoreActions)).toBeVisible();
    });

    it('displays and interacts with filters', async () => {
      expect(screen.getAllByTestId('search-bar')[0]).toBeVisible();

      await userEvent.click(screen.getByTestId(labelFilters));

      const advancedFilters = await screen.findByTestId('advanced-filters');

      expect(advancedFilters).toBeVisible();

      // What the screenshot showed: every declared filter, empty, with nothing
      // to clear yet.
      const filters = within(advancedFilters);

      expect(filters.getAllByTestId(labelName)[1]).toHaveValue('');
      expect(filters.getAllByTestId(labelAlias)[1]).toHaveValue('');
      expect(filters.getByLabelText(labelEnabled)).not.toBeChecked();
      expect(filters.getByLabelText(labelDisabled)).not.toBeChecked();
      expect(filters.getByTestId(labelClear)).toBeDisabled();
      expect(filters.getByTestId(labelSearch)).toBeEnabled();
    });
  });
};
