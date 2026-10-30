import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelClear,
  labelFilters,
  labelSearch
} from '../../ConfigurationBase/translatedLabels';
import { labelLockedElements, labelName } from '../translatedLabels';
import initialize from './initialize';

const openAdvancedFilters = async (): Promise<HTMLElement> => {
  await userEvent.click(await screen.findByTestId(labelFilters));

  const panel = await screen.findByTestId('advanced-filters');

  await waitFor(() => expect(panel).toBeVisible());

  return panel;
};

const initializeWithAdvancedFilters = async (): Promise<HTMLElement> => {
  initialize();

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  return openAdvancedFilters();
};

// The checkbox carries the label as its test id.
const getLockedElements = (panel: HTMLElement): HTMLInputElement =>
  within(within(panel).getByTestId(labelLockedElements)).getByRole(
    'checkbox'
  ) as HTMLInputElement;

const search = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId(labelSearch));
};

export default () => {
  describe('Filters: ', () => {
    it('offers the name and locked elements filters, the latter unchecked', async () => {
      const panel = await initializeWithAdvancedFilters();

      expect(within(panel).getAllByText(labelName)[0]).toBeVisible();
      expect(within(panel).getByText(labelLockedElements)).toBeVisible();
      expect(getLockedElements(panel)).not.toBeChecked();
    });

    it('sends the searched name as `name[lk]`', async () => {
      initialize();

      await waitForRequest('@getAllHostTemplates');
      await screen.findByText('generic-active-host-custom');

      await userEvent.type(screen.getByPlaceholderText(labelSearch), 'linux');

      // Sent once the typing settles.
      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.searchParams.get('name[lk]')).toBe('linux');
    });

    it('sends `is_locked=true` only while locked elements is checked', async () => {
      const panel = await initializeWithAdvancedFilters();

      await userEvent.click(getLockedElements(panel));
      await search();

      const { request: checked } = await waitForRequest('@getAllHostTemplates');

      expect(checked.url.searchParams.get('is_locked')).toBe('true');

      await userEvent.click(getLockedElements(panel));
      await search();

      const { request: unchecked } = await waitForRequest(
        '@getAllHostTemplates'
      );

      // Absent, never `false`.
      expect(unchecked.url.searchParams.has('is_locked')).toBe(false);
    });

    it('drops the locked elements filter with the clear action', async () => {
      const panel = await initializeWithAdvancedFilters();

      await userEvent.click(getLockedElements(panel));
      await search();

      await waitForRequest('@getAllHostTemplates');

      await userEvent.click(screen.getByTestId(labelClear));

      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.searchParams.has('is_locked')).toBe(false);
      expect(getLockedElements(panel)).not.toBeChecked();
    });

    it('keeps the filters in local storage so they survive navigation', async () => {
      const panel = await initializeWithAdvancedFilters();

      await userEvent.click(getLockedElements(panel));
      await search();

      await waitForRequest('@getAllHostTemplates');

      await waitFor(() => {
        const stored = JSON.parse(
          window.localStorage.getItem('filters_host_templates') as string
        );

        expect(stored.is_locked).toBe(true);
      });
    });
  });
};
