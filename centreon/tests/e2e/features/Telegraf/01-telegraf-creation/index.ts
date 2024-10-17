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
  }).as('addAgents');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is in the Agents Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/pollers/agent-configurations');
  cy.wait('@getAgentsPage');
});

When('the user clicks on Add', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
});

Then('a pop-up menu with the form is displayed', () => {
  cy.contains('Add poller/agent configuration').should('be.visible');
});

When('the user fills in all the informations', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('telegraf-001');
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type('my-otel-certificate-name-001');
  cy.getByLabel({ label: 'CA file name', tag: 'input' }).type('ca-file-name-001');
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).type('my-otel-private-key-name-001');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type('my-certificate-name-001');
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).type('my-otel-private-key-name-001');
});

When('the user clicks on Create', () => {
  cy.getByTestId({ testId: 'SaveIcon' }).click();
});

Then('the first agent is displayed in the Agents Configuration page', () => {
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'telegraf-001');
});

When('the user fills in the mandatory informations', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('telegraf-002');
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type('my-otel-certificate-name-002');
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).type('my-otel-private-key-name-002');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type('my-certificate-name-002');
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).type('my-otel-private-key-name-002');
});

Then('the second agent is displayed in the Agents Configuration page', () => {
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'telegraf-002');
});

When('the user clicks to add a second host', () => {
});

Then('a second group of parameters for hosts is displayed', () => {
});

When('the user fills in the informations of all the parameter groups', () => {
});

Then('the third agent is displayed in the Agents Configuration page', () => {
});

When("the user doesn't fill in all the mandatory informations", () => {
});

Then('the user cannot click on Create', () => {
});

When("the user doesn't fill in correct type of informations", () => {
});

Then('the form displayed an error', () => {
});

When('the user clicks on the Cancel button of the creation form', () => {
});

Then('a pop-up appears to confirm cancellation', () => {
});

When('the user confirms the the cancellation', () => {
});

Then('the creation form is closed', () => {
});

Then('the agent has not been created', () => {
});

Then('the form fields are empty', () => {
});