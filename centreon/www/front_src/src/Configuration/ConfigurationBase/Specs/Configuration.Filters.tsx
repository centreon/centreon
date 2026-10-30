import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import type { ResourceType } from '../../models';
import { defaultFiltersPanelWidth } from '../Filters/Filters.styles';
import {
  labelAlias,
  labelClear,
  labelDisabled,
  labelFilters,
  labelName,
  labelSearch
} from '../translatedLabels';
import initialize, { toResource } from './initialize';
import {
  filtersConfiguration,
  filtersConfigurationWithSingleConnectedAutocomplete,
  filtersInitialValuesWithSingleConnectedAutocomplete,
  labelHostTemplate
} from './utils';

const initializeWithSingleConnectedAutocomplete = (
  resourceType: ResourceType
): ReturnType<typeof initialize> =>
  initialize({
    filters: filtersConfigurationWithSingleConnectedAutocomplete,
    initialValues: filtersInitialValuesWithSingleConnectedAutocomplete,
    resourceType
  });

const initializeListing = async (
  props: Parameters<typeof initialize>[0]
): Promise<ReturnType<typeof initialize>> => {
  const rendered = initialize(props);

  await waitForRequest('@getAll');
  await screen.findByText(`${toResource(props?.resourceType ?? '')} 0`);

  return rendered;
};

const openAdvancedFilters = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId(labelFilters));

  const advancedFilters = await screen.findByTestId('advanced-filters');

  expect(advancedFilters).toBeVisible();

  return advancedFilters;
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getTextFilter = (label: string): HTMLElement =>
  within(screen.getByTestId('advanced-filters')).getAllByTestId(label)[1];

const getHostTemplateInput = (): HTMLElement =>
  screen.getByTestId(labelHostTemplate);

const selectHostTemplate = async (name: string): Promise<void> => {
  await userEvent.click(getHostTemplateInput());
  await waitForRequest('@getHostTemplates');
  await userEvent.click(await screen.findByText(name));
};

// The count of applied filters on the advanced filters icon. MUI keeps the
// badge in the page when the count is 0, hidden by a class.
const getFiltersBadge = (): HTMLElement =>
  screen
    .getByTestId(labelFilters)
    .closest('.MuiBadge-root')
    ?.querySelector('.MuiBadge-badge') as HTMLElement;

const expectFiltersBadge = (count: number): void => {
  expect(getFiltersBadge()).toHaveTextContent(new RegExp(`^${count}$`));
  expect(getFiltersBadge()).not.toHaveClass('MuiBadge-invisible');
};

const expectNoFiltersBadge = (): void => {
  expect(getFiltersBadge()).toHaveClass('MuiBadge-invisible');
};

const getSearchParameter = (url: URL): unknown =>
  JSON.parse(url.searchParams.get('search') ?? 'null');

export default (resourceType: ResourceType) => {
  describe('Filters', () => {
    it('sends a listing request with the name filter when the search bar is manually updated, debounced by 500ms', async () => {
      await initializeListing({ resourceType });

      const searchBar = screen.getAllByPlaceholderText(labelSearch)[0];

      await userEvent.clear(searchBar);
      await userEvent.type(searchBar, `${resourceType} 1`);

      // Debounced: the request is sent once typing stopped.
      const { request } = await waitForRequest('@getAll');

      expect(getSearchParameter(request.url)).toEqual({
        $and: [{ $or: [{ name: { $rg: `${resourceType} 1` } }] }]
      });
      // One request for the whole typed name, after the initial listing.
      expect(getRequestCalls('@getAll')).toHaveLength(2);

      // The typed name counts as one filter.
      await waitFor(() => expectFiltersBadge(1));
      expect(searchBar).toHaveValue(`${resourceType} 1`);
    });

    it('sends a listing request when fields are filled and the search button is clicked', async () => {
      await initializeListing({ resourceType });

      await openAdvancedFilters();

      await userEvent.clear(getTextFilter(labelName));
      await userEvent.type(getTextFilter(labelName), `${resourceType} 1`);
      await userEvent.clear(getTextFilter(labelAlias));
      await userEvent.type(
        getTextFilter(labelAlias),
        `${resourceType} alias 1`
      );

      await userEvent.click(screen.getByTestId(labelDisabled));

      await userEvent.click(screen.getByTestId(labelSearch));

      const { request } = await waitForRequest('@getAll');

      expect(getSearchParameter(request.url)).toEqual({
        $and: [
          { $or: [{ is_activated: { $eq: false } }] },
          { $or: [{ name: { $rg: `${resourceType} 1` } }] },
          { $or: [{ alias: { $rg: `${resourceType} alias 1` } }] }
        ]
      });

      // Three filters applied, the name mirrored in the search bar, and
      // something to clear.
      await waitFor(() => expectFiltersBadge(3));
      expect(screen.getAllByPlaceholderText(labelSearch)[0]).toHaveValue(
        `${resourceType} 1`
      );
      expect(screen.getByTestId(labelClear)).toBeEnabled();
    });

    it('clears all applied filters and sends a listing request with empty search parameters when the clear button is clicked', async () => {
      await initializeListing({ resourceType });

      await openAdvancedFilters();

      await userEvent.clear(getTextFilter(labelName));
      await userEvent.type(getTextFilter(labelName), `${resourceType} 1`);

      await userEvent.clear(getTextFilter(labelAlias));
      await userEvent.type(
        getTextFilter(labelAlias),
        `${resourceType} alias 1`
      );

      await userEvent.click(screen.getByTestId(labelDisabled));

      await userEvent.click(screen.getByTestId(labelClear));

      const { request } = await waitForRequest('@getAll');

      expect(getSearchParameter(request.url)).toEqual({ $and: [] });

      // Every field emptied, nothing left to clear.
      await waitFor(() => expect(getTextFilter(labelName)).toHaveValue(''));
      expect(getTextFilter(labelAlias)).toHaveValue('');
      expect(
        within(screen.getByTestId('advanced-filters')).getByLabelText(
          labelDisabled
        )
      ).not.toBeChecked();
      expect(screen.getAllByPlaceholderText(labelSearch)[0]).toHaveValue('');
      expect(screen.getByTestId(labelClear)).toBeDisabled();
      expectNoFiltersBadge();
    });

    describe('Single connected autocomplete', () => {
      it('sends a listing request matching the selected id when a value is selected in the single connected autocomplete filter', async () => {
        initializeWithSingleConnectedAutocomplete(resourceType);

        await waitForRequest('@getAll');

        await openAdvancedFilters();

        await selectHostTemplate('host template 1');

        await userEvent.click(screen.getByTestId(labelSearch));

        const { request } = await waitForRequest('@getAll');

        expect(getSearchParameter(request.url)).toEqual({
          $and: [{ $or: [{ 'host_template.id': { $eq: 1 } }] }]
        });

        // The template counts as one filter.
        await waitFor(() => expectFiltersBadge(1));
        expect(getHostTemplateInput()).toHaveValue('host template 1');
        expect(screen.getByTestId(labelClear)).toBeEnabled();
      });

      it('removes the filter from the search parameters when the selected value is cleared', async () => {
        initializeWithSingleConnectedAutocomplete(resourceType);

        await waitForRequest('@getAll');

        await openAdvancedFilters();

        await selectHostTemplate('host template 1');

        const clearButton = getHostTemplateInput()
          .closest('.MuiAutocomplete-root')
          ?.querySelector('button[title="Clear"]') as HTMLElement;

        // Shown on hover only.
        await userEvent.click(clearButton, { pointerEventsCheck: 0 });

        await waitFor(() => expect(getHostTemplateInput()).toHaveValue(''));

        await userEvent.click(screen.getByTestId(labelSearch));

        const { request } = await waitForRequest('@getAll');

        expect(getSearchParameter(request.url)).toEqual({ $and: [] });

        // No filter left, nothing to clear.
        await waitFor(() =>
          expect(screen.getByTestId(labelClear)).toBeDisabled()
        );
        expectNoFiltersBadge();
      });

      it('keeps the selected value when the listing is mounted again', async () => {
        const { unmount } =
          initializeWithSingleConnectedAutocomplete(resourceType);

        await waitForRequest('@getAll');

        await openAdvancedFilters();

        await selectHostTemplate('host template 1');

        await waitFor(() =>
          expect(getHostTemplateInput()).toHaveValue('host template 1')
        );

        // Mounted again, as a user coming back to the page.
        unmount();

        initializeWithSingleConnectedAutocomplete(resourceType);

        await waitForRequest('@getAll');

        await openAdvancedFilters();

        await waitFor(() =>
          expect(getHostTemplateInput()).toHaveValue('host template 1')
        );

        // The kept template counts as a filter.
        expectFiltersBadge(1);
      });
    });

    describe('Filters panel width', () => {
      const spacingUnit = 8;
      const customWidth = 60;

      it('renders the advanced filters panel with the default width when no width is declared', async () => {
        await initializeListing({ resourceType });

        const advancedFilters = await openAdvancedFilters();

        expect(getComputedStyle(advancedFilters).width).toBe(
          `${defaultFiltersPanelWidth * spacingUnit}px`
        );
      });

      it('renders the advanced filters panel with the declared width when a width is declared', async () => {
        await initializeListing({
          filtersPanelWidth: customWidth,
          resourceType
        });

        const advancedFilters = await openAdvancedFilters();

        expect(getComputedStyle(advancedFilters).width).toBe(
          `${customWidth * spacingUnit}px`
        );
      });
    });

    it('hides the advanced filters icon when only the name field is filterable', async () => {
      const onlyNameFilter = filtersConfiguration.filter(
        ({ fieldName }) => fieldName === 'name'
      );

      await initializeListing({
        filters: onlyNameFilter,
        resourceType
      });

      // Checked once the listing is shown, so not before the page settled.
      expect(screen.queryByTestId(labelFilters)).not.toBeInTheDocument();

      // The search bar itself stays.
      expect(screen.getAllByTestId('search-bar')[0]).toBeVisible();
    });
  });
};
