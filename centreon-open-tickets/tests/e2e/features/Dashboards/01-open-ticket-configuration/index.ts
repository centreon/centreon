import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import {
  checkMetricsAreMonitored
} from '../../../common';

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
   cy.loginByTypeOfUser({
    jsonName: 'admin'
  });

  ['Disk-/', 'Load', 'Memory', 'Ping'].forEach((service) => {
    cy.scheduleServiceCheck({ host: 'Centreon-Server', service });
  });
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
  cy.loginByTypeOfUser({
    jsonName: 'admin',
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

Then(
  'configuration properties for ticket management are displayed',
  () => {
    cy.contains('Select all').click();
    cy.get('[data-testid="-summary"]').contains('Ticket management').click();
    cy.getByLabel({ label: 'Enable ticket management' }).click()
    cy.getByTestId({ testId: 'Select rule (ticket provider)' }).click()
    cy.contains('glpi').click();  }
);

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

When("the dashboard administrator fills out the ticket creation form and submits the form", () => {
  cy.enterIframe('#open-ticket').within(() => {
      cy.get('#custom_message').type('New ticket');
      cy.get('#select2-select_glpi_entity-container').click();
      cy.contains('Root entity').click({force:true});
      cy.get('#select_glpi_requester').select('glpi',{force:true});
      cy.get('input[type="submit"][value="Open"]').click();
    });
});

Then("a new ticket is created and the selected resource is associated with the ticket", () => {
    cy.waitForElementInIframe('#open-ticket', 'td.FormRowField').then(() => {
      cy.get('#open-ticket').then(($iframe) => {
        const iframeBody = $iframe[0].contentDocument.body;
        cy.wrap(iframeBody)
          .find('td.FormRowField')
          .should('have.text')
          .and('include', 'New ticket opened');
      });
  });
});
