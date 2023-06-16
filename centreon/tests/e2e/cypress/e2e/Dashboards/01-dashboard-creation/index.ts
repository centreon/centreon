import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@0@1@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
});

beforeEach(() => {
  loginAsAdminViaApiV2();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?page=1&limit=100'
  }).as('listAllDashboards');
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

When('the user fills in the name field', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-without-desc');
});

Then('the user is allowed to create the dashboard', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should(
    'have.value',
    'dashboard-without-desc'
  );

  cy.getByLabel({ label: 'submit', tag: 'button' }).should('be.enabled');
});

When('the user saves the dashboard', () => {
  cy.getByLabel({ label: 'submit', tag: 'button' }).click();
});

Then('the newly created dashboard appears in the dashboards library', () => {
  cy.getByLabel({ label: 'view', tag: 'button' }).should(
    'contain.text',
    'dashboard-without-desc'
  );

  cy.getByLabel({ label: 'delete', tag: 'button' }).click();
  cy.getByLabel({ label: 'Delete', tag: 'button' }).click();
});

Given(
  'a user with dashboard edition rights on the dashboard creation form',
  () => {
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
  }
);

When('the user fills in the name and description fields and save', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-with-desc');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    'My First Dashboard :)'
  );
  cy.getByLabel({ label: 'submit', tag: 'button' }).click();
});

Then(
  'the newly created dashboard appears in the dashboards library with its name and description',
  () => {
    cy.getByLabel({ label: 'view', tag: 'button' })
      .should('contain.text', 'dashboard-with-desc')
      .should('contain.text', 'My First Dashboard :)');
    cy.getByLabel({ label: 'delete', tag: 'button' }).click();
    cy.getByLabel({ label: 'Delete', tag: 'button' }).click();
  }
);

Given(
  'a user with dashboard edition rights who is about to create a dashboard',
  () => {
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
    cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-cancel');
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
      'My Cancelled Dashboard :('
    );
  }
);

When('the user leaves the creation form without saving the dashboard', () => {
  cy.getByLabel({ label: 'cancel', tag: 'button' }).click();
});

Then(
  'the dashboard has not been created when the user is redirected back on the dashboards library',
  () => {
    cy.getByLabel({ label: 'view', tag: 'button' }).should('not.exist');
  }
);

When(
  'the user opens the form to create a new dashboard for the second time',
  () => {
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
  }
);

Then(
  'the information the user filled in the first creation form has not been saved',
  () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'not.contain.text',
      'dashboard-cancel'
    );
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).should(
      'not.contain.text',
      'My Cancelled Dashboard :('
    );
  }
);
