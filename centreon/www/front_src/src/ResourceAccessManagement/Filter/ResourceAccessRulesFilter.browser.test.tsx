import type { ListingParameters } from '@centreon/ui';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { buildResourceAccessRulesEndpoint } from '../Listing/api/endpoints';
import {
  defaultQueryParams,
  getListingResponse
} from '../Listing/Tests/testUtils';
import useListing from '../Listing/useListing';
import { labelSearch } from '../translatedLabels';
import Filter from '.';

// The fixture parameters, as the listing builds them.
const listingEndpoint = (parameters: object): string =>
  buildResourceAccessRulesEndpoint(parameters as ListingParameters);

const searchValue = 'foobar';

const search = {
  regex: {
    fields: ['name', 'description'],
    value: searchValue
  }
};

const expectedSearchParameter = {
  $or: [{ name: { $rg: searchValue } }, { description: { $rg: searchValue } }]
};

const FilterTest = (): JSX.Element => {
  useListing();

  return <Filter />;
};

const initialize = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: listingEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });

  // Answers only the listing searched for the whole value, the default mock
  // answering the others.
  interceptAPIRequest({
    alias: 'requestWithSearchQuery',
    method: Method.GET,
    path: listingEndpoint({
      ...defaultQueryParams,
      search
    }),
    query: { name: 'search', value: JSON.stringify(expectedSearchParameter) },
    response: getListingResponse({})
  });

  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  render(
    <Provider store={createStore()}>
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

  it('executes a listing request with search parameter when a search value is typed in the search field', async () => {
    await waitForRequest('@defaultRequest');

    const searchField = screen.getByPlaceholderText(labelSearch);

    await userEvent.clear(searchField);
    await userEvent.type(searchField, searchValue);

    const { request } = await waitForRequest('@requestWithSearchQuery');

    // The mock answers only the expected search, so a wrong one times out.
    expect(request.url.searchParams.get('search')).toBe(
      JSON.stringify(expectedSearchParameter)
    );
    expect(searchField).toHaveValue(searchValue);
  });
});
