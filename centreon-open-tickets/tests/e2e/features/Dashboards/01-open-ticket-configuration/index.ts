import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import {
  checkHostsAreMonitored,
  checkServicesAreMonitored,
  checkMetricsAreMonitored
} from '../../../common';

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
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.startContainers({
    moduleName: 'centreon-open-tickets',
    useSlim: false,
    profiles:['glpi']
  });
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

  ['Disk-/', 'Load', 'Memory', 'Ping'].forEach((service) => {
    cy.scheduleServiceCheck({ host: 'Centreon-Server', service });
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
  checkMetricsAreMonitored([
    {
      host: 'Centreon-Server',
      name: 'rta',
      service: 'Ping'
    }
  ]);
  cy.logoutViaAPI();
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
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
  cy.intercept({
    method: 'GET',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('getDashboard');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('addingDashboard');
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
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
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.getByTestId({ testId: 'AddIcon' }).click();
  }
);

When(
  'the dashboard administrator selects the widget type "resource table"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Resource table').click();
  }
);

Then('configuration properties for ticket management are displayed', () => {
  cy.contains('Select all').click();
  cy.get('input[name="acknowledged"]').click();
  cy.get('input[name="in_downtime"]').click();
  cy.get('[data-testid="-summary"]').contains('Ticket management').click();
  cy.getByLabel({ label: 'Enable ticket management' }).click();
  cy.getByTestId({ testId: 'Select rule (ticket provider)' }).click();
  cy.contains('glpi').click();
});

When(
  'the dashboard administrator selects a resource to associate with a ticket',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains('Linux-Servers').realClick();
    cy.wait('@resourceRequest');
    cy.getByLabel({ label: 'Open ticket for service' }).eq(0).click();
  }
);

Then('the open ticket modal should appear', () => {
  cy.waitForElementInIframe(
    '#open-ticket',
    '#select2-select_glpi_entity-container'
  );
});

When(
  'the dashboard administrator fills out the ticket creation form and submits the form',
  () => {
    cy.enterIframe('#open-ticket').within(() => {
      cy.get('#custom_message').type('New ticket');
      cy.get('#select2-select_glpi_entity-container').click();
      cy.contains('Root entity').click({ force: true });
      cy.get('#select_glpi_requester').select('glpi', { force: true });
      cy.get('input[type="submit"][value="Open"]').click();
    });
  }
);

Then(
  'a new ticket is created and the selected resource is associated with the ticket',
  () => {
    cy.waitForElementInIframe('#open-ticket', 'h3');
    cy.enterIframe('#open-ticket').within(() => {
      cy.get('td.FormRowField').should('include.text', 'New ticket opened');
    });
    cy.get('[class$="modalCloseButton"]')
      .find('[data-testid="CloseIcon"]')
      .eq(1)
      .click();
    cy.getByLabel({ label: 'Resources linked to a ticket' }).click();
    cy.getByTestId({ testId: 'confirm' }).realClick();
    cy.getByTestId({ testId: 'save_dashboard' }).click();
    cy.wait('@addingDashboard');
  }
);

Given('the dashboard administrator accesses the resource table widget', () => {
  cy.visitDashboard(dashboards.default.name);
  cy.editDashboard(dashboards.default.name);
});

When(
  'the dashboard administrator clicks on the delete button of a ticket',
  () => {
    cy.getByLabel({ label: 'Close ticket' }).click({ force: true });
    cy.contains('Confirm').click();
  }
);

Then(
  'the ticket should be deleted and the resource should no longer be associated with the ticket',
  () => {
    cy.waitUntil(
      () => {
        return cy
          .getByLabel({ label: 'Unknown status services', tag: 'a' })
          .invoke('text')
          .then((text) => {
            if (text !== '3') {
              cy.exportConfig();
            }

            return text === '3';
          });
      },
      { interval: 20000, timeout: 600000 }
    );
    cy.waitForElementToBeVisible('[class*="root-emptyDataCell"]');
    cy.contains('div', 'No result found').should('be.visible');
  }
);
