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
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
});

When('the user clicks on the Specific Connector Configuration page', () => {
  cy.navigateTo({
    page: 'Additional Connector Configurations',
    rootItemNumber: 0
  });
});

Then('the user sees the Specific Connector Configuration page', () => {
  cy.wait('@getConnectorPage');
});

Then('there is no additional connector configuration listed', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', 'No result found');
});

Given('a non-admin user is in the Specific Connector Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
});

Given('an already existing additional connector configuration', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.get('#vCenternamevalue').clear().type('vCenter-001');
  cy.get('#URLvalue').clear().type('https://10.0.0.0/sdk');
  cy.get('#Usernamevalue').type('admin');
  cy.get('#Passwordvalue').type('Abcde!2021');
  cy.get('#Portvalue').should('have.value', '5700');
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
  cy.wait('@addAdditionalConnector');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-001');
});

When('the user clicks on the Edit button of the additional connector configuration', () => {
  cy.getByLabel({ label: 'Edit connector configuration', tag: 'button' }).click();
});

Then('a pop up is displayed with all of the additional connector informations', () => {
  cy.wait('@getConnectorDetail');
  cy.contains('Update additional connector configuration').should('be.visible');
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('have.value', 'Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.get('*[class^="MuiChip-label MuiChip-labelMedium"]').should('contain', 'Central');
  cy.get('#vCenternamevalue').should('have.value', 'vCenter-001');
  cy.get('#URLvalue').should('have.value', 'https://10.0.0.0/sdk');
  cy.get('#Usernamevalue').should('be.empty');
  cy.get('#Passwordvalue').should('be.empty');
  cy.get('#Portvalue').should('have.value', '5700');
});