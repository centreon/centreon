import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import Hosts from '.';

interface Props {
  hasWriteAccess?: boolean;
  isAdditiveInheritanceEnabled?: boolean;
  isCloudPlatform?: boolean;
}

export const Default = ({
  hasWriteAccess = true,
  isAdditiveInheritanceEnabled = false,
  isCloudPlatform = false
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userPermissionsAtom, {
      configuration_host_write: hasWriteAccess
    } as never);
    created.set(platformFeaturesAtom, { isCloudPlatform } as never);
    created.set(isAdditiveInheritanceEnabledAtom, isAdditiveInheritanceEnabled);

    return created;
  }, [hasWriteAccess, isAdditiveInheritanceEnabled, isCloudPlatform]);

  return (
    <MemoryRouter>
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <Hosts />
            </div>
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    </MemoryRouter>
  );
};
