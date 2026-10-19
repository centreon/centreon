import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { LocationRecorder } from '../../../../playwright/gallery/storyHelpers';
import { platformInstallationStatusAtom } from '../Main/atoms/platformInstallationStatusAtom';
import { areUserParametersLoadedAtom } from '../Main/useUser';
import LoginPage from '.';

interface Props {
  hasItEditionInstalled?: boolean;
  /** Query string of the login URL, e.g. '?authenticationError=...'. */
  search?: string;
}

const web = { fix: '1', major: '21', minor: '10', version: '21.10.1' };

export const Default = ({
  hasItEditionInstalled = false,
  search = ''
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(areUserParametersLoadedAtom, false);
    created.set(platformInstallationStatusAtom, {
      hasUpgradeAvailable: false,
      isInstalled: false
    });
    created.set(platformVersionsAtom, {
      modules: hasItEditionInstalled
        ? {
            'centreon-it-edition-extensions': {
              fix: '0',
              major: '23',
              minor: '10',
              version: '23.10.0'
            }
          }
        : {},
      web
    } as never);

    return created;
  }, [hasItEditionInstalled]);

  return (
    <MemoryRouter initialEntries={[`/login${search}`]}>
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <LoginPage />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
      <LocationRecorder />
    </MemoryRouter>
  );
};
