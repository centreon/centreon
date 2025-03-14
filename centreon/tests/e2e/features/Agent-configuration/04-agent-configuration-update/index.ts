import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import agentsConfiguration from '../../../fixtures/agents-configuration/agent-config.json';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/ac-acl-user.json');
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-1.json');
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
    method: 'GET',
    url: '/centreon/api/latest/configuration/agent-configurations/**'
  }).as('getAgentsDetails');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/configuration/agent-configurations/*'
  }).as('updateAgents');
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
  cy.contains('button', 'Add poller/agent configuration').click();
  cy.addTelegrafAgent(agentsConfiguration.telegraf1);
  cy.getByTestId({ testId: 'SaveIcon' }).click();
  cy.wait('@addAgents');
});

When('the user clicks on the line of the agent configuration', () => {
  cy.get('*[role="row"]').eq(1).click({force: true});
  cy.wait('@getAgentsDetails');
});

Then('a pop up is displayed with all of the agent information', () => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.contains('Update poller/agent configuration').should('be.visible');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).should('have.value', 'Telegraf');
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('have.value', agentsConfiguration.telegraf1.name);
  cy.get('[class^="MuiChip-label MuiChip-labelMedium"]').should('have.text', 'Central');
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' })
    .should('have.value', `/etc/pki/${agentsConfiguration.telegraf1.publicCertfFileName}`);
  cy.getByLabel({ label: 'CA file name', tag: 'input' })
    .should('have.value', `/etc/pki/${agentsConfiguration.telegraf1.caFileName}`);
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).eq(0)
    .should('have.value', `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`);
  cy.getByLabel({ label: 'Port', tag: 'input' })
    .should('have.value', '1443');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' })
    .should('have.value', `/etc/pki/${agentsConfiguration.telegraf1.certfFileName}`);
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).eq(1)
    .should('have.value', `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`);
});

When('the user updates some information', () => {
  cy.updateTelegrafAgent(agentsConfiguration.telegraf2); 
});

When('the user clicks on Save', () => {
    cy.getByTestId({ testId: 'SaveIcon' }).click();
    cy.wait('@updateAgents');
});

Then('the form is closed', () => {
  cy.get('*[role="dialog"]').should('not.exist');
  cy.contains('Update poller/agent configuration').should('not.exist');
});

Then('the information are successfully saved', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', agentsConfiguration.telegraf2.name);
  cy.get('*[role="rowgroup"]')
    .should('contain', '2 pollers');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Telegraf');
});