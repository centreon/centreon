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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/additional-connector-configurations/*'
  }).as('getConnectorDetail');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/configuration/additional-connector-configurations/*'
  }).as('updateConnectorDetail');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is in the Specific Connector Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
});

Given('an additional connector configuration is already created', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Centreon!2021');
  cy.get('[id="Portvalue"]').should('have.value', '5700');
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
  cy.wait('@addAdditionalConnector');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-001');
});

When('the user clicks on the Edit properties button of an additional connector configuration', () => {
  cy.getByLabel({ label: 'Edit connector configuration', tag: 'button' }).click();
});

Then('a pop-up menu with the form is displayed', () => {
  cy.wait('@getConnectorDetail');
  cy.contains('Update additional connector configuration').should('be.visible');
});

Then('all of the informations of the additional connector configuration are correct', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('have.value', 'Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.get('*[class="MuiChip-label MuiChip-labelMedium css-14vsv3w"]').should('contain', 'Central');
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(1).should('have.value', 'vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(1).should('have.value', 'https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(1).should('be.empty');
  cy.getByTestId({ testId: 'Password_value' }).eq(1).should('be.empty');
  cy.get('[id="Portvalue"]').should('have.value', '5700');
});

When('the user udpates some informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type('Connector-002');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.getByTestId({ testId: 'CancelIcon' }).click();
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click().click();
  cy.contains('Poller-1').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.3.3.3/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Centreon!2022');
  cy.get('[id="Portvalue"]').clear().click().type('6900');
});

When('the user clicks on Update', () => {
  cy.getByLabel({ label: 'Update', tag: 'button' }).click();
});

Then('the form is closed', () => {
  cy.wait('@updateConnectorDetail');
  cy.get('Update additional connector configuration').should('not.exist');
});

Then('the informations are successfully saved', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-002');
  cy.getByLabel({ label: 'Edit connector configuration', tag: 'button' }).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('have.value', 'Connector-002');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.get('*[class="MuiChip-label MuiChip-labelMedium css-14vsv3w"]').should('contain', 'Poller-1');
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(1).should('have.value', 'vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).eq(1).should('have.value', 'https://10.3.3.3/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(1).should('be.empty');
  cy.getByTestId({ testId: 'Password_value' }).eq(1).should('be.empty');
  cy.get('[id="Portvalue"]').should('have.value', '6900');
});