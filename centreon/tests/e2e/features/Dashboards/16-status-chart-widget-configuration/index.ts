import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

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
  // cy.startContainers();
  // cy.enableDashboardFeature();
  // cy.executeCommandsViaClapi(
  //   'resources/clapi/config-ACL/dashboard-metrics-graph.json'
  // );
  // cy.addHost({
  //   hostGroup: 'Linux-Servers',
  //   name: services.serviceOk.host,
  //   template: 'generic-host'
  // })
  //   .addService({
  //     activeCheckEnabled: false,
  //     host: services.serviceOk.host,
  //     maxCheckAttempts: 1,
  //     name: services.serviceOk.name,
  //     template: services.serviceOk.template
  //   })
  //   .addService({
  //     activeCheckEnabled: false,
  //     host: services.serviceOk.host,
  //     maxCheckAttempts: 1,
  //     name: 'service2',
  //     template: services.serviceWarning.template
  //   })
  //   .addService({
  //     activeCheckEnabled: false,
  //     host: services.serviceOk.host,
  //     maxCheckAttempts: 1,
  //     name: services.serviceCritical.name,
  //     template: services.serviceCritical.template
  //   });
  // cy.addHost({
  //   hostGroup: 'Linux-Servers',
  //   name: services.serviceCritical.host,
  //   template: 'generic-host'
  // })
  //   .addService({
  //     activeCheckEnabled: false,
  //     host: services.serviceCritical.host,
  //     maxCheckAttempts: 1,
  //     name: services.serviceOk.name,
  //     template: services.serviceOk.template
  //   })
  //   .addService({
  //     activeCheckEnabled: false,
  //     host: services.serviceCritical.host,
  //     maxCheckAttempts: 1,
  //     name: 'service2',
  //     template: services.serviceWarning.template
  //   })
  //   .addService({
  //     activeCheckEnabled: false,
  //     host: services.serviceCritical.host,
  //     maxCheckAttempts: 1,
  //     name: services.serviceCritical.name,
  //     template: services.serviceCritical.template
  //   })
  //   .applyPollerConfiguration();

  // cy.loginByTypeOfUser({
  //   jsonName: 'admin'
  // });

  // checkHostsAreMonitored([
  //   { name: services.serviceOk.host },
  //   { name: services.serviceCritical.host }
  // ]);
  // checkServicesAreMonitored([
  //   { name: services.serviceCritical.name },
  //   { name: services.serviceOk.name }
  // ]);
  // cy.submitResults(resultsToSubmit);
  // checkServicesAreMonitored([
  //   { name: services.serviceCritical.name, status: 'critical' },
  //   { name: services.serviceOk.name, status: 'ok' }
  // ]);

  // cy.logoutViaAPI();
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
    cy.visit('/centreon/home/dashboards');
    cy.wait('@listAllDashboards');
    cy.contains(dashboards.default.name).click();
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
  'the dashboard administrator user selects the widget type "Status chart"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
  }
);

Then(
  'configuration properties for the status chart widget are displayed',
  () => {
    cy.getByTestId({ testId: 'up' }).should('be.visible');
    cy.getByTestId({ testId: 'CheckCircleIcon' }).should('exist');
    cy.getByLabel({ label: 'Donut chart' }).should('exist');
    cy.getByLabel({ label: 'Pie chart' }).should('exist');
    cy.getByLabel({ label: 'Vertical bar chart' }).should('exist');
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
