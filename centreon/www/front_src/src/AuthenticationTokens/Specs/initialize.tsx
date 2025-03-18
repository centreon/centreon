import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';
import { Provider, createStore } from 'jotai';
import { BrowserRouter as Router } from 'react-router';
import Page from '../Page';
import { listTokensEndpoint } from '../api';

const interceptRequests = () => {
  cy.fixture('authenticationTokens/listTokens').then((data) => {
    cy.interceptAPIRequest({
      alias: 'listToken',
      method: Method.GET,
      path: `**${listTokensEndpoint}**`,
      response: data
    });
  });
};

export const initilize = (): void => {
  const store = createStore();

  store.set(userAtom, {
    canManageApiTokens: true,
    isAdmin: true,
    locale: 'en_US',
    timezone: 'Europe/Paris'
  });

  interceptRequests();

  cy.mount({
    Component: (
      <Provider store={store}>
        <SnackbarProvider>
          <Router>
            <TestQueryProvider>
              <Page />
            </TestQueryProvider>
          </Router>
        </SnackbarProvider>
      </Provider>
    )
  });
};
