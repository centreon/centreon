import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import { listTokensEndpoint } from '../api';
import { listUsers } from '../api/endpoints';
import Page from '../Page';

const interceptRequests = () => {
  cy.fixture('authenticationTokens/listTokens').then((data) => {
    cy.interceptAPIRequest({
      alias: 'listToken',
      method: Method.GET,
      path: `**${listTokensEndpoint}?**`,
      response: data
    });
  });

  cy.interceptAPIRequest({
    alias: 'deleteToken',
    method: Method.DELETE,
    path: '**tokens/d-token/users/23',
    response: { status: 'ok', code: 200 }
  });

  cy.interceptAPIRequest({
    alias: 'enableDisableToken',
    method: Method.PATCH,
    path: '**tokens**',
    response: { status: 'ok', code: 200 }
  });

  cy.fixture('authenticationTokens/tokenDetails').then((data) => {
    cy.interceptAPIRequest({
      alias: 'tokenDetails',
      method: Method.GET,
      path: '**tokens/e-token/users/23',
      response: data
    });
  });

  cy.fixture('authenticationTokens/listUsers').then((data) => {
    cy.interceptAPIRequest({
      alias: 'listUsers',
      method: Method.GET,
      path: `**${listUsers}**`,
      response: data
    });
  });

  cy.fixture('authenticationTokens/tokenDetails').then((data) => {
    cy.interceptAPIRequest({
      alias: 'addToken',
      method: Method.POST,
      path: `**${listTokensEndpoint}**`,
      response: data
    });
  });
};

export const initilize = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  store.set(userAtom, {
    canManageApiTokens: true,
    isAdmin: true,
    locale: 'en_US',
    timezone: 'Europe/Paris'
  });

  cy.window().then((win) => {
    cy.stub(win.navigator.clipboard, 'writeText').as('writeText');
  });

  interceptRequests();

  cy.mount({
    Component: (
      <div style={{ height: '90vh' }}>
        <Router>
          <SnackbarProvider>
            <TestQueryProvider>
              <Provider store={store}>
                <Page />
              </Provider>
            </TestQueryProvider>
          </SnackbarProvider>
        </Router>
      </div>
    )
  });
};
