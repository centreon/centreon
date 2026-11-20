import type { ListingParameters } from '@centreon/ui';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createStore, Provider } from 'jotai';

import { buildNotificationsEndpoint } from '../Listing/api/endpoints';
import {
  defaultQueryParams,
  getListingResponse
} from '../Listing/Tests/testUtils';
import useLoadingNotifications from '../Listing/useLoadNotifications';
import { labelSearch } from '../translatedLabels';
import Filter from '.';

// The endpoint the page builds for these parameters; the fixture leaves out
// what the page does not set.
const getListingEndpoint = (parameters: object): string =>
  buildNotificationsEndpoint(parameters as ListingParameters);

const searchValue = 'foobar';

const expectedSearch = {
  $or: [{ name: { $rg: searchValue } }]
};

const FilterTest = (): JSX.Element => {
  useLoadingNotifications();

  return <Filter />;
};

const initialize = (): void => {
  const defaultendpoint = getListingEndpoint(defaultQueryParams);

  interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: defaultendpoint,
    response: getListingResponse({})
  });

  const search = {
    regex: {
      fields: ['name'],
      value: searchValue
    }
  };

  const requestEndpointSearch = getListingEndpoint({
    ...defaultQueryParams,
    search
  });

  // Both requests share one path: only the one searching the whole typed
  // value is answered by this mock, the others by the default one.
  interceptAPIRequest({
    alias: 'requestWithSearchQuery',
    method: Method.GET,
    path: requestEndpointSearch,
    query: { name: 'search', value: JSON.stringify(expectedSearch) },
    response: getListingResponse({})
  });

  // A store and a client of their own, so no search value or cache leaks
  // from one test to the next.
  const store = createStore();
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  render(
    <Provider store={store}>
      <QueryClientProvider client={queryClient}>
        <FilterTest />
      </QueryClientProvider>
    </Provider>
  );
};

describe('Filter', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize();
  });

  it('executes a listing request with search param when a search value is typed in the search field', async () => {
    await waitForRequest('@defaultRequest');

    const searchField = screen.getByPlaceholderText(labelSearch);

    await userEvent.clear(searchField);
    await userEvent.type(searchField, searchValue);

    const { request } = await waitForRequest('@requestWithSearchQuery');

    expect(request.url.searchParams.get('search')).toBe(
      JSON.stringify(expectedSearch)
    );

    expect(searchField).toHaveValue(searchValue);
  });
});
