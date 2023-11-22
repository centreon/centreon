import { BrowserRouter as Router } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';

import { listTokensEndpoint } from '../api/endpoints';

import Listing from './Listing';

describe('app-token listing', () => {
  beforeEach(() => {
    cy.fixture('appTokens/list.json').then((data) => {
      cy.interceptAPIRequest({
        alias: 'getListTokens',
        method: Method.GET,
        path: listTokensEndpoint,
        response: data
      });
    });
    cy.viewport('macbook-13');
    cy.mount({
      Component: (
        <Router>
          <TestQueryProvider>
            <Listing />
          </TestQueryProvider>
        </Router>
      )
    });
  });

  it('first test', () => {
    cy.waitForRequest('@getListTokens');
    cy.contains('my-api-token');
  });
});
