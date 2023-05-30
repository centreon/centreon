import { TestQueryProvider, Method } from '@centreon/ui';

import { buildNotificationsEndpoint } from '../Listing/api/endpoints';
import { defaultQueryParams, getListingResponse } from '../Listing/testUtils';

import ListingPage from '.';

const ListingWithQueryProvider = (): JSX.Element => {
  return (
    <TestQueryProvider>
      <ListingPage />
    </TestQueryProvider>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });

  cy.render(ListingWithQueryProvider);
};

describe('Notifications Listing', () => {
  beforeEach(initialize);

  it('displays the first page of the notifications listing', () => {
    cy.waitForRequest('@defaultRequest');
    cy.matchImageSnapshot();
  });
});
