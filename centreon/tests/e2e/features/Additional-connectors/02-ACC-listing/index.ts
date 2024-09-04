import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  // cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/acc-acl-user.json');
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/additional-connectors?*'
  }).as('getConnectorPage');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/additional-connectors/*'
  }).as('getConnectorDetail');
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is logged in', () => {
  cy.loginByTypeOfUser({ 
    jsonName: 'admin',
    loginViaApi: false
  });
  cy.wait('@getNavigationList');
});

When('the user clicks on the Specific Connector Configuration page', () => {
  cy.navigateTo({
    page: 'Configuration',
    rootItemNumber: 3,
    subMenu:'Additional connector configurations'
  });
});

Then('the user sees the Specific Connector Configuration page', () => {
  cy.wait('@getConnectorPage');
});

Then('there is no additional connector configuration listed', () => {
  cy.get('@getConnectorPage');
});

Given('a non-admin user is in the Specific Connector Configuration page', () => {
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
});

Given('an already existing additional connector configuration', () => {
  ...
});

When('the user clicks on the Edit button of the additional connector configuration', () => {
  ...
});

Then('a pop up is displayed with all of the additional connector informations', () => {
  ...
});