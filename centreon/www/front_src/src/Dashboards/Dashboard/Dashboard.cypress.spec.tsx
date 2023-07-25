/* eslint-disable import/no-unresolved,@typescript-eslint/no-unused-vars */

import { createStore, Provider } from 'jotai';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-text/moduleFederation.json'.
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-input/moduleFederation.json'.
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';
import { BrowserRouter } from 'react-router-dom';

import {
  DashboardGlobalRole,
  ListingVariant,
  userAtom
} from '@centreon/ui-context';
import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from '../../federatedModules/atoms';
import { DashboardRole } from '../api/models';
import {
  dashboardsContactGroupsEndpoint,
  dashboardsContactsEndpoint,
  dashboardsEndpoint,
  getDashboardAccessRightsEndpoint,
  getDashboardEndpoint
} from '../api/endpoints';
import { dialogStateAtom } from '../components/DashboardAccessRights/useDashboardAccessRights';
import { labelDelete } from '../translatedLabels';

import {
  labelAdd,
  labelAddAWidget,
  labelDeleteAWidget,
  labelDeleteWidget,
  labelDoYouWantToDeleteThisWidget,
  labelEdit,
  labelEditDashboard,
  labelEditWidget,
  labelMoreActions,
  labelName,
  labelWidgetsLibrary
} from './translatedLabels';
import { routerParams } from './useDashboardDetails';
import { Dashboard } from './Dashboard';

const initializeWidgets = (): ReturnType<typeof createStore> => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetInputConfiguration,
      moduleFederationName: 'centreon-widget-input/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties
  ]);

  return store;
};

interface InitializeAndMountProps {
  canAdministrateDashboard?: boolean;
  canCreateDashboard?: boolean;
  canViewDashboard?: boolean;
  globalRole?: DashboardGlobalRole;
  ownRole?: DashboardRole;
}

const editorRoles = {
  canAdministrateDashboard: false,
  canCreateDashboard: true,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.creator,
  ownRole: DashboardRole.editor
};

const viewerRoles = {
  canAdministrateDashboard: false,
  canCreateDashboard: false,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.viewer,
  ownRole: DashboardRole.viewer
};

const viewerCreatorRoles = {
  canAdministrateDashboard: false,
  canCreateDashboard: true,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.creator,
  ownRole: DashboardRole.viewer
};

const viewerAdministratorRoles = {
  canAdministrateDashboard: true,
  canCreateDashboard: true,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.administrator,
  ownRole: DashboardRole.viewer
};

const initializeAndMount = ({
  ownRole = DashboardRole.editor,
  globalRole = DashboardGlobalRole.administrator,
  canCreateDashboard = true,
  canViewDashboard = true,
  canAdministrateDashboard = true
}: InitializeAndMountProps): ReturnType<typeof createStore> => {
  const store = initializeWidgets();

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
  store.set(dialogStateAtom, {
    dashboard: {
      createdAt: '',
      createdBy: {
        id: 1,
        name: 'Joe'
      },
      description: null,
      id: 1,
      name: 'Dashboard 1',
      ownRole: DashboardRole.editor,
      updatedAt: '',
      updatedBy: {
        id: 1,
        name: 'Joe'
      }
    },
    isOpen: false,
    status: 'idle'
  });

  cy.viewport('macbook-13');

  cy.fixture('Dashboards/Dashboard/details.json').then((dashboardDetails) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardDetails',
      method: Method.GET,
      path: getDashboardEndpoint('1'),
      response: {
        ...dashboardDetails,
        own_role: ownRole
      }
    });
  });

  cy.interceptAPIRequest({
    alias: 'patchDashboardDetails',
    method: Method.PATCH,
    path: getDashboardEndpoint('1'),
    statusCode: 201
  });

  cy.fixture('Dashboards/dashboards.json').then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}?**`,
      response: dashboards
    });
  });

  cy.fixture('Dashboards/Dashboard/accessRights.json').then((shares) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardAccessRights',
      method: Method.GET,
      path: `${getDashboardAccessRightsEndpoint(1)}**`,
      response: shares
    });
  });

  cy.fixture('Dashboards/Dashboard/contacts.json').then((contacts) => {
    cy.interceptAPIRequest({
      alias: 'getContacts',
      method: Method.GET,
      path: `${dashboardsContactsEndpoint}**`,
      response: contacts
    });
  });

  cy.fixture('Dashboards/Dashboard/contactGroups.json').then(
    (contactgroups) => {
      cy.interceptAPIRequest({
        alias: 'getContactGroups',
        method: Method.GET,
        path: `${dashboardsContactGroupsEndpoint}**`,
        response: contactgroups
      });
    }
  );

  cy.interceptAPIRequest({
    alias: 'putDashboardAccessRights',
    method: Method.PUT,
    path: getDashboardAccessRightsEndpoint(1),
    statusCode: 204
  });

  cy.stub(routerParams, 'useParams').returns({ dashboardId: '1' });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <BrowserRouter>
          <SnackbarProvider>
            <Provider store={store}>
              <Dashboard />
            </Provider>
          </SnackbarProvider>
        </BrowserRouter>
      </TestQueryProvider>
    )
  });

  return store;
};

describe('Dashboard', () => {
  describe('Roles', () => {
    it('has access to the dashboard edition features when the user has the editor role', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');
      cy.waitForRequest('@getContacts');
      cy.waitForRequest('@getContactGroups');

      cy.contains(labelEditDashboard).should('be.visible');
    });

    it('does not have access to the dashboard edition features when the user has the viewer role and the global viewer role', () => {
      initializeAndMount(viewerRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('not.exist');
    });

    it('does not have access to the dashboard edition features when the user has the viewer role and the global creator role', () => {
      initializeAndMount(viewerCreatorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('not.exist');
    });

    it('has access to the dashboard edition features when the user has the viewer role and the global administrator role', () => {
      initializeAndMount(viewerAdministratorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('be.visible');
    });
  });

  describe('Add widget', () => {
    it('adds a widget when a widget type is selected and the submission button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findByLabelText(labelEditDashboard).click();
      cy.findByLabelText(labelAddAWidget).click();

      cy.findByLabelText(labelWidgetsLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelName).type('Generic input');
      cy.findByLabelText('Generic text').type('Text for the new widget');

      cy.findByLabelText(labelAdd).click();

      cy.contains('Text for the new widget').should('be.visible');

      cy.matchImageSnapshot();
    });
  });

  describe('Edit widget', () => {
    it('edits a widget when the corresponding button is clicked, the widget type is changed the edit button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findByLabelText(labelMoreActions).click();
      cy.contains(labelEditWidget).click();

      cy.findByLabelText(labelWidgetsLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelName).type('Generic input');
      cy.findByLabelText('Generic text').type('Text for the new widget');

      cy.findByLabelText(labelEdit).click();

      cy.contains('Text for the new widget').should('be.visible');

      cy.matchImageSnapshot();
    });
  });

  describe('Delete widget', () => {
    it('deletes a widget when the corresponding button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findByLabelText(labelMoreActions).click();
      cy.contains(labelDeleteWidget).click();

      cy.contains(labelDeleteAWidget).should('be.visible');
      cy.contains(labelDoYouWantToDeleteThisWidget).should('be.visible');

      cy.findByLabelText(labelDelete).click();

      cy.contains(labelAddAWidget).should('be.visible');

      cy.matchImageSnapshot();
    });
  });
});
