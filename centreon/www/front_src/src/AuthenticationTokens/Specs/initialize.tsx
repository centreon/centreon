import { SnackbarProvider } from '@centreon/ui';
import { type User, userAtom } from '@centreon/ui-context';

import { rs } from '@rstest/core';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import { listTokensEndpoint } from '../api';
import { listUsers } from '../api/endpoints';
import Page from '../Page';
import {
  listTokensResponse,
  listUsersResponse,
  tokenDetailsResponse
} from './utils';

const interceptRequests = (listTokens: unknown): void => {
  interceptAPIRequest({
    alias: 'listToken',
    method: Method.GET,
    path: `**${listTokensEndpoint}?**`,
    response: listTokens
  });

  interceptAPIRequest({
    alias: 'deleteToken',
    method: Method.DELETE,
    path: `**${listTokensEndpoint}/d-token/users/23`,
    response: { code: 200, status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'enableDisableToken',
    method: Method.PATCH,
    path: `**${listTokensEndpoint}/*`,
    response: { code: 200, status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'tokenDetails',
    method: Method.GET,
    path: `**${listTokensEndpoint}/e-token/users/23`,
    response: tokenDetailsResponse
  });

  interceptAPIRequest({
    alias: 'listUsers',
    method: Method.GET,
    path: `**${listUsers}**`,
    response: listUsersResponse
  });

  interceptAPIRequest({
    alias: 'addToken',
    method: Method.POST,
    path: `**${listTokensEndpoint}**`,
    response: tokenDetailsResponse
  });
};

export const initialize = (
  listTokens: unknown = listTokensResponse
): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The creation modal is URL-driven, and the page is not reloaded between
  // tests.
  window.history.pushState({}, '', window.location.pathname);

  const store = createStore();

  store.set(userAtom, {
    canManageApiTokens: true,
    isAdmin: true,
    locale: 'en_US',
    timezone: 'Europe/Paris'
  } as User);

  // Restored after each test by the entry file.
  rs.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();

  interceptRequests(listTokens);

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <div style={{ height: '90vh' }}>
      <Router>
        <SnackbarProvider>
          <QueryClientProvider client={queryClient}>
            <Provider store={store}>
              <Page />
            </Provider>
          </QueryClientProvider>
        </SnackbarProvider>
      </Router>
    </div>
  );
};
