import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';

before(() => {
  cy.startWebContainer();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?page=1&limit=4'
  }).as('listAllDashboards');
});

Given(
  'a user with dashboard edition rights on the dashboard listing page',
  () => {
    loginAsAdminViaApiV2();
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  }
);

When('they open the form to create a new dashboard', () => {
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

When('they fill in the mandatory fields of the form', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-1');

  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    'My First Dashboard :)'
  );
});

Then('they are allowed to create the dashboard', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should(
    'have.value',
    'dashboard-1'
  );

  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should(
    'have.value',
    'My First Dashboard :)'
  );

  cy.getByLabel({ label: 'submit', tag: 'button' }).should('be.enabled');
});

When('they save the dashboard', () => {
  cy.getByLabel({ label: 'submit', tag: 'button' }).click();
  cy.reload();
});

Then('the newly created dashboard appears in the dashboards library', () => {
  cy.getByLabel({ label: 'view', tag: 'button' }).should(
    'contain.text',
    'dashboard-1'
  );
});

afterEach(() => {
  cy.getByLabel({ label: 'delete', tag: 'button' }).each((element) => {
    cy.wrap(element).click();
    cy.getByLabel({ label: 'confirm', tag: 'button' }).click();
    cy.wait('@listAllDashboards');
  });
});
