/* eslint-disable prettier/prettier */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import agentsConfiguration from '../../../fixtures/agents-configuration/agent-config.json';

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
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
});

When('the user clicks on the Agents Configuration page', () => {
  cy.navigateTo({
    page: 'Agent configurations',
    rootItemNumber: 0,
    subMenu: 'Pollers'
  });
});

Then('the user sees the Agents Configuration page', () => {
  cy.wait('@getAgentsPage');
  cy.contains('Welcome to the poller/agent configuration page').should(
    'be.visible'
  );
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
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add poller/agent configuration');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('telegraf-001');
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(
    'my-otel-certificate-name-001'
  );
  cy.getByLabel({ label: 'CA file name', tag: 'input' }).type(
    'ca-file-name-001'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(0)
    .type('my-otel-private-key-name-001');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type(
    'my-certificate-name-001'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(1)
    .type('my-conf-private-key-name-001');
  cy.getByTestId({ testId: 'SaveIcon' }).click();
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]').should('contain', 'telegraf-001');
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

When('the user clicks on the line of the agent configuration', () => {
  cy.get('*[role="row"]').eq(1).click({ force: true });
  cy.wait('@getAgentsDetails');
});

Then('a pop up is displayed with all of the agent information', () => {
  cy.contains('Update poller/agent configuration').should('be.visible');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).should(
    'have.value',
    'Telegraf'
  );
  cy.getByLabel({ label: 'Name', tag: 'input' }).should(
    'have.value',
    'telegraf-001.crt'
  );
  cy.get('[class^="MuiChip-label MuiChip-labelMedium"]').should(
    'have.text',
    'Central'
  );
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).should(
    'have.value',
    'my-otel-certificate-name-001.crt'
  );
  cy.getByLabel({ label: 'CA file name', tag: 'input' }).should(
    'have.value',
    'ca-file-name-001.crt'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(0)
    .should('have.value', 'my-otel-private-key-name-001.key');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).should(
    'have.value',
    'my-certificate-name-001'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(1)
    .should('have.value', 'my-conf-private-key-name-001.key');
});

Given('some poller agent configurations are created', () => {
  cy.contains('button', 'Add').click();
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add poller/agent configuration');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Centreon Monitoring Agent').click();
  cy.FillCMAMandatoryFields(agentsConfiguration.CMA1);
  cy.getByTestId({ testId: 'SaveIcon' }).click();
  cy.wait('@addAgents');
});

When('the user enters a non-existent name into the search bar', () => {
  cy.getByTestId({ testId: 'Search' }).eq(1).clear().type('xYz');
  cy.wait('@getAgentsPage');
});

Then('an empty listing page with no results is displayed', () => {
  cy.contains('div', 'No result found').should('be.visible');
});

Given('some configured poller agent configurations', () => {
  cy.contains('telegraf-001').should('exist');
  cy.contains(agentsConfiguration.CMA1.name).should('exist');
});

When('the user enters an existing name into the search bar', () => {
  cy.getByTestId({ testId: 'Search' }).eq(1).clear().type(agentsConfiguration.CMA1.name);
  cy.wait('@getAgentsPage');
});

Then('a listing page is displayed showing only the poller agent configurations that match the entered name', () => {
  cy.contains('p', agentsConfiguration.CMA1.name).should('be.visible');
  cy.get('div[role="table"]')
    .find('div[class*="-tableBody"]')
    .find('div[role="row"]')
    .should('have.length', 1);
});
