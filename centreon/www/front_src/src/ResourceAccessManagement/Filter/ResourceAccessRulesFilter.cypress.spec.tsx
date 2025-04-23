import { Method, TestQueryProvider } from '@centreon/ui';

import {
  defaultQueryParams,
  getListingResponse
} from '../Listing/Tests/testUtils';
import { buildResourceAccessRulesEndpoint } from '../Listing/api/endpoints';
import useListing from '../Listing/useListing';
import { labelSearch } from '../translatedLabels';

import Filter from '.';

const FilterTest = (): JSX.Element => {
  useListing();

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
  const defaultEndpoint = buildResourceAccessRulesEndpoint(defaultQueryParams);
  const searchValue = 'foobar';
  const search = {
    regex: {
      fields: ['name', 'description'],
      value: searchValue
    }
  };
  const requestEndpointSearch = buildResourceAccessRulesEndpoint({
    ...defaultQueryParams,
    search
  });

  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: defaultEndpoint,
    response: getListingResponse({})
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

  it('executes a listing request with search parameter when a search value is typed in the search field', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByPlaceholderText(labelSearch).clear().type('foobar');

    cy.waitForRequest('@requestWithSearchQuery');

    cy.waitForRequestAndVerifyQueries({
      queries: [
        {
          key: 'search',
          value: {
            $or: [
              { name: { $rg: 'foobar' } },
              { description: { $rg: 'foobar' } }
            ]
          }
        }
      ],
      requestAlias: 'requestWithSearchQuery'
    });

    cy.makeSnapshot();
  });
});
