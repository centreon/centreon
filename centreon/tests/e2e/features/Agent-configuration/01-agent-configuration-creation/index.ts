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
  }).as('addAgents');
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

When('the user clicks on Add', () => {
  cy.contains('button', 'Add').click();
});

Then('a pop-up menu with the form is displayed', () => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add poller/agent configuration');
});

When('the user fills in all the information', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.FillTelegrafMandatoryFields(agentsConfiguration.telegraf1);
});

When('the user clicks on Create', () => {
  cy.getByTestId({ testId: 'SaveIcon' }).click();
});

Then('the first agent is displayed in the Agents Configuration page', () => {
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]').should('contain', 'telegraf-001');
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

When('the user selects the centreon agent', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Centreon Monitoring Agent').click();
});

Then('the connection initiated by poller field must be disabled', () => {
  cy.get('[class*="Mui-checked Mui-checked"]').should('not.exist');
});

When('the user enables the connection initiated by the poller option', () => {
  cy.getByLabel({
    label: 'Connection initiated by poller',
    tag: 'input'
  }).click();
  cy.get('[class*="Mui-checked Mui-checked"]').should('exist');
});

Then('a new parameters group is displayed for the host', () => {
  cy.get('[class$="hostConfigurations"]')
    .find('[class^="MuiDivider-root MuiDivider-fullWidth"]')
    .should('have.length', 1);
});

When('the user disables the connection initiated by poller option', () => {
  cy.getByLabel({
    label: 'Connection initiated by poller',
    tag: 'input'
  }).click();
  cy.get('[class*="Mui-checked Mui-checked"]').should('not.exist');
});

Then('the group of parameters for the host disappears', () => {
  cy.get('[class$="hostConfigurations"]').should('not.exist');
});

When('the user fills in the mandatory information', () => {
  cy.FillCMAMandatoryFields(agentsConfiguration.CMA1);
});

Then('the second agent is displayed in the Agents Configuration page', () => {
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]').should('contain', 'centreon-agent-001');
  cy.get('*[role="rowgroup"]').should('contain', 'Centreon Monitoring Agent');
});

When('the user clicks to add a second host', () => {
  cy.contains('Add a host').click();
});

Then('a second group of parameters for hosts is displayed', () => {
  cy.get('[class$="hostConfigurations"]')
    .find('[class^="MuiDivider-root MuiDivider-fullWidth"]')
    .should('have.length', 2);
});

When('the user fills in the centreon agent parameters', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('centreon-agent-002');
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-2').click();
  cy.contains('Poller-3').click();
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(
    'my-otel-certificate-name-003'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' }).type(
    'my-otel-private-key-name-003'
  );
  cy.getByLabel({ label: 'CA file name', tag: 'input' })
    .eq(0)
    .type('my-ca-file-003');
  cy.getByLabel({ label: 'Add host', tag: 'input' }).eq(0).click();
  cy.contains('Centreon-Server').click();
  cy.getByLabel({ label: 'DNS/IP', tag: 'input' }).eq(1).type('10.0.0.0');
  cy.getByTestId({ testId: 'Port' }).eq(1).type('4317');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' })
    .eq(1)
    .type('my-certificate-name-003');
});

Then('the third agent is displayed in the Agents Configuration page', () => {
  cy.wait('@addAgents');
  cy.get('*[role="rowgroup"]').should('contain', 'centreon-agent-002');
  cy.get('*[role="rowgroup"]').should('contain', 'Centreon Monitoring Agent');
});

When("the user doesn't fill in all the mandatory information", () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(
    'my-otel-certificate-name-002'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(0)
    .type('my-otel-private-key-name-002');
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type(
    'my-certificate-name-002'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(1)
    .type('my-otel-private-key-name-002');
});

Then('the user cannot click on Create', () => {
  cy.getByTestId({ testId: 'SaveIcon' })
    .parents('button')
    .should('be.disabled');
});

When("the user doesn't fill in correct type of information", () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('telegraf-003');
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(
    'my-otel-certificate-name-001.crt'
  );
  cy.getByLabel({ label: 'CA file name', tag: 'input' }).type(
    'ca-file-name-001.crt'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(0)
    .type('my-otel-private-key-name-001.key');
  cy.getByLabel({ label: 'Port', tag: 'input' }).clear().type('700000');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type(
    'my-certificate-name-001.crt'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(1)
    .type('my-otel-private-key-name-001.key');
  cy.getByLabel({ label: 'Name', tag: 'input' }).click();
});

Then('the form displayed an error', () => {
  cy.getByTestId({ testId: 'Public certificate file name' }).contains(
    'Invalid filename'
  );
  cy.getByTestId({ testId: 'CA file name' }).contains('Invalid filename');
  cy.getByTestId({ testId: 'Private key file name' })
    .eq(0)
    .contains('Invalid filename');
  cy.getByTestId({ testId: 'Port' }).contains(
    'Port number must be at most 65535'
  );
  cy.getByTestId({ testId: 'Certificate file name' }).contains(
    'Invalid filename'
  );
  cy.getByTestId({ testId: 'Private key file name' })
    .eq(2)
    .contains('Invalid filename');
  cy.getByTestId({ testId: 'Private key file name' })
    .eq(0)
    .contains('Invalid filename');
  cy.getByTestId({ testId: 'Port' }).contains(
    'Port number must be at most 65535'
  );
  cy.getByTestId({ testId: 'Certificate file name' }).contains(
    'Invalid filename'
  );
  cy.getByTestId({ testId: 'Private key file name' })
    .eq(2)
    .contains('Invalid filename');
});

When('the user fills in the needed information', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('telegraf-004');
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-4').click();
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
    .type('my-otel-private-key-name-001');
  cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type(
    'my-certificate-name-001'
  );
  cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(1)
    .type('my-otel-private-key-name-001');
});

When('the user clicks on the Cancel button of the creation form', () => {
  cy.contains('Cancel').click();
});

Then('a pop-up appears to confirm cancellation', () => {
  cy.get('*[role="dialog"]')
    .eq(1)
    .children()
    .contains('Do you want to save the changes?');
  cy.get('*[role="dialog"]')
    .eq(1)
    .children()
    .contains('If you click on Discard, your changes will not be saved.');
});

When('the user confirms the cancellation', () => {
  cy.getByLabel({ label: 'Discard', tag: 'button' }).click();
});

Then('the creation form is closed', () => {
  cy.get('*[role="dialog"]').should('not.exist');
});

Then('the agent has not been created', () => {
  cy.get('*[role="rowgroup"]').should('not.contain', 'telegraf-004');
});

Then('the form fields are empty', () => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');
});

When('the user clicks on Save in the cancellation pop-up', () => {
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@addAgents');
});

Then('the agent has been created', () => {
  cy.get('*[role="rowgroup"]').should('contain', 'telegraf-004');
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

When(
  'the user fills in the {string} mandatory fields',
  (agent_type: string) => {
    if (agent_type.includes('Agent')) {
      cy.FillCMAMandatoryFields(agentsConfiguration.CMA1);
    } else {
      cy.FillTelegrafMandatoryFields(agentsConfiguration.telegraf1);
    }
  }
);

When('the user selects the {string} type', (agentType: string) => {
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains(agentType).click();
});

When('the user fills all the Telegraf mandatory fields', () => {
  cy.FillTelegrafMandatoryFields(agentsConfiguration.telegraf1);
});

When('the user {string} the form', (action: string) => {
  if (action.includes('cancel')) {
    cy.contains('button', 'Cancel').click();
  } else {
    cy.get('body').click(0, 0);
  }
});

Then('a pop-up is displayed', () => {
  cy.get('div[role="dialog"]').eq(1).should('be.visible');
});

Then('the title of this pop-up is {string}', (popupTitle: string) => {
  cy.get('div[class*="-modalHeader"]')
    .eq(1)
    .within(() => {
      cy.get('h2').should('contain.text', popupTitle);
    });
});

Then('the message body of this pop-up is {string}', (popupMessage: string) => {
  cy.get('div[class*="-modalBody"]').eq(1).should('contain.text', popupMessage);
});

When(
  "the user doesn't fill some {string} mandatory fields",
  (agentType: string) => {
    if (agentType.includes('Agent')) {
      cy.FillOnlySomeCMAMandatoryFields(agentsConfiguration.CMA1);
    } else {
      cy.FillOnlySomeTelegrafMandatoryFields(agentsConfiguration.telegraf1);
    }
  }
);

Then('this pop-up contains two buttons "Resolve" and "Discard"', () => {
  cy.get('div[class*="-modalActions"]').within(() => {
    cy.get('button').contains('Discard').should('exist');
    cy.get('button').contains('Resolve').should('exist');
    cy.get('button').should('have.length', 2);
  });
});
