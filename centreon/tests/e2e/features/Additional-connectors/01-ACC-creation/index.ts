import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/acc-acl-user.json');
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-1.json');
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-2.json'); 
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/additional-connector-configurations?*'
  }).as('getConnectorPage');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/additional-connector-configurations'
  }).as('addAdditionalConnector');
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
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
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Centreon!2021');
  cy.getByTestId({ testId: 'Port_value' }).eq(1).should('have.value', '5700');
});

When('the user clicks on Create', () => {
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
});

Then('the new configuration is displayed in the Specific Connector Configuration page', () => {
  cy.wait('@addAdditionalConnector');
  cy.get('*[class="MuiTypography-root MuiTypography-body1 css-7bmf3k-text-rowNotHovered"]')
    .eq(0)
    .should('contain', 'Connector-001');
});

When('the user fills in the mandatory informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-002');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Centreon!2021');
  cy.getByTestId({ testId: 'Port_value' }).eq(1).should('have.value', '5700');
});

Then('the new configuration is displayed in the Specific Connector Configuration page', () => {
  cy.wait('@addAdditionalConnector');
  cy.get('*[class="MuiTypography-root MuiTypography-body1 css-7bmf3k-text-rowNotHovered"]')
    .eq(0)
    .should('contain', 'Connector-002');
});

When("the user doesn't fill in all the mandatory informations", () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).click();
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear();
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear();
  cy.getByTestId({ testId: 'Username_value' }).eq(0).click();
  cy.getByTestId({ testId: 'Password_value' }).eq(0).click();
  cy.getByTestId({ testId: 'Port_value' }).eq(1).click();
});

Then('the user cannot click on Create', () => {
  cy.getByTestId({ testId: 'Name' }).contains('Required').should('be.visible');
  cy.contains('At least one poller is required').should('be.visible');
  cy.getByTestId({ testId: 'vCenter name_value' }).contains('Required').should('be.visible');
  cy.getByTestId({ testId: 'URL_value' }).contains('Required').should('be.visible');
  cy.getByTestId({ testId: 'Username_value' }).contains('Required').should('be.visible');
  cy.getByTestId({ testId: 'Password_value' }).contains('Required').should('be.visible');
  cy.getByLabel({ label: 'Create', tag: 'button' }).should('be.disabled');
});

When("the user doesn't fill in correct type of informations", () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('a');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-2').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).click();
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Centreon!2021');
  cy.getByTestId({ testId: 'Port_value' }).eq(1).clear().type('500000');
  cy.getByLabel({ label: 'Name', tag: 'input' }).click();
});

Then('the form displayed an error', () => {
  cy.getByTestId({ testId: 'Name' }).contains('The name should be at least 3 characters long');
  cy.getByTestId({ testId: 'URL_value' }).contains('Please enter a valid URL or IP address');
  cy.getByTestId({ testId: 'Port_value' }).contains('Invalid port number');
});

When('the user fills in the needed informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-002');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-2').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Centreon!2021');
  cy.getByTestId({ testId: 'Port_value' }).eq(1).should('have.value', '5700');
});

When('the user clicks on the Cancel button of the creation form', () => {
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
});

Then('a pop-up appears to confirm cancellation', () => {
  cy.contains('Do you want to quit without saving the changes?').should('be.visible');
  cy.contains('Your form has unsaved changes').should('be.visible');
});

When('the user confirms the the cancellation', () => {
  cy.getByLabel({ label: 'Confirm', tag: 'button' }).click();
});

Then('the creation form is closed', () => {
  cy.contains('Create additional connector configuration').should('not.be.visible');
});

Then('the additional connector configuration has not been created', () => {
  cy.get('#maint-content > div > div > div > div > div > div.css-1xsyaox-container > div:nth-child(2) > div > div > div.MuiTableBody-root.css-ws5y8r-tableBody > div > div').should('not.have.value', 'Connector-002');
});

Then('the form fields are empty', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).should('be.empty');
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(1).should('have.value', 'my_vcenter');
  cy.getByTestId({ testId: 'URL_value' }).eq(1).should('have.value', 'https://<ip_hostname>/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(1).should('be.empty');
  cy.getByTestId({ testId: 'Password_value' }).eq(1).should('be.empty');
  cy.getByTestId({ testId: 'Port_value' }).eq(1).should('have.value', '5700');
});
