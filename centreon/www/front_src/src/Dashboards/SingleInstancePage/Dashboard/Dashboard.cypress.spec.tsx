import widgetGenericTextProperties from './Widgets/centreon-widget-generictext/properties.json';
import widgetInputProperties from './Widgets/centreon-widget-input/properties.json';
import widgetSingleMetricProperties from './Widgets/centreon-widget-singlemetric/properties.json';
import widgetTextProperties from './Widgets/centreon-widget-text/properties.json';
import widgetWebpageProperties from './Widgets/centreon-widget-webpage/properties.json';

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

import { federatedWidgetsPropertiesAtom } from '../../../federatedModules/atoms';
import {
  dashboardSharesEndpoint,
  dashboardsContactsEndpoint,
  dashboardsEndpoint,
  getDashboardEndpoint
} from '../../api/endpoints';
import { DashboardRole } from '../../api/models';
import {
  labelAddAContact,
  labelDashboardUpdated,
  labelDelete,
  labelExtend,
  labelReduce,
  labelSharesSaved,
  labelUpdate
} from '../../translatedLabels';
import Dashboard from './Dashboard';
import { internalWidgetComponents } from './Widgets/widgets';
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
  {}
);

const initializeWidgets = (): ReturnType<typeof createStore> => {
  const store = createStore();
  store.set(federatedWidgetsAtom, internalWidgetComponents);
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
  store?;
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
  customDetailsPath,
  store=initializeWidgets()
}: InitializeAndMountProps): {
  blockNavigation;
  proceedNavigation;
  store: ReturnType<typeof createStore>;
} => {
  // const store = initializeWidgets();

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

  console.log({customDetailsPath})

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
      <div style={{ height: '90vh' }}>
        <TestQueryProvider>
          <BrowserRouter>
            <SnackbarProvider>
              <Provider store={store}>
                <Dashboard />
              </Provider>
            </SnackbarProvider>
          </BrowserRouter>
        </TestQueryProvider>
      </div>
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
  canAdministrateDashboard = true,
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
    });

    it('adds a widget according to its custom default size when a widget type is selected and the submission button is clicked', () => {
      initializeAndMount(editorRoles);

      cy.waitForRequest('@getDashboardDetails');

      cy.findByLabelText(labelAddAWidget).click();

      cy.findByLabelText(labelWidgetType).click();

      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelTitle).type('Generic input');
      cy.findAllByLabelText('Generic text')
        .eq(1)
        .type('Text for the new widget');

      cy.findAllByLabelText(labelSave).eq(1).click();
      cy.findAllByLabelText(labelSave).eq(1).should('be.disabled');

      cy.get('.react-grid-item')
        .eq(3)
        .should('have.css', 'transform', 'matrix(1, 0, 0, 1, 4, 252)');
      cy.get('.react-grid-item').eq(3).should('have.css', 'width', '585px');

      cy.get('.react-grid-item').eq(3).should('have.css', 'height', '484px');
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

  describe.only('Expand\Reduce',()=>{
    it('expandes the widget when the corresponding icon is clicked',()=>{

    const federatedWidgets = [
        {
            "moduleName": "centreon-widget-graph",
            "remoteEntry": "",
            "moduleFederationName": "centreon-widget-graph",
            "federatedPages": [],
            "federatedComponentsConfiguration": [
                {
                    "path": "/widgets/graph",
                    "federatedComponents": [],
                    "name": "graph",
                    "panelMinHeight": 3,
                    "panelMinWidth": 4,
                    "panelDefaultHeight": 4,
                    "panelDefaultWidth": 6
                }
            ],
            "Component": {
                "_payload": {
                    "_status": -1
                }
            },
            "properties": {
                "title": "Metrics graph",
                "description": "Displays metrics for a given time period.",
                "icon": "<path d=\"M41.8,54.4c-0.1,0-0.2,0-0.3,0c-4.7-0.4-6.2-9.5-7.7-19.2c-0.8-4.9-2.2-13.2-3.6-13.6c-1.6,0.1-3.2,6.2-4.1,10.1c-1.7,7-3.6,14.3-8.4,14.3c-3.8,0-5.2-4.1-6.8-8.4c-1.6-4.8-3.5-10.1-8.4-13c-0.7-0.3-0.9-1.2-0.5-1.9c0.4-0.5,1.3-0.8,2-0.4c5.9,3.3,8,9.5,9.7,14.4c1.4,4,2.3,6.5,4.1,6.5c2.6,0,4.4-6.8,5.7-12.2c1.6-6.4,2.9-12,6.5-12.2c3.9-0.3,5,6.4,6.5,15.8c1.1,6.7,2.7,16.7,5.2,16.9l0,0c1.7,0,2.8-9.1,3.5-15.1c1.3-11.8,2.8-25.1,9.7-29.8c0.7-0.4,1.4-0.3,1.9,0.3c0.4,0.7,0.3,1.4-0.3,1.9c-5.8,4-7.3,17.2-8.5,27.9C46.8,47,45.9,54.4,41.8,54.4z\"/>",
                "options": {
                    "timeperiod": {
                        "type": "time-period",
                        "defaultValue": {
                            "start": null,
                            "end": null,
                            "timePeriodType": 1
                        }
                    },
                    "threshold": {
                        "type": "threshold",
                        "defaultValue": {
                            "enabled": true,
                            "customCritical": null,
                            "criticalType": "default",
                            "customWarning": null,
                            "warningType": "default"
                        },
                        "label": "threshold"
                    },
                    "refreshInterval": {
                        "type": "refresh-interval",
                        "defaultValue": "default",
                        "label": "Interval"
                    }
                },
                "data": {
                    "resources": {
                        "type": "resources",
                        "defaultValue": [],
                        "required": true
                    },
                    "metrics": {
                        "type": "metrics",
                        "defaultValue": []
                    }
                },
                "categories": {
                    "Graph settings": {
                        "groups": [
                            {
                                "id": "graphStyles",
                                "name": "Graph style"
                            },
                            {
                                "id": "axis",
                                "name": "Axes"
                            },
                            {
                                "id": "legend",
                                "name": "Legend"
                            },
                            {
                                "id": "tooltip",
                                "name": "Tooltips"
                            }
                        ],
                        "elements": {
                            "displayType": {
                                "type": "displayType",
                                "defaultValue": "line",
                                "options": [
                                    {
                                        "id": "line",
                                        "label": "Line",
                                        "icon": "<path d=\"M41.8,54.4c-0.1,0-0.2,0-0.3,0c-4.7-0.4-6.2-9.5-7.7-19.2c-0.8-4.9-2.2-13.2-3.6-13.6c-1.6,0.1-3.2,6.2-4.1,10.1c-1.7,7-3.6,14.3-8.4,14.3c-3.8,0-5.2-4.1-6.8-8.4c-1.6-4.8-3.5-10.1-8.4-13c-0.7-0.3-0.9-1.2-0.5-1.9c0.4-0.5,1.3-0.8,2-0.4c5.9,3.3,8,9.5,9.7,14.4c1.4,4,2.3,6.5,4.1,6.5c2.6,0,4.4-6.8,5.7-12.2c1.6-6.4,2.9-12,6.5-12.2c3.9-0.3,5,6.4,6.5,15.8c1.1,6.7,2.7,16.7,5.2,16.9l0,0c1.7,0,2.8-9.1,3.5-15.1c1.3-11.8,2.8-25.1,9.7-29.8c0.7-0.4,1.4-0.3,1.9,0.3c0.4,0.7,0.3,1.4-0.3,1.9c-5.8,4-7.3,17.2-8.5,27.9C46.8,47,45.9,54.4,41.8,54.4z\"/>"
                                    },
                                    {
                                        "id": "bar",
                                        "label": "Bar",
                                        "icon": "<svg viewBox=\"0 0 65 65\"><rect x=\"5\" y=\"38.51\" width=\"12\" height=\"20\" rx=\"4.26\" ry=\"4.26\" /><rect x=\"19.33\" y=\"28.51\" width=\"12\" height=\"30\" rx=\"4\" ry=\"4\" /><rect x=\"33.67\" y=\"16.51\" width=\"12\" height=\"41.99\" rx=\"4\" ry=\"4\" /><rect x=\"48\" y=\"9.51\" width=\"12\" height=\"49\" rx=\"4.32\" ry=\"4.32\" /></svg>"
                                    },
                                    {
                                        "id": "bar-stacked",
                                        "label": "Stacked bar",
                                        "icon": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 65 65\"><defs><style>.cls-1 {fill: #a2a2a2;}.cls-2 {fill: #666;}.cls-4 {fill: #ccc;}</style></defs><rect x=\"33.67\" y=\"14.51\" width=\"12\" height=\"44\" rx=\"4\" ry=\"4\" /><path class=\"cls-1\" d=\"M5,38.5h12v16c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-16h0Z\" /><rect class=\"cls-2\" x=\"5\" y=\"24.5\" width=\"12\" height=\"14\" /><path class=\"cls-4\" d=\"M9,14.5h4c2.21,0,4,1.79,4,4v6H5v-6c0-2.21,1.79-4,4-4Z\" /><path class=\"cls-1\" d=\"M19.33,43.5h12v11c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-11h0Z\" /><rect class=\"cls-2\" x=\"19.33\" y=\"21.5\" width=\"12\" height=\"22\" /><path class=\"cls-4\" d=\"M23.33,7.5h4c2.21,0,4,1.79,4,4v10h-12v-10c0-2.21,1.79-4,4-4Z\" /><path class=\"cls-1\" d=\"M48,52.5h12v2c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-2h0Z\" /><rect class=\"cls-2\" x=\"48\" y=\"43.5\" width=\"12\" height=\"9\" /><path class=\"cls-4\" d=\"M52,26.5h4c2.21,0,4,1.79,4,4v13h-12v-13c0-2.21,1.79-4,4-4Z\" /><path class=\"cls-1\" d=\"M33.67,29.5h12v25c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-25h0Z\" /><rect class=\"cls-2\" x=\"33.67\" y=\"23.5\" width=\"12\" height=\"6\" /><path class=\"cls-4\" d=\"M37.67,14.5h4c2.21,0,4,1.79,4,4v5h-12v-5c0-2.21,1.79-4,4-4Z\" /></svg>"
                                    }
                                ]
                            },
                            "curveType": {
                                "group": "graphStyles",
                                "type": "select",
                                "label": "Curve type",
                                "defaultValue": "linear",
                                "options": [
                                    {
                                        "id": "linear",
                                        "name": "Linear"
                                    },
                                    {
                                        "id": "natural",
                                        "name": "Smooth"
                                    },
                                    {
                                        "id": "step",
                                        "name": "Step"
                                    }
                                ],
                                "hiddenCondition": {
                                    "target": "options",
                                    "method": "includes",
                                    "when": "options.displayType",
                                    "matches": [
                                        "bar",
                                        "bar-stacked"
                                    ]
                                }
                            },
                            "showPoints": {
                                "group": "graphStyles",
                                "type": "switch",
                                "label": "Display curve points",
                                "defaultValue": false,
                                "hiddenCondition": {
                                    "target": "options",
                                    "when": "options.displayType",
                                    "method": "includes",
                                    "matches": [
                                        "bar",
                                        "bar-stacked"
                                    ]
                                }
                            },
                            "lineWidthMode": {
                                "group": "graphStyles",
                                "type": "button-group",
                                "label": "Line width",
                                "defaultValue": "auto",
                                "secondaryLabel": "Auto: Default value defined for the corresponding curve template (Monitoring > Performances > Curves).",
                                "options": [
                                    {
                                        "id": "auto",
                                        "name": "Auto"
                                    },
                                    {
                                        "id": "custom",
                                        "name": "Custom"
                                    }
                                ],
                                "subInputs": [
                                    {
                                        "name": "lineWidth",
                                        "displayValue": "custom",
                                        "input": {
                                            "type": "slider",
                                            "defaultValue": 2,
                                            "slider": {
                                                "min": 0,
                                                "max": 10,
                                                "unit": "px"
                                            }
                                        }
                                    }
                                ],
                                "hiddenCondition": {
                                    "target": "options",
                                    "method": "includes",
                                    "when": "options.displayType",
                                    "matches": [
                                        "bar",
                                        "bar-stacked"
                                    ]
                                }
                            },
                            "showArea": {
                                "group": "graphStyles",
                                "type": "button-group",
                                "label": "Area",
                                "defaultValue": "auto",
                                "secondaryLabel": "Auto: Default value defined for the corresponding curve template (Monitoring > Performances > Curves).",
                                "options": [
                                    {
                                        "id": "auto",
                                        "name": "Auto"
                                    },
                                    {
                                        "id": "show",
                                        "name": "Show"
                                    },
                                    {
                                        "id": "hide",
                                        "name": "Hide"
                                    }
                                ],
                                "subInputs": [
                                    {
                                        "name": "areaOpacity",
                                        "displayValue": "show",
                                        "input": {
                                            "type": "slider",
                                            "defaultValue": 20,
                                            "label": "Fill opacity",
                                            "slider": {
                                                "max": 100,
                                                "min": 0,
                                                "unit": "%"
                                            }
                                        }
                                    }
                                ],
                                "hiddenCondition": {
                                    "target": "options",
                                    "method": "includes",
                                    "when": "options.displayType",
                                    "matches": [
                                        "bar",
                                        "bar-stacked"
                                    ]
                                }
                            },
                            "lineStyleMode": {
                                "group": "graphStyles",
                                "type": "button-group",
                                "defaultValue": "solid",
                                "label": "Line style",
                                "hiddenCondition": {
                                    "target": "options",
                                    "method": "includes",
                                    "when": "options.displayType",
                                    "matches": [
                                        "bar",
                                        "bar-stacked"
                                    ]
                                },
                                "options": [
                                    {
                                        "id": "solid",
                                        "name": "Solid"
                                    },
                                    {
                                        "id": "dash",
                                        "name": "Dashes"
                                    },
                                    {
                                        "id": "dots",
                                        "name": "Dots"
                                    }
                                ],
                                "subInputs": [
                                    {
                                        "name": "dashLength",
                                        "displayValue": "dash",
                                        "direction": "row",
                                        "input": {
                                            "type": "textfield",
                                            "defaultValue": 5,
                                            "label": "Dash width",
                                            "text": {
                                                "size": "compact",
                                                "type": "number",
                                                "min": 0,
                                                "autoSize": true
                                            }
                                        }
                                    },
                                    {
                                        "name": "dashOffset",
                                        "displayValue": "dash",
                                        "direction": "row",
                                        "input": {
                                            "type": "textfield",
                                            "defaultValue": 5,
                                            "label": "Space",
                                            "text": {
                                                "size": "compact",
                                                "type": "number",
                                                "min": 0,
                                                "autoSize": true
                                            }
                                        }
                                    },
                                    {
                                        "name": "",
                                        "displayValue": "dash",
                                        "direction": "row",
                                        "input": {
                                            "type": "text",
                                            "label": "px"
                                        }
                                    },
                                    {
                                        "name": "dotOffset",
                                        "displayValue": "dots",
                                        "direction": "row",
                                        "input": {
                                            "type": "textfield",
                                            "defaultValue": 5,
                                            "label": "Space",
                                            "text": {
                                                "size": "compact",
                                                "type": "number",
                                                "min": 0,
                                                "autoSize": true
                                            }
                                        }
                                    },
                                    {
                                        "name": "",
                                        "displayValue": "dots",
                                        "direction": "row",
                                        "input": {
                                            "type": "text",
                                            "label": "px"
                                        }
                                    }
                                ]
                            },
                            "orientation": {
                                "group": "graphStyles",
                                "type": "button-group",
                                "label": "Orientation",
                                "defaultValue": "auto",
                                "options": [
                                    {
                                        "id": "auto",
                                        "name": "Auto"
                                    },
                                    {
                                        "id": "vertical",
                                        "name": "Vertical"
                                    },
                                    {
                                        "id": "horizontal",
                                        "name": "Horizontal"
                                    }
                                ],
                                "hiddenCondition": {
                                    "target": "options",
                                    "method": "equals",
                                    "when": "options.displayType",
                                    "matches": "line"
                                }
                            },
                            "barRadius": {
                                "group": "graphStyles",
                                "type": "slider",
                                "label": "Bar radius",
                                "defaultValue": 0,
                                "slider": {
                                    "min": 0,
                                    "max": 100,
                                    "unit": "%"
                                },
                                "hiddenCondition": {
                                    "target": "options",
                                    "method": "includes",
                                    "when": "options.displayType",
                                    "matches": [
                                        "line"
                                    ]
                                }
                            },
                            "barOpacity": {
                                "group": "graphStyles",
                                "type": "slider",
                                "label": "Bar opacity",
                                "defaultValue": 100,
                                "slider": {
                                    "min": 0,
                                    "max": 100,
                                    "unit": "%"
                                },
                                "hiddenCondition": {
                                    "target": "options",
                                    "method": "equals",
                                    "when": "options.displayType",
                                    "matches": "line"
                                }
                            },
                            "showAxisBorder": {
                                "type": "switch",
                                "label": "Show axis borders",
                                "group": "axis",
                                "defaultValue": true
                            },
                            "yAxisTickLabelRotation": {
                                "group": "axis",
                                "type": "slider",
                                "label": "Y-axis label rotation",
                                "defaultValue": 0,
                                "slider": {
                                    "min": -45,
                                    "max": 45,
                                    "unit": "°"
                                }
                            },
                            "isCenteredZero": {
                                "type": "switch",
                                "label": "Zero-centered",
                                "group": "axis",
                                "defaultValue": false
                            },
                            "showGridLines": {
                                "type": "switch",
                                "label": "Show gridlines",
                                "group": "axis",
                                "defaultValue": true,
                                "subInputs": [
                                    {
                                        "name": "gridLinesType",
                                        "displayValue": true,
                                        "input": {
                                            "group": "axis",
                                            "type": "button-group",
                                            "label": "Gridline type",
                                            "defaultValue": "all",
                                            "options": [
                                                {
                                                    "id": "horizontal",
                                                    "name": "Horizontal"
                                                },
                                                {
                                                    "id": "vertical",
                                                    "name": "Vertical"
                                                },
                                                {
                                                    "id": "all",
                                                    "name": "Both"
                                                }
                                            ]
                                        }
                                    }
                                ]
                            },
                            "scale": {
                                "group": "axis",
                                "type": "button-group",
                                "label": "Scale",
                                "defaultValue": "linear",
                                "options": [
                                    {
                                        "id": "linear",
                                        "name": "Linear"
                                    },
                                    {
                                        "id": "logarithmic",
                                        "name": "Logarithmic"
                                    }
                                ],
                                "subInputs": [
                                    {
                                        "name": "scaleLogarithmicBase",
                                        "input": {
                                            "type": "radio",
                                            "defaultValue": "2",
                                            "options": [
                                                {
                                                    "id": "2",
                                                    "name": "Base 2"
                                                },
                                                {
                                                    "id": "10",
                                                    "name": "Base 10"
                                                }
                                            ]
                                        },
                                        "displayValue": "logarithmic"
                                    }
                                ]
                            },
                            "showLegend": {
                                "group": "legend",
                                "type": "switch",
                                "label": "Show legend",
                                "defaultValue": true,
                                "subInputs": [
                                    {
                                        "name": "legendDisplayMode",
                                        "displayValue": true,
                                        "input": {
                                            "group": "legend",
                                            "type": "button-group",
                                            "label": "Display mode",
                                            "defaultValue": "grid",
                                            "options": [
                                                {
                                                    "id": "grid",
                                                    "name": "Grid"
                                                },
                                                {
                                                    "id": "list",
                                                    "name": "List"
                                                }
                                            ]
                                        }
                                    },
                                    {
                                        "name": "legendPlacement",
                                        "displayValue": true,
                                        "input": {
                                            "group": "legend",
                                            "type": "button-group",
                                            "label": "Position",
                                            "defaultValue": "bottom",
                                            "options": [
                                                {
                                                    "id": "left",
                                                    "name": "Left"
                                                },
                                                {
                                                    "id": "bottom",
                                                    "name": "Bottom"
                                                },
                                                {
                                                    "id": "right",
                                                    "name": "Right"
                                                }
                                            ]
                                        }
                                    }
                                ]
                            },
                            "tooltipMode": {
                                "group": "tooltip",
                                "type": "button-group",
                                "label": "Display",
                                "defaultValue": "all",
                                "options": [
                                    {
                                        "id": "all",
                                        "name": "All"
                                    },
                                    {
                                        "id": "single",
                                        "name": "Only one"
                                    },
                                    {
                                        "id": "hidden",
                                        "name": "Hidden"
                                    }
                                ]
                            },
                            "tooltipSortOrder": {
                                "group": "tooltip",
                                "type": "button-group",
                                "label": "Value sort order",
                                "defaultValue": "name",
                                "options": [
                                    {
                                        "id": "name",
                                        "name": "By name"
                                    },
                                    {
                                        "id": "ascending",
                                        "name": "Ascending"
                                    },
                                    {
                                        "id": "descending",
                                        "name": "Descending"
                                    }
                                ]
                            }
                        }
                    }
                },
                "moduleName": "centreon-widget-graph",
                "canExtand": true
            }
        },
        {
            "moduleName": "centreon-widget-topbottom",
            "remoteEntry": "",
            "moduleFederationName": "centreon-widget-topbottom",
            "federatedPages": [],
            "federatedComponentsConfiguration": [
                {
                    "path": "/widgets/topbottom",
                    "federatedComponents": [],
                    "name": "topbottom"
                }
            ],
            "Component": {
                "_payload": {
                    "_status": -1
                }
            },
            "properties": {
                "title": "Top/bottom",
                "description": "Displays the top or bottom x hosts, for a selected metric.",
                "icon": "<rect x=\"6\" y=\"25\" width=\"30\" height=\"10\" rx=\"4\" ry=\"4\"></rect><rect x=\"6\" y=\"11\" width=\"20\" height=\"10\" rx=\"4\" ry=\"4\"></rect><rect x=\"6\" y=\"39\" width=\"48\" height=\"10\" rx=\"4\" ry=\"4\"></rect>",
                "options": {
                    "topBottomSettings": {
                        "type": "top-bottom-settings",
                        "label": "Top Bottom Settings",
                        "defaultValue": {
                            "numberOfValues": 10,
                            "order": "top",
                            "showLabels": true
                        }
                    },
                    "valueFormat": {
                        "type": "value-format",
                        "defaultValue": "human",
                        "label": "Value format"
                    },
                    "threshold": {
                        "type": "threshold",
                        "defaultValue": {
                            "enabled": true,
                            "customCritical": null,
                            "criticalType": "default",
                            "customWarning": null,
                            "warningType": "default",
                            "baseColor": null
                        },
                        "label": "threshold"
                    },
                    "refreshInterval": {
                        "type": "refresh-interval",
                        "defaultValue": "default",
                        "label": "Interval"
                    }
                },
                "data": {
                    "resources": {
                        "type": "resources",
                        "defaultValue": []
                    },
                    "metrics": {
                        "type": "metrics",
                        "defaultValue": []
                    }
                },
                "singleMetricSelection": true,
                "customBaseColor": false,
                "moduleName": "centreon-widget-topbottom",
                "canExtand": true
            }
        },
        {
            "moduleName": "centreon-widget-statusgrid",
            "remoteEntry": "",
            "moduleFederationName": "centreon-widget-statusgrid",
            "federatedPages": [],
            "federatedComponentsConfiguration": [
                {
                    "path": "/widgets/statusgrid",
                    "federatedComponents": [],
                    "name": "statusgrid",
                    "panelDefaultHeight": 3
                }
            ],
            "Component": {
                "_payload": {
                    "_status": -1
                }
            },
            "properties": {
                "title": "Status grid",
                "description": "Displays a status overview for selected resources, grouped by hosts or by services.",
                "icon": "<rect x=\"6.51\" y=\"6\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect><rect x=\"6.51\" y=\"32\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect><rect x=\"32.51\" y=\"32\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect><rect x=\"32.51\" y=\"6\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect>",
                "options": {
                    "viewMode": {
                        "type": "radio",
                        "label": "Display with this view",
                        "options": [
                            {
                                "id": "standard",
                                "name": "Standard"
                            },
                            {
                                "id": "condensed",
                                "name": "Condensed"
                            }
                        ],
                        "defaultValue": "standard"
                    },
                    "resourceType": {
                        "type": "radio",
                        "label": "Display resources with this type",
                        "options": [
                            {
                                "id": "host",
                                "name": "Host"
                            },
                            {
                                "id": "service",
                                "name": "Service"
                            }
                        ],
                        "defaultValue": "host",
                        "hiddenCondition": {
                            "target": "data",
                            "when": "data.resources",
                            "property": "resourceType",
                            "method": "includes",
                            "matches": [
                                "business-view",
                                "business-activity"
                            ]
                        }
                    },
                    "statuses": {
                        "type": "checkbox",
                        "label": "Display resources with this status",
                        "options": [
                            {
                                "id": "problem",
                                "name": "Problem (Down/Critical)"
                            },
                            {
                                "id": "warning",
                                "name": "Warning"
                            },
                            {
                                "id": "pending",
                                "name": "Pending"
                            },
                            {
                                "id": "success",
                                "name": "Success (OK/Up)"
                            },
                            {
                                "id": "undefined",
                                "name": "Undefined (Unreachable/Unknown)"
                            }
                        ],
                        "defaultValue": [
                            "warning",
                            "problem"
                        ]
                    },
                    "sortBy": {
                        "type": "radio",
                        "label": "Sort by",
                        "options": [
                            {
                                "id": "status_severity_code",
                                "name": "Status"
                            },
                            {
                                "id": "name",
                                "name": "Name"
                            }
                        ],
                        "defaultValue": "status_severity_code",
                        "hiddenCondition": {
                            "target": "options",
                            "when": "options.viewMode",
                            "method": "equals",
                            "matches": "condensed"
                        }
                    },
                    "tiles": {
                        "type": "tiles",
                        "defaultValue": 100,
                        "hiddenCondition": {
                            "target": "options",
                            "when": "options.viewMode",
                            "method": "equals",
                            "matches": "condensed"
                        }
                    },
                    "refreshInterval": {
                        "type": "refresh-interval",
                        "defaultValue": "default",
                        "label": "Interval"
                    }
                },
                "data": {
                    "resources": {
                        "type": "resources",
                        "defaultValue": [],
                        "required": false,
                        "useAdditionalResources": true,
                        "excludedResourceTypes": [
                            "meta-service"
                        ]
                    }
                },
                "moduleName": "centreon-widget-statusgrid",
                "canExtand": true
            }
        },
        {
            "moduleName": "centreon-widget-statuschart",
            "remoteEntry": "",
            "moduleFederationName": "centreon-widget-statuschart",
            "federatedPages": [],
            "federatedComponentsConfiguration": [
                {
                    "path": "/widgets/statuschart",
                    "federatedComponents": [],
                    "name": "statuschart",
                    "panelMinWidth": 2,
                    "panelMinHeight": 3
                }
            ],
            "Component": {
                "_payload": {
                    "_status": -1
                }
            },
            "properties": {
                "title": "Status chart",
                "description": "Displays a detailed view of the current status for selected resources as a chart.",
                "icon": "<svg viewBox=\"0 0 24 24\"><g><path d=\"M16.36,5.87c-.06.08-.17.1-.26.05-.53-.36-1.11-.65-1.72-.86-.61-.21-1.24-.34-1.88-.38-.1,0-.18-.09-.18-.2l.07-1.69c0-.21.19-.38.41-.36.79.07,1.57.23,2.32.49.75.26,1.46.61,2.13,1.04.18.12.22.36.1.53l-.98,1.38Z\"/><path d=\"M12,21.6c-.08,0-.16,0-.24,0-2.55-.06-4.93-1.13-6.7-2.99-1.77-1.87-2.72-4.3-2.65-6.85.06-2.55,1.13-4.93,2.99-6.7,1.77-1.68,4.05-2.62,6.45-2.65.21,0,.39.17.38.39l-.04,1.69c0,.1-.09.18-.19.18-1.88,0-3.66.72-5.05,2.03-1.43,1.36-2.24,3.18-2.29,5.12s.67,3.8,2.03,5.23c1.36,1.43,3.18,2.24,5.12,2.29,1.94.05,3.8-.67,5.23-2.03,1.43-1.36,2.24-3.18,2.29-5.12.06-2.33-1-4.55-2.83-5.98-.08-.06-.1-.18-.04-.26l1-1.36c.13-.17.37-.2.54-.07,2.33,1.87,3.66,4.73,3.58,7.73-.06,2.55-1.13,4.93-2.99,6.7-1.81,1.72-4.14,2.66-6.6,2.66Z\"/></g></svg>",
                "options": {
                    "displayType": {
                        "type": "displayType",
                        "options": [
                            {
                                "icon": "<svg viewBox=\"0 0 65 65\"> <rect width=\"65\" height=\"65\" rx=\"8\" ry=\"8\" fill=\"none\"/> <g id=\"Groupe_3913\" data-name=\"Groupe 3913\"> <path fill=\"#666\" class=\"cls-3\" d=\"M43.9,16.53c-.16.22-.46.27-.68.12-2.79-1.88-6.04-3-9.4-3.22-.27-.02-.47-.24-.46-.51l.19-4.39c.02-.54.48-.96,1.02-.94.01,0,.02,0,.04,0,4.13.35,8.12,1.71,11.59,3.97.45.3.58.9.28,1.36,0,.01-.01.02-.02.03l-2.55,3.58Z\"/> <path fill=\"#a2a2a2\" data-name=\"Tracé 3893\" d=\"M32.53,57.5c-.21,0-.42,0-.63,0-13.79-.32-24.72-11.75-24.39-25.53.16-6.65,2.96-12.97,7.8-17.54,4.53-4.35,10.55-6.82,16.83-6.91.54,0,.99.43.99.97,0,.01,0,.02,0,.03l-.11,4.4c0,.26-.23.47-.49.47-10.57,0-19.14,8.56-19.14,19.12,0,10.56,8.57,19.12,19.14,19.12s19.14-8.56,19.14-19.12c0-5.9-2.73-11.47-7.39-15.09-.21-.16-.26-.46-.1-.68l2.61-3.54c.32-.44.94-.53,1.38-.2,0,0,.02.01.02.02,10.76,8.69,12.43,24.45,3.74,35.2-4.74,5.85-11.86,9.27-19.4,9.29\"/> </g> </svg>",
                                "label": "Donut chart",
                                "id": "donut"
                            },
                            {
                                "icon": "<svg viewBox=\"0 0 65 65\"> <rect width=\"65\" height=\"65\" rx=\"8\" ry=\"8\" fill=\"none\"/> <g> <ellipse fill=\"#a2a2a2\" cx=\"31.84\" cy=\"33.48\" rx=\"24.34\" ry=\"24.02\"/> <path fill=\"#666\" d=\"M37.46,7.5c9.46,1.95,17.13,8.88,20.04,18.1-24.81,7.6-.57.2-25.66,7.84,5.42-25.16.35-1.35,5.63-25.94Z\"/> </g> </svg>",
                                "label": "Pie chart",
                                "id": "pie"
                            },
                            {
                                "icon": "<svg viewBox=\"0 0 65 65\"> <rect width=\"65\" height=\"65\" rx=\"8\" ry=\"8\" fill=\"none\" /> <g id=\"Groupe_3909\" data-name=\"Groupe 3909\"> <g> <rect x=\"7.5\" y=\"36.5\" width=\"50\" height=\"12\" rx=\"4\" ry=\"4\" fill=\"#a2a2a2\"/> <rect fill=\"#666\" x=\"7.5\" y=\"36.5\" width=\"32\" height=\"12\" rx=\"4\" ry=\"4\"/> </g> <g> <rect x=\"7.5\" y=\"16.5\" width=\"50\" height=\"12\" rx=\"4\" ry=\"4\" fill=\"#a2a2a2\"/> <rect fill=\"#666\" x=\"7.5\" y=\"16.5\" width=\"20\" height=\"12\" rx=\"4\" ry=\"4\"/> </g> </g> </svg>",
                                "label": "Horizontal bar chart",
                                "id": "horizontal"
                            },
                            {
                                "icon": "<svg viewBox=\"0 0 65 65\"> <rect/> <g > <g> <rect fill=\"#a2a2a2\" x=\"36.5\" y=\"7.5\" width=\"12\" height=\"50\" rx=\"4\" ry=\"4\"/> <rect fill=\"#666\" x=\"36.5\" y=\"25.5\" width=\"12\" height=\"32\" rx=\"4\" ry=\"4\"/> </g> <g > <rect fill=\"#a2a2a2\" x=\"16.5\" y=\"7.5\" width=\"12\" height=\"50\" rx=\"4\" ry=\"4\"/> <rect fill=\"#666\" x=\"16.5\" y=\"37.5\" width=\"12\" height=\"20\" rx=\"4\" ry=\"4\"/> </g> </g> </svg>",
                                "label": "Vertical bar chart",
                                "id": "vertical"
                            }
                        ],
                        "defaultValue": "donut"
                    },
                    "resourceTypes": {
                        "type": "checkbox",
                        "label": "Display resources with this type",
                        "options": [
                            {
                                "id": "host",
                                "name": "Host"
                            },
                            {
                                "id": "service",
                                "name": "Service"
                            }
                        ],
                        "defaultValue": [
                            "host",
                            "service"
                        ],
                        "keepOneOptionSelected": true
                    },
                    "unit": {
                        "type": "radio",
                        "label": "Display resources with this unit",
                        "options": [
                            {
                                "id": "percentage",
                                "name": "Percentage"
                            },
                            {
                                "id": "number",
                                "name": "Number"
                            }
                        ],
                        "defaultValue": "percentage"
                    },
                    "displayValues": {
                        "type": "switch",
                        "label": "Show value on chart",
                        "defaultValue": true
                    },
                    "displayLegend": {
                        "type": "switch",
                        "label": "Show legend",
                        "secondaryLabel": "Legend",
                        "defaultValue": true
                    },
                    "refreshInterval": {
                        "type": "refresh-interval",
                        "defaultValue": "default",
                        "label": "Interval"
                    }
                },
                "data": {
                    "resources": {
                        "type": "resources",
                        "defaultValue": [],
                        "required": false,
                        "excludedResourceTypes": [
                            "meta-service"
                        ]
                    }
                },
                "moduleName": "centreon-widget-statuschart",
                "canExtand": true
            }
        },
        {
            "moduleName": "centreon-widget-resourcestable",
            "remoteEntry": "",
            "moduleFederationName": "centreon-widget-resourcestable",
            "federatedPages": [],
            "federatedComponentsConfiguration": [
                {
                    "path": "/widgets/resourcestable",
                    "federatedComponents": [],
                    "name": "resourcestable",
                    "panelMinWidth": 6,
                    "panelMinHeight": 3,
                    "panelDefaultHeight": 4,
                    "panelDefaultWidth": 6
                }
            ],
            "Component": {
                "_payload": {
                    "_status": -1
                }
            },
            "properties": {
                "title": "Resource table",
                "description": "Displays data on resource status and events, centralized in a table.",
                "icon": "<g><path  d=\"M49.2,6H10.8c-2.65,0-4.8,2.15-4.8,4.8v38.4c0,2.65,2.15,4.8,4.8,4.8h38.4c2.65,0,4.8-2.15,4.8-4.8V10.8c0-2.65-2.15-4.8-4.8-4.8ZM52.08,18.48v16.1h-13.47v-16.1h13.47ZM23.63,34.58v-16.1h12.99v16.1h-12.99ZM36.61,36.58v15.5h-12.99v-15.5h12.99ZM21.63,18.48v16.1H7.92v-16.1h13.71ZM7.92,49.2v-12.62h13.71v15.5h-10.83c-1.59,0-2.88-1.29-2.88-2.88ZM49.2,52.08h-10.59v-15.5h13.47v12.62c0,1.59-1.29,2.88-2.88,2.88\"/></g>",
                "options": {
                    "statuses": {
                        "type": "checkbox",
                        "label": "Display resources with this status",
                        "options": [
                            {
                                "id": "problem",
                                "name": "Problem (Down/Critical)"
                            },
                            {
                                "id": "warning",
                                "name": "Warning"
                            },
                            {
                                "id": "pending",
                                "name": "Pending"
                            },
                            {
                                "id": "success",
                                "name": "Success (OK/Up)"
                            },
                            {
                                "id": "undefined",
                                "name": "Undefined (Unreachable/Unknown)"
                            }
                        ],
                        "defaultValue": [
                            "warning",
                            "problem"
                        ]
                    },
                    "states": {
                        "type": "checkbox",
                        "label": "Display resources with this state",
                        "options": [
                            {
                                "id": "unhandled_problems",
                                "name": "Unhandled"
                            },
                            {
                                "id": "acknowledged",
                                "name": "Acknowledged"
                            },
                            {
                                "id": "in_downtime",
                                "name": "In downtime"
                            }
                        ],
                        "defaultValue": [
                            "unhandled_problems"
                        ]
                    },
                    "statusTypes": {
                        "type": "checkbox",
                        "label": "Display resources with these status types",
                        "options": [
                            {
                                "id": "hard",
                                "name": "Hard"
                            },
                            {
                                "id": "soft",
                                "name": "Soft"
                            }
                        ],
                        "defaultValue": []
                    },
                    "hostSeverities": {
                        "type": "connected-autocomplete",
                        "label": "Host severities",
                        "secondaryLabel": "Select host severities",
                        "baseEndpoint": "/monitoring/severities/host",
                        "isSingleAutocomplete": false
                    },
                    "serviceSeverities": {
                        "type": "connected-autocomplete",
                        "label": "Service severities",
                        "secondaryLabel": "Select service severities",
                        "baseEndpoint": "/monitoring/severities/service",
                        "isSingleAutocomplete": false
                    },
                    "refreshInterval": {
                        "type": "refresh-interval",
                        "defaultValue": "default",
                        "label": "Interval"
                    }
                },
                "data": {
                    "resources": {
                        "type": "resources",
                        "defaultValue": [],
                        "required": false
                    }
                },
                "categories": {
                    "Ticket management": {
                        "hasModule": "centreon-open-tickets",
                        "groups": [
                            {
                                "id": "isOpenTicketEnabled",
                                "name": "Enable ticket management"
                            },
                            {
                                "id": "provider",
                                "name": "Rule (ticket provider)"
                            },
                            {
                                "id": "displayResources",
                                "name": "Display resources"
                            }
                        ],
                        "elements": {
                            "warning": {
                                "type": "warning",
                                "label": "If ticket management is enabled, only the “by service” view is available."
                            },
                            "isOpenTicketEnabled": {
                                "type": "switch",
                                "label": "Enable ticket management",
                                "defaultValue": false
                            },
                            "provider": {
                                "type": "connected-autocomplete",
                                "label": "Rule (ticket provider)",
                                "secondaryLabel": "Select rule (ticket provider)",
                                "baseEndpoint": "/open-tickets/providers",
                                "isSingleAutocomplete": true,
                                "hiddenCondition": {
                                    "target": "options",
                                    "when": "options.isOpenTicketEnabled",
                                    "method": "equals",
                                    "matches": false
                                },
                                "isRequiredProperty": true
                            },
                            "displayResources": {
                                "type": "radio",
                                "label": "Display resources",
                                "options": [
                                    {
                                        "id": "withoutTicket",
                                        "name": "Resources with no ticket"
                                    },
                                    {
                                        "id": "withTicket",
                                        "name": "Resources linked to a ticket"
                                    }
                                ],
                                "defaultValue": "withoutTicket",
                                "hiddenCondition": {
                                    "target": "options",
                                    "when": "options.provider",
                                    "method": "isNil"
                                }
                            },
                            "isDownHostHidden": {
                                "type": "switch",
                                "label": "Hide services with Down host",
                                "defaultValue": false,
                                "hiddenCondition": {
                                    "target": "options",
                                    "when": "options.isOpenTicketEnabled",
                                    "method": "equals",
                                    "matches": false
                                }
                            },
                            "isUnreachableHostHidden": {
                                "type": "switch",
                                "label": "Hide services with Unreachable host",
                                "defaultValue": false,
                                "hiddenCondition": {
                                    "target": "options",
                                    "when": "options.isOpenTicketEnabled",
                                    "method": "equals",
                                    "matches": false
                                }
                            }
                        }
                    }
                },
                "moduleName": "centreon-widget-resourcestable",
                "canExtand": true
            }
        },
          {
              "moduleName": "centreon-widget-groupmonitoring",
              "remoteEntry": "",
              "moduleFederationName": "centreon-widget-groupmonitoring",
              "federatedPages": [],
              "federatedComponentsConfiguration": [
                  {
                      "path": "/widgets/groupmonitoring",
                      "federatedComponents": [],
                      "name": "groupmonitoring",
                      "panelMinWidth": 4,
                      "panelMinHeight": 3,
                      "panelDefaultWidth": 6,
                      "panelDefaultHeight": 4
                  }
              ],
              "Component": {
                  "_payload": {
                      "_status": -1
                  }
              },
              "properties": {
                  "title": "Group monitoring",
                  "icon": "<svg viewBox=\"0 0 24 24\"><circle cx=\"19.68\" cy=\"5.98\" r=\"1.5\"/><circle cx=\"19.67\" cy=\"11.99\" r=\"1.5\"/><circle cx=\"19.67\" cy=\"18.02\" r=\"1.5\"/><path d=\"M11.17 6.98h-7c-.55 0-1-.45-1-1s.45-1 1-1h7c.55 0 1 .45 1 1s-.45 1-1 1z\"/><circle cx=\"15.01\" cy=\"5.98\" r=\"1.5\"/><path d=\"M11.29 12.99h-7c-.55 0-1-.45-1-1s.45-1 1-1h7c.55 0 1 .45 1 1s-.45 1-1 1z\"/><circle cx=\"15\" cy=\"11.99\" r=\"1.5\"/><path d=\"M11.29 19.02h-7c-.55 0-1-.45-1-1s.45-1 1-1h7c.55 0 1 .45 1 1s-.45 1-1 1z\"/><circle cx=\"15\" cy=\"18.02\" r=\"1.5\"/></svg>",
                  "description": "Displays the distribution of current statuses on selected groups of resources, as a table.",
                  "options": {
                      "resourceTypes": {
                          "type": "checkbox",
                          "label": "Display resources with this type",
                          "options": [
                              {
                                  "id": "host",
                                  "name": "Host"
                              },
                              {
                                  "id": "service",
                                  "name": "Service"
                              }
                          ],
                          "defaultValue": [
                              "host",
                              "service"
                          ],
                          "keepOneOptionSelected": true
                      },
                      "statuses": {
                          "type": "checkbox",
                          "label": "Display resources with this status",
                          "options": [
                              {
                                  "id": "1",
                                  "name": "Problem (Down/Critical)"
                              },
                              {
                                  "id": "2",
                                  "name": "Warning"
                              },
                              {
                                  "id": "4",
                                  "name": "Pending"
                              },
                              {
                                  "id": "5",
                                  "name": "Success (OK/Up)"
                              },
                              {
                                  "id": "6",
                                  "name": "Undefined (Unreachable/Unknown)"
                              }
                          ],
                          "defaultValue": [
                              "2",
                              "1"
                          ]
                      },
                      "refreshInterval": {
                          "type": "refresh-interval",
                          "defaultValue": "default",
                          "label": "Interval"
                      }
                  },
                  "data": {
                      "resources": {
                          "type": "resources",
                          "defaultValue": [],
                          "required": false,
                          "requireResourceType": true,
                          "singleResourceType": true,
                          "restrictedResourceTypes": [
                              "host-group",
                              "service-group"
                          ]
                      }
                  },
                  "moduleName": "centreon-widget-groupmonitoring",
                  "canExtand": true
              }
          },
          {
              "moduleName": "centreon-widget-webpage",
              "remoteEntry": "",
              "moduleFederationName": "centreon-widget-webpage",
              "federatedPages": [],
              "federatedComponentsConfiguration": [
                  {
                      "path": "/widgets/webpage",
                      "federatedComponents": [],
                      "name": "webpage"
                  }
              ],
              "Component": {
                  "_payload": {
                      "_status": -1
                  }
              },
              "properties": {
                  "title": "Web page",
                  "description": "Displays a web page",
                  "icon": "<path d=\"M30,54c-3.2,0-6.4-0.6-9.3-1.9c-2.9-1.2-5.4-3-7.6-5.2c-2.2-2.2-3.9-4.8-5.2-7.6C6.6,36.4,6,33.2,6,30c0-3.2,0.6-6.4,1.9-9.3c1.2-2.8,3-5.4,5.2-7.6c2.2-2.2,4.8-3.9,7.6-5.2C23.6,6.6,26.8,6,30,6c3.2,0,6.4,0.6,9.3,1.9c2.8,1.2,5.4,3,7.6,5.2c2.2,2.2,3.9,4.8,5.2,7.6c1.3,2.9,1.9,6.1,1.9,9.3c0,3.2-0.6,6.4-1.9,9.3c-1.2,2.9-3,5.4-5.2,7.6c-2.2,2.2-4.8,3.9-7.6,5.2C36.4,53.4,33.2,54,30,54z M30,50.2c1.2-1.6,2.3-3.4,3.3-5.2c0.9-1.8,1.6-3.7,2.2-5.7H24.6c0.5,2,1.3,3.9,2.2,5.8C27.7,46.9,28.8,48.6,30,50.2z M25.1,49.5c-1-1.5-1.9-3.1-2.6-4.7c-0.8-1.8-1.4-3.6-1.8-5.4h-8.6c1.3,2.6,3.1,4.8,5.4,6.6C19.7,47.7,22.3,48.9,25.1,49.5z M34.9,49.5c2.8-0.6,5.4-1.8,7.6-3.6c2.2-1.8,4.1-4,5.4-6.6h-8.6c-0.5,1.9-1.1,3.7-1.9,5.5C36.7,46.4,35.9,48,34.9,49.5z M10.6,35.5H20c-0.2-0.9-0.3-1.9-0.3-2.8c-0.1-0.9-0.1-1.8-0.1-2.7c0-0.9,0-1.8,0.1-2.7s0.2-1.8,0.3-2.8h-9.4c-0.2,0.9-0.4,1.8-0.6,2.7c-0.1,0.9-0.2,1.9-0.2,2.8c0,0.9,0.1,1.9,0.2,2.8C10.1,33.7,10.3,34.6,10.6,35.5z M23.7,35.5h12.6c0.2-0.9,0.3-1.9,0.3-2.7s0.1-1.8,0.1-2.8c0-0.9,0-1.9-0.1-2.8c-0.1-0.9-0.2-1.8-0.3-2.7H23.7c-0.2,0.9-0.3,1.9-0.3,2.7c-0.1,0.9-0.1,1.8-0.1,2.8c0,0.9,0,1.9,0.1,2.8C23.5,33.6,23.6,34.5,23.7,35.5L23.7,35.5z M40.1,35.5h9.4c0.2-0.9,0.4-1.8,0.6-2.7c0.1-0.9,0.2-1.9,0.2-2.8c0-0.9-0.1-1.9-0.2-2.8c-0.1-0.9-0.3-1.8-0.6-2.7h-9.4c0.2,0.9,0.3,1.9,0.3,2.8c0.1,0.9,0.1,1.8,0.1,2.7c0,0.9,0,1.8-0.1,2.7C40.4,33.6,40.2,34.6,40.1,35.5L40.1,35.5z M39.3,20.7h8.6c-1.3-2.6-3.1-4.8-5.3-6.6c-2.2-1.8-4.9-3-7.6-3.6c1,1.5,1.9,3.1,2.6,4.8C38.2,17.1,38.9,18.9,39.3,20.7L39.3,20.7z M24.6,20.7h10.8c-0.5-2-1.3-4-2.2-5.8c-0.9-1.8-2-3.5-3.2-5c-1.2,1.6-2.3,3.3-3.2,5C25.9,16.8,25.2,18.7,24.6,20.7z M12.2,20.7h8.6c0.5-1.8,1.1-3.6,1.8-5.4c0.7-1.7,1.6-3.3,2.6-4.8c-2.8,0.6-5.4,1.8-7.7,3.6C15.2,15.9,13.4,18.2,12.2,20.7L12.2,20.7z\"/>",
                  "moduleName": "centreon-widget-webpage",
                  "options": {
                      "url": {
                          "type": "textfield",
                          "defaultValue": "",
                          "label": "URL",
                          "required": true,
                          "secondaryLabel": "URL"
                      },
                      "refreshInterval": {
                          "type": "refresh-interval",
                          "defaultValue": "default",
                          "label": "Interval"
                      }
                  },
                  "message": {
                      "label": "Please note that domains outside your organization must be authorized in your Apache configuration.",
                      "icon": "<path fill-rule=\"evenodd\" d=\"M15.35 8c0 3.377-2.945 6.25-6.75 6.25S1.85 11.377 1.85 8 4.795 1.75 8.6 1.75 15.35 4.623 15.35 8zm1.25 0c0 4.142-3.582 7.5-8 7.5S.6 12.142.6 8C.6 3.858 4.182.5 8.6.5s8 3.358 8 7.5zM9.229 3.101l-.014 7.3-1.25-.002.014-7.3 1.25.002zm.016 9.249a.65.65 0 1 0-1.3 0 .65.65 0 0 0 1.3 0z\"/>"
                  },
                  "availableOnPremOnly": true,
                  "canExtand": true
              }
          }
      ]





      const federatedWidgetsProperties = [
        {
            "title": "Business Activity Diagram",
            "description": "Displays graphically a business activity hierarchy of KPIs and lets you navigate through it.",
            "icon": "<path d=\"M53.06,13.33l-12.14-6.99-11.14,6.41-11.14-6.41-12.14,6.99v14.02l11.14,6.44v12.87l12.14,6.99,12.14-6.99v-12.87l11.14-6.44v-14.02ZM8.5,14.49l10.14-5.83,10.14,5.83v11.71l-5.07,2.93-5.07,2.93-10.14-5.86v-11.71ZM29.78,51.35l-10.14-5.83v-11.71l5.07-2.93,5.07-2.93,5.07,2.93,5.07,2.93v11.71l-10.14,5.83ZM51.06,26.2l-10.14,5.86-5.07-2.93-5.07-2.93v-11.71l10.14-5.83,10.14,5.83v11.71Z\"></path>",
            "options": {
                "pathStatuses": {
                    "type": "checkbox",
                    "label": "Display the diagram with this status",
                    "keepOneOptionSelected": true,
                    "options": [
                        {
                            "id": "problem",
                            "name": "Problem (Critical)"
                        },
                        {
                            "id": "warning",
                            "name": "Warning"
                        },
                        {
                            "id": "success",
                            "name": "Success (OK)"
                        }
                    ],
                    "defaultValue": [
                        "problem"
                    ]
                },
                "link": {
                    "type": "radio",
                    "label": "Link types",
                    "options": [
                        {
                            "id": "curve",
                            "name": "Curve"
                        },
                        {
                            "id": "line",
                            "name": "Line"
                        },
                        {
                            "id": "step",
                            "name": "Step"
                        }
                    ],
                    "defaultValue": "curve"
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "data": {
                "resources": {
                    "type": "resources",
                    "defaultValue": [],
                    "required": true,
                    "useAdditionalResources": true,
                    "singleResourceType": true,
                    "restrictedResourceTypes": [
                        "business-activity"
                    ]
                }
            },
            "singleResourceSelection": true,
            "moduleName": "centreon-widget-batree",
            "canExtand":true
        },
        {
            "title": "Metrics graph",
            "description": "Displays metrics for a given time period.",
            "icon": "<path d=\"M41.8,54.4c-0.1,0-0.2,0-0.3,0c-4.7-0.4-6.2-9.5-7.7-19.2c-0.8-4.9-2.2-13.2-3.6-13.6c-1.6,0.1-3.2,6.2-4.1,10.1c-1.7,7-3.6,14.3-8.4,14.3c-3.8,0-5.2-4.1-6.8-8.4c-1.6-4.8-3.5-10.1-8.4-13c-0.7-0.3-0.9-1.2-0.5-1.9c0.4-0.5,1.3-0.8,2-0.4c5.9,3.3,8,9.5,9.7,14.4c1.4,4,2.3,6.5,4.1,6.5c2.6,0,4.4-6.8,5.7-12.2c1.6-6.4,2.9-12,6.5-12.2c3.9-0.3,5,6.4,6.5,15.8c1.1,6.7,2.7,16.7,5.2,16.9l0,0c1.7,0,2.8-9.1,3.5-15.1c1.3-11.8,2.8-25.1,9.7-29.8c0.7-0.4,1.4-0.3,1.9,0.3c0.4,0.7,0.3,1.4-0.3,1.9c-5.8,4-7.3,17.2-8.5,27.9C46.8,47,45.9,54.4,41.8,54.4z\"/>",
            "options": {
                "timeperiod": {
                    "type": "time-period",
                    "defaultValue": {
                        "start": null,
                        "end": null,
                        "timePeriodType": 1
                    }
                },
                "threshold": {
                    "type": "threshold",
                    "defaultValue": {
                        "enabled": true,
                        "customCritical": null,
                        "criticalType": "default",
                        "customWarning": null,
                        "warningType": "default"
                    },
                    "label": "threshold"
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "data": {
                "resources": {
                    "type": "resources",
                    "defaultValue": [],
                    "required": true
                },
                "metrics": {
                    "type": "metrics",
                    "defaultValue": []
                }
            },
            "categories": {
                "Graph settings": {
                    "groups": [
                        {
                            "id": "graphStyles",
                            "name": "Graph style"
                        },
                        {
                            "id": "axis",
                            "name": "Axes"
                        },
                        {
                            "id": "legend",
                            "name": "Legend"
                        },
                        {
                            "id": "tooltip",
                            "name": "Tooltips"
                        }
                    ],
                    "elements": {
                        "displayType": {
                            "type": "displayType",
                            "defaultValue": "line",
                            "options": [
                                {
                                    "id": "line",
                                    "label": "Line",
                                    "icon": "<path d=\"M41.8,54.4c-0.1,0-0.2,0-0.3,0c-4.7-0.4-6.2-9.5-7.7-19.2c-0.8-4.9-2.2-13.2-3.6-13.6c-1.6,0.1-3.2,6.2-4.1,10.1c-1.7,7-3.6,14.3-8.4,14.3c-3.8,0-5.2-4.1-6.8-8.4c-1.6-4.8-3.5-10.1-8.4-13c-0.7-0.3-0.9-1.2-0.5-1.9c0.4-0.5,1.3-0.8,2-0.4c5.9,3.3,8,9.5,9.7,14.4c1.4,4,2.3,6.5,4.1,6.5c2.6,0,4.4-6.8,5.7-12.2c1.6-6.4,2.9-12,6.5-12.2c3.9-0.3,5,6.4,6.5,15.8c1.1,6.7,2.7,16.7,5.2,16.9l0,0c1.7,0,2.8-9.1,3.5-15.1c1.3-11.8,2.8-25.1,9.7-29.8c0.7-0.4,1.4-0.3,1.9,0.3c0.4,0.7,0.3,1.4-0.3,1.9c-5.8,4-7.3,17.2-8.5,27.9C46.8,47,45.9,54.4,41.8,54.4z\"/>"
                                },
                                {
                                    "id": "bar",
                                    "label": "Bar",
                                    "icon": "<svg viewBox=\"0 0 65 65\"><rect x=\"5\" y=\"38.51\" width=\"12\" height=\"20\" rx=\"4.26\" ry=\"4.26\" /><rect x=\"19.33\" y=\"28.51\" width=\"12\" height=\"30\" rx=\"4\" ry=\"4\" /><rect x=\"33.67\" y=\"16.51\" width=\"12\" height=\"41.99\" rx=\"4\" ry=\"4\" /><rect x=\"48\" y=\"9.51\" width=\"12\" height=\"49\" rx=\"4.32\" ry=\"4.32\" /></svg>"
                                },
                                {
                                    "id": "bar-stacked",
                                    "label": "Stacked bar",
                                    "icon": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 65 65\"><defs><style>.cls-1 {fill: #a2a2a2;}.cls-2 {fill: #666;}.cls-4 {fill: #ccc;}</style></defs><rect x=\"33.67\" y=\"14.51\" width=\"12\" height=\"44\" rx=\"4\" ry=\"4\" /><path class=\"cls-1\" d=\"M5,38.5h12v16c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-16h0Z\" /><rect class=\"cls-2\" x=\"5\" y=\"24.5\" width=\"12\" height=\"14\" /><path class=\"cls-4\" d=\"M9,14.5h4c2.21,0,4,1.79,4,4v6H5v-6c0-2.21,1.79-4,4-4Z\" /><path class=\"cls-1\" d=\"M19.33,43.5h12v11c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-11h0Z\" /><rect class=\"cls-2\" x=\"19.33\" y=\"21.5\" width=\"12\" height=\"22\" /><path class=\"cls-4\" d=\"M23.33,7.5h4c2.21,0,4,1.79,4,4v10h-12v-10c0-2.21,1.79-4,4-4Z\" /><path class=\"cls-1\" d=\"M48,52.5h12v2c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-2h0Z\" /><rect class=\"cls-2\" x=\"48\" y=\"43.5\" width=\"12\" height=\"9\" /><path class=\"cls-4\" d=\"M52,26.5h4c2.21,0,4,1.79,4,4v13h-12v-13c0-2.21,1.79-4,4-4Z\" /><path class=\"cls-1\" d=\"M33.67,29.5h12v25c0,2.21-1.79,4-4,4h-4c-2.21,0-4-1.79-4-4v-25h0Z\" /><rect class=\"cls-2\" x=\"33.67\" y=\"23.5\" width=\"12\" height=\"6\" /><path class=\"cls-4\" d=\"M37.67,14.5h4c2.21,0,4,1.79,4,4v5h-12v-5c0-2.21,1.79-4,4-4Z\" /></svg>"
                                }
                            ]
                        },
                        "curveType": {
                            "group": "graphStyles",
                            "type": "select",
                            "label": "Curve type",
                            "defaultValue": "linear",
                            "options": [
                                {
                                    "id": "linear",
                                    "name": "Linear"
                                },
                                {
                                    "id": "natural",
                                    "name": "Smooth"
                                },
                                {
                                    "id": "step",
                                    "name": "Step"
                                }
                            ],
                            "hiddenCondition": {
                                "target": "options",
                                "method": "includes",
                                "when": "options.displayType",
                                "matches": [
                                    "bar",
                                    "bar-stacked"
                                ]
                            }
                        },
                        "showPoints": {
                            "group": "graphStyles",
                            "type": "switch",
                            "label": "Display curve points",
                            "defaultValue": false,
                            "hiddenCondition": {
                                "target": "options",
                                "when": "options.displayType",
                                "method": "includes",
                                "matches": [
                                    "bar",
                                    "bar-stacked"
                                ]
                            }
                        },
                        "lineWidthMode": {
                            "group": "graphStyles",
                            "type": "button-group",
                            "label": "Line width",
                            "defaultValue": "auto",
                            "secondaryLabel": "Auto: Default value defined for the corresponding curve template (Monitoring > Performances > Curves).",
                            "options": [
                                {
                                    "id": "auto",
                                    "name": "Auto"
                                },
                                {
                                    "id": "custom",
                                    "name": "Custom"
                                }
                            ],
                            "subInputs": [
                                {
                                    "name": "lineWidth",
                                    "displayValue": "custom",
                                    "input": {
                                        "type": "slider",
                                        "defaultValue": 2,
                                        "slider": {
                                            "min": 0,
                                            "max": 10,
                                            "unit": "px"
                                        }
                                    }
                                }
                            ],
                            "hiddenCondition": {
                                "target": "options",
                                "method": "includes",
                                "when": "options.displayType",
                                "matches": [
                                    "bar",
                                    "bar-stacked"
                                ]
                            }
                        },
                        "showArea": {
                            "group": "graphStyles",
                            "type": "button-group",
                            "label": "Area",
                            "defaultValue": "auto",
                            "secondaryLabel": "Auto: Default value defined for the corresponding curve template (Monitoring > Performances > Curves).",
                            "options": [
                                {
                                    "id": "auto",
                                    "name": "Auto"
                                },
                                {
                                    "id": "show",
                                    "name": "Show"
                                },
                                {
                                    "id": "hide",
                                    "name": "Hide"
                                }
                            ],
                            "subInputs": [
                                {
                                    "name": "areaOpacity",
                                    "displayValue": "show",
                                    "input": {
                                        "type": "slider",
                                        "defaultValue": 20,
                                        "label": "Fill opacity",
                                        "slider": {
                                            "max": 100,
                                            "min": 0,
                                            "unit": "%"
                                        }
                                    }
                                }
                            ],
                            "hiddenCondition": {
                                "target": "options",
                                "method": "includes",
                                "when": "options.displayType",
                                "matches": [
                                    "bar",
                                    "bar-stacked"
                                ]
                            }
                        },
                        "lineStyleMode": {
                            "group": "graphStyles",
                            "type": "button-group",
                            "defaultValue": "solid",
                            "label": "Line style",
                            "hiddenCondition": {
                                "target": "options",
                                "method": "includes",
                                "when": "options.displayType",
                                "matches": [
                                    "bar",
                                    "bar-stacked"
                                ]
                            },
                            "options": [
                                {
                                    "id": "solid",
                                    "name": "Solid"
                                },
                                {
                                    "id": "dash",
                                    "name": "Dashes"
                                },
                                {
                                    "id": "dots",
                                    "name": "Dots"
                                }
                            ],
                            "subInputs": [
                                {
                                    "name": "dashLength",
                                    "displayValue": "dash",
                                    "direction": "row",
                                    "input": {
                                        "type": "textfield",
                                        "defaultValue": 5,
                                        "label": "Dash width",
                                        "text": {
                                            "size": "compact",
                                            "type": "number",
                                            "min": 0,
                                            "autoSize": true
                                        }
                                    }
                                },
                                {
                                    "name": "dashOffset",
                                    "displayValue": "dash",
                                    "direction": "row",
                                    "input": {
                                        "type": "textfield",
                                        "defaultValue": 5,
                                        "label": "Space",
                                        "text": {
                                            "size": "compact",
                                            "type": "number",
                                            "min": 0,
                                            "autoSize": true
                                        }
                                    }
                                },
                                {
                                    "name": "",
                                    "displayValue": "dash",
                                    "direction": "row",
                                    "input": {
                                        "type": "text",
                                        "label": "px"
                                    }
                                },
                                {
                                    "name": "dotOffset",
                                    "displayValue": "dots",
                                    "direction": "row",
                                    "input": {
                                        "type": "textfield",
                                        "defaultValue": 5,
                                        "label": "Space",
                                        "text": {
                                            "size": "compact",
                                            "type": "number",
                                            "min": 0,
                                            "autoSize": true
                                        }
                                    }
                                },
                                {
                                    "name": "",
                                    "displayValue": "dots",
                                    "direction": "row",
                                    "input": {
                                        "type": "text",
                                        "label": "px"
                                    }
                                }
                            ]
                        },
                        "orientation": {
                            "group": "graphStyles",
                            "type": "button-group",
                            "label": "Orientation",
                            "defaultValue": "auto",
                            "options": [
                                {
                                    "id": "auto",
                                    "name": "Auto"
                                },
                                {
                                    "id": "vertical",
                                    "name": "Vertical"
                                },
                                {
                                    "id": "horizontal",
                                    "name": "Horizontal"
                                }
                            ],
                            "hiddenCondition": {
                                "target": "options",
                                "method": "equals",
                                "when": "options.displayType",
                                "matches": "line"
                            }
                        },
                        "barRadius": {
                            "group": "graphStyles",
                            "type": "slider",
                            "label": "Bar radius",
                            "defaultValue": 0,
                            "slider": {
                                "min": 0,
                                "max": 100,
                                "unit": "%"
                            },
                            "hiddenCondition": {
                                "target": "options",
                                "method": "includes",
                                "when": "options.displayType",
                                "matches": [
                                    "line"
                                ]
                            }
                        },
                        "barOpacity": {
                            "group": "graphStyles",
                            "type": "slider",
                            "label": "Bar opacity",
                            "defaultValue": 100,
                            "slider": {
                                "min": 0,
                                "max": 100,
                                "unit": "%"
                            },
                            "hiddenCondition": {
                                "target": "options",
                                "method": "equals",
                                "when": "options.displayType",
                                "matches": "line"
                            }
                        },
                        "showAxisBorder": {
                            "type": "switch",
                            "label": "Show axis borders",
                            "group": "axis",
                            "defaultValue": true
                        },
                        "yAxisTickLabelRotation": {
                            "group": "axis",
                            "type": "slider",
                            "label": "Y-axis label rotation",
                            "defaultValue": 0,
                            "slider": {
                                "min": -45,
                                "max": 45,
                                "unit": "°"
                            }
                        },
                        "isCenteredZero": {
                            "type": "switch",
                            "label": "Zero-centered",
                            "group": "axis",
                            "defaultValue": false
                        },
                        "showGridLines": {
                            "type": "switch",
                            "label": "Show gridlines",
                            "group": "axis",
                            "defaultValue": true,
                            "subInputs": [
                                {
                                    "name": "gridLinesType",
                                    "displayValue": true,
                                    "input": {
                                        "group": "axis",
                                        "type": "button-group",
                                        "label": "Gridline type",
                                        "defaultValue": "all",
                                        "options": [
                                            {
                                                "id": "horizontal",
                                                "name": "Horizontal"
                                            },
                                            {
                                                "id": "vertical",
                                                "name": "Vertical"
                                            },
                                            {
                                                "id": "all",
                                                "name": "Both"
                                            }
                                        ]
                                    }
                                }
                            ]
                        },
                        "scale": {
                            "group": "axis",
                            "type": "button-group",
                            "label": "Scale",
                            "defaultValue": "linear",
                            "options": [
                                {
                                    "id": "linear",
                                    "name": "Linear"
                                },
                                {
                                    "id": "logarithmic",
                                    "name": "Logarithmic"
                                }
                            ],
                            "subInputs": [
                                {
                                    "name": "scaleLogarithmicBase",
                                    "input": {
                                        "type": "radio",
                                        "defaultValue": "2",
                                        "options": [
                                            {
                                                "id": "2",
                                                "name": "Base 2"
                                            },
                                            {
                                                "id": "10",
                                                "name": "Base 10"
                                            }
                                        ]
                                    },
                                    "displayValue": "logarithmic"
                                }
                            ]
                        },
                        "showLegend": {
                            "group": "legend",
                            "type": "switch",
                            "label": "Show legend",
                            "defaultValue": true,
                            "subInputs": [
                                {
                                    "name": "legendDisplayMode",
                                    "displayValue": true,
                                    "input": {
                                        "group": "legend",
                                        "type": "button-group",
                                        "label": "Display mode",
                                        "defaultValue": "grid",
                                        "options": [
                                            {
                                                "id": "grid",
                                                "name": "Grid"
                                            },
                                            {
                                                "id": "list",
                                                "name": "List"
                                            }
                                        ]
                                    }
                                },
                                {
                                    "name": "legendPlacement",
                                    "displayValue": true,
                                    "input": {
                                        "group": "legend",
                                        "type": "button-group",
                                        "label": "Position",
                                        "defaultValue": "bottom",
                                        "options": [
                                            {
                                                "id": "left",
                                                "name": "Left"
                                            },
                                            {
                                                "id": "bottom",
                                                "name": "Bottom"
                                            },
                                            {
                                                "id": "right",
                                                "name": "Right"
                                            }
                                        ]
                                    }
                                }
                            ]
                        },
                        "tooltipMode": {
                            "group": "tooltip",
                            "type": "button-group",
                            "label": "Display",
                            "defaultValue": "all",
                            "options": [
                                {
                                    "id": "all",
                                    "name": "All"
                                },
                                {
                                    "id": "single",
                                    "name": "Only one"
                                },
                                {
                                    "id": "hidden",
                                    "name": "Hidden"
                                }
                            ]
                        },
                        "tooltipSortOrder": {
                            "group": "tooltip",
                            "type": "button-group",
                            "label": "Value sort order",
                            "defaultValue": "name",
                            "options": [
                                {
                                    "id": "name",
                                    "name": "By name"
                                },
                                {
                                    "id": "ascending",
                                    "name": "Ascending"
                                },
                                {
                                    "id": "descending",
                                    "name": "Descending"
                                }
                            ]
                        }
                    }
                }
            },
            "moduleName": "centreon-widget-graph",
            "canExtand": true
        },
        {
            "title": "Group monitoring",
            "icon": "<svg viewBox=\"0 0 24 24\"><circle cx=\"19.68\" cy=\"5.98\" r=\"1.5\"/><circle cx=\"19.67\" cy=\"11.99\" r=\"1.5\"/><circle cx=\"19.67\" cy=\"18.02\" r=\"1.5\"/><path d=\"M11.17 6.98h-7c-.55 0-1-.45-1-1s.45-1 1-1h7c.55 0 1 .45 1 1s-.45 1-1 1z\"/><circle cx=\"15.01\" cy=\"5.98\" r=\"1.5\"/><path d=\"M11.29 12.99h-7c-.55 0-1-.45-1-1s.45-1 1-1h7c.55 0 1 .45 1 1s-.45 1-1 1z\"/><circle cx=\"15\" cy=\"11.99\" r=\"1.5\"/><path d=\"M11.29 19.02h-7c-.55 0-1-.45-1-1s.45-1 1-1h7c.55 0 1 .45 1 1s-.45 1-1 1z\"/><circle cx=\"15\" cy=\"18.02\" r=\"1.5\"/></svg>",
            "description": "Displays the distribution of current statuses on selected groups of resources, as a table.",
            "options": {
                "resourceTypes": {
                    "type": "checkbox",
                    "label": "Display resources with this type",
                    "options": [
                        {
                            "id": "host",
                            "name": "Host"
                        },
                        {
                            "id": "service",
                            "name": "Service"
                        }
                    ],
                    "defaultValue": [
                        "host",
                        "service"
                    ],
                    "keepOneOptionSelected": true
                },
                "statuses": {
                    "type": "checkbox",
                    "label": "Display resources with this status",
                    "options": [
                        {
                            "id": "1",
                            "name": "Problem (Down/Critical)"
                        },
                        {
                            "id": "2",
                            "name": "Warning"
                        },
                        {
                            "id": "4",
                            "name": "Pending"
                        },
                        {
                            "id": "5",
                            "name": "Success (OK/Up)"
                        },
                        {
                            "id": "6",
                            "name": "Undefined (Unreachable/Unknown)"
                        }
                    ],
                    "defaultValue": [
                        "2",
                        "1"
                    ]
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "data": {
                "resources": {
                    "type": "resources",
                    "defaultValue": [],
                    "required": false,
                    "requireResourceType": true,
                    "singleResourceType": true,
                    "restrictedResourceTypes": [
                        "host-group",
                        "service-group"
                    ]
                }
            },
            "moduleName": "centreon-widget-groupmonitoring",
            "canExtand": true
        },
        {
            "title": "Resource table",
            "description": "Displays data on resource status and events, centralized in a table.",
            "icon": "<g><path  d=\"M49.2,6H10.8c-2.65,0-4.8,2.15-4.8,4.8v38.4c0,2.65,2.15,4.8,4.8,4.8h38.4c2.65,0,4.8-2.15,4.8-4.8V10.8c0-2.65-2.15-4.8-4.8-4.8ZM52.08,18.48v16.1h-13.47v-16.1h13.47ZM23.63,34.58v-16.1h12.99v16.1h-12.99ZM36.61,36.58v15.5h-12.99v-15.5h12.99ZM21.63,18.48v16.1H7.92v-16.1h13.71ZM7.92,49.2v-12.62h13.71v15.5h-10.83c-1.59,0-2.88-1.29-2.88-2.88ZM49.2,52.08h-10.59v-15.5h13.47v12.62c0,1.59-1.29,2.88-2.88,2.88\"/></g>",
            "options": {
                "statuses": {
                    "type": "checkbox",
                    "label": "Display resources with this status",
                    "options": [
                        {
                            "id": "problem",
                            "name": "Problem (Down/Critical)"
                        },
                        {
                            "id": "warning",
                            "name": "Warning"
                        },
                        {
                            "id": "pending",
                            "name": "Pending"
                        },
                        {
                            "id": "success",
                            "name": "Success (OK/Up)"
                        },
                        {
                            "id": "undefined",
                            "name": "Undefined (Unreachable/Unknown)"
                        }
                    ],
                    "defaultValue": [
                        "warning",
                        "problem"
                    ]
                },
                "states": {
                    "type": "checkbox",
                    "label": "Display resources with this state",
                    "options": [
                        {
                            "id": "unhandled_problems",
                            "name": "Unhandled"
                        },
                        {
                            "id": "acknowledged",
                            "name": "Acknowledged"
                        },
                        {
                            "id": "in_downtime",
                            "name": "In downtime"
                        }
                    ],
                    "defaultValue": [
                        "unhandled_problems"
                    ]
                },
                "statusTypes": {
                    "type": "checkbox",
                    "label": "Display resources with these status types",
                    "options": [
                        {
                            "id": "hard",
                            "name": "Hard"
                        },
                        {
                            "id": "soft",
                            "name": "Soft"
                        }
                    ],
                    "defaultValue": []
                },
                "hostSeverities": {
                    "type": "connected-autocomplete",
                    "label": "Host severities",
                    "secondaryLabel": "Select host severities",
                    "baseEndpoint": "/monitoring/severities/host",
                    "isSingleAutocomplete": false
                },
                "serviceSeverities": {
                    "type": "connected-autocomplete",
                    "label": "Service severities",
                    "secondaryLabel": "Select service severities",
                    "baseEndpoint": "/monitoring/severities/service",
                    "isSingleAutocomplete": false
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "data": {
                "resources": {
                    "type": "resources",
                    "defaultValue": [],
                    "required": false
                }
            },
            "categories": {
                "Ticket management": {
                    "hasModule": "centreon-open-tickets",
                    "groups": [
                        {
                            "id": "isOpenTicketEnabled",
                            "name": "Enable ticket management"
                        },
                        {
                            "id": "provider",
                            "name": "Rule (ticket provider)"
                        },
                        {
                            "id": "displayResources",
                            "name": "Display resources"
                        }
                    ],
                    "elements": {
                        "warning": {
                            "type": "warning",
                            "label": "If ticket management is enabled, only the “by service” view is available."
                        },
                        "isOpenTicketEnabled": {
                            "type": "switch",
                            "label": "Enable ticket management",
                            "defaultValue": false
                        },
                        "provider": {
                            "type": "connected-autocomplete",
                            "label": "Rule (ticket provider)",
                            "secondaryLabel": "Select rule (ticket provider)",
                            "baseEndpoint": "/open-tickets/providers",
                            "isSingleAutocomplete": true,
                            "hiddenCondition": {
                                "target": "options",
                                "when": "options.isOpenTicketEnabled",
                                "method": "equals",
                                "matches": false
                            },
                            "isRequiredProperty": true
                        },
                        "displayResources": {
                            "type": "radio",
                            "label": "Display resources",
                            "options": [
                                {
                                    "id": "withoutTicket",
                                    "name": "Resources with no ticket"
                                },
                                {
                                    "id": "withTicket",
                                    "name": "Resources linked to a ticket"
                                }
                            ],
                            "defaultValue": "withoutTicket",
                            "hiddenCondition": {
                                "target": "options",
                                "when": "options.provider",
                                "method": "isNil"
                            }
                        },
                        "isDownHostHidden": {
                            "type": "switch",
                            "label": "Hide services with Down host",
                            "defaultValue": false,
                            "hiddenCondition": {
                                "target": "options",
                                "when": "options.isOpenTicketEnabled",
                                "method": "equals",
                                "matches": false
                            }
                        },
                        "isUnreachableHostHidden": {
                            "type": "switch",
                            "label": "Hide services with Unreachable host",
                            "defaultValue": false,
                            "hiddenCondition": {
                                "target": "options",
                                "when": "options.isOpenTicketEnabled",
                                "method": "equals",
                                "matches": false
                            }
                        }
                    }
                }
            },
            "moduleName": "centreon-widget-resourcestable",
            "canExtand": true
        },
        {
            "title": "Status chart",
            "description": "Displays a detailed view of the current status for selected resources as a chart.",
            "icon": "<svg viewBox=\"0 0 24 24\"><g><path d=\"M16.36,5.87c-.06.08-.17.1-.26.05-.53-.36-1.11-.65-1.72-.86-.61-.21-1.24-.34-1.88-.38-.1,0-.18-.09-.18-.2l.07-1.69c0-.21.19-.38.41-.36.79.07,1.57.23,2.32.49.75.26,1.46.61,2.13,1.04.18.12.22.36.1.53l-.98,1.38Z\"/><path d=\"M12,21.6c-.08,0-.16,0-.24,0-2.55-.06-4.93-1.13-6.7-2.99-1.77-1.87-2.72-4.3-2.65-6.85.06-2.55,1.13-4.93,2.99-6.7,1.77-1.68,4.05-2.62,6.45-2.65.21,0,.39.17.38.39l-.04,1.69c0,.1-.09.18-.19.18-1.88,0-3.66.72-5.05,2.03-1.43,1.36-2.24,3.18-2.29,5.12s.67,3.8,2.03,5.23c1.36,1.43,3.18,2.24,5.12,2.29,1.94.05,3.8-.67,5.23-2.03,1.43-1.36,2.24-3.18,2.29-5.12.06-2.33-1-4.55-2.83-5.98-.08-.06-.1-.18-.04-.26l1-1.36c.13-.17.37-.2.54-.07,2.33,1.87,3.66,4.73,3.58,7.73-.06,2.55-1.13,4.93-2.99,6.7-1.81,1.72-4.14,2.66-6.6,2.66Z\"/></g></svg>",
            "options": {
                "displayType": {
                    "type": "displayType",
                    "options": [
                        {
                            "icon": "<svg viewBox=\"0 0 65 65\"> <rect width=\"65\" height=\"65\" rx=\"8\" ry=\"8\" fill=\"none\"/> <g id=\"Groupe_3913\" data-name=\"Groupe 3913\"> <path fill=\"#666\" class=\"cls-3\" d=\"M43.9,16.53c-.16.22-.46.27-.68.12-2.79-1.88-6.04-3-9.4-3.22-.27-.02-.47-.24-.46-.51l.19-4.39c.02-.54.48-.96,1.02-.94.01,0,.02,0,.04,0,4.13.35,8.12,1.71,11.59,3.97.45.3.58.9.28,1.36,0,.01-.01.02-.02.03l-2.55,3.58Z\"/> <path fill=\"#a2a2a2\" data-name=\"Tracé 3893\" d=\"M32.53,57.5c-.21,0-.42,0-.63,0-13.79-.32-24.72-11.75-24.39-25.53.16-6.65,2.96-12.97,7.8-17.54,4.53-4.35,10.55-6.82,16.83-6.91.54,0,.99.43.99.97,0,.01,0,.02,0,.03l-.11,4.4c0,.26-.23.47-.49.47-10.57,0-19.14,8.56-19.14,19.12,0,10.56,8.57,19.12,19.14,19.12s19.14-8.56,19.14-19.12c0-5.9-2.73-11.47-7.39-15.09-.21-.16-.26-.46-.1-.68l2.61-3.54c.32-.44.94-.53,1.38-.2,0,0,.02.01.02.02,10.76,8.69,12.43,24.45,3.74,35.2-4.74,5.85-11.86,9.27-19.4,9.29\"/> </g> </svg>",
                            "label": "Donut chart",
                            "id": "donut"
                        },
                        {
                            "icon": "<svg viewBox=\"0 0 65 65\"> <rect width=\"65\" height=\"65\" rx=\"8\" ry=\"8\" fill=\"none\"/> <g> <ellipse fill=\"#a2a2a2\" cx=\"31.84\" cy=\"33.48\" rx=\"24.34\" ry=\"24.02\"/> <path fill=\"#666\" d=\"M37.46,7.5c9.46,1.95,17.13,8.88,20.04,18.1-24.81,7.6-.57.2-25.66,7.84,5.42-25.16.35-1.35,5.63-25.94Z\"/> </g> </svg>",
                            "label": "Pie chart",
                            "id": "pie"
                        },
                        {
                            "icon": "<svg viewBox=\"0 0 65 65\"> <rect width=\"65\" height=\"65\" rx=\"8\" ry=\"8\" fill=\"none\" /> <g id=\"Groupe_3909\" data-name=\"Groupe 3909\"> <g> <rect x=\"7.5\" y=\"36.5\" width=\"50\" height=\"12\" rx=\"4\" ry=\"4\" fill=\"#a2a2a2\"/> <rect fill=\"#666\" x=\"7.5\" y=\"36.5\" width=\"32\" height=\"12\" rx=\"4\" ry=\"4\"/> </g> <g> <rect x=\"7.5\" y=\"16.5\" width=\"50\" height=\"12\" rx=\"4\" ry=\"4\" fill=\"#a2a2a2\"/> <rect fill=\"#666\" x=\"7.5\" y=\"16.5\" width=\"20\" height=\"12\" rx=\"4\" ry=\"4\"/> </g> </g> </svg>",
                            "label": "Horizontal bar chart",
                            "id": "horizontal"
                        },
                        {
                            "icon": "<svg viewBox=\"0 0 65 65\"> <rect/> <g > <g> <rect fill=\"#a2a2a2\" x=\"36.5\" y=\"7.5\" width=\"12\" height=\"50\" rx=\"4\" ry=\"4\"/> <rect fill=\"#666\" x=\"36.5\" y=\"25.5\" width=\"12\" height=\"32\" rx=\"4\" ry=\"4\"/> </g> <g > <rect fill=\"#a2a2a2\" x=\"16.5\" y=\"7.5\" width=\"12\" height=\"50\" rx=\"4\" ry=\"4\"/> <rect fill=\"#666\" x=\"16.5\" y=\"37.5\" width=\"12\" height=\"20\" rx=\"4\" ry=\"4\"/> </g> </g> </svg>",
                            "label": "Vertical bar chart",
                            "id": "vertical"
                        }
                    ],
                    "defaultValue": "donut"
                },
                "resourceTypes": {
                    "type": "checkbox",
                    "label": "Display resources with this type",
                    "options": [
                        {
                            "id": "host",
                            "name": "Host"
                        },
                        {
                            "id": "service",
                            "name": "Service"
                        }
                    ],
                    "defaultValue": [
                        "host",
                        "service"
                    ],
                    "keepOneOptionSelected": true
                },
                "unit": {
                    "type": "radio",
                    "label": "Display resources with this unit",
                    "options": [
                        {
                            "id": "percentage",
                            "name": "Percentage"
                        },
                        {
                            "id": "number",
                            "name": "Number"
                        }
                    ],
                    "defaultValue": "percentage"
                },
                "displayValues": {
                    "type": "switch",
                    "label": "Show value on chart",
                    "defaultValue": true
                },
                "displayLegend": {
                    "type": "switch",
                    "label": "Show legend",
                    "secondaryLabel": "Legend",
                    "defaultValue": true
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "data": {
                "resources": {
                    "type": "resources",
                    "defaultValue": [],
                    "required": false,
                    "excludedResourceTypes": [
                        "meta-service"
                    ]
                }
            },
            "moduleName": "centreon-widget-statuschart",
            "canExtand": true
        },
        {
            "title": "Status grid",
            "description": "Displays a status overview for selected resources, grouped by hosts or by services.",
            "icon": "<rect x=\"6.51\" y=\"6\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect><rect x=\"6.51\" y=\"32\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect><rect x=\"32.51\" y=\"32\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect><rect x=\"32.51\" y=\"6\" width=\"22\" height=\"22\" rx=\"4\" ry=\"4\"></rect>",
            "options": {
                "viewMode": {
                    "type": "radio",
                    "label": "Display with this view",
                    "options": [
                        {
                            "id": "standard",
                            "name": "Standard"
                        },
                        {
                            "id": "condensed",
                            "name": "Condensed"
                        }
                    ],
                    "defaultValue": "standard"
                },
                "resourceType": {
                    "type": "radio",
                    "label": "Display resources with this type",
                    "options": [
                        {
                            "id": "host",
                            "name": "Host"
                        },
                        {
                            "id": "service",
                            "name": "Service"
                        }
                    ],
                    "defaultValue": "host",
                    "hiddenCondition": {
                        "target": "data",
                        "when": "data.resources",
                        "property": "resourceType",
                        "method": "includes",
                        "matches": [
                            "business-view",
                            "business-activity"
                        ]
                    }
                },
                "statuses": {
                    "type": "checkbox",
                    "label": "Display resources with this status",
                    "options": [
                        {
                            "id": "problem",
                            "name": "Problem (Down/Critical)"
                        },
                        {
                            "id": "warning",
                            "name": "Warning"
                        },
                        {
                            "id": "pending",
                            "name": "Pending"
                        },
                        {
                            "id": "success",
                            "name": "Success (OK/Up)"
                        },
                        {
                            "id": "undefined",
                            "name": "Undefined (Unreachable/Unknown)"
                        }
                    ],
                    "defaultValue": [
                        "warning",
                        "problem"
                    ]
                },
                "sortBy": {
                    "type": "radio",
                    "label": "Sort by",
                    "options": [
                        {
                            "id": "status_severity_code",
                            "name": "Status"
                        },
                        {
                            "id": "name",
                            "name": "Name"
                        }
                    ],
                    "defaultValue": "status_severity_code",
                    "hiddenCondition": {
                        "target": "options",
                        "when": "options.viewMode",
                        "method": "equals",
                        "matches": "condensed"
                    }
                },
                "tiles": {
                    "type": "tiles",
                    "defaultValue": 100,
                    "hiddenCondition": {
                        "target": "options",
                        "when": "options.viewMode",
                        "method": "equals",
                        "matches": "condensed"
                    }
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "data": {
                "resources": {
                    "type": "resources",
                    "defaultValue": [],
                    "required": false,
                    "useAdditionalResources": true,
                    "excludedResourceTypes": [
                        "meta-service"
                    ]
                }
            },
            "moduleName": "centreon-widget-statusgrid",
            "canExtand": true
        },
        {
            "title": "Top/bottom",
            "description": "Displays the top or bottom x hosts, for a selected metric.",
            "icon": "<rect x=\"6\" y=\"25\" width=\"30\" height=\"10\" rx=\"4\" ry=\"4\"></rect><rect x=\"6\" y=\"11\" width=\"20\" height=\"10\" rx=\"4\" ry=\"4\"></rect><rect x=\"6\" y=\"39\" width=\"48\" height=\"10\" rx=\"4\" ry=\"4\"></rect>",
            "options": {
                "topBottomSettings": {
                    "type": "top-bottom-settings",
                    "label": "Top Bottom Settings",
                    "defaultValue": {
                        "numberOfValues": 10,
                        "order": "top",
                        "showLabels": true
                    }
                },
                "valueFormat": {
                    "type": "value-format",
                    "defaultValue": "human",
                    "label": "Value format"
                },
                "threshold": {
                    "type": "threshold",
                    "defaultValue": {
                        "enabled": true,
                        "customCritical": null,
                        "criticalType": "default",
                        "customWarning": null,
                        "warningType": "default",
                        "baseColor": null
                    },
                    "label": "threshold"
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "data": {
                "resources": {
                    "type": "resources",
                    "defaultValue": []
                },
                "metrics": {
                    "type": "metrics",
                    "defaultValue": []
                }
            },
            "singleMetricSelection": true,
            "customBaseColor": false,
            "moduleName": "centreon-widget-topbottom",
            "canExtand": true
        },
        {
            "title": "Web page",
            "description": "Displays a web page",
            "icon": "<path d=\"M30,54c-3.2,0-6.4-0.6-9.3-1.9c-2.9-1.2-5.4-3-7.6-5.2c-2.2-2.2-3.9-4.8-5.2-7.6C6.6,36.4,6,33.2,6,30c0-3.2,0.6-6.4,1.9-9.3c1.2-2.8,3-5.4,5.2-7.6c2.2-2.2,4.8-3.9,7.6-5.2C23.6,6.6,26.8,6,30,6c3.2,0,6.4,0.6,9.3,1.9c2.8,1.2,5.4,3,7.6,5.2c2.2,2.2,3.9,4.8,5.2,7.6c1.3,2.9,1.9,6.1,1.9,9.3c0,3.2-0.6,6.4-1.9,9.3c-1.2,2.9-3,5.4-5.2,7.6c-2.2,2.2-4.8,3.9-7.6,5.2C36.4,53.4,33.2,54,30,54z M30,50.2c1.2-1.6,2.3-3.4,3.3-5.2c0.9-1.8,1.6-3.7,2.2-5.7H24.6c0.5,2,1.3,3.9,2.2,5.8C27.7,46.9,28.8,48.6,30,50.2z M25.1,49.5c-1-1.5-1.9-3.1-2.6-4.7c-0.8-1.8-1.4-3.6-1.8-5.4h-8.6c1.3,2.6,3.1,4.8,5.4,6.6C19.7,47.7,22.3,48.9,25.1,49.5z M34.9,49.5c2.8-0.6,5.4-1.8,7.6-3.6c2.2-1.8,4.1-4,5.4-6.6h-8.6c-0.5,1.9-1.1,3.7-1.9,5.5C36.7,46.4,35.9,48,34.9,49.5z M10.6,35.5H20c-0.2-0.9-0.3-1.9-0.3-2.8c-0.1-0.9-0.1-1.8-0.1-2.7c0-0.9,0-1.8,0.1-2.7s0.2-1.8,0.3-2.8h-9.4c-0.2,0.9-0.4,1.8-0.6,2.7c-0.1,0.9-0.2,1.9-0.2,2.8c0,0.9,0.1,1.9,0.2,2.8C10.1,33.7,10.3,34.6,10.6,35.5z M23.7,35.5h12.6c0.2-0.9,0.3-1.9,0.3-2.7s0.1-1.8,0.1-2.8c0-0.9,0-1.9-0.1-2.8c-0.1-0.9-0.2-1.8-0.3-2.7H23.7c-0.2,0.9-0.3,1.9-0.3,2.7c-0.1,0.9-0.1,1.8-0.1,2.8c0,0.9,0,1.9,0.1,2.8C23.5,33.6,23.6,34.5,23.7,35.5L23.7,35.5z M40.1,35.5h9.4c0.2-0.9,0.4-1.8,0.6-2.7c0.1-0.9,0.2-1.9,0.2-2.8c0-0.9-0.1-1.9-0.2-2.8c-0.1-0.9-0.3-1.8-0.6-2.7h-9.4c0.2,0.9,0.3,1.9,0.3,2.8c0.1,0.9,0.1,1.8,0.1,2.7c0,0.9,0,1.8-0.1,2.7C40.4,33.6,40.2,34.6,40.1,35.5L40.1,35.5z M39.3,20.7h8.6c-1.3-2.6-3.1-4.8-5.3-6.6c-2.2-1.8-4.9-3-7.6-3.6c1,1.5,1.9,3.1,2.6,4.8C38.2,17.1,38.9,18.9,39.3,20.7L39.3,20.7z M24.6,20.7h10.8c-0.5-2-1.3-4-2.2-5.8c-0.9-1.8-2-3.5-3.2-5c-1.2,1.6-2.3,3.3-3.2,5C25.9,16.8,25.2,18.7,24.6,20.7z M12.2,20.7h8.6c0.5-1.8,1.1-3.6,1.8-5.4c0.7-1.7,1.6-3.3,2.6-4.8c-2.8,0.6-5.4,1.8-7.7,3.6C15.2,15.9,13.4,18.2,12.2,20.7L12.2,20.7z\"/>",
            "moduleName": "centreon-widget-webpage",
            "options": {
                "url": {
                    "type": "textfield",
                    "defaultValue": "",
                    "label": "URL",
                    "required": true,
                    "secondaryLabel": "URL"
                },
                "refreshInterval": {
                    "type": "refresh-interval",
                    "defaultValue": "default",
                    "label": "Interval"
                }
            },
            "message": {
                "label": "Please note that domains outside your organization must be authorized in your Apache configuration.",
                "icon": "<path fill-rule=\"evenodd\" d=\"M15.35 8c0 3.377-2.945 6.25-6.75 6.25S1.85 11.377 1.85 8 4.795 1.75 8.6 1.75 15.35 4.623 15.35 8zm1.25 0c0 4.142-3.582 7.5-8 7.5S.6 12.142.6 8C.6 3.858 4.182.5 8.6.5s8 3.358 8 7.5zM9.229 3.101l-.014 7.3-1.25-.002.014-7.3 1.25.002zm.016 9.249a.65.65 0 1 0-1.3 0 .65.65 0 0 0 1.3 0z\"/>"
            },
            "availableOnPremOnly": true,
            "canExtand": true
        }
    ]

    const version = {
      "modules": {},
      "web": {
          "fix": "0",
          "major": "24",
          "minor": "11",
          "version": "24.11.0"
      },
      "widgets":{
        "centreon-widget-graph": {
            "version": "24.11.0",
            "major": "24",
            "minor": "11",
            "fix": "0"
        },
        "centreon-widget-topbottom": {
            "version": "24.11.0",
            "major": "24",
            "minor": "11",
            "fix": "0"
        },
        "centreon-widget-statusgrid": {
            "version": "24.11.0",
            "major": "24",
            "minor": "11",
            "fix": "0"
        },
        "centreon-widget-resourcestable": {
            "version": "24.11.0",
            "major": "24",
            "minor": "11",
            "fix": "0"
        },
        "centreon-widget-groupmonitoring": {
            "version": "24.11.0",
            "major": "24",
            "minor": "11",
            "fix": "0"
        },
        "centreon-widget-statuschart": {
            "version": "24.11.0",
            "major": "24",
            "minor": "11",
            "fix": "0"
        },
        "centreon-widget-webpage": {
            "version": "24.11.0",
            "major": "24",
            "minor": "11",
            "fix": "0"
        }
    }
  }

  const initializeWidgets = (): ReturnType<typeof createStore> => {
    const store = createStore();
    store.set(federatedWidgetsAtom, federatedWidgets);
    store.set(federatedWidgetsPropertiesAtom, federatedWidgetsProperties);
    store.set(platformVersionsAtom, version);
  
    return store;
  };



cy.fixture('Dashboards/Dashboard/ExpandReduce/widgetGraphData.json').then(data=>{
    cy.interceptAPIRequest({
        path:'./api/latest/monitoring/dashboard/metrics/performances/data?**',
        response:data,
        method:Method.GET,
        alias:'graphData'
    })
})

cy.fixture('Dashboards/Dashboard/ExpandReduce/widgetTopBottomData.json').then(data=>{
    cy.interceptAPIRequest({
        path:'./api/latest/monitoring/dashboard/metrics/top?**',
        response:data,
        method:Method.GET,
        alias:'topBottom'
    })
})

cy.fixture('Dashboards/Dashboard/ExpandReduce/widgetRSdata.json').then(data=>{
    cy.interceptAPIRequest({
        path:'./api/latest/monitoring/resources?**',
        response:data,
        method:Method.GET,
        alias:'rsourceStatus'
    })
})

cy.fixture('Dashboards/Dashboard/ExpandReduce/widgetStatusChart/services.json').then(data=>{
    cy.interceptAPIRequest({
        path:'./api/latest/monitoring/services/status?**',
        response:data,
        method:Method.GET,
        alias:'statusChartService'
    })
})

cy.fixture('Dashboards/Dashboard/ExpandReduce/widgetStatusChart/hosts.json').then(data=>{
    cy.interceptAPIRequest({
        path:'./api/latest/monitoring/hosts/status?**',
        response:data,
        method:Method.GET,
        alias:'statusChartHosts'
    })
})

cy.fixture('Dashboards/Dashboard/ExpandReduce/widgetGroupMonitoringData.json').then(data=>{
    cy.interceptAPIRequest({
        path:'./api/latest/monitoring/hostgroups?**',
        response:data,
        method:Method.GET,
        alias:'groupMonitoring'
    })
})


 


      initializeAndMount({customDetailsPath:'Dashboards/Dashboard/ExpandReduce/details.json',store:initializeWidgets()});


      cy.waitForRequest('@getDashboardDetails')
      cy.waitForRequest('@graphData')
      cy.waitForRequest('@topBottom')
      cy.waitForRequest('@rsourceStatus')
      cy.waitForRequest('@statusChartService')
      cy.waitForRequest('@statusChartHosts')
      cy.waitForRequest('@groupMonitoring')


      federatedWidgets.forEach(({properties},index)=>{

            cy.findAllByLabelText(labelMoreActions).eq(index).click();
      
            // cy.makeSnapshot('extend',properties.name)
      
           cy.findByRole('menuitem',{name:labelExtend}).click()
            cy.findByRole('dialog').as('modal')
            cy.get('@modal').should('be.visible')
            cy.get('@modal').findByTestId(labelMoreActions).click()
            // cy.makeSnapshot('reduce')
            cy.findByRole('menuitem',{name:labelReduce}).click()
      })


    })

  })
});

describe('Dashboard with complex layout', () => {
  it('displays a new widget in the correct position when a widget is added', () => {
    const store = initializeWidgets();

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

    const proceedNavigation = cy.stub();
    const blockNavigation = cy.stub();

    cy.stub(routerParams, 'useParams').returns({ dashboardId: '1' });
    cy.stub(saveBlockerHooks, 'useBlocker').returns({
      proceed: proceedNavigation,
      reset: blockNavigation,
      state: 'unblocked'
    });

    i18next.use(initReactI18next).init({
      lng: 'en',
      resources: {}
    });

    cy.viewport('macbook-13');

    cy.fixture('Dashboards/Dashboard/complexLayout.json').then(
      (dashboardDetails) => {
        cy.interceptAPIRequest({
          alias: 'getDashboardDetails',
          method: Method.GET,
          path: getDashboardEndpoint('1'),
          response: dashboardDetails
        });
      }
    );

    cy.mount({
      Component: (
        <div style={{ height: '90vh' }}>
          <TestQueryProvider>
            <BrowserRouter>
              <SnackbarProvider>
                <Provider store={store}>
                  <Dashboard />
                </Provider>
              </SnackbarProvider>
            </BrowserRouter>
          </TestQueryProvider>
        </div>
      )
    });

    cy.waitForRequest('@getDashboardDetails');

    cy.findByLabelText(labelAddAWidget).click();
    cy.findByLabelText(labelWidgetType).click();
    cy.contains('Allows you to add free text').click();
    cy.findAllByLabelText(labelSave).eq(1).click();

    cy.get('.react-grid-item')
      .eq(4)
      .should('have.css', 'transform', 'matrix(1, 0, 0, 1, 315, 0)');

    cy.makeSnapshot();
  });
});
