import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import groupMonitoringwidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithGroupMonitoringWidget.json';
import twoGroupMonitoringwidgets from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwoGroupMonitoringWidgets.json';

const hostGroupName = 'Linux-Servers';

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
    });
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

When(
  'the dashboard administrator user selects the widget type "Group monitoring"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Group monitoring').click();
  }
);

Then(
  'configuration properties for the Group monitoring widget are displayed',
  () => {
    cy.contains('Host').should('exist');
    cy.contains('Service').should('exist');
    cy.contains('Success (OK/Up)').should('exist');
    cy.contains('Warning').should('exist');
    cy.contains('Problem (Down/Critical)').should('exist');
    cy.contains('Undefined (Unreachable/Unknown)').should('exist');
    cy.contains('Pending').should('exist');
  }
);

When(
  'the dashboard administrator user selects resources for the widget',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains(hostGroupName).realClick();
  }
);

Then(
  'a table representing the statuses of this list of resources are displayed in the widget preview',
  () => {
    cy.get('[class$="-status"]')
      .eq(0)
      .should('have.attr', 'data-status', 'Down')
      .should('be.visible');
    cy.get('[class$="-status"]')
      .eq(1)
      .should('have.attr', 'data-status', 'Critical')
      .should('be.visible');
    cy.get('[class$="-status"]')
      .eq(2)
      .should('have.attr', 'data-status', 'Warning')
      .should('be.visible');
  }
);

When('the user saves the Group monitoring widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Group monitoring widget is added in the dashboard's layout", () => {
  cy.get('[class$="-status-link"]')
    .eq(0)
    .should('have.attr', 'data-status', 'Down')
    .should('be.visible');
  cy.get('[class$="-status-link"]')
    .eq(1)
    .should('have.attr', 'data-status', 'Critical')
    .should('be.visible');
});

Given('a dashboard that includes a configured Group monitoring widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, groupMonitoringwidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
});

When(
  'the dashboard administrator user selects a particular status in the displayed resource status list',
  () => {
    cy.contains('Select all').click();
    cy.getByTestId({ testId: 'confirm' }).click();
  }
);

Then(
  'only the resources with this particular status are displayed in the Group monitoring Widget',
  () => {
    cy.get('[class$="-status-link"]')
      .eq(0)
      .should('have.attr', 'data-status', 'Down')
      .should('be.visible');
    cy.get('[class$="-status-link"]')
      .eq(1)
      .should('have.attr', 'data-status', 'Pending')
      .should('be.visible');
    cy.get('[class$="-status-link"]')
      .eq(2)
      .should('have.attr', 'data-status', 'Up')
      .should('be.visible');
    cy.get('[class$="-status-link"]')
      .eq(3)
      .should('have.attr', 'data-status', 'Unreachable')
      .should('be.visible');
  }
);

Given('a dashboard featuring two group monitoring widgets', () => {
  cy.insertDashboardWithWidget(dashboards.default, twoGroupMonitoringwidgets);
  cy.editDashboard(dashboards.default.name);
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
  cy.get('[class$="-status-link"]')
    .eq(0)
    .should('have.attr', 'data-status', 'Down')
    .should('be.visible');
  cy.get('[class$="-status-link"]')
    .eq(1)
    .should('have.attr', 'data-status', 'Critical')
    .should('be.visible');
});

Given('a dashboard having a configured group monitoring widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, groupMonitoringwidget);
  cy.visitDashboard(dashboards.default.name);
});

When(
  'the dashboard administrator user duplicates the group monitoring widget',
  () => {
    cy.getByTestId({ testId: 'RefreshIcon' }).should('be.visible');
    cy.getByTestId({ testId: 'RefreshIcon' }).click();
    cy.getByLabel({
      label: 'Edit dashboard',
      tag: 'button'
    }).click();
    cy.getByTestId({ testId: 'More actions' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click({ force: true });
  }
);

Then('a second Status Grid widget is displayed on the dashboard', () => {
  cy.getByTestId({
    testId: 'panel_/widgets/groupmonitoring_1_2_move_panel'
  }).should('exist');
});

Then('the second widget has the same properties as the first widget', () => {
  cy.get('[class$="-status-link"]')
    .eq(3)
    .should('have.attr', 'data-status', 'Down')
    .should('be.visible');
  cy.get('[class$="-status-link"]')
    .eq(4)
    .should('have.attr', 'data-status', 'Critical')
    .should('be.visible');
});

Given('a dashboard configuring group monitoring widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, groupMonitoringwidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
});

When(
  'the dashboard administrator user updates the displayed resource type of the widget',
  () => {
    cy.getByTestId({ testId: 'ExpandMoreIcon' }).eq(0).click();
    cy.get('input[name="host"].PrivateSwitchBase-input').click();
  }
);

Then(
  'the widget is updated to reflect that change in displayed resource type',
  () => {
    cy.get('[class$="-status"]')
      .eq(0)
      .should('have.attr', 'data-status', 'Critical')
      .should('be.visible');
    cy.get('[class$="-status"]')
      .eq(1)
      .should('have.attr', 'data-status', 'Warning')
      .should('be.visible');
  }
);

Given('a dashboard with a group monitoring widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, groupMonitoringwidget);
  cy.editDashboard(dashboards.default.name);
  cy.contains('Linux-Servers').should('be.visible');
});

When('the dashboard administrator clicks on a random resource', () => {
  cy.contains('a', 'Linux-Servers')
    .should('have.attr', 'href')
    .then((href) => {
      cy.visit(href);
    });
});

Then(
  'the user should be redirected to the resource status screen and all the resources must be displayed',
  () => {
    cy.contains('host2').should('exist');
  }
);
