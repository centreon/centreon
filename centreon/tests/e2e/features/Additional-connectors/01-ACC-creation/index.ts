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
    url: '/centreon/api/latest/configuration/additional-connectors?*'
  }).as('getConnectorPage');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/additional-connectors'
  }).as('addAdditionalConnector');
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is in the Specific Connector Configuration page', () => {
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
});

When('the user clicks on Add', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
});

Then('a pop-up menu with the form is displayed', () => {
  cy.contains('Create additional connector configuration').should('be.visible');
});

When('the user fills in all the informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type("I'm the first connector created");
  cy.get('[id="mui-component-select-type"]').contains('VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).type('Central');
  cy.getByTestId({ testId: 'vCenter name_value' }).clear().type('vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).type('Centreon!2021');
  cy.getByTestId({ testId: 'Port_value' }).contains('5700');
});

When('the user clicks on Create', () => {
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
});

Then('the new configuration is displayed in the Specific Connector Configuration page', () => {
  cy.wait('@addAdditionalConnector');
});

When('the user fills in the mandatory informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-002');
  cy.get('[id="mui-component-select-type"]').contains('VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).type('Central');
  cy.getByTestId({ testId: 'vCenter name_value' }).clear().type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).type('Centreon!2021');
  cy.getByTestId({ testId: 'Port_value' }).contains('5700');
});

When("the user doesn't fill in all the mandatory informations", () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).click();
  cy.get('[id="mui-component-select-type"]').contains('VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.getByTestId({ testId: 'vCenter name_value' }).clear().type('vCenter-003');
  cy.getByTestId({ testId: 'URL_value' }).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).click();
  cy.getByTestId({ testId: 'Password_value' }).click();
  cy.getByTestId({ testId: 'Port_value' }).contains('5700');
});

Then('the user cannot click on Create', () => {
  cy.getByTestId({ testId: 'Name' }).contains('Required').should('be.visible');
  cy.contains('At least one poller is required').should('be.visible');
  cy.getByTestId({ testId: 'URL_value' }).contains('Required').should('be.visible');
  cy.getByTestId({ testId: 'Port_value' }).contains('Required').should('be.visible');
  cy.getByLabel({ label: 'Create', tag: 'button' }).should('be.disabled');
});

When("the user doesn't fill in correct type of informations", () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('a');
  cy.get('[id="mui-component-select-type"]').contains('VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).type('Central');
  cy.getByTestId({ testId: 'vCenter name_value' }).clear().type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).click;
  cy.getByTestId({ testId: 'Username_value' }).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).type('Centreon!2021');
  cy.getByTestId({ testId: 'Port_value' }).clear().type('500000');
});

Then('the form displayed an error', () => {
  cy.getByTestId({ testId: 'Name' }).contains('The name should be at least 3 characters long');
  cy.getByTestId({ testId: 'URL_value' }).contains('Please enter a valid URL or IP address');
  cy.getByTestId({ testId: 'Port_value' }).contains('Invalid port number');
});

When('the user clicks on the Cancel button of the creation form', () => {
  cy.getByLabel({ label:'Cancel', tag: 'button' }).click();
});

Then('a pop-up appears to confirm cancellation', () => {
  cy.contains('Do you want to quit without saving the changes?').should('be.visible');
  cy.contains('Your form has unsaved changes').should('be.visible');
});

When('the user confirms the the cancellation', () => {
  cy.getByLabel({ label:'Confirm', tag: 'button' }).click();
});

Then('the user is on the Specific Connector Configuration page', () => {
  cy.contains('...');
});

Then('the additional connector configuration has not been created', () => {
  cy.contains('...');
});

When('the user opens the form to create a new additional connector configuration for the second time', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
});

Then('the form fields are empty', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');
// cy.getByLabel({ label: 'Name', tag: 'input' }).should('have.value', 'Name');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
// cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('have.value', 'Description');
  cy.get('[id="mui-component-select-type"]').contains('VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).should('be.empty');
  cy.getByTestId({ testId: 'vCenter name_value' }).clear().type('my_vcenter');
  cy.getByTestId({ testId: 'URL_value' }).clear().type('https://<ip_hostname>/sdk');
  cy.getByTestId({ testId: 'Username_value' }).should('be.empty');
  cy.getByTestId({ testId: 'Password_value' }).should('be.empty');
  cy.getByTestId({ testId: 'Port_value' }).contains('5700');
});