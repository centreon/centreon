import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';

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
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-regex-filter.json'
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

after(() => {
  cy.stopContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
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

Given("a dashboard exists in the dashboard administrator's library", () => {
  cy.insertDashboard({ ...dashboards.default });
  cy.visitDashboard(dashboards.default.name);
});

When(
  'the dashboard administrator user selects the option to add a new widget',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 0);
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.contains('div[class*="-addWidgetPanel"] h5', 'Add a widget').click();
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
  'configuration options for the "Group monitoring" widget are displayed',
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

Then('the Save button is disabled', () => {
  cy.getByTestId({ testId: 'confirm' }).should('be.disabled');
});

When(
  'the dashboard administrator user applies a regex filter to select host group resources for the widget',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'RegexIcon' }).should('be.visible');
    cy.getByTestId({ testId: 'RegexIcon' }).click();
    cy.getByTestId({ testId: 'Exit regex mode-host-group' }).should(
      'be.visible'
    );
    cy.get('#EnterRegexhostgroup').type('^lin');
    cy.waitUntil(
      () =>
        cy
          .get('[class$="-status"]')
          .eq(0)
          .then((el) => Cypress.dom.isVisible(el)),
      {
        errorMsg: '❌ Status element not visible within timeout',
        interval: 2000,
        timeout: 20000
      }
    );
  }
);

Then(
  'a table showing statuses of the matching resources are displayed in the widget preview',
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

Then('the Save button is enabled', () => {
  cy.getByTestId({ testId: 'confirm' }).should('be.enabled');
});

When('the user saves the "Group monitoring" widget', () => {
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

When(
  'the dashboard administrator user selects the widget type "Resource table"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Resource table').click();
  }
);

Then(
  'configuration options for the "Resource table" widget are displayed',
  () => {
    cy.contains('Widget properties').should('exist');
    cy.getByLabel({ label: 'Title' }).should('exist');

    cy.get('button[data-testid="View by host"]').should('exist');
    cy.get('button[data-testid="All"]').should('exist');

    cy.get('input[name="success"]').should('exist');
    cy.get('input[name="warning"]').should('exist');
    cy.get('input[name="problem"]').should('exist');
    cy.get('input[name="undefined"]').should('exist');
    cy.get('input[name="pending"]').should('exist');
    cy.get('input[name="unhandled_problems"]').should('exist');
    cy.get('input[name="acknowledged"]').should('exist');
    cy.get('input[name="in_downtime"]').should('exist');
  }
);

When(
  'the dashboard administrator user applies a regex filter to select the hosts and services displayed by the widget',
  () => {
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host' }).click();
    cy.getByTestId({ testId: 'RegexIcon' }).should('be.visible');
    cy.getByTestId({ testId: 'RegexIcon' }).click();
    cy.getByTestId({ testId: 'Exit regex mode-host' }).should('be.visible');
    cy.get('#EnterRegexhost').type('^cen.*er$');
    cy.wait('@resourceRequest');
    cy.getByTestId({ testId: 'Add filter' }).click();
    cy.getByTestId({ testId: 'Resource type' }).eq(1).realClick();
    cy.getByLabel({ label: 'Service' }).click();
    cy.getByTestId({ testId: 'RegexIcon' }).eq(1).should('be.visible');
    cy.getByTestId({ testId: 'RegexIcon' }).eq(1).click();
    cy.get('#EnterRegexservice').type('^CPU$');
    cy.wait('@resourceRequest');
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.get('input[name="problem"]').click();
    cy.get('input[name="unhandled_problems"]').click();
    cy.get('input[name="warning"]').click();
  }
);

When('the user saves the "Resource table" widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then(
  'the resource table widget is added to the dashboard layout and correctly displays the filtered hosts',
  () => {
    cy.waitUntil(
      () =>
        cy
          .get(
            '.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(4)'
          )
          .should('be.visible')
          .find('p')
          .then((paragraphs) => {
            const found = [...paragraphs].some(
              (el) => el.textContent?.trim() === 'Centreon-Server'
            );
            return found;
          }),
      {
        errorMsg:
          '❌ "Centreon-Server" not found as exact text in <p> tags of the 3rd cell of the first row',
        interval: 2000,
        timeout: 20000
      }
    );
  }
);

When(
  'the dashboard administrator selects a service using a regex filter to configure the widget’s data source',
  () => {
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Service' }).click();
    cy.getByTestId({ testId: 'RegexIcon' }).should('be.visible');
    cy.getByTestId({ testId: 'RegexIcon' }).click();
    cy.getByTestId({ testId: 'Exit regex mode-service' }).should('be.visible');
    cy.get('#EnterRegexservice').type('^Service3');
    cy.wait('@resourceRequest');
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
  }
);

Then(
  'the resource table widget is added to the dashboard layout and shows the filtered service correctly',
  () => {
    cy.waitUntil(
      () =>
        cy
          .get(
            '.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(3)'
          )
          .should('be.visible')
          .find('p')
          .then((paragraphs) => {
            const found = [...paragraphs].some(
              (el) => el.textContent?.trim() === 'service3'
            );
            return found;
          }),
      {
        errorMsg:
          '❌ "service3" not found as exact text in <p> tags of the 3rd cell of the first row',
        interval: 2000,
        timeout: 20000
      }
    );
  }
);
