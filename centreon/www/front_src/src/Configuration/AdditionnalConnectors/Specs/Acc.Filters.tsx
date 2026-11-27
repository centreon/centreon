import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelClear,
  labelName,
  labelPoller,
  labelSearch,
  labelType
} from '../translatedLabels';
import initialize from './initialize';

const getSearchBar = async (): Promise<HTMLElement> =>
  (await screen.findAllByPlaceholderText(labelSearch))[0];

const getNameFilter = (): HTMLElement =>
  document.querySelector(`input[data-testid="${labelName}"]`) as HTMLElement;

const getClearButton = (): HTMLElement =>
  document.querySelector(`button[data-testid="${labelClear}"]`) as HTMLElement;

// The number of changed filters, on the icon that opens them.
const getFiltersBadge = (): HTMLElement =>
  screen
    .getByTestId('Filters')
    .closest('.MuiBadge-root')
    ?.querySelector('.MuiBadge-badge') as HTMLElement;

const openAdvancedFilters = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('Filters'));

  expect(await screen.findByTestId('advanced-filters')).toBeVisible();
};

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

const selectOptions = async (
  label: string,
  options: Array<string>
): Promise<void> => {
  await userEvent.click(screen.getByTestId(label));

  for (const option of options) {
    await waitFor(() => expect(getPopper()).not.toBeNull());
    await userEvent.click(await within(getPopper()).findByText(option));
  }

  await userEvent.click(screen.getByTestId(label));
};

const fillAllFilters = async (): Promise<void> => {
  await userEvent.type(getNameFilter(), 'vmware1');

  await selectOptions(labelType, ['VMWare 6/7']);
  await selectOptions(labelPoller, ['poller1', 'poller2']);
};

const getSearchParameter = (url: URL): unknown =>
  JSON.parse(url.searchParams.get('search') as string);

export default (): void => {
  describe('Filters', () => {
    beforeEach(() => {
      initialize();
    });
    it('displays the search bar component', async () => {
      await waitForRequest('@getConnectors');

      // The wrapper and the input both carry the test id.
      const searchBarElements = await screen.findAllByTestId('search-bar');

      expect(searchBarElements).toHaveLength(2);
      searchBarElements.forEach((element) => {
        expect(element).toBeVisible();
      });

      // An empty search bar, no filter counted.
      expect(await getSearchBar()).toHaveValue('');
      expect(getFiltersBadge()).toHaveClass('MuiBadge-invisible');
    });
    it('displays the advanced filters component when the corresponding icon is clicked', async () => {
      await waitForRequest('@getConnectors');

      await userEvent.click(await screen.findByTestId('Filters'));

      expect(await screen.findByTestId('advanced-filters')).toBeVisible();

      expect(getNameFilter()).toBeVisible();
      expect(screen.getByTestId(labelPoller)).toBeVisible();
      expect(screen.getByTestId(labelType)).toBeVisible();

      expect(
        document.querySelector(`button[data-testid="${labelSearch}"]`)
      ).toBeVisible();
      expect(getClearButton()).toBeVisible();

      // The filters are empty, so there is nothing to clear.
      expect(getNameFilter()).toHaveValue('');
      expect(getClearButton()).toBeDisabled();
    });
    it('updates the name filter with the value from the search bar', async () => {
      await waitForRequest('@getConnectors');

      const searchBar = await getSearchBar();

      await userEvent.clear(searchBar);
      await userEvent.type(searchBar, 'vmware1');

      await userEvent.click(screen.getByTestId('Filters'));

      await waitFor(() => expect(getNameFilter()).toHaveValue('vmware1'));
    });
    it('updates the search bar with the value from the filters', async () => {
      await waitForRequest('@getConnectors');

      const searchBar = await getSearchBar();

      await userEvent.clear(searchBar);
      await openAdvancedFilters();

      await userEvent.type(getNameFilter(), 'vmware1');

      await waitFor(() => expect(searchBar).toHaveValue('vmware1'));

      // One changed filter, which can be cleared.
      expect(getFiltersBadge()).toHaveTextContent(/^1$/);
      expect(getFiltersBadge()).not.toHaveClass('MuiBadge-invisible');
      expect(getClearButton()).toBeEnabled();
    });
    it('sends a listing request with the search bar content when after a delay', async () => {
      await waitForRequest('@getConnectors');

      const searchBar = await getSearchBar();

      await userEvent.clear(searchBar);
      await userEvent.type(searchBar, 'vmware1');

      const { request } = await waitForRequest('@getConnectors');

      expect(getSearchParameter(request.url)).toEqual({
        $and: [{ $or: [{ name: { $rg: 'vmware1' } }] }]
      });
    });
    it('sends a listing request with selected filters when the search button is clicked', async () => {
      await waitForRequest('@getConnectors');

      await userEvent.clear(await getSearchBar());

      await openAdvancedFilters();
      await fillAllFilters();

      await userEvent.click(screen.getByTestId(labelSearch));

      const { request } = await waitForRequest('@getConnectors');

      expect(getSearchParameter(request.url)).toEqual({
        $and: [
          { $or: [{ name: { $rg: 'vmware1' } }] },
          { $or: [{ type: { $in: ['vmware_v6'] } }] },
          {
            $or: [{ 'poller.id': { $in: [1, 2] } }]
          }
        ]
      });
    });

    it('clears filters and the search bar, and sends a listing request with empty search parameter when the clear button is clicked', async () => {
      await waitForRequest('@getConnectors');

      await openAdvancedFilters();
      await fillAllFilters();

      const typeField = screen.getByTestId(labelType)
        .parentElement as HTMLElement;
      const pollerField = screen.getByTestId(labelPoller)
        .parentElement as HTMLElement;

      // The chips the clear button must remove.
      expect(within(typeField).getByText('VMWare 6/7')).toBeVisible();
      expect(within(pollerField).getByText('poller1')).toBeVisible();

      await userEvent.click(getClearButton());

      await waitFor(async () => expect(await getSearchBar()).toHaveValue(''));

      await waitFor(() => expect(getNameFilter()).toHaveValue(''));

      expect(
        within(typeField).queryByText('VMWare 6/7')
      ).not.toBeInTheDocument();

      expect(
        within(pollerField).queryByText('poller1')
      ).not.toBeInTheDocument();
      expect(
        within(pollerField).queryByText('poller2')
      ).not.toBeInTheDocument();

      const { request } = await waitForRequest('@getConnectors');

      expect(getSearchParameter(request.url)).toEqual({ $and: [] });

      // Nothing left to clear.
      await waitFor(() => expect(getClearButton()).toBeDisabled());
      expect(getFiltersBadge()).toHaveClass('MuiBadge-invisible');
    });
  });
};
