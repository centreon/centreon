/* eslint-disable import/no-unresolved,@typescript-eslint/no-unused-vars */

import widgetGenericTextConfiguration from 'centreon-widgets/centreon-widget-generictext/moduleFederation.json';
import widgetGenericTextProperties from 'centreon-widgets/centreon-widget-generictext/properties.json';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-input/moduleFederation.json'.
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-text/moduleFederation.json'.
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';
import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  DashboardGlobalRole,
  ListingVariant,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';

import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from '../../../federatedModules/atoms';
import {
  dashboardSharesEndpoint,
  dashboardsContactsEndpoint,
  dashboardsEndpoint,
  getDashboardEndpoint
} from '../../api/endpoints';
import { DashboardRole } from '../../api/models';
import {
  labelAddAContact,
  labelDelete,
  labelSharesSaved
} from '../../translatedLabels';

import Dashboard from './Dashboard';
import { dashboardAtom } from './atoms';
import { routerParams } from './hooks/useDashboardDetails';
import { saveBlockerHooks } from './hooks/useDashboardSaveBlocker';
import {
  labelAddAWidget,
  labelCancel,
  labelDeleteWidget,
  labelDoYouWantToSaveChanges,
  labelDuplicate,
  labelEditDashboard,
  labelEditWidget,
  labelGlobalRefreshInterval,
  labelIfYouClickOnDiscard,
  labelInterval,
  labelManualRefreshOnly,
  labelMoreActions,
  labelPleaseContactYourAdministrator,
  labelRefresh,
  labelSave,
  labelTitle,
  labelViewProperties,
  labelWidgetType,
  labelYourRightsOnlyAllowToView
} from './translatedLabels';

const initializeWidgets = (): ReturnType<typeof createStore> => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetInputConfiguration,
      moduleFederationName: 'centreon-widget-input/src'
    },
    {
      ...widgetGenericTextConfiguration,
      moduleFederationName: 'centreon-widget-generictext/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties,
    widgetGenericTextProperties
  ]);

  return store;
};

interface InitializeAndMountProps {
  canAdministrateDashboard?: boolean;
  canCreateDashboard?: boolean;
  canViewDashboard?: boolean;
  globalRole?: DashboardGlobalRole;
  isBlocked?: boolean;
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
  canAdministrateDashboard = true,
  isBlocked = false
}: InitializeAndMountProps): {
  blockNavigation;
  proceedNavigation;
  store: ReturnType<typeof createStore>;
} => {
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
  store.set(refreshIntervalAtom, 15);

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
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

  cy.fixture(`Dashboards/contacts.json`).then((response) => {
    cy.interceptAPIRequest({
      alias: 'getContacts',
      method: Method.GET,
      path: `./api/latest${dashboardsContactsEndpoint}?**`,
      response
    });
  });

  cy.interceptAPIRequest({
    alias: 'putShares',
    method: Method.PUT,
    path: `./api/latest${dashboardSharesEndpoint(1)}`,
    statusCode: 204
  });

  const proceedNavigation = cy.stub();
  const blockNavigation = cy.stub();

  cy.stub(routerParams, 'useParams').returns({ dashboardId: '1' });
  cy.stub(saveBlockerHooks, 'useBlocker').returns({
    proceed: proceedNavigation,
    reset: blockNavigation,
    state: isBlocked ? 'blocked' : 'unblocked'
  });

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

  return {
    blockNavigation,
    proceedNavigation,
    store
  };
};

describe('Dashboard', () => {
  describe('Roles', () => {
    it('has access to the dashboard edition features when the user has the editor role', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.contains(labelEditDashboard).should('be.visible');

      cy.contains('Widget text').should('be.visible');
      cy.contains('Generic text').should('be.visible');
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

      cy.findByLabelText(labelWidgetType).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelTitle).type('Generic input');
      cy.findByLabelText('Generic text').type('Text for the new widget');

      cy.findAllByLabelText(labelSave).eq(1).click();
      cy.findAllByLabelText(labelSave).eq(1).should('be.disabled');

      cy.contains('Text for the new widget').should('be.visible');
    });
  });

  describe('Edit widget', () => {
    it('edits a widget when the corresponding button is clicked and the widget type is changed the edit button is clicked', () => {
      const { store } = initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.contains(labelEditWidget).click();

      cy.findByLabelText(labelWidgetType).click({ force: true });
      cy.contains('Generic input (example)').click({ force: true });

      cy.findByLabelText(labelTitle).type('Generic input', { force: true });
      cy.findByLabelText('Generic text').type('Text for the new widget');

      cy.url().should('include', 'edit=true');

      cy.findAllByLabelText(labelSave).eq(1).click();

      cy.contains(labelEditWidget).should('not.exist');
      cy.contains('Text for the new widget')
        .should('be.visible')
        .then(() => {
          const dashboard = store.get(dashboardAtom);

          assert.equal(dashboard.layout.length, 3);
          assert.exists(dashboard.layout[2].data);
          assert.equal(
            dashboard.layout[2].options?.text,
            'Text for the new widget'
          );
          assert.equal(dashboard.layout[2].name, 'centreon-widget-input');
        });

      cy.makeSnapshot();
    });
  });

  describe('Delete widget', () => {
    it('deletes a widget when the corresponding button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.contains(labelDeleteWidget).click();

      cy.contains('The Widget text widget will be permanently deleted.').should(
        'be.visible'
      );

      cy.findByLabelText(labelDelete).click();

      cy.contains(labelAddAWidget).should('be.visible');

      cy.makeSnapshot();
    });

    it('does not display the name of the widget when the corresponding button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findAllByLabelText(labelMoreActions).eq(2).click();
      cy.contains(labelDeleteWidget).click();

      cy.contains('The widget will be permanently deleted.').should(
        'be.visible'
      );

      cy.makeSnapshot();
    });
  });

  describe('View mode', () => {
    it('displays the widget form in editor mode when the user has editor role and the user is not editing the dashboard', () => {
      initializeAndMount(editorRoles);

      cy.contains(labelCancel).click();

      cy.findAllByLabelText(labelMoreActions).eq(0).click();

      cy.findByLabelText(labelEditWidget).click();

      cy.findByLabelText(labelWidgetType).should('be.enabled');

      cy.findByLabelText('close').click();

      cy.findByLabelText(labelWidgetType).should('exist');

      cy.makeSnapshot();
    });

    it('displays the widget form in view mode when the user has viewer role', () => {
      initializeAndMount(viewerRoles);

      cy.findAllByLabelText(labelMoreActions).eq(0).click();

      cy.findByLabelText(labelViewProperties).click();

      cy.findByLabelText(labelWidgetType).should('be.disabled');
      cy.findByLabelText(labelCancel).should('not.exist');
      cy.findByLabelText(labelSave).should('not.exist');
      cy.contains(labelYourRightsOnlyAllowToView).should('be.visible');
      cy.contains(labelPleaseContactYourAdministrator).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the refresh button when the more actions button is clicked', () => {
      initializeAndMount(viewerRoles);

      cy.findAllByLabelText(labelMoreActions).eq(0).click();

      cy.contains(labelRefresh).should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Duplicate', () => {
    it('duplicates the widget when the corresponding button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.findByLabelText(labelDuplicate).click();

      cy.findAllByText('Widget text').should('have.length', 2);

      cy.makeSnapshot();
    });
  });

  describe('Dashboard global properties', () => {
    it('displays the dashboard global properties form when the corresponding button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findByLabelText(labelCancel).click();

      cy.findByLabelText('edit').click();

      cy.contains(labelGlobalRefreshInterval).should('be.visible');
      cy.contains(labelManualRefreshOnly).should('be.visible');

      cy.findByLabelText(labelInterval).should('have.value', '15');
    });
  });

  it('displays the title and the description in the panel', () => {
    initializeAndMount(editorRoles);

    cy.waitForRequest('@getDashboardDetails');

    cy.contains('Generic text').should('be.visible');
    cy.contains('Description').should('be.visible');
  });

  it('cancels the dashboard edition when the cancel button is clicked and the dashboard is edited', () => {
    initializeAndMount(editorRoles);

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).click();

    cy.findAllByLabelText(labelMoreActions).eq(0).trigger('click');
    cy.contains(labelDeleteWidget).click();
    cy.findByLabelText(labelDelete).click();

    cy.findByLabelText(labelCancel).click();

    cy.contains('Widget text').should('be.visible');
    cy.contains('Generic text').should('be.visible');
  });

  it('sends a shares update request when the shares are update and the corresponding button is clicked', () => {
    initializeAndMount(editorRoles);

    cy.findAllByTestId('share').eq(0).click();

    cy.findByLabelText(labelAddAContact).click();

    cy.waitForRequest('@getContacts');

    cy.contains(/^User$/)
      .parent()
      .click();

    cy.findByTestId('add').click();

    cy.contains(labelSave).click();

    cy.waitForRequest('@putShares');

    cy.contains(labelSharesSaved).should('be.visible');

    cy.makeSnapshot();
  });

  describe('Route blocking', () => {
    it('saves changes when a dashboard is being edited, a dashboard is updated, the user goes to another page and the corresponding button is clicked', () => {
      const { proceedNavigation } = initializeAndMount({
        ...editorRoles,
        isBlocked: true
      });

      cy.contains(labelEditDashboard).click();

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.findByLabelText(labelDuplicate).click();

      cy.contains(labelDoYouWantToSaveChanges).should('be.visible');
      cy.contains(labelIfYouClickOnDiscard).should('be.visible');

      cy.findByTestId('confirm').click();

      cy.waitForRequest('@patchDashboardDetails').then(() => {
        expect(proceedNavigation).to.have.been.calledWith();
      });

      cy.makeSnapshot();
    });

    it('does not save changes when a dashboard is being edited, a dashboard is updated, the user goes to another page and the corresponding button is clicked', () => {
      const { proceedNavigation } = initializeAndMount({
        ...editorRoles,
        isBlocked: true
      });

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.findByLabelText(labelDuplicate).click();

      cy.findByTestId('cancel')
        .click()
        .then(() => {
          expect(proceedNavigation).to.have.been.calledWith();
        });

      cy.makeSnapshot();
    });

    it('blocks the redirection when a dashboard is being edited, a dashboard is updated, the user goes to another page and the close button is clicked', () => {
      const { blockNavigation } = initializeAndMount({
        ...editorRoles,
        isBlocked: true
      });

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.findByLabelText(labelDuplicate).click();

      cy.findByLabelText('close')
        .click()
        .then(() => {
          expect(blockNavigation).to.have.been.calledWith();
        });

      cy.makeSnapshot();
    });
  });
});
