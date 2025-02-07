import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
  cy.execInContainer({
    command: `sed -i 's/"vault": 0/"vault": 3/' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
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
  cy.stopContainers();
});

Given('an admin user is in the Vault page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  cy.visit('/centreon/administration/parameters/vault');
  cy.wait('@getVault');
});

When('the user fills in all the informations', () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).type('vault-ft-secrets-dev.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).type('443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).type('marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).type('ec09c7e9-b9eb-f812-ef31-47f557d019e2');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).type('0b47ab5c-7406-19f7-e26f-ba64b0154f07');
});

When('the user clicks on the Reset button', () => {
  cy.get('*[class="MuiButtonBase-root MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary css-mcvclk-button"]').click();
});

Then('a pop-up appears to confirm the reset', () => {
  cy.contains("Reset configuration");
});

When('the user confirms the reset', () => {
  cy.getByLabel({ label: 'Reset', tag: 'button' }).click();
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
  cy.wait('@getVault');
});

When('the user clicks on Save', () => {
  cy.getByTestId({ testId: "SaveIcon" }).click();
});

Then('the vault is successfully saved', () => {
  cy.wait('@updateVault');
  cy.contains('Vault configuration updated');
});

Then('the informations are displayed', () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).should('have.value', 'vault-ft-secrets-dev.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).should('have.value', 'marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).should('have.value', 'ec09c7e9-b9eb-f812-ef31-47f557d019e2');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).should('be.empty');
});

When("the user doesn't fill in all the informations", () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).should('have.value', 'vault-ft-secrets-dev.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).should('have.value', 'marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).should('be.empty');
});

Then('the user cannot click on Save', () => {
  cy.get('*[class="MuiButtonBase-root MuiButton-root MuiButton-contained MuiButton-containedPrimary MuiButton-sizeMedium MuiButton-containedSizeMedium MuiButton-colorPrimary Mui-disabled MuiButton-root MuiButton-contained MuiButton-containedPrimary MuiButton-sizeMedium MuiButton-containedSizeMedium MuiButton-colorPrimary css-1ln3kta-button"]')
  .should('be.disabled');
});

When("the user doesn't fill in the correct informations", () => {
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).should('have.value', 'vault-ft-secrets-dev.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).should('have.value', 'marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).type('nop');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).type('nop');
});

Then('the form displayed an error for invalid configuration', () => {
  cy.get('*[role="alert"]').should('have.text', "Vault configuration is invalid");
});

Given("the configuration is already defined", () => {
  cy.get('*[role="alert"]').should('not.exist');
  cy.getByLabel({ label: 'Vault address', tag: 'input' }).should('have.value', 'vault-ft-secrets-dev.apps.centreon.com');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '443');
  cy.getByLabel({ label: 'Root path', tag: 'input' }).should('have.value', 'marion');
  cy.getByLabel({ label: 'Role ID', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Secret ID', tag: 'input' }).should('be.empty');
});

When('the user clicks on the Migrate button', () => {
  cy.get('[class="MuiButtonBase-root MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary css-mcvclk-button"]').click();
});

Then("a pop-up appears with the migration informations", () => {
  cy.contains('Migration script');
  cy.get('[class="css-y6g9xh-code-highlight"]').should('contain', '/usr/share/centreon/bin/migrateCredentials.php');
  cy.getByTestId({ testId: "Copy command" }).should('exist');
});