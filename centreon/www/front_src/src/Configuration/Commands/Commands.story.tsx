import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { userPermissionsAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import Commands from '.';

export const Default = (): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userPermissionsAtom, {
      manage_check_commands: true,
      manage_discovery_commands: true,
      manage_miscellaneous_commands: true,
      manage_notification_commands: true
    } as never);

    return created;
  }, []);

  return (
    <MemoryRouter>
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <Commands />
            </div>
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    </MemoryRouter>
  );
};
