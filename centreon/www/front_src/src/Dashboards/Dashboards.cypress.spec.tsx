import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import {
  DashboardGlobalRole,
  ListingVariant,
  userAtom
} from '@centreon/ui-context';
import { Method, TestQueryProvider } from '@centreon/ui';

import { DashboardRole } from './models';
import { dashboardsEndpoint } from './api/endpoints';

import Dashboards from '.';

interface InitializeAndMountProps {
  canAdministrateDashboard?: boolean;
  canCreateDashboard?: boolean;
  canViewDashboard?: boolean;
  globalRole?: DashboardGlobalRole;
  ownRole?: DashboardRole;
}

const initializeAndMount = ({
  globalRole = DashboardGlobalRole.administrator,
  canCreateDashboard = true,
  canViewDashboard = true,
  canAdministrateDashboard = true
}: InitializeAndMountProps): ReturnType<typeof createStore> => {
  const store = createStore();

  store.set(userAtom, {
    alias: 'admin',
    dashboard: {
      administrateRole: canAdministrateDashboard,
      createRole: canCreateDashboard,
      globalUserRole: globalRole,
      viewRole: canViewDashboard
    },
    isExportButtonEnabled: true,
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris',
    use_deprecated_pages: false,
    user_interface_density: ListingVariant.compact
  });

  cy.viewport('macbook-13');

  cy.fixture('Dashboards/dashboards.json').then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}**`,
      response: dashboards
    });
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <BrowserRouter>
          <Provider store={store}>
            <Dashboards />
          </Provider>
        </BrowserRouter>
      </TestQueryProvider>
    )
  });

  return store;
};

const editorRole = {
  canAdministrateDashboard: false,
  canCreateDashboard: true,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.creator
};

const viewerRole = {
  canAdministrateDashboard: false,
  canCreateDashboard: false,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.viewer
};

const administratorRole = {
  canAdministrateDashboard: true,
  canCreateDashboard: true,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.administrator
};

describe('Dashboards', () => {
  describe('Roles', () => {
    it('displays the dashboard actions on the corresponding dashboard when the user has editor roles', () => {
      initializeAndMount(editorRole);

      cy.waitForRequest('@getDashboards');

      cy.findByLabelText('create').should('be.visible');

      cy.get('[data-item-title="My Dashboard"]')
        .findByLabelText('edit')
        .should('exist');
      cy.get('[data-item-title="My Dashboard"]')
        .findByLabelText('delete')
        .should('exist');
      cy.get('[data-item-title="My Dashboard 2"]')
        .findByLabelText('edit')
        .should('not.exist');
      cy.get('[data-item-title="My Dashboard 2"]')
        .findByLabelText('delete')
        .should('not.exist');

      cy.matchImageSnapshot();
    });

    it('displays the dashboard actions on the corresponding dashboard when the user has viewer roles', () => {
      initializeAndMount(viewerRole);

      cy.waitForRequest('@getDashboards');

      cy.findByLabelText('create').should('not.exist');

      cy.fixture('Dashboards/dashboards.json').then((dashboards) => {
        dashboards.result.forEach(({ name }) => {
          cy.get(`[data-item-title="${name}"]`)
            .findByLabelText('edit')
            .should('not.exist');
          cy.get(`[data-item-title="${name}"]`)
            .findByLabelText('delete')
            .should('not.exist');
        });
      });

      cy.matchImageSnapshot();
    });

    it('displays the dashboards actions on all dashboards when the user has administrator global roles', () => {
      initializeAndMount(administratorRole);

      cy.waitForRequest('@getDashboards');

      cy.findByLabelText('create').should('be.visible');

      cy.get('[data-item-title="My Dashboard"]')
        .findByLabelText('edit')
        .should('exist');
      cy.get('[data-item-title="My Dashboard"]')
        .findByLabelText('delete')
        .should('exist');
      cy.get('[data-item-title="My Dashboard 2"]')
        .findByLabelText('edit')
        .should('exist');
      cy.get('[data-item-title="My Dashboard 2"]')
        .findByLabelText('delete')
        .should('exist');

      cy.matchImageSnapshot();
    });
  });
});
