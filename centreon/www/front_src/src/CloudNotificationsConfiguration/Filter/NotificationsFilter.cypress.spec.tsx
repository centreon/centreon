import { Method, TestQueryProvider } from '@centreon/ui';

import {
  defaultQueryParams,
  getListingResponse
} from '../Listing/Tests/testUtils';
import { buildNotificationsEndpoint } from '../Listing/api/endpoints';
import useLoadingNotifications from '../Listing/useLoadNotifications';
import { labelSearch } from '../translatedLabels';

import Filter from '.';

const FilterTest = (): JSX.Element => {
  useLoadingNotifications();

  return <Filter />;
};

const FilterWithQueryProvider = (): JSX.Element => {
  return (
    <TestQueryProvider>
      <FilterTest />
    </TestQueryProvider>
  );
};

const initialize = (): void => {
  const defaultendpoint = buildNotificationsEndpoint(defaultQueryParams);

  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: defaultendpoint,
    response: getListingResponse({})
  });

  const searchValue = 'foobar';

  const search = {
    regex: {
      fields: ['name'],
      value: searchValue
    }
  };

  const requestEndpointSearch = buildNotificationsEndpoint({
    ...defaultQueryParams,
    search
  });

  cy.interceptAPIRequest({
    alias: 'requestWithSearchQuery',
    method: Method.GET,
    path: requestEndpointSearch,
    response: getListingResponse({})
  });

  cy.render(FilterWithQueryProvider);
};

describe('Filter', () => {
  beforeEach(initialize);

  it('executes a listing request with search param when a search value is typed in the search field', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByPlaceholderText(labelSearch).clear().type('foobar');

    cy.waitForRequest('@requestWithSearchQuery');

    cy.waitForRequestAndVerifyQueries({
      queries: [
        {
          key: 'search',
          value: {
            $or: [{ name: { $rg: 'foobar' } }]
          }
        }
      ],
      requestAlias: 'requestWithSearchQuery'
    });

    cy.makeSnapshot();
  });
});
