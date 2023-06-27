/* eslint-disable import/no-unresolved,@typescript-eslint/no-unused-vars */

import { createStore, Provider } from 'jotai';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-text/moduleFederation.json'.
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-input/moduleFederation.json'.
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import { BrowserRouter } from 'react-router-dom';

import {
  DashboardGlobalRole,
  ListingVariant,
  userAtom
} from '@centreon/ui-context';
import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { federatedWidgetsAtom } from '../../federatedModules/atoms';
// import { unstable_Blocker } from 'react-router-dom';
// import { router } from './useDashboardSaveBlocker';
import { DashboardRole } from '../api/models';
import {
  dashboardsEndpoint,
  getDashboardAccessRightsEndpoint,
  getDashboardEndpoint
} from '../api/endpoints';
import { labelShareTheDashboard } from '../translatedLabels';

import { routerParams } from './useDashboardDetails';
import { labelEditDashboard } from './translatedLabels';
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

  return store;
};

// const initializeBlocker = (isNavigationBlocked = false): unstable_Blocker => {
//   const useBlockerResult: unstable_Blocker = {
//     location: {
//       hash: '',
//       key: '5nvxpbdafa',
//       pathname: '/dashboards/1',
//       search: '',
//       state: null
//     },
//     proceed: cy.stub(),
//     reset: cy.stub(),
//     state: isNavigationBlocked ? 'blocked' : 'unblocked'
//   };
//   cy.stub(router, 'useBlocker').returns(useBlockerResult);
//
//   return useBlockerResult;
// };

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
      path: getDashboardAccessRightsEndpoint(1),
      response: shares
    });
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
  // FIXME the `unstable_Blocker` is conflicting with the default behavior of react-router-dom, feature has been disabled for now
  /*
  describe('Unsaved changes navigation blocker', () => {
  it('cancels the dashboard changes when the "Cancel" button is clicked in the confirmation modal', () => {

    initializeBlocker();
    const store = initializeAndMount({});

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    cy.fixture('Dashboards/Dashboard/updatedLayout.json').then((panels) => {
      store.set(dashboardAtom, {
        layout: panels
      });
    });

    cy.findByTestId('1_move_panel').should('not.exist');

    cy.contains(labelExit).click();

    cy.contains(labelExitEditionMode).should('be.visible');
    cy.contains(labelLeaveEditionModeChangesNotSaved).should('be.visible');

    cy.matchImageSnapshot();

    cy.findByTestId('cancel').click();

    cy.contains(labelEditDashboard).click();

    cy.findByTestId('1_move_panel').should('exist');
  });

  it('closes the cancel confirmation modal when the modal backdrop is clicked', () => {
    initializeBlocker();
    const store = initializeAndMount({});

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    cy.fixture('Dashboards/Dashboard/updatedLayout.json').then((panels) => {
      store.set(dashboardAtom, {
        layout: panels
      });
    });

    cy.contains(labelExit).click();
    cy.get('body').click('topLeft');

    cy.contains(labelExit).should('be.visible');

    cy.matchImageSnapshot();
  });

  it('displays the cancel confirmation modal when the user tries to navigate away from the dashboard', () => {
    const store = initializeAndMount({});

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    cy.fixture('Dashboards/Dashboard/updatedLayout.json').then((panels) => {
      store.set(dashboardAtom, {
        layout: panels
      });
    });

    initializeBlocker(true);

    cy.contains(labelExitDashboard).should('be.visible');

    cy.matchImageSnapshot();
  });

  it('does not display the cancel confirmation modal when the Exit button is clicked and the dashboard is not updated', () => {
    initializeBlocker();
    initializeAndMount({});

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    cy.contains(labelExit).click();

    cy.contains(labelExitEditionMode).should('not.exist');

    cy.matchImageSnapshot();
  });

  it('does not display the cancel confirmation modal when the user tries to navigate away from the dashboard and the dashboard is not updated', () => {
    initializeAndMount({});

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    initializeBlocker(true);

    cy.contains(labelExitEditionMode).should('not.exist');

    cy.matchImageSnapshot();
  });

  it('proceeds the navigation when the corresponding button is clicked and the user tries to navigate away from the dashboard', () => {
    const store = initializeAndMount({});

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    cy.fixture('Dashboards/Dashboard/updatedLayout.json').then((panels) => {
      store.set(dashboardAtom, {
        layout: panels
      });
    });

    const { proceed } = initializeBlocker(true);

    cy.contains(labelExitDashboard).should('be.visible');

    cy.findByTestId('cancel')
      .click()
      .then(() => {
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        expect(proceed).to.have.been.calledOnce;
      });
  });

  it('saves the dashboard when the corresponding button is clicked and the dashboard is changed', () => {
    initializeBlocker();
    const store = initializeAndMount({});

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    cy.findByLabelText(labelSave).should('be.disabled');

    cy.fixture('Dashboards/Dashboard/updatedLayout.json').then((panels) => {
      store.set(dashboardAtom, {
        layout: panels
      });
    });

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@patchDashboardDetails');

    cy.contains(labelYourDashboardHasBeenSaved).should('be.visible');
    cy.waitForRequest('@getDashboardDetails');

    cy.matchImageSnapshot();
  });
  });
  */

  describe('Roles', () => {
    it('has access to the dashboard edition features when the user has the editor role', () => {
      // initializeBlocker();
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('be.visible');
    });

    it('does not have access to the dashboard edition features when the user has the viewer role and the global viewer role', () => {
      // initializeBlocker();
      initializeAndMount(viewerRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('not.exist');
    });

    it('does not have access to the dashboard edition features when the user has the viewer role and the global creator role', () => {
      // initializeBlocker();
      initializeAndMount(viewerCreatorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('not.exist');
    });

    it('has access to the dashboard edition features when the user has the viewer role and the global administrator role', () => {
      // initializeBlocker();
      initializeAndMount(viewerAdministratorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('be.visible');
    });
  });

  describe('AccessRights', () => {
    it('displays the list of user roles when the corresponding button is clicked', () => {
      // initializeBlocker();
      initializeAndMount(editorRoles);

      cy.findByLabelText(labelShareTheDashboard).click();

      cy.fixture('Dashboards/Dashboard/accessRights.json').then((shares) => {
        shares.result.forEach(({ fullname, email, role }, index) => {
          cy.get('[data-element="avatar"]')
            .contains(fullname[0])
            .should('be.visible');
          cy.findByText(fullname).should('be.visible');

          if (email) {
            cy.findByText(email).should('be.visible');
          }

          cy.findAllByTestId('change_role')
            .eq(index)
            .should('have.value', role);
          cy.findAllByTestId('remove_user').eq(index).should('be.visible');
        });
      });
    });

    it('changes a user role when a new role is selected for a user and the corresponding button is clicked', () => {
      // initializeBlocker();
      initializeAndMount(editorRoles);

      cy.findByLabelText(labelShareTheDashboard).click();

      cy.findAllByTestId('change_role')
        .eq(0)
        .should('have.value', DashboardRole.viewer);

      cy.findAllByTestId('change_role').eq(0).parent().click();
      cy.get('[data-value="editor"]').click();

      cy.findAllByTestId('change_role')
        .eq(0)
        .should('have.value', DashboardRole.editor);

      cy.matchImageSnapshot();
    });

    it('removes a user from the list when when the corresponding button is clicked', () => {
      // initializeBlocker();
      initializeAndMount(editorRoles);

      cy.findByLabelText(labelShareTheDashboard).click();

      cy.findByText('Walter Sobchak').should('be.visible');

      cy.findAllByTestId('remove_user').eq(0).click();

      cy.findByText('Walter Sobchak').should('not.exist');

      cy.matchImageSnapshot();
    });

    it('does not display the share button when the user has only the viewer role', () => {
      // initializeBlocker();
      initializeAndMount(viewerRoles);

      cy.findByLabelText(labelShareTheDashboard).should('not.exist');

      cy.matchImageSnapshot();
    });
  });
});
