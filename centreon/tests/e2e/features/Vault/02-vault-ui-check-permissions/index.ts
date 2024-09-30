import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  // cy.startContainers();
  // cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/vault-acl-user.json');
  // cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/vault-acl-user-2.json');  
  // cy.enableVaultFeature();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/vaults/configurations'
  }).as('getVault');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/administration/vaults/configurations'
  }).as('updateVault');
});

after(() => {
  // cy.stopContainers();
});

Given('a non-admin user without topology right is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-local-authentication',
    loginViaApi: false
  });
});

When('the user visits the Vault page', () => {
  cy.visit('/centreon/administration/parameters/vault');
});

Then('the user cannot access the Vault page', () => {
  cy.getByTestId({ testId: 'You are not allowed to see this page' }).should('be.visible');
});

Given('a non-admin user with topology right is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-vault',
    loginViaApi: false
  });
});

When('the user clicks on the Vault page', () => {
  cy.navigateTo({
    page: 'Vault',
    rootItemNumber: 0,
    subMenu: 'Parameters'
  });
});

Then('the user is redirected to the Vault page', () => {
  cy.wait('@getVault');
});

Then('an error message is displayed to require an admin user', () => {
  cy.get('*[role="alert"]').should('have.text', "This operation requires an admin user");
});

When('the user fills in all the informations', () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).type('vault-ft-secrets-dev.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).type('443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).type('marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).type('ec09c7e9-b9eb-f812-ef31-47f557d019e2');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).type('0b47ab5c-7406-19f7-e26f-ba64b0154f07');
});

When('the user clicks on Save', () => {
  cy.getByTestId({ testId: "SaveIcon" }).click();
});

Given("the configuration is already defined", () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  cy.visit('/centreon/administration/parameters/vault');
  cy.wait('@getVault');
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).type('vault-ft-secrets-dev.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).type('443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).type('marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).type('ec09c7e9-b9eb-f812-ef31-47f557d019e2');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).type('0b47ab5c-7406-19f7-e26f-ba64b0154f07');
  cy.getByTestId({ testId: "SaveIcon" }).click();
  cy.wait('@updateVault');
  cy.contains('Vault configuration updated');
  cy.logout();
});

Then("the vault fields are empty", () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).should('be.empty');
});