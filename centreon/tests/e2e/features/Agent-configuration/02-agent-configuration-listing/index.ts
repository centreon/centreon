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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/tokens?*'
  }).as('getTokens');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/administration/tokens'
  }).as('addToken');
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
  cy.contains('Welcome to the agent configuration page').should('be.visible');
});

Given('a CMA Token is configured', () => {
  cy.addCmaToken();
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
  cy.contains('button', 'Add agent configuration').click();
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add agent configuration');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    agentsConfiguration.telegraf1.name
  );
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  // Click outside to close the pollers dropdown list
  cy.contains('h6', 'Pollers').click();
  cy.getByLabel({ label: 'Public certificate (.crt, .cert, .cer)', tag: 'input' })
    .eq(0)
    .type(agentsConfiguration.telegraf1.publicCertfFileName);
  cy.getByLabel({ label: 'CA (.crt, .cert, .cer)', tag: 'input' }).type(
    agentsConfiguration.telegraf1.caFileName
  );
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(0)
    .type(agentsConfiguration.telegraf1.privateKFileName);
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Public certificate (.crt, .cert, .cer)', tag: 'input' })
    .eq(1)
    .type(agentsConfiguration.telegraf1.certfFileName);
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(1)
    .type(agentsConfiguration.telegraf1.privateKFileName);
  cy.getByTestId({ testId: 'submit' }).click();
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]').should('contain', 'telegraf-001');
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

When('the user clicks on the line of the agent configuration', () => {
  cy.get('*[role="row"]').eq(1).click({ force: true });
  cy.wait('@getAgentsDetails');
});

Then('a pop up is displayed with all of the agent information', () => {
  cy.contains('Update agent configuration').should('be.visible');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).should(
    'have.value',
    'Telegraf'
  );
  cy.getByLabel({ label: 'Name', tag: 'input' }).should(
    'have.value',
    agentsConfiguration.telegraf1.name
  );
  cy.get('[class^="MuiChip-label MuiChip-labelMedium"]').should(
    'have.text',
    'Central'
  );
  cy.getByLabel({ label: 'Public certificate (.crt, .cert, .cer)', tag: 'input' })
    .eq(0)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.publicCertfFileName}`
    );
  cy.getByLabel({ label: 'CA (.crt, .cert, .cer)', tag: 'input' }).should(
    'have.value',
    `/etc/pki/${agentsConfiguration.telegraf1.caFileName}`
  );
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(0)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`
    );
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Public certificate (.crt, .cert, .cer)', tag: 'input' })
    .eq(1)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.certfFileName}`
    );
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(1)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`
    );
});

Given('some poller agent configurations are created', () => {
  cy.contains('button', 'Add').click();
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add agent configuration');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Centreon Monitoring Agent').click();
  cy.fillCmaMandatoryFields({
    ...agentsConfiguration.CMA1,
    publicCertificationFileName: agentsConfiguration.CMA1.publicCertfFileName,
    privateKeyFileName: agentsConfiguration.CMA1.privateKFileName
  });
  cy.getByTestId({ testId: 'submit' }).click();
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
  cy.getByTestId({ testId: 'Search' })
    .eq(1)
    .clear()
    .type(agentsConfiguration.CMA1.name);
  cy.wait('@getAgentsPage');
});

Then(
  'a listing page is displayed showing only the poller agent configurations that match the entered name',
  () => {
    cy.contains('p', agentsConfiguration.CMA1.name).should('be.visible');
    cy.get('div[role="table"]')
      .find('div.MuiTableBody-root')
      .find('div[role="row"]')
      .should('have.length', 1);
  }
);
