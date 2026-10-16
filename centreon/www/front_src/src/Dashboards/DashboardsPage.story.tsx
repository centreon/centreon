import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  DashboardGlobalRole,
  ListingVariant,
  platformVersionsAtom,
  userAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { LocationRecorder } from '../../../../playwright/gallery/storyHelpers';
import { viewModeAtom } from './components/DashboardLibrary/DashboardListing/atom';
import { ViewMode } from './components/DashboardLibrary/DashboardListing/models';
import { DashboardsPage } from './DashboardsPage';
import { DashboardLayout } from './models';
import { routerHooks } from './routerHooks';

const roles = {
  administrator: {
    createDashboards: true,
    globalUserRole: DashboardGlobalRole.administrator,
    manageAllDashboards: true,
    viewDashboards: true
  },
  editor: {
    createDashboards: true,
    globalUserRole: DashboardGlobalRole.creator,
    manageAllDashboards: false,
    viewDashboards: true
  },
  viewer: {
    createDashboards: false,
    globalUserRole: DashboardGlobalRole.viewer,
    manageAllDashboards: false,
    viewDashboards: true
  }
};

const version = { fix: '0', major: '0', minor: '0', version: '1.0.0' };

interface Props {
  role?: keyof typeof roles;
}

export const Default = ({ role = 'administrator' }: Props): JSX.Element => {
  // The page reads its layout from the route (cy.stub in Cypress).
  routerHooks.useParams = (() => ({
    layout: DashboardLayout.Library
  })) as typeof routerHooks.useParams;

  const store = useMemo(() => {
    const created = createStore();
    created.set(viewModeAtom, ViewMode.List);
    created.set(userAtom, {
      alias: 'admin',
      dashboard: roles[role],
      isExportButtonEnabled: true,
      locale: 'en',
      name: 'admin',
      timezone: 'Europe/Paris',
      use_deprecated_pages: false,
      user_interface_density: ListingVariant.compact
    } as never);
    created.set(platformVersionsAtom, {
      modules: { 'centreon-it-edition-extensions': version },
      web: version,
      widgets: {}
    } as never);

    return created;
  }, [role]);

  return (
    <SnackbarProvider>
      <TestQueryProvider>
        <MemoryRouter>
          <Provider store={store}>
            <DashboardsPage />
            <LocationRecorder />
          </Provider>
        </MemoryRouter>
      </TestQueryProvider>
    </SnackbarProvider>
  );
};
