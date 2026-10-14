import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import Page from './Page';

// Admin allowed to manage tokens; specs mock the token endpoints.
export const Default = (): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, {
      canManageApiTokens: true,
      isAdmin: true,
      locale: 'en_US',
      timezone: 'Europe/Paris'
    } as never);

    return created;
  }, []);

  return (
    <div style={{ height: '90vh' }}>
      <MemoryRouter>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <Page />
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </MemoryRouter>
    </div>
  );
};
