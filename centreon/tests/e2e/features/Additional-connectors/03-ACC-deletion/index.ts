import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
  cy.executeCommandsViaClapi('resources/clapi/config-ACL/ACC-acl-user.json');
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
