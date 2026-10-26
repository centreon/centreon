import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelCheck,
  labelClear,
  labelDisabled,
  labelDiscovery,
  labelEnabled,
  labelLockedElements,
  labelMiscellaneous,
  labelName,
  labelNotification,
  labelSearch,
  labelStatus,
  labelType
} from '../translatedLabels';
import initialize from './initialize';

const filterCheckboxes = [
  labelNotification,
  labelCheck,
  labelMiscellaneous,
  labelDiscovery,
  labelEnabled,
  labelDisabled,
  labelLockedElements
];

const getSearchBar = (): HTMLInputElement =>
  screen.getByPlaceholderText(labelSearch) as HTMLInputElement;

// The text field puts the test id on its wrapper and on the input.
const getNameFilter = (): HTMLInputElement =>
  document.querySelector(
    `input[data-testid="${labelName}"]`
  ) as HTMLInputElement;

const getCheckbox = (name: string): HTMLInputElement =>
  document.querySelector(`input[name="${name}"]`) as HTMLInputElement;

const getClearButton = (): HTMLElement =>
  document.querySelector(
    `button[data-testid="${labelClear}"]`
  ) as HTMLButtonElement;

// The count of changed filters, next to the filters icon.
const getChangedFiltersBadge = (): HTMLElement =>
  screen
    .getByTestId('Filters')
    .closest('.MuiBadge-root')
    ?.querySelector('.MuiBadge-badge') as HTMLElement;

// The URL of the last listing request: the filters change several times,
// each change sending its own request.
const getLastListingUrl = (): string =>
  getRequestCalls('@getCommands').at(-1)?.request.url.href ?? '';

const waitForFilteredListing = async (): Promise<void> => {
  await waitFor(() => {
    expect(getLastListingUrl()).toContain('name[lk]=abc');
    expect(getLastListingUrl()).toContain(
      'type[]=Notification&type[]=Discovery'
    );
    expect(getLastListingUrl()).toContain('is_activated=true');
  });
};

const openAdvancedFilters = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId('Filters'));

  const panel = await screen.findByTestId('advanced-filters');

  await waitFor(() => expect(panel).toBeVisible());

  return panel;
};

// The listing is displayed: the page has applied the first answer.
const initializeAndWaitForListing = async (): Promise<void> => {
  initialize();

  await waitForRequest('@getCommands');

  await screen.findByText('check_host_alive');
};

export default (): void => {
  describe('Filters', () => {
    it('displays the search bar component', async () => {
      await initializeAndWaitForListing();

      // The field puts the test id on its wrapper and on the input.
      const searchBar = await screen.findAllByTestId('search-bar');

      expect(searchBar).toHaveLength(2);
      searchBar.forEach((element) => {
        expect(element).toBeVisible();
      });

      // An empty search bar, the advanced filters closed.
      expect(getSearchBar()).toHaveValue('');
      expect(screen.getByTestId('Filters')).toBeVisible();
      expect(screen.queryByTestId('advanced-filters')).not.toBeInTheDocument();
    });
    it('displays the advanced filters component when the corresponding icon is clicked', async () => {
      await initializeAndWaitForListing();

      const panel = await openAdvancedFilters();

      expect(getNameFilter()).toBeVisible();
      expect(within(panel).getByText(labelStatus)).toBeVisible();
      expect(within(panel).getByText(labelType)).toBeVisible();

      expect(
        document.querySelector(`button[data-testid="${labelSearch}"]`)
      ).toBeVisible();
      expect(getClearButton()).toBeVisible();

      // Every type and status unchecked, nothing to clear.
      filterCheckboxes.forEach((label) => {
        // `Check ` ends with a space the text matcher trims.
        expect(within(panel).getByText(label.trim())).toBeVisible();
      });
      expect(within(panel).getAllByRole('checkbox')).toHaveLength(
        filterCheckboxes.length
      );
      within(panel)
        .getAllByRole('checkbox')
        .forEach((checkbox) => {
          expect(checkbox).not.toBeChecked();
        });
      expect(getNameFilter()).toHaveValue('');
      expect(getClearButton()).toBeDisabled();
      expect(
        document.querySelector(`button[data-testid="${labelSearch}"]`)
      ).toBeEnabled();
    });
    it('updates the name filter with the value from the search bar', async () => {
      await initializeAndWaitForListing();

      await userEvent.clear(getSearchBar());
      await userEvent.type(getSearchBar(), 'abc');

      await openAdvancedFilters();

      await waitFor(() => expect(getNameFilter()).toHaveValue('abc'));
    });
    it('updates the search bar with the value from the filters', async () => {
      await initializeAndWaitForListing();

      await userEvent.clear(getSearchBar());
      await openAdvancedFilters();

      await userEvent.type(getNameFilter(), 'abc');

      await waitFor(() => expect(getSearchBar()).toHaveValue('abc'));

      // One changed filter, which can now be cleared.
      await waitFor(() =>
        expect(getChangedFiltersBadge()).toHaveTextContent(/^1$/)
      );
      expect(getChangedFiltersBadge()).not.toHaveClass('MuiBadge-invisible');
      expect(getClearButton()).toBeEnabled();
    });
    it('sends a listing request with the search bar content when after a delay', async () => {
      await initializeAndWaitForListing();

      await userEvent.clear(getSearchBar());
      await userEvent.type(getSearchBar(), 'abc');

      // The search is debounced: the next listing request carries the whole
      // text.
      const { request } = await waitForRequest('@getCommands');

      expect(request.url.href).toContain('name[lk]=abc');
    });
    it('sends a listing request with selected filters when the search button is clicked', async () => {
      await initializeAndWaitForListing();

      await userEvent.clear(getSearchBar());

      const panel = await openAdvancedFilters();

      await userEvent.type(getNameFilter(), 'abc');

      await userEvent.click(within(panel).getByText(labelNotification));
      await userEvent.click(within(panel).getByText(labelDiscovery));

      await userEvent.click(within(panel).getByText(labelEnabled));

      await userEvent.click(
        document.querySelector(
          `button[data-testid="${labelSearch}"]`
        ) as HTMLButtonElement
      );

      await waitForRequest('@getCommands');

      // The name typed in the advanced filters sends no request of its own:
      // the search button's request carries every filter.
      await waitForFilteredListing();
    });

    it('clears filters and the search bar, and sends a listing request with empty search parameter when the clear button is clicked', async () => {
      await initializeAndWaitForListing();

      const panel = await openAdvancedFilters();

      await userEvent.type(getNameFilter(), 'abc');

      await userEvent.click(within(panel).getByText(labelNotification));
      await userEvent.click(within(panel).getByText(labelDiscovery));

      await userEvent.click(within(panel).getByText(labelEnabled));

      await userEvent.click(
        document.querySelector(
          `button[data-testid="${labelSearch}"]`
        ) as HTMLButtonElement
      );

      await waitForFilteredListing();

      await userEvent.click(getClearButton());

      await waitFor(() => expect(getSearchBar()).toHaveValue(''));
      await waitFor(() => expect(getNameFilter()).toHaveValue(''));
      await waitFor(() =>
        expect(getCheckbox(labelNotification)).not.toBeChecked()
      );
      expect(getCheckbox(labelDiscovery)).not.toBeChecked();
      expect(getCheckbox(labelEnabled)).not.toBeChecked();

      await waitForRequest('@getCommands');

      // The last request is the one the clear action sent, the filtered one
      // having been waited for above.
      await waitFor(() => {
        expect(getLastListingUrl()).not.toContain('name[lk]=abc');
        expect(getLastListingUrl()).not.toContain(
          'type[]=Notification&type[]=Discovery'
        );
        expect(getLastListingUrl()).not.toContain('is_activated=true');
      });

      // Nothing left to clear.
      await waitFor(() => expect(getClearButton()).toBeDisabled());
    });
  });
};
