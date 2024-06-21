import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import statusGridWidget from '../../../fixtures/dashboards/creation/widgets/status-grid-widget.json';
import twoStatusGridWidgets from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwostatusGrid.json';

const services = {
  serviceCritical: {
    host: 'host3',
    name: 'service3',
    template: 'SNMP-Linux-Load-Average'
  },
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
  serviceWarning: {
    host: 'host2',
    name: 'service2',
    template: 'SNMP-Linux-Memory'
  }
};
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceCritical.name,
    status: 'critical'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceWarning.name,
    status: 'warning'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceOk.name,
    status: 'ok'
  },
  {
    host: services.serviceCritical.host,
    output: 'submit_status_2',
    service: services.serviceOk.name,
    status: 'ok'
  }
];
before(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload'
  }).as('generateAndReloadPollers');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-metrics-graph.json'
  );
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: 'service2',
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceCritical.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: 'service2',
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  cy.loginByTypeOfUser({
    jsonName: 'admin'
  });

  checkHostsAreMonitored([
    { name: services.serviceOk.host },
    { name: services.serviceCritical.host }
  ]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name }
  ]);
  cy.submitResults(resultsToSubmit);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name, status: 'critical' },
    { name: services.serviceOk.name, status: 'ok' }
  ]);

  cy.logoutViaAPI();
  cy.applyAcl();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards**'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.intercept({
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

after(() => {
  cy.stopContainers();
});

Given('a dashboard that includes a configured Status Grid widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, statusGridWidget);
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.editWidget(1);
});

When(
  'the dashboard administrator user selects a particular status in the displayed resource status list',
  () => {
    cy.get('input[value="service"]').click();
    cy.get('input[name="success"]').click();
    cy.wait('@resourceRequest');
  }
);

Then(
  'only the resources with this particular status are displayed in the Status Grid Widget',
  () => {
    cy.get('[data-status="critical"]').should('be.visible');
    cy.get('[data-status="warning"]').should('be.visible');
  }
);

Given('a dashboard configuring Status Grid widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, statusGridWidget);
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.editWidget(1);
});

When(
  'the dashboard administrator user updates the displayed resource type of the widget',
  () => {
    cy.get('input[value="service"]').click();
    cy.get('input[name="success"]').click();
    cy.wait('@resourceRequest');
  }
);

Then(
  'the list of available statuses to display is updated in the configuration properties',
  () => {
    cy.get('input[name="warning"]').should('exist');
    cy.get('input[name="problem"]').should('exist');
    cy.get('input[name="undefined"]').should('exist');
    cy.get('input[name="pending"]').should('exist');
  }
);

Then(
  'the widget is updated to reflect that change in displayed resource type',
  () => {
    cy.get('[data-status="warning"]').should('exist');
    cy.get('[data-status="critical"]').should('exist');
  }
);

Given('a dashboard featuring two Status Grid widgets', () => {
  cy.insertDashboardWithWidget(dashboards.default, twoStatusGridWidgets);
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.getByTestId({ testId: 'More actions' }).eq(0).click();
});

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByTestId({ testId: 'DeleteIcon' }).click();
  cy.getByLabel({
    label: 'Delete',
    tag: 'button'
  }).realClick();
});

Then('only the contents of the other widget are displayed', () => {
  cy.get('[class*="resourceName"]').contains('Centreon-Server').should('exist');
  cy.get('[class*="resourceName"]').contains('host2').should('exist');
  cy.get('[class*="resourceName"]').contains('host3').should('exist');
});

Given(
  "a dashboard in the dashboard administrator user's dashboard library",
  () => {
    cy.insertDashboard({ ...dashboards.default });
    cy.visitDashboard(dashboards.default.name);
  }
);

When(
  'the dashboard administrator user selects the option to add a new widget',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 0);
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.getByTestId({ testId: 'AddIcon' }).should('have.length', 1).click();
  }
);

When('selects the widget type "Status Grid"', () => {
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Status grid').click();
});

Then(
  'configuration properties for the Status Grid widget are displayed',
  () => {
    cy.contains('Widget properties').should('exist');
    cy.getByLabel({ label: 'Title' }).should('exist');
    cy.get('input[value="host"]').should('exist');
    cy.get('input[value="service"]').should('exist');
    cy.get('input[name="success"]').should('exist');
    cy.get('input[name="problem"]').should('exist');
    cy.get('input[name="undefined"]').should('exist');
    cy.get('input[name="pending"]').should('exist');
    cy.get('input[value="status_severity_code"]').should('exist');
    cy.get('input[value="name"]').should('exist');
  }
);

When(
  'the dashboard administrator user selects a list of resources for the widget',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains('Linux-Servers').realClick();
    cy.get('input[name="success"]').click();
  }
);

Then(
  'a grid representing the statuses of this list of resources are displayed in the widget preview',
  () => {
    cy.get('[class*="heatMapTile"]').should('exist');
  }
);

When('the user saves the Status Grid widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Status Grid widget is added in the dashboard's layout", () => {
  cy.get('[class*="heatMapTile"]').should('exist');
});

Given('a dashboard with a configured Status Grid widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, statusGridWidget);
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.editWidget(1);
});

When(
  'the dashboard administrator user updates the maximum number of displayed tiles in the configuration properties',
  () => {
    cy.getByLabel({
      label: 'tiles',
      tag: 'input'
    }).clear();
    cy.wait('@resourceRequest');
  }
);

Then('the Status Grid widget displays up to that number of tiles', () => {
  cy.getByTestId({
    testId: 'DvrIcon'
  }).should('be.visible');
  cy.get('[class*="resourceName"]').contains('Centreon-Server').should('exist');
});

Given('a dashboard having a configured Status Grid widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, statusGridWidget);
  cy.visitDashboard(dashboards.default.name);
});

When(
  'the dashboard administrator user duplicates the Status Grid widget',
  () => {
    cy.getByTestId({ testId: 'RefreshIcon' }).should('be.visible');
    cy.getByTestId({ testId: 'RefreshIcon' }).click();
    cy.getByLabel({
      label: 'Edit dashboard',
      tag: 'button'
    }).click();
    cy.getByTestId({ testId: 'More actions' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click();
    cy.wait('@resourceRequest');
  }
);

Then('a second Status Grid widget is displayed on the dashboard', () => {
  cy.getByTestId({
    testId: 'panel_/widgets/statusgrid_1_2_move_panel'
  }).should('exist');
});

Then('the second widget has the same properties as the first widget', () => {
  cy.get('[class*="resourceName"]')
    .eq(3)
    .contains('Centreon-Server')
    .should('exist');
  cy.get('[class*="resourceName"]').eq(4).contains('host2').should('exist');
  cy.get('[class*="resourceName"]').eq(5).contains('host3').should('exist');
});
