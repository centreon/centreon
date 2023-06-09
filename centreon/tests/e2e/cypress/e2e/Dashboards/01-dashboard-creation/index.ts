import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';

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

When('they fill in the name field', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-1');
});

Then('they are allowed to create the dashboard', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should(
    'have.value',
    'dashboard-1'
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

  cy.getByLabel({ label: 'delete', tag: 'button' }).each((element) => {
    cy.wrap(element).click();
    cy.getByLabel({ label: 'confirm', tag: 'button' }).click();
    cy.wait('@listAllDashboards');
  });
});

Given(
  'a user with dashboard edition rights on the dashboard creation form',
  () => {
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
  }
);

When('they fill in the name and description fields and save', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-1');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    'My First Dashboard :)'
  );
  cy.getByLabel({ label: 'submit', tag: 'button' }).click();
});

Then(
  'the newly created dashboard appears in the dashboards library with its name and description',
  () => {
    cy.getByLabel({ label: 'view', tag: 'button' })
      .should('contain.text', 'dashboard-1')
      .should('contain.text', 'My First Dashboard :)');
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

When('they leave the creation form without saving the dashboard', () => {
  cy.getByLabel({ label: 'cancel', tag: 'button' }).click();
});

Then(
  'the dashboard has not been created when they are redirected back on the dashboards library',
  () => {
    cy.reload();
    cy.getByLabel({ label: 'view', tag: 'button' }).should('not.exist');
  }
);

When('they open the form to create a new dashboard for the second time', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
});

Then(
  'the information they filled in the first creation form has not been saved',
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
