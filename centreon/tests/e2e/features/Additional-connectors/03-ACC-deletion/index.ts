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
    method: 'DELETE',
    url: '/centreon/api/latest/configuration/additional-connector-configurations/*'
  }).as('deleteConnector');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is in the Additional Connector Configuration page', () => {
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
  cy.get('#vCenternamevalue').clear().type('vCenter-001');
  cy.get('#URLvalue').clear().type('https://10.0.0.0/sdk');
  cy.get('#Usernamevalue').type('admin');
  cy.get('#Passwordvalue').type('Abcde!2021');
  cy.get('#Portvalue').should('have.value', '5700');
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
  cy.wait('@addAdditionalConnector');
});

When('the user deletes the additional connector configuration', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).eq(0).click();
});

When('the user cancel on the pop-up', () => {
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
});

Then('the additional connector configuration is still displayed in the listing page', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-001');
});

When('the user confirms on the pop-up', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).eq(1).click();
});

Then('the additional connector configuration is no longer displayed in the listing page', () => {
  cy.wait('@deleteConnector');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'No result found');
});