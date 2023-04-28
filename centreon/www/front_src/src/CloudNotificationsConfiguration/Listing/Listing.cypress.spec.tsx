import { equals } from 'ramda';

import { TestQueryProvider, Method } from '@centreon/ui';

import { buildNotificationsEndpoint } from './api/endpoints';
import useLoadingNotifications from './useLoadNotifications';
import {
  defaultQueryParams,
  getListingColumns,
  listingResponse as response
} from './testUtils';

import Listing from '.';

const ListingTest = (): JSX.Element => {
  useLoadingNotifications();

  return <Listing />;
};
const ListingWithQueryProvider = (): JSX.Element => {
  return (
    <TestQueryProvider>
      <ListingTest />
    </TestQueryProvider>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint(defaultQueryParams),
    response
  });

  cy.interceptAPIRequest({
    alias: 'secondePageRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint({
      ...defaultQueryParams,
      page: 2
    }),
    query: { name: 'page', value: '2' },
    response
  });

  cy.interceptAPIRequest({
    alias: 'lastPageRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint({
      ...defaultQueryParams,
      page: 6
    }),
    query: { name: 'page', value: '6' },
    response
  });

  cy.interceptAPIRequest({
    alias: 'ListingWithLimit',
    method: Method.GET,
    path: buildNotificationsEndpoint({
      ...defaultQueryParams,
      limit: 20
    }),
    query: { name: 'limit', value: '20' },
    response
  });

  cy.render(ListingWithQueryProvider);
};

const columnToSort = getListingColumns()
  .filter(({ sortable }) => equals(sortable, true))
  .filter(({ id }) => id !== 'name');

const initializeSorting = (): void => {
  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint(defaultQueryParams),
    response
  });

  columnToSort.forEach(({ id, label, sortField }) => {
    const sortBy = (sortField || id) as string;

    const requestEndpointDesc = buildNotificationsEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'desc'
      }
    });

    cy.interceptAPIRequest({
      alias: `dataToListingTableDesc${label}`,
      method: Method.GET,
      path: requestEndpointDesc,
      response
    });

    const requestEndpointAsc = buildNotificationsEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'asc'
      }
    });

    cy.interceptAPIRequest({
      alias: `dataToListingTableAsc${label}`,
      method: Method.GET,
      path: requestEndpointAsc,
      response
    });
  });

  cy.render(ListingWithQueryProvider);
};

describe('Notifications Listing', () => {
  beforeEach(initialize);

  it('displays the first page of the notifications listing', () => {
    cy.waitForRequest('@defaultRequest');

    cy.contains('notification0').should('be.visible');

    cy.matchImageSnapshot();
  });

  it('executes a get notifications request after updating limit param', () => {
    cy.waitForRequest('@defaultRequest');

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^20$/).click();

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: '20' }],
      requestAlias: 'ListingWithLimit'
    });

    cy.matchImageSnapshot();
  });

  it('executes a listing request with an updated page param when a change page action is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByLabelText('Next page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '2' }],
      requestAlias: 'secondePageRequest'
    });

    cy.findByLabelText('Previous page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    cy.findByLabelText('Last page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '6' }],
      requestAlias: 'lastPageRequest'
    });

    cy.findByLabelText('First page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    cy.matchImageSnapshot();
  });
});

describe('column sorting', () => {
  beforeEach(initializeSorting);

  it('executes a listing request with sort_by param when a sortable column is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    columnToSort.forEach(({ label, id, sortField }) => {
      const sortBy = (sortField || id) as string;

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'desc' } }],
        requestAlias: `dataToListingTableDesc${label}`
      });

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'asc' } }],
        requestAlias: `dataToListingTableAsc${label}`
      });

      cy.matchImageSnapshot(
        `column sorting --  executes a listing request with sorty_by param when the ${label} column is clicked`
      );
    });
  });
});
