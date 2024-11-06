import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/ac-acl-user.json');
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/agent-configurations?*'
  }).as('getAgentsPage');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/agent-configurations'
  }).as('addAgents');
  cy.intercept({
    method: 'DELETE',
    url: '/centreon/api/latest/configuration/agent-configurations/*'
  }).as('deleteAgents');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is in the Agents Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/pollers/agent-configurations');
  cy.wait('@getAgentsPage');
});

Given('an already existing agent configuration', () => {
  cy.getByTestId({ testId: 'AddIcon' }).click();
  cy.contains('Add poller/agent configuration').should('be.visible');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('telegraf-001');
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type('my-otel-certificate-name-001');
  cy.getByLabel({ label: 'CA file name', tag: 'input' }).type('ca-file-name-001');
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).eq(0).type('my-otel-private-key-name-001');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type('my-certificate-name-001');
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).eq(1).type('my-conf-private-key-name-001');
  cy.getByTestId({ testId: 'SaveIcon' }).click();
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'telegraf-001');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Telegraf');
});

When('the user deletes the agent configuration', () => {
  cy.getByTestId({ testId: 'DeleteOutlineIcon' }).click();
});

When('the user confirms on the pop-up', () => {
  cy.get('[class^="MuiButtonBase-root MuiButton-root MuiButton-contained MuiButton-containedPrimary MuiButton-sizeMedium MuiButton-containedSizeMedium MuiButton-colorPrimary MuiButton-root MuiButton-contained MuiButton-containedPrimary MuiButton-sizeMedium MuiButton-containedSizeMedium MuiButton-colorPrimary"]').click();
  cy.wait('@deleteAgents');
});

Then('the agent configuration is no longer displayed in the listing page', () => {
  cy.contains('Welcome to the poller/agent configuration page').should('be.visible');
  cy.contains('telegraf-001').should('not.exist');
});

When('the user cancel on the pop-up', () => {
  cy.get('[class^="MuiButtonBase-root MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary MuiButton-root MuiButton-text MuiButton-textPrimary MuiButton-sizeMedium MuiButton-textSizeMedium MuiButton-colorPrimary css-mcvclk-button"]').click();
});

Then('the agent configuration is still displayed in the listing page', () => {
  cy.get('*[role="rowgroup"]')
  .should('contain', 'telegraf-001');
});