import { createGenerateClassName, StylesProvider } from '@mui/styles';

import {
  SnackbarProvider,
  TestQueryProvider,
  ThemeProvider
} from '@centreon/ui';
import {
  browserLocaleAtom,
  platformVersionsAtom,
  userAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { LocationRecorder } from '../../../../playwright/gallery/storyHelpers';
import type Navigation from '../Navigation/models';
import navigationAtom from '../Navigation/navigationAtoms';
import Header from '.';
import { testUtils } from './UserMenu';

// The navigation the Header specs default to: the poller configuration page.
const allowedPages = {
  result: [
    {
      children: [
        {
          groups: [
            {
              children: [
                {
                  is_react: false,
                  label: 'Pollers',
                  options: null,
                  page: '60901',
                  show: true,
                  url: './include/configuration/configServers/servers.php'
                }
              ],
              label: 'Main Menu'
            }
          ],
          is_react: false,
          label: 'Pollers',
          options: null,
          page: '609',
          show: true,
          url: null
        }
      ],
      color: '319ED5',
      icon: 'configuration',
      is_react: false,
      label: 'Configuration',
      menu_id: 'Configuration',
      options: null,
      page: '6',
      show: true,
      url: null
    }
  ],
  status: true
};

interface Props {
  installedModules?: Array<string>;
  isExportButtonEnabled?: boolean;
  navigationList?: object;
}

export const Default = ({
  installedModules = [],
  isExportButtonEnabled = false,
  navigationList
}: Props): JSX.Element => {
  // The user menu reads its allowed pages from here (cy.stub in Cypress).
  testUtils.useNavigation = (() => ({
    allowedPages: ['50104']
  })) as unknown as typeof testUtils.useNavigation;

  const store = useMemo(() => {
    const created = createStore();
    // Pinned so the clock renders the same whatever the machine locale.
    created.set(browserLocaleAtom, 'en');
    created.set(userAtom, { ...created.get(userAtom), isExportButtonEnabled });
    created.set(userPermissionsAtom, {
      poller_statistics: true,
      top_counter: true
    } as never);
    created.set(platformVersionsAtom, {
      modules: Object.fromEntries(
        installedModules.map((module) => [
          module,
          { fix: '0', major: '25', minor: '10', version: '25.10.0' }
        ])
      ),
      web: { fix: '0', major: '25', minor: '10', version: '25.10.0' },
      widgets: {}
    } as never);
    created.set(navigationAtom, (navigationList ?? allowedPages) as Navigation);

    return created;
  }, [installedModules, isExportButtonEnabled, navigationList]);
  const generateClassName = useMemo(
    () => createGenerateClassName({ seed: 'seedName' }),
    []
  );

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <StylesProvider generateClassName={generateClassName}>
          <ThemeProvider>
            <SnackbarProvider maxSnackbars={2}>
              <MemoryRouter>
                <Header />
                <LocationRecorder />
              </MemoryRouter>
            </SnackbarProvider>
          </ThemeProvider>
        </StylesProvider>
      </Provider>
    </TestQueryProvider>
  );
};
