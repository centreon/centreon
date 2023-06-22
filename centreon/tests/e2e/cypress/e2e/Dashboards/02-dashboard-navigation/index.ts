import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { loginAsAdminViaApiV2 } from '../../../commons';
import dashboardsOnePage from '../../../fixtures/dashboards/navigation/01-onepage.json';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 1@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
});

beforeEach(() => {
  loginAsAdminViaApiV2();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards*'
  }).as('listAllDashboards');
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

Given('a user with access to the dashboards overview page', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/monitoring/resources`);
});

When('the user accesses the dashboard overview page with no dashboards', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

Then(
  'an empty state message and a button to create a new dashboard are displayed instead of the dashboards',
  () => {
    cy.getByTestId({ testId: 'data-table-empty-state' }).should('be.visible');

    cy.getByLabel({
      label: 'view',
      tag: 'button'
    }).should('not.exist');
    cy.getByLabel({
      label: 'create',
      tag: 'button'
    }).should('exist');
  }
);

Given('a list of dashboards', () => {
  cy.insertDashboardList('dashboards/navigation/01-onepage.json');
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

When('the user clicks on the dashboard they want to select', () => {
  const lastDashboard = dashboardsOnePage[dashboardsOnePage.length - 1];

  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(lastDashboard.name)
    .click();
});

Then('the user is redirected to the detail page for this dashboard', () => {
  const lastDashboard = dashboardsOnePage[dashboardsOnePage.length - 1];

  cy.location('pathname')
    .should('include', '/dashboards/')
    .invoke('split', '/')
    .should('not.be.empty')
    .then(Cypress._.last)
    .then(Number)
    .should('not.be', 'dashboards')
    .should('be.a', 'number'); // dashboard id

  cy.getByLabel({ label: 'page header title' }).should(
    'contain.text',
    lastDashboard.name
  );
});
