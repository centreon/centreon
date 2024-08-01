import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
  cy.executeCommandsViaClapi('resources/clapi/config-ACL/ACC-acl-user.json')
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '...'
  }).as('getConnectorPage');
  cy.intercept({
    method: 'GET',
    url: '...'
  }).as('getConnectorDetail');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is in the Specific Connector Configuration page', () => {
  cy.visit('...');
  cy.wait('@getConnectorPage');
});

When('the user clicks on Add', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
  cy.contains('Connector configuration').should('be.visible');
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type("I'm the first connector created");

  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@getConnectorDetail');
});