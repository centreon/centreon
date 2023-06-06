/* eslint-disable import/no-unresolved */

import { Provider, createStore } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import { unstable_Blocker } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';
import {
  DashboardGlobalRole,
  ListingVariant,
  userAtom
} from '@centreon/ui-context';

import { federatedWidgetsAtom } from '../../federatedModules/atoms';
import { dashboardsEndpoint } from '../api/endpoints';
import { DashboardRole } from '../models';

import { router } from './useDashboardSaveBlocker';
import {
  labelEditDashboard,
  labelExit,
  labelExitDashboard,
  labelExitEditionMode,
  labelLeaveEditionModeChangesNotSaved
} from './translatedLabels';
import { routerParams } from './useDashboardDetails';
import { dashboardAtom } from './atoms';

import Dashboard from '.';

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

const initializeBlocker = (isNavigationBlocked = false): unstable_Blocker => {
  const useBlockerResult: unstable_Blocker = {
    location: {
      hash: '',
      key: '5nvxpbdafa',
      pathname: '/dashboards/1',
      search: '',
      state: null
    },
    proceed: cy.stub(),
    reset: cy.stub(),
    state: isNavigationBlocked ? 'blocked' : 'unblocked'
  };
  cy.stub(router, 'useBlocker').returns(useBlockerResult);

  return useBlockerResult;
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

  cy.fixture('Dashboards/Dashboard/details.json').then((dashboardDetails) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardDetails',
      method: Method.GET,
      path: `${dashboardsEndpoint}/1`,
      response: {
        ...dashboardDetails,
        own_role: ownRole
      }
    });
  });

  cy.stub(routerParams, 'useParams').returns({ dashboardId: '1' });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Dashboard />
        </Provider>
      </TestQueryProvider>
    )
  });

  return store;
};

describe('Dashboard', () => {
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

  describe('Roles', () => {
    it('has access to the dashboard edition features when the user has the editor role', () => {
      initializeBlocker();
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('be.visible');
    });

    it('does not have access to the dashboard edition features when the user has the viewer role and the global viewer role', () => {
      initializeBlocker();
      initializeAndMount(viewerRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('not.exist');
    });

    it('does not have access to the dashboard edition features when the user has the viewer role and the global creator role', () => {
      initializeBlocker();
      initializeAndMount(viewerCreatorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('not.exist');
    });

    it('has access to the dashboard edition features when the user has the viewer role and the global administrator role', () => {
      initializeBlocker();
      initializeAndMount(viewerAdministratorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('be.visible');
    });
  });
});
