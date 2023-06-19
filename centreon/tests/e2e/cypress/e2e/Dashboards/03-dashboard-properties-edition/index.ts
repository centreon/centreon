import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';

before(() => {
  cy.startWebContainer({ version: 'develop' });
  /* cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  }); */
});

beforeEach(() => {
  loginAsAdminViaApiV2();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?page=1&limit=100'
  }).as('listAllDashboards');
});

Given(
  'a user with update rights on a dashboard featured in the dashboards library',
  () => {
    cy.insertDashboardList('dashboards/navigation/dashboards-single-page.json');
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  }
);

When('the user selects the properties of the dashboard', () => {
  cy.contains('dashboard-to-edit')
    .parent()
    .find('button[aria-label="edit"]')
    .click();
});

Then(
  'the update form is displayed and contains fields to update this dashboard',
  () => {
    cy.contains('Update dashboard').should('be.visible');
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'have.value',
      'dashboard-to-edit'
    );

    cy.getByLabel({ label: 'Description', tag: 'textarea' }).should(
      'contain.text',
      'dashboard-to-edit'
    );

    cy.getByLabel({ label: 'submit', tag: 'button' }).should('be.disabled');

    cy.getByLabel({ label: 'cancel', tag: 'button' }).should('be.enabled');
  }
);

When(
  'the user fills in the name and description fields with new compliant values',
  () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).clear();
    cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-edited');
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).clear();
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
      'dashboard-edited'
    );
  }
);

Then('the user is allowed to update the dashboard', () => {
  cy.getByLabel({ label: 'submit', tag: 'button' }).should('be.enabled');
});

When('the user saves the dashboard with its new values', () => {
  cy.getByLabel({ label: 'submit', tag: 'button' }).click();
});

Then(
  'the dashboard is listed in the dashboards library with its new name and description',
  () => {
    cy.contains('Dashboards').should('be.visible');
    cy.contains('dashboard-edit').parent().should('exist');
    cy.requestOnDatabase({
      database: 'centreon',
      query: 'DELETE FROM dashboard'
    });
  }
);

Given(
  'a user with dashboard update rights who is about to update a dashboard with new values',
  () => {
    cy.insertDashboardList('dashboards/navigation/dashboards-single-page.json');
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
    cy.contains('dashboard-to-edit')
      .parent()
      .find('button[aria-label="edit"]')
      .click();
    cy.getByLabel({ label: 'Name', tag: 'input' }).clear();
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(
      'dashboard-cancel-update-changes'
    );
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).clear();
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
      'dashboard-cancel-update-changes'
    );
  }
);

When('the user leaves the update form without saving', () => {
  cy.getByLabel({ label: 'cancel', tag: 'button' }).click();
});

Then('the dashboard has not been edited and features its former values', () => {
  cy.contains('dashboard-cancel-update-changes').should('not.exist');
  cy.contains('dashboard-to-edit').parent().should('exist');
});

When(
  'the user opens the form to update the dashboard for the second time',
  () => {
    cy.contains('dashboard-to-edit')
      .parent()
      .find('button[aria-label="edit"]')
      .click();
  }
);

Then(
  'the information the user filled in the first update form has not been saved',
  () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'not.contain.text',
      'dashboard-cancel-update-changes'
    );
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).should(
      'not.contain.text',
      'dashboard-cancel-update-changes'
    );
  }
);
