import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import AgentConfigurationPage from './Page';

export const Default = (): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, {
      ...created.get(userAtom),
      is_admin: true,
      locale: 'en',
      timezone: 'Europe/Paris'
    } as never);
    created.set(platformFeaturesAtom, {
      featureFlags: {},
      isCloudPlatform: false
    } as never);

    return created;
  }, []);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <MemoryRouter>
          <SnackbarProvider>
            <div style={{ display: 'grid', height: '100vh' }}>
              <AgentConfigurationPage />
            </div>
          </SnackbarProvider>
        </MemoryRouter>
      </Provider>
    </TestQueryProvider>
  );
};
