import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformFeaturesAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo, useState } from 'react';
import { MemoryRouter } from 'react-router';

import { generatedCommandAtom, isModalOpenAtom, pollerIdAtom } from './atoms';
import CloudInstallCommand from './CloudInstallCommand';
import { centralWebAddress } from './Modal/useInstallCommand';

interface Props {
  /** Replaces the address read from the browser URL (cy.stub in Cypress). */
  centralWebAddress?: string;
  generatedCommand?: string | null;
  isCloudPlatform?: boolean;
  isModalOpen?: boolean;
  pollerId?: number | null;
}

export const Default = ({
  centralWebAddress: address,
  generatedCommand = null,
  isCloudPlatform = false,
  isModalOpen = false,
  pollerId = null
}: Props): JSX.Element => {
  const [closeSubMenuCalls, setCloseSubMenuCalls] = useState(0);
  const store = useMemo(() => {
    const created = createStore();
    created.set(isModalOpenAtom, isModalOpen);
    created.set(platformFeaturesAtom, {
      featureFlags: {},
      isCloudPlatform
    } as never);
    created.set(generatedCommandAtom, generatedCommand);
    created.set(pollerIdAtom, pollerId);

    return created;
  }, [generatedCommand, isCloudPlatform, isModalOpen, pollerId]);

  if (address) {
    centralWebAddress.get = () => address;
  }

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <MemoryRouter>
          <SnackbarProvider maxSnackbars={2}>
            <CloudInstallCommand
              closeSubMenu={() => setCloseSubMenuCalls((calls) => calls + 1)}
            />
          </SnackbarProvider>
        </MemoryRouter>
      </Provider>
      <form hidden>
        <input
          data-testid="close-sub-menu-calls"
          readOnly
          value={closeSubMenuCalls}
        />
      </form>
    </TestQueryProvider>
  );
};
