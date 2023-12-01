import { BrowserRouter } from 'react-router-dom';
import { Provider, createStore } from 'jotai';

import { TestQueryProvider } from '@centreon/ui';
import {
  userAtom,
  DashboardGlobalRole,
  ListingVariant
} from '@centreon/ui-context';

import {
  labelCreateAPlaylist,
  labelWelcomeToThePlaylistInterface
} from '../../translatedLabels';

import DashboardPlaylistsOverview from './DashboardPlaylistsOverview';

const initialize = (): void => {
  const store = createStore();
  store.set(userAtom, {
    alias: 'admin',
    dashboard: {
      createDashboards: true,
      globalUserRole: DashboardGlobalRole.administrator,
      manageAllDashboards: true,
      viewDashboards: true
    },
    isExportButtonEnabled: true,
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris',
    use_deprecated_pages: false,
    user_interface_density: ListingVariant.compact
  });

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestQueryProvider>
          <BrowserRouter>
            <DashboardPlaylistsOverview />
          </BrowserRouter>
        </TestQueryProvider>
      </Provider>
    )
  });
};

describe('Playlists', () => {
  it('displays the landing screen when there is no data', () => {
    initialize();

    cy.contains(labelWelcomeToThePlaylistInterface).should('be.visible');
    cy.contains(labelCreateAPlaylist).should('be.visible');

    cy.makeSnapshot();
  });
});
