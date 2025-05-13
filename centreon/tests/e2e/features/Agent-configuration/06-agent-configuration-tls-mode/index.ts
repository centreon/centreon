/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import agentsConfiguration from '../../../fixtures/agents-configuration/agent-config.json';

const cmaMessage = 'You have selected No TLS for the encryption level. This parameter is meant for test purposes only and is not allowed in production. The agent monitoring will stop after 1 hour.';
const telegrafMessage = 'You have selected No TLS for the encryption level.';
const cmaTypeName = 'Centreon Monitoring Agent';
const telegrafTypeName= 'Telegraf';
before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/ac-acl-user.json'
  );
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-1.json'
  );
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-2.json'
  );
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-3.json'
  );
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-4.json'
  );
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
  }).as('addorUpdateAgents');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is on the Agents Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/pollers/agent-configurations');
  cy.wait('@getAgentsPage');
});

When('the user clicks on the {string} button', (addBtnName: string) => {
  cy.contains('button', addBtnName).click();
});

Then('a pop-up form is displayed', () => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add poller/agent configuration');
});

When('the user selects "CMA" as the agent type', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains(cmaTypeName).click();
});

When('the user selects "No TLS" as the encryption level', () => {
  cy.getByLabel({ label: 'Encryption level', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('No TLS').click();
});

Then('a warning message explaining the No TLS mode for {string} is displayed', (agentType: string) => {
  cy.get('[class*="warning"]')
    .should('be.visible')
    .and('contain.text', agentType == "CMA" ? cmaMessage : telegrafMessage);
});

Then('no certificate fields are shown', () => {
  cy.get('#Publiccertificate').should('not.exist');
  cy.get('#Privatekey').should('not.exist');
  cy.get('#CA').should('not.exist');
});

When('the user enables connection initiated by the poller', () => {
  cy.getByLabel({
    label: 'Connection initiated by poller',
    tag: 'input'
  }).click();
  cy.get('[class*="Mui-checked Mui-checked"]').should('exist');
});

Then('no certificate fields are displayed in the Host Configuration section', () => {
  cy.get('#CA').should('not.exist');
  cy.get('#CACommonNameCN').should('not.exist');
});

When('the user fills in the mandatory fields', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(agentsConfiguration.CMA1.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.contains('Poller-2').click();
  cy.getByLabel({ label: 'Add host', tag: 'input' }).eq(0).click();
  cy.contains('Centreon-Server').click();
  cy.getByLabel({ label: 'DNS/IP', tag: 'input' }).eq(0).clear().type('10.0.0.0');
  cy.getByTestId({ testId: 'Port' }).eq(0).clear().type('4317');
});

When('the user clicks "Save"', () => {
  cy.getByTestId({ testId: 'submit' }).click();
  cy.wait('@getAgentsPage');
});

Then('the first created agent appears on the Agents Configuration page', () => {
  cy.get('*[role="rowgroup"]').should('contain', agentsConfiguration.CMA1.name);
  cy.get('*[role="rowgroup"]').should('contain', cmaTypeName);
});

When('the user selects "Telegraf" as the agent type', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains(telegrafTypeName).click();
});

Then('no Telegraf certificate fields are shown', () => {
  cy.get('#Publiccertificate').should('not.exist');
  cy.get('#Privatekey').should('not.exist');
  cy.get('#CA').should('not.exist');    
});

When('the user fills in the mandatory Telegraf fields', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(agentsConfiguration.telegraf1.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByLabel({ label: 'Port', tag: 'input' }).clear().type('1447');
});

Then('the second agent appears on the Agents Configuration page', () => {
  cy.get('*[role="rowgroup"]').should('contain', agentsConfiguration.telegraf1.name);
  cy.get('*[role="rowgroup"]').should('contain', telegrafTypeName);
});

When('the user clicks on the first configured CMA agent', () => {
  cy.contains(agentsConfiguration.CMA1.name).click();
});

Then('a pop-up with the agent details is displayed', () => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Update poller/agent configuration');
  cy.get('#Name').should('contain.value', agentsConfiguration.CMA1.name);
  cy.get('#Agenttype').should('have.value', cmaTypeName);
  cy.get('#Encryptionlevel').should('have.value', 'No TLS');
  cy.contains('Poller-1').should('be.visible');
  cy.contains('Poller-2').should('be.visible');
  cy.get('#DNSIP').should('have.value', '10.0.0.0');
  cy.get('#Port').should('have.value', '43170');
});

When('the user updates the CMA details', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type(`${agentsConfiguration.CMA1.name}_changed`);
  cy.getByLabel({ label: 'DNS/IP', tag: 'input' }).eq(0).clear().type('10.0.0.1');
  cy.getByTestId({ testId: 'Port' }).eq(0).clear().type('4314');
});

Then('the first configured CMA agent is updated', () => {
  cy.get('*[role="rowgroup"]').should('contain', `${agentsConfiguration.CMA1.name}_changed`);
  cy.get('*[role="rowgroup"]').should('contain', cmaTypeName);
});

When('the user clicks on the second configured Telegraf agent', () => {
  cy.contains(agentsConfiguration.telegraf1.name).click();
});

Then('a pop-up with the Telegraf agent details is displayed', () => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Update poller/agent configuration');
  cy.get('#Name').should('contain.value', agentsConfiguration.telegraf1.name);
  cy.get('#Agenttype').should('have.value', telegrafTypeName);
  cy.get('#Encryptionlevel').should('have.value', 'No TLS');
  cy.contains('Central').should('be.visible');
  cy.get('#Port').should('have.value', '1447');
});

When('the user updates the Telegraf agent details', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type(agentsConfiguration.telegraf2.name);
  cy.getByTestId({ testId: 'Port' }).eq(0).clear().type('4314');
});

Then('the second configured Telegraf agent is updated', () => {
  cy.get('*[role="rowgroup"]').should('contain', agentsConfiguration.telegraf2.name);
});