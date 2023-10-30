import { createStore, Provider } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import {
  DashboardGlobalRole,
  ListingVariant,
  userAtom
} from '@centreon/ui-context';
import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { Method } from '@centreon/js-config/cypress/component/commands';

import { DashboardRole } from './api/models';
import { DashboardsPage } from './DashboardsPage';
import {
  dashboardsContactGroupsEndpoint,
  dashboardsContactsEndpoint,
  dashboardsEndpoint
} from './api/endpoints';
import {
  labelCancel,
  labelCreate,
  labelDashboardDeleted,
  labelDelete,
  labelName,
  labelWelcomeToDashboardInterface
} from './translatedLabels';

interface InitializeAndMountProps {
  canAdministrateDashboard?: boolean;
  canCreateDashboard?: boolean;
  canViewDashboard?: boolean;
  emptyList?: boolean;
  globalRole?: DashboardGlobalRole;
  ownRole?: DashboardRole;
}

const initializeAndMount = ({
  globalRole = DashboardGlobalRole.administrator,
  canCreateDashboard = true,
  canViewDashboard = true,
  canAdministrateDashboard = true,
  emptyList
}: InitializeAndMountProps): ReturnType<typeof createStore> => {
  const store = createStore();

  store.set(userAtom, {
    alias: 'admin',
    dashboard: {
      createDashboards: canCreateDashboard,
      globalUserRole: globalRole,
      manageAllDashboards: canAdministrateDashboard,
      viewDashboards: canViewDashboard
    },
    isExportButtonEnabled: true,
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris',
    use_deprecated_pages: false,
    user_interface_density: ListingVariant.compact
  });

  cy.viewport('macbook-13');

  cy.fixture(
    `Dashboards/${emptyList ? 'emptyDashboards' : 'dashboards'}.json`
  ).then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}?**`,
      response: dashboards
    });
  });

  cy.fixture(`Dashboards/contacts.json`).then((response) => {
    cy.interceptAPIRequest({
      alias: 'getContacts',
      method: Method.GET,
      path: `${dashboardsContactsEndpoint}?**`,
      response
    });
  });

  cy.fixture(`Dashboards/contactGroups.json`).then((response) => {
    cy.interceptAPIRequest({
      alias: 'getContactGroups',
      method: Method.GET,
      path: `${dashboardsContactGroupsEndpoint}?**`,
      response
    });
  });

  cy.interceptAPIRequest({
    alias: 'postDashboards',
    method: Method.POST,
    path: dashboardsEndpoint,
    response: {
      created_at: '',
      created_by: {
        id: 1,
        name: 'User 1'
      },
      description: null,
      id: 1,
      name: 'My Dashboard',
      own_role: 'editor',
      updated_at: '',
      updated_by: {
        id: 1,
        name: 'User 1'
      }
    },
    statusCode: 201
  });

  cy.interceptAPIRequest({
    alias: 'deleteDashboard',
    method: Method.DELETE,
    path: `${dashboardsEndpoint}/1`,
    statusCode: 204
  });

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <BrowserRouter>
            <Provider store={store}>
              <DashboardsPage />
            </Provider>
          </BrowserRouter>
        </TestQueryProvider>
      </SnackbarProvider>
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

      cy.makeSnapshot();
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

      cy.makeSnapshot();
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

      cy.makeSnapshot();
    });
  });

  it('displays a welcome label when the dashboard library is empty', () => {
    initializeAndMount({
      ...administratorRole,
      emptyList: true
    });

    cy.contains(labelWelcomeToDashboardInterface).should('be.visible');
    cy.findByLabelText('create').should('be.visible');

    cy.makeSnapshot();
  });

  it('creates a dashboard when the corresponding button is clicked and the title is filled', () => {
    initializeAndMount({
      ...administratorRole,
      emptyList: true
    });

    cy.findByLabelText('create').click();

    cy.findByLabelText(labelName).type('My Dashboard');

    cy.makeSnapshot();

    cy.viewport('macbook-13');

    cy.findByLabelText(labelCreate).click();
    cy.waitForRequest('@postDashboards');
    cy.url().should(
      'equal',
      'http://localhost:9092/home/dashboards/1?edit=true'
    );
  });

  it('deletes a dashboard when the corresponding icon button is clicked and the confirmation button is clicked', () => {
    initializeAndMount(administratorRole);

    cy.findAllByLabelText('delete').eq(0).click();
    cy.contains(labelDelete).click();

    cy.waitForRequest('@deleteDashboard');

    cy.contains(labelDashboardDeleted).should('be.visible');
  });

  it('does not delete a dashboard when the corresponding icon button is clicked and the cancellation button is clicked', () => {
    initializeAndMount(administratorRole);

    cy.findAllByLabelText('delete').eq(0).click();
    cy.contains(labelCancel).click();

    cy.contains(labelCancel).should('not.exist');
    cy.contains(labelDelete).should('not.exist');
  });
});
