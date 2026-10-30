import { SnackbarProvider } from '@centreon/ui';
import {
  defaultLimitPerPageAtom,
  type UserPermissions,
  userPermissionsAtom
} from '@centreon/ui-context';

import {
  QueryCache,
  QueryClient,
  QueryClientProvider
} from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import HostTemplates from '..';
import { hostTemplatesListEndpoint } from '../api/endpoints';
import { emptyListingResponse, getListingResponse } from './utils';

interface Props {
  isEmpty?: boolean;
  hasWriteAccess?: boolean;
}

interface InitializeResult extends RenderResult {
  // Every query that failed, by key: a query can fail before any request is
  // sent, which no intercept sees and no snackbar reports.
  queryErrors: Array<{ error: Error; queryKey: ReadonlyArray<unknown> }>;
  store: ReturnType<typeof createStore>;
}

// The header cells carry no role: their labels, in order, read from the head.
export const getHeaderLabels = (): Array<string | null> =>
  Array.from(
    document.querySelectorAll('.MuiTableHead-root .MuiTypography-root')
  ).map(({ textContent }) => textContent);

const initialize = ({
  isEmpty = false,
  hasWriteAccess = true
}: Props = {}): InitializeResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The form is URL-driven, and the page is not reloaded between tests.
  window.history.pushState({}, '', window.location.pathname);

  const store = createStore();

  // Only what the module reads.
  store.set(userPermissionsAtom, {
    configuration_host_template_write: hasWriteAccess
  } as UserPermissions);
  store.set(defaultLimitPerPageAtom, 10);

  interceptAPIRequest({
    alias: 'getAllHostTemplates',
    method: Method.GET,
    path: `**${hostTemplatesListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  const queryErrors: InitializeResult['queryErrors'] = [];

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } },
    queryCache: new QueryCache({
      onError: (error, { queryKey }) => {
        queryErrors.push({ error, queryKey });
      }
    })
  });

  const renderResult = render(
    <Router>
      <SnackbarProvider>
        <QueryClientProvider client={queryClient}>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <HostTemplates />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );

  return { ...renderResult, queryErrors, store };
};

export default initialize;
