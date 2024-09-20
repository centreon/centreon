import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  // cy.startContainers();
  // cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/vault-acl-user.json');
  // cy.enableVaultFeature();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'PUT',
    url: '/administration/vaults/configurations'
  }).as('updateVault');
});

after(() => {
  // cy.stopContainers();
});

Given('an admin user is in the Vault page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  cy.visit('/centreon/administration/parameters/vault');
});

When('the user fills in all the informations', () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).type('vault-sdbx.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).type('443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).type('marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).type('5935dd16-d18d-884a-0786-6847681f99a9');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).type('cf31afc2-5e7c-849a-ac72-bb45fc883895');
});

When('the user clicks on the Reset button', () => {
  cy.get('*[class="MuiButtonBase-root MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary css-mcvclk-button"]').click();
});

Then('a pop-up appears to confirm the reset', () => {
  cy.contains("Reset configuration");
});

When('the user confirms the reset', () => {
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
});

Then('the vault configuration fields are empty', () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).should('be.empty');
});

Given('an admin user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the user clicks on the Vault page', () => {
  cy.navigateTo({
    page: 'Vault',
    rootItemNumber: 4,
    subMenu: 'Parameters'

  });
});

Then('the user is redirected to the Vault page', () => {
  cy.contains('Vault configuration');
});

When('the user clicks on Save', () => {
  cy.getByTestId({ testId: "SaveIcon" }).click();
});

Then('the vault is successfully saved', () => {
  cy.wait('@updateVault');
  cy.contains('Vault configuration updated');
});

Then('the informations are displayed', () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).should('have.value', 'vault-sdbx.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).should('have.value', 'marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).should('have.value', '5935dd16-d18d-884a-0786-6847681f99a9');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).should('be.empty');
});