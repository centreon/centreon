/* eslint-disable import/no-unresolved,@typescript-eslint/no-unused-vars */

import widgetGenericTextConfiguration from 'centreon-widgets/centreon-widget-generictext/moduleFederation.json';
import widgetGenericTextProperties from 'centreon-widgets/centreon-widget-generictext/properties.json';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-input/moduleFederation.json'.
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';
import widgetSingleMetricConfiguration from 'centreon-widgets/centreon-widget-singlemetric/moduleFederation.json';
import widgetSingleMetricProperties from 'centreon-widgets/centreon-widget-singlemetric/properties.json';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-text/moduleFederation.json'.
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';

import widgetWebpageConfiguration from 'centreon-widgets/centreon-widget-webpage/moduleFederation.json';
import widgetWebpageProperties from 'centreon-widgets/centreon-widget-webpage/properties.json';

import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  DashboardGlobalRole,
  ListingVariant,
  additionalResourcesAtom,
  federatedWidgetsAtom,
  platformVersionsAtom,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';

import { equals } from 'ramda';
import { federatedWidgetsPropertiesAtom } from '../../../federatedModules/atoms';
import {
  dashboardSharesEndpoint,
  dashboardsContactsEndpoint,
  dashboardsEndpoint,
  dashboardsFavoriteEndpoint,
  getDashboardEndpoint
} from '../../api/endpoints';
import { DashboardRole } from '../../api/models';
import { FavoriteAction } from '../../models';
import {
  interceptDashboardsFavoriteDelete,
  manageAFavorite
} from '../../testsUtils';
import {
  labelAddAContact,
  labelDashboardAddedToFavorites,
  labelDashboardUpdated,
  labelDelete,
  labelRemoveFromFavorites,
  labelSharesSaved,
  labelUpdate
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
  labelSave,
  labelTitle,
  labelViewProperties,
  labelWidgetType,
  labelYourDashboardHasBeenSaved,
  labelYourRightsOnlyAllowToView
} from './translatedLabels';

const widgetProperties = [
  widgetTextProperties,
  widgetInputProperties,
  widgetGenericTextProperties,
  widgetSingleMetricProperties,
  widgetWebpageProperties
];

const availableWidgets = widgetProperties.reduce(
  (acc, { moduleName }) => ({ ...acc, [moduleName]: {} }),
  []
);

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
    },
    {
      ...widgetSingleMetricConfiguration,
      moduleFederationName: 'centreon-widget-singlemetric/src'
    },
    {
      ...widgetWebpageConfiguration,
      moduleFederationName: 'centreon-widget-webpage/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, widgetProperties);
  store.set(platformVersionsAtom, {
    modules: {},
    web: {
      version: '23.04.0'
    },
    widgets: availableWidgets
  });

  return store;
};

interface InitializeAndMountProps {
  canAdministrateDashboard?: boolean;
  canCreateDashboard?: boolean;
  canViewDashboard?: boolean;
  detailsWithData?: boolean;
  globalRole?: DashboardGlobalRole;
  isBlocked?: boolean;
  ownRole?: DashboardRole;
  customDetailsPath?: string;
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

const interceptDetailsDashboard = ({
  path,
  own_role = DashboardRole.editor
}) => {
  cy.fixture(path).then((dashboardDetails) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardDetails',
      method: Method.GET,
      path: getDashboardEndpoint('1'),
      response: { ...dashboardDetails, own_role }
    });
  });
};

const initializeAndMount = ({
  ownRole = DashboardRole.editor,
  globalRole = DashboardGlobalRole.administrator,
  canCreateDashboard = true,
  canViewDashboard = true,
  canAdministrateDashboard = true,
  isBlocked = false,
  detailsWithData = false,
  customDetailsPath
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
  store.set(additionalResourcesAtom, [
    {
      baseEndpoint: '/ba',
      label: 'BA',
      resourceType: 'business-activity'
    }
  ]);

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  cy.viewport('macbook-13');

  interceptDetailsDashboard({
    path:
      customDetailsPath ??
      `Dashboards/Dashboard/${detailsWithData ? 'detailsWithData' : 'details'}.json`,
    own_role: ownRole
  });

  cy.interceptAPIRequest({
    alias: 'updateDashboard',
    method: Method.POST,
    path: getDashboardEndpoint('1'),
    statusCode: 201,
    response: {
      id: 1
    }
  });

  cy.fixture('Dashboards/dashboards.json').then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}?**`,
      response: dashboards
    });
  });

  cy.fixture('Dashboards/contacts.json').then((response) => {
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

  cy.interceptAPIRequest({
    alias: 'addFavorite',
    method: Method.POST,
    path: `./api/latest${dashboardsFavoriteEndpoint}`
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

const initializeDashboardWithWebpageWidgets = ({
  ownRole = DashboardRole.editor,
  globalRole = DashboardGlobalRole.administrator,
  canCreateDashboard = true,
  canViewDashboard = true,
  canAdministrateDashboard = true
}: InitializeAndMountProps): void => {
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
  store.set(additionalResourcesAtom, [
    {
      baseEndpoint: '/ba',
      label: 'BA',
      resourceType: 'business-activity'
    }
  ]);

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  cy.viewport('macbook-13');

  cy.fixture('Dashboards/Dashboard/detailsWithWebPageWidget.json').then(
    (dashboardDetails) => {
      cy.interceptAPIRequest({
        alias: 'getDashboardDetails',
        method: Method.GET,
        path: getDashboardEndpoint('1'),
        response: {
          ...dashboardDetails,
          own_role: ownRole
        }
      });
    }
  );

  cy.fixture('Dashboards/dashboards.json').then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}?**`,
      response: dashboards
    });
  });

  cy.fixture('Dashboards/contacts.json').then((response) => {
    cy.interceptAPIRequest({
      alias: 'getContacts',
      method: Method.GET,
      path: `./api/latest${dashboardsContactsEndpoint}?**`,
      response
    });
  });

  const proceedNavigation = cy.stub();
  const blockNavigation = cy.stub();

  cy.stub(routerParams, 'useParams').returns({ dashboardId: '1' });
  cy.stub(saveBlockerHooks, 'useBlocker').returns({
    proceed: proceedNavigation,
    reset: blockNavigation,
    state: 'unblocked'
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
};

const runFavoriteManagementFromDetails = ({ action, customDetailsPath }) => {
  initializeAndMount({ customDetailsPath });

  cy.waitForRequest('@getDashboardDetails');

  const path = equals(action, FavoriteAction.delete)
    ? 'Dashboards/Dashboard/details.json'
    : 'Dashboards/favorites/details.json';

  const aliasRequestAction = equals(FavoriteAction.add, action)
    ? '@addFavorite'
    : '@removeFavorite';

  interceptDetailsDashboard({ path });

  cy.findByRole('button', { name: 'FavoriteIconButton' }).as('favoriteIcon');
  manageAFavorite({
    action,
    buttonAlias: '@favoriteIcon',
    requestsToWait: [aliasRequestAction, '@getDashboardDetails']
  });
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
      cy.findAllByLabelText('Generic text')
        .eq(1)
        .type('Text for the new widget');

      cy.findAllByLabelText(labelSave).eq(1).click();
      cy.findAllByLabelText(labelSave).eq(1).should('be.disabled');

      cy.contains('Text for the new widget').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Edit widget', () => {
    it('edits a widget when the corresponding button is clicked and the widget type is changed the edit button is clicked', () => {
      const { store } = initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.contains(labelEditWidget).click();

      cy.findByLabelText(labelTitle).type(' updated', { force: true });
      cy.get('[data-testid="RichTextEditor"]').eq(1).type('Description');

      cy.url().should('include', 'edit=true');

      cy.findAllByLabelText(labelSave).eq(1).click();

      cy.contains(labelEditWidget).should('not.exist');
      cy.contains('Widget text updated')
        .should('be.visible')
        .then(() => {
          const dashboard = store.get(dashboardAtom);

          assert.equal(dashboard.layout.length, 3);
          assert.equal(
            dashboard.layout[0].options?.description?.content,
            '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Description","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1,"textFormat":0,"textStyle":""}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}'
          );
          assert.equal(dashboard.layout[0].name, 'centreon-widget-text');
        });

      cy.makeSnapshot();
    });

    it('resizes the widget to its minimum size when the handle is dragged', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.get('[data-canmove="true"]')
        .eq(0)
        .parent()
        .should('have.css', 'height')
        .and('equal', '232px');

      cy.get('[class*="react-resizable-handle-se"]')
        .eq(0)
        .realMouseDown()
        .realMouseMove(-70, -70)
        .realMouseMove(-70, -70)
        .realMouseUp();

      cy.get('[data-canmove="true"]')
        .eq(0)
        .parent()
        .should('have.css', 'height');
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
  });

  describe('Duplicate', () => {
    it('duplicates the widget when the corresponding button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.findByLabelText(labelDuplicate).click();

      cy.findAllByText('Widget text').should('have.length', 2);
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

      cy.makeSnapshot();
    });

    it('edits the dashboards when the refresh type and refresh are updated and the save button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findByLabelText('edit').click();

      cy.contains(labelGlobalRefreshInterval).should('be.visible');
      cy.contains(labelManualRefreshOnly).should('be.visible');

      cy.findByLabelText(labelInterval).type('15');
      cy.contains(labelManualRefreshOnly).click();
      cy.findByLabelText(labelUpdate).click();

      cy.waitForRequest('@updateDashboard').then(({ request }) => {
        expect(request.body).to.deep.equal({
          name: 'My Dashboard',
          description: 'my description',
          'refresh[type]': 'manual',
          'refresh[interval]': '1515'
        });
      });

      cy.contains(labelDashboardUpdated).should('be.visible');

      cy.makeSnapshot();
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

  it('saves an empty dashbord when widgets are removed and the save button is clicked', () => {
    initializeAndMount(editorRoles);

    cy.findAllByLabelText(labelMoreActions).eq(0).click();
    cy.contains(labelDeleteWidget).click();
    cy.findByLabelText(labelDelete).click();
    cy.findAllByLabelText(labelMoreActions).eq(0).click();
    cy.contains(labelDeleteWidget).click();
    cy.findByLabelText(labelDelete).click();
    cy.findByLabelText(labelMoreActions).click();
    cy.contains(labelDeleteWidget).click();
    cy.findByLabelText(labelDelete).click();
    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@updateDashboard').then(({ request }) => {
      expect(request.body['panels[]']).equal('');
      expect(request.body['thumbnail[directory]']).equal('dashboards');
      expect(request.body['thumbnail[name]']).equal('dashboard-1.png');
    });

    cy.contains(labelYourDashboardHasBeenSaved).should('be.visible');

    cy.makeSnapshot();
  });

  describe('Route blocking', () => {
    it('saves changes when a dashboard is being edited, a dashboard is updated, the user goes to another page and the corresponding button is clicked', () => {
      initializeAndMount({
        ...editorRoles,
        isBlocked: true
      });

      cy.contains(labelEditDashboard).click();

      cy.findAllByLabelText(labelMoreActions).eq(0).click();
      cy.findByLabelText(labelDuplicate).click();

      cy.contains(labelDoYouWantToSaveChanges).should('be.visible');
      cy.contains(labelIfYouClickOnDiscard).should('be.visible');

      cy.findByTestId('confirm').click();

      cy.waitForRequest('@updateDashboard').then(({ request }) => {
        const formData = new URLSearchParams(request.body);

        const formDataObj = {};
        formData.forEach((value, key) => {
          formDataObj[key] = value;
        });

        expect(formDataObj).to.include({
          'thumbnail[directory]': 'dashboards',
          'thumbnail[name]': 'dashboard-1.png'
        });
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
    });
  });

  describe('Dataset', () => {
    it('displays header link to Resources Status when the widget has resources compatible with Resource Status', () => {
      initializeAndMount({
        ...editorRoles,
        detailsWithData: true
      });

      cy.findAllByTestId('See more on the Resources Status page')
        .eq(0)
        .should(
          'have.attr',
          'href',
          '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbMy%20host%5C%5Cb%22%2C%22name%22%3A%22My%20host%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
        );
    });

    it('displays header link to Resources Status when the widget has resources compatible with Resource Status and the widget is single metric', () => {
      initializeAndMount({
        ...editorRoles,
        detailsWithData: true
      });

      cy.findAllByTestId('See more on the Resources Status page')
        .eq(2)
        .should(
          'have.attr',
          'href',
          '/monitoring/resources?details=%7B%22id%22%3Anull%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fhosts%2Fundefined%2Fservices%2Fundefined%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22graph%22%2C%22tabParameters%22%3A%7B%7D%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%5D%7D%2C%7B%22name%22%3A%22h.name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbCentreon-Server%5C%5Cb%22%2C%22name%22%3A%22Centreon-Server%22%7D%5D%7D%2C%7B%22name%22%3A%22name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbPing%5C%5Cb%22%2C%22name%22%3A%22Ping%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
        );
    });

    it('displays header link to Business activity when the widget has only business activities', () => {
      initializeAndMount({
        ...editorRoles,
        detailsWithData: true
      });

      cy.findAllByTestId('See more on the Business Activity page')
        .eq(0)
        .should('have.attr', 'href', '/main.php?p=20701&o=d&ba_id=1');
      cy.makeSnapshot();
    });
  });

  describe('Web Page widget', () => {
    beforeEach(() => initializeDashboardWithWebpageWidgets({}));

    it('renders Web Page widgets', () => {
      cy.findAllByTestId('Webpage Display').should('have.length', 2);
    });

    it('renders iframes with correct source URL', () => {
      cy.findAllByTestId('Webpage Display').should('have.length', 2);
      cy.findAllByTestId('Webpage Display')
        .eq(0)
        .should('have.attr', 'src', 'https://docs.centreon.com/fr/');
      cy.findAllByTestId('Webpage Display')
        .eq(1)
        .should('have.attr', 'src', 'https://react.dev/');
    });

    it('displays widget refresh buttons', () => {
      cy.findAllByTestId('Webpage Display').should('have.length', 2);

      cy.findAllByTestId('UpdateIcon').should('have.length', 2);
    });
  });

  describe('Managment favorite dashboards', () => {
    it('add a dashboard to favorites when clicking on the corresponding icon in the details view', () => {
      runFavoriteManagementFromDetails({
        action: FavoriteAction.add,
        customDetailsPath: 'Dashboards/Dashboard/details.json'
      });
      cy.makeSnapshot();
    });

    it('remove a dashboard from favorites when clicking on the corresponding icon in the details view', () => {
      interceptDashboardsFavoriteDelete(1);
      runFavoriteManagementFromDetails({
        action: FavoriteAction.delete,
        customDetailsPath: 'Dashboards/favorites/details.json'
      });
      cy.makeSnapshot();
    });

    it('disable favorite icon button on click', () => {
      initializeAndMount({
        customDetailsPath: 'Dashboards/Dashboard/details.json'
      });

      cy.waitForRequest('@getDashboardDetails');

      interceptDetailsDashboard({ path: 'Dashboards/favorites/details.json' });

      cy.findByRole('button', { name: 'FavoriteIconButton' }).as(
        'favoriteIcon'
      );
      cy.get('@favoriteIcon').dblclick();
      cy.get('@favoriteIcon').should('be.disabled');

      const labelSuccess = labelDashboardAddedToFavorites;

      const updatedTitle = labelRemoveFromFavorites;

      cy.waitForRequest('@addFavorite');

      cy.waitForRequest('@getDashboardDetails');

      cy.get('@favoriteIcon').should('be.enabled');

      cy.findByText(labelSuccess).should('be.visible');

      cy.get('@favoriteIcon').trigger('mouseover');

      cy.findByText(updatedTitle).should('be.visible');
    });
  });
});
