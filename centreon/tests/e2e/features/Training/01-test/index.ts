import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?'
  }).as('getDashboardPage');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards/*'
  }).as('getDashboardDetail');
});

after(() => {
  cy.stopContainers();
});

Given('the administrator is logged in', () => {
  cy.getByLabel({ label: 'Alias', tag: 'input' }).type('admin');
  cy.getByLabel({ label: 'Password', tag:'input' }).type('Centreon!2021');
  cy.getByLabel({ label: 'Connect', tag: 'button' }).click();
  cy.wait('@getNavigationList');
});

When('the admin user visits dashboard page', () => {
  cy.visit('/centreon/home/dashboards');
  cy.wait('@getDashboardPage');
});

Then('the admin user could create a new dashboard', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
  cy.contains('Create dashboard').should('be.visible');
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Dashboard-001');
  cy.getByLabel({ label: 'Description', tag: 'input' }).type('Hello there');
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
  cy.wait('@getDashboardDetail');
});