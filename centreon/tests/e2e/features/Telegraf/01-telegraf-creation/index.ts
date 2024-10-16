import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/acc-acl-user.json');
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-1.json');
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-2.json'); 
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-3.json'); 
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/pollers/agent-configurations?*'
  }).as('getAgentsPage');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/pollers/agent-configurations'
  }).as('addTelegraf');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is in the Additional Connector Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/pollers/agent-configurations');
  cy.wait('@getAgentsPage');
});

When('the user clicks on Add', () => {
});

Then('a pop-up menu with the form is displayed', () => {
});

When('the user fills in all the informations', () => {
});

When('the user clicks on Create', () => {
});

Then('the first connector is displayed in the Additional Connector Configuration page', () => {
});

When('the user fills in the mandatory informations', () => {
});

Then('the second configuration is displayed in the Additional Connector Configuration page', () => {
});

Then('the third configuration is displayed in the Additional Connector Configuration page', () => {
});

When('the user clicks to add a second vCenter', () => {
});

Then('a second group of parameters is displayed', () => {
});

When('the user fills in the informations of all the parameter groups', () => {
});

When("the user doesn't fill in all the mandatory informations", () => {
});

Then('the user cannot click on Create', () => {
});

When("the user doesn't fill in correct type of informations", () => {
});

Then('the form displayed an error', () => {
});

When('the user fills in the needed informations', () => {
});

When('the user clicks on the Cancel button of the creation form', () => {
});

Then('a pop-up appears to confirm cancellation', () => {
});

When('the user confirms the the cancellation', () => {
});

Then('the creation form is closed', () => {
});

Then('the additional connector configuration has not been created', () => {
});

Then('the form fields are empty', () => {
});