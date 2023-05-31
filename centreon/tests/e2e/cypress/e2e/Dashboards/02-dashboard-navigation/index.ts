import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';

before(() => {
  cy.startWebContainer();
});

beforeEach(() => {
  loginAsAdminViaApiV2();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?page=1&limit=100'
  }).as('listAllDashboards');
});

Given('a user with access to the dashboards library', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/monitoring/resources`);
});

When(
  'they access the dashboard listing page on a platform with no dashboards',
  () => {
    cy.navigateTo({
      page: 'Dashboard (beta)',
      rootItemNumber: 0
    });
  }
);

Then(
  'a special message and a button to create a new dashboard are displayed instead of the dashboards',
  () => {
    cy.get('body').should('contain.text', 'No dashboards found');
    cy.getByLabel({ label: 'view', tag: 'button' }).should('not.exist');
    cy.getByLabel({ label: 'create', tag: 'button' }).should('exist');
  }
);
