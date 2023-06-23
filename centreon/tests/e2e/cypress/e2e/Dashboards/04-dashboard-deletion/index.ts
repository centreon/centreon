import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
});

beforeEach(() => {
  loginAsAdminViaApiV2();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?page=1&limit=100'
  }).as('listAllDashboardsOnFirstPage');
});

Given('a user with dashboard update rights on the dashboards library', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

When(
  'the user clicks on the delete button for a dashboard featured in the library',
  () => {
    cy.contains('dashboard-to-delete-name')
      .parent()
      .find('button[aria-label="edit"]')
      .click();
  }
);
