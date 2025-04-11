import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import agentsConfiguration from '../../../fixtures/agents-configuration/agent-config.json';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/ac-acl-user.json'
  );
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/local-authentication-acl-user.json'
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
    method: 'PUT',
    url: '/centreon/api/latest/configuration/agent-configurations/*'
  }).as('updateAgents');
  cy.intercept({
    method: 'DELETE',
    url: '/centreon/api/latest/configuration/agent-configurations/*'
  }).as('deleteAgents');
});

after(() => {
  cy.stopContainers();
});

Given('an admin user is in the Agents Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/pollers/agent-configurations');
  cy.wait('@getAgentsPage');
});

When('the user clicks on Add', () => {
  cy.contains('button', 'Add poller/agent configuration').click();
});

Then('a pop-up menu with the form is displayed', () => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add poller/agent configuration');
});

When('the admin user fills in all the information', () => {
  cy.addTelegrafAgent(agentsConfiguration.telegraf1);
});

When('the user clicks on Save', () => {
  cy.get('[data-testid="SaveIcon"]')
  .should('be.visible')
  .click();
  cy.wait('@addAgents');
});

Then('the creation form is closed', () => {
  cy.get('*[role="dialog"]').should('not.exist');
});

Then(
  'the first configuration is displayed in the Agents Configuration page',
  () => {
    cy.get('*[role="rowgroup"]').should('contain', 'telegraf-001');
    cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
  }
);

Given('an agent configuration is already created', () => {
  cy.get('*[role="rowgroup"]').should('contain', 'telegraf-001');
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

When('the user clicks on the line of the Agents Configuration', () => {
  cy.get('*[role="row"]').eq(1).click({ force: true });
  cy.wait('@getAgentsDetails');
});

Then('a pop up is displayed with all of the agent information', () => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.contains('Update poller/agent configuration').should('be.visible');
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
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(0)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.publicCertfFileName}`
    );
  cy.getByLabel({ label: 'CA', tag: 'input' }).should(
    'have.value',
    `/etc/pki/${agentsConfiguration.telegraf1.caFileName}`
  );
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(0)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`
    );
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(1)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.certfFileName}`
    );
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(1)
    .should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`
    );
});

When('the user modifies the configuration', () => {
  cy.updateTelegrafAgent(agentsConfiguration.telegraf2);
});

Then('the update form is closed', () => {
  cy.wait('@updateAgents');
  cy.get('*[role="dialog"]').should('not.exist');
  cy.contains('Update poller/agent configuration').should('not.exist');
});

Then(
  'the updated configuration is displayed correctly in the Agents Configuration page',
  () => {
    cy.get('*[role="rowgroup"]').should(
      'contain',
      agentsConfiguration.telegraf2.name
    );
    cy.get('*[role="rowgroup"]').should('contain', '2 pollers');
    cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
  }
);

When('the user deletes the Agents Configuration', () => {
  cy.getByTestId({ testId: 'DeleteOutlineIcon' }).eq(0).click();
  cy.contains('button', 'Delete').click();
  cy.wait('@deleteAgents');
});

Then(
  'the Agents Configuration is no longer displayed in the listing page',
  () => {
    cy.contains('Welcome to the poller/agent configuration page').should(
      'be.visible'
    );
    cy.contains('telegraf-001-updated').should('not.exist');
  }
);

Given('a non-admin user without topology rights is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user',
    loginViaApi: false
  });
});

When('the user tries to access the Agents Configuration page', () => {
  cy.visit('/centreon/configuration/pollers/agent-configurations');
});

Then('the user cannot access the Agents Configuration page', () => {
  cy.getByTestId({ testId: 'You are not allowed to see this page' }).should(
    'be.visible'
  );
});

Given('a non-admin user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
});

Given('an agent configuration already created linked with two pollers', () => {
  cy.visit('/centreon/configuration/pollers/agent-configurations');
  cy.wait('@getAgentsPage');
  cy.contains('button', 'Add').click();
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add poller/agent configuration');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    agentsConfiguration.telegraf1.name
  );
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.contains('Poller-1').click();
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(0)
    .type(agentsConfiguration.telegraf1.publicCertfFileName);
  cy.getByLabel({ label: 'CA', tag: 'input' }).type(
    agentsConfiguration.telegraf1.caFileName
  );
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(0)
    .type(agentsConfiguration.telegraf1.privateKFileName);
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(1)
    .type(agentsConfiguration.telegraf1.certfFileName);
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(1)
    .type(agentsConfiguration.telegraf1.privateKFileName);
  cy.getByTestId({ testId: 'SaveIcon' }).click();
  cy.wait('@addAgents');
});

Given('the user has a filter on one of the pollers', () => {
  cy.setUserTokenApiV1().executeActionViaClapi({
    bodyContent: {
      action: 'addfilter_instance',
      object: 'ACLRESOURCE',
      values: `All Resources;Poller-1`
    }
  });
  cy.setUserTokenApiV1().executeActionViaClapi({
    bodyContent: {
      action: 'reload',
      object: 'ACL',
      values: ''
    }
  });
});

When('the user accesses the Agents Configuration page', () => {
  cy.reload();
  cy.wait('@getAgentsPage');
});

Then(
  'the user can not view the agent configuration linked to the 2 pollers',
  () => {
    cy.contains('Welcome to the poller/agent configuration page').should(
      'be.visible'
    );
    cy.contains('telegraf-001-updated').should('not.exist');
  }
);

When(
  'the admin user updates the filtered pollers of the non-admin user',
  () => {
    cy.setUserTokenApiV1().executeActionViaClapi({
      bodyContent: {
        action: 'addfilter_instance',
        object: 'ACLRESOURCE',
        values: `All Resources;Central`
      }
    });
    cy.setUserTokenApiV1().executeActionViaClapi({
      bodyContent: {
        action: 'reload',
        object: 'ACL',
        values: ''
      }
    });
  }
);

Then('the user can view the agent configuration linked to the pollers', () => {
  cy.reload();
  cy.get('*[role="rowgroup"]').should(
    'contain',
    agentsConfiguration.telegraf1.name
  );
  cy.get('*[role="rowgroup"]').should('contain', '2 pollers');
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

Then(
  'a pop up is displayed with all of the agent configuration information with the 2 pollers',
  () => {
    cy.get('*[role="dialog"]').should('be.visible');
    cy.contains('Update poller/agent configuration').should('be.visible');
    cy.getByLabel({ label: 'Agent type', tag: 'input' }).should(
      'have.value',
      'Telegraf'
    );
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'have.value',
      agentsConfiguration.telegraf1.name
    );
    cy.get('[class^="MuiChip-label MuiChip-labelMedium"]')
      .eq(0)
      .should('have.text', 'Central');
    cy.get('[class^="MuiChip-label MuiChip-labelMedium"]')
      .eq(1)
      .should('have.text', 'Poller-1');
    cy.getByLabel({
      label: 'Public certificate',
      tag: 'input'
    })
      .eq(0)
      .should(
        'have.value',
        `/etc/pki/${agentsConfiguration.telegraf1.publicCertfFileName}`
      );
    cy.getByLabel({ label: 'CA', tag: 'input' }).should(
      'have.value',
      `/etc/pki/${agentsConfiguration.telegraf1.caFileName}`
    );
    cy.getByLabel({ label: 'Private key', tag: 'input' })
      .eq(0)
      .should(
        'have.value',
        `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`
      );
    cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
    cy.getByLabel({
      label: 'Public certificate',
      tag: 'input'
    })
      .eq(1)
      .should(
        'have.value',
        `/etc/pki/${agentsConfiguration.telegraf1.certfFileName}`
      );
    cy.getByLabel({ label: 'Private key', tag: 'input' })
      .eq(1)
      .should(
        'have.value',
        `/etc/pki/${agentsConfiguration.telegraf1.privateKFileName}`
      );
  }
);

When('the user can update the Agents Configuration', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' })
    .clear()
    .type(agentsConfiguration.telegraf2.name);
  cy.getByTestId({ testId: 'CancelIcon' }).eq(0).click();
  cy.getByTestId({ testId: 'SaveIcon' }).click();
  cy.wait('@updateAgents');
  cy.get('*[role="dialog"]').should('not.exist');
  cy.contains('Update poller/agent configuration').should('not.exist');
  cy.get('*[role="rowgroup"]').should(
    'contain',
    agentsConfiguration.telegraf2.name
  );
  cy.get('*[role="rowgroup"]').should('contain', '1 poller');
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

Given('a non-admin user is in the Agents Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/pollers/agent-configurations');
  cy.wait('@getAgentsPage');
});

Given('an already existing agent configuration is displayed', () => {
  cy.get('*[role="rowgroup"]').should(
    'contain',
    agentsConfiguration.telegraf2.name
  );
  cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
});

When('the user adds a second agent configuration', () => {
  cy.contains('button', 'Add').click();
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
});

Then('only the filtered pollers are listed in the Pollers field', () => {
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.get('[class^="MuiPopper-root MuiAutocomplete-popper"]').contains(
    'Central'
  );
  cy.get('[class^="MuiPopper-root MuiAutocomplete-popper"]').contains(
    'Poller-1'
  );
});

When('the non-admin user fills in all the information', () => {
  cy.addTelegrafAgent(agentsConfiguration.telegraf1);
});

Then(
  'the second configuration is displayed in the Agents Configuration page',
  () => {
    cy.get('*[role="rowgroup"]').should(
      'contain',
      agentsConfiguration.telegraf1.name
    );
    cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
  }
);

Then(
  'the first Agents Configuration is no longer displayed in the listing page',
  () => {
    cy.contains('telegraf-001-updated').should('not.exist');
    cy.get('*[role="rowgroup"]').should(
      'contain',
      agentsConfiguration.telegraf2.name
    );
    cy.get('*[role="rowgroup"]').should('contain', 'Telegraf');
  }
);
