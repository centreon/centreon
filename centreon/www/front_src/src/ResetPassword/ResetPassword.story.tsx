import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { LocationRecorder } from '../../../../playwright/gallery/storyHelpers';
import ResetPassword from '.';
import { passwordResetInformationsAtom } from './passwordResetInformationsAtom';

interface Props {
  /** null reproduces a page opened without the reset information. */
  alias?: string | null;
  hasItEditionInstalled?: boolean;
}

const itEditionPlatform = {
  modules: {
    'centreon-it-edition-extensions': {
      fix: '0',
      major: '23',
      minor: '10',
      version: '23.10.0'
    }
  },
  web: { fix: '1', major: '21', minor: '10', version: '21.10.1' }
};

export const Default = ({
  alias = 'admin',
  hasItEditionInstalled = false
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(passwordResetInformationsAtom, alias ? { alias } : null);
    if (hasItEditionInstalled) {
      created.set(platformVersionsAtom, itEditionPlatform as never);
    }

    return created;
  }, [alias, hasItEditionInstalled]);

  return (
    <MemoryRouter initialEntries={['/reset-password']}>
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <ResetPassword />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
      <LocationRecorder />
    </MemoryRouter>
  );
};
