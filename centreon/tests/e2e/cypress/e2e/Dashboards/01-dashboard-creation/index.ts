import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-configuration-creator.json'
  );
});

after(() => {
  cy.stopWebContainer();
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
  cy.loginByTypeOfUser({
    jsonName: 'user-dashboard-creator',
    loginViaApi: false
  });
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});


Given(
  'a user with dashboard edition rights on the dashboard listing page',
  () => {
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  }
);

When('the user opens the form to create a new dashboard', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
});

Then(
  'the creation form is displayed and contains the fields to create a dashboard',
  () => {
    cy.contains('Create dashboard').should('be.visible');

    cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');

    cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');

    cy.getByLabel({ label: 'submit', tag: 'button' }).should('be.disabled');

    cy.getByLabel({ label: 'cancel', tag: 'button' }).should('be.enabled');
  }
);

When('the user fills in the required fields', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    dashboards.requiredOnly.name
  );
});

Then(
  'the user is allowed to create the dashboard with the required fields only',
  () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'have.value',
      dashboards.requiredOnly.name
    );

    cy.getByLabel({ label: 'submit', tag: 'button' }).should('be.enabled');
  }
);

When('the user saves the dashboard', () => {
  cy.getByLabel({ label: 'submit', tag: 'button' }).click();
  cy.wait('@createDashboard');
});

Then('the user is redirected to the newly created dashboard', () => {
  cy.location('pathname')
    .should('include', '/dashboards/')
    .invoke('split', '/')
    .should('not.be.empty')
    .then(Cypress._.last)
    .then(Number)
    .should('not.be', 'dashboards')
    .should('be.a', 'number'); // dashboard id
});

Then('the newly created dashboard detail page is in edit mode', () => {
  cy.location('search').should('include', 'edit=true');
});

Then('the newly created dashboard has the required only dashboard data', () => {
  cy.get('@createDashboard')
    .its('response.body.name')
    .should('eq', dashboards.requiredOnly.name);

  cy.getByLabel({ label: 'page header title' }).should(
    'contain.text',
    dashboards.requiredOnly.name
  );
});

Given(
  'a user with dashboard edition rights on the dashboard creation form',
  () => {
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
  }
);

When('the user fills in all the fields', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(dashboards.default.name);
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    dashboards.default.description
  );
});

Then(
  'the newly created dashboard has the name and description the user filled in',
  () => {
    cy.get('@createDashboard')
      .its('response.body.name')
      .should('eq', dashboards.default.name);
    cy.get('@createDashboard')
      .its('response.body.description')
      .should('eq', dashboards.default.description);

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.default.name
    );
    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      dashboards.default.description
    );
  }
);

Given(
  'a user with dashboard edition rights who is about to create a dashboard',
  () => {
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(
      `${dashboards.default.name} to be cancelled`
    );
  }
);

When('the user leaves the creation form without saving the dashboard', () => {
  cy.getByLabel({ label: 'cancel', tag: 'button' }).click();
});

Then('the dashboard has not been created', () => {
  cy.get('@createDashboard').should('be.null');
});

Then('the user is on the dashboards overview page', () => {
  cy.location('pathname')
    .should('include', '/dashboards')
    .invoke('split', '/')
    .should('not.be.empty')
    .then(Cypress._.last)
    .should('eq', 'dashboards'); // dashboards overview
});

When(
  'the user opens the form to create a new dashboard for the second time',
  () => {
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
  }
);

Then('the form fields are empty', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
});


