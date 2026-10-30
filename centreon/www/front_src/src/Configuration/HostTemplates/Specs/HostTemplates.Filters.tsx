import { getRequestCalls, waitForRequest } from '@test-setup/api';
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

    // Tolerances: the popper sits on whole pixels, and the toolbar beside the
    // field may still widen by about a pixel after the first paint, which an
    // open popper does not follow. The default panel is 336px, the field 480.
    it('opens the filters panel as wide as the search field, lined up with it', async () => {
      const panel = await initializeWithAdvancedFilters();

      // The outlined box the user sees, not the bare input inside it.
      const field = (
        screen
          .getByPlaceholderText(labelSearch)
          .closest('.MuiInputBase-root') as HTMLElement
      ).getBoundingClientRect();
      const { left, right, width } = panel.getBoundingClientRect();

      expect(Math.abs(width - field.width)).toBeLessThanOrEqual(1);
      expect(Math.abs(left - field.left)).toBeLessThanOrEqual(2);
      expect(Math.abs(right - field.right)).toBeLessThanOrEqual(2);
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

    // The read half of the persistence: what was stored is what the page opens
    // with, and what its very first request carries.
    it('opens with the filters kept in local storage, and requests with them', async () => {
      window.localStorage.setItem(
        'filters_host_templates',
        JSON.stringify({ is_locked: true, name: 'linux' })
      );

      initialize();

      await waitForRequest('@getAllHostTemplates');

      const [{ request: first }] = getRequestCalls('@getAllHostTemplates');

      expect(first.url.searchParams.get('name[lk]')).toBe('linux');
      expect(first.url.searchParams.get('is_locked')).toBe('true');

      expect(await screen.findByPlaceholderText(labelSearch)).toHaveValue(
        'linux'
      );
      expect(getLockedElements(await openAdvancedFilters())).toBeChecked();
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
