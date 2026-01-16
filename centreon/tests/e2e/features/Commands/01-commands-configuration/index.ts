import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { PAGES } from 'fixtures/shared/constants/pages';
import data from '../../../fixtures/commands/command.json';

let hostId = 0;

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/commands?page=*'
  }).as('getCommandsList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/global-macros?*'
  }).as('getGlobalMacros');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/standard-macros?page=*'
  }).as('getStandardMacros');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/plugins?page=*'
  }).as('getPlugins');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/connectors?page=*'
  }).as('getConnectors');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/commands/*'
  }).as('getCommandDetails');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/commands/_duplicate*'
  }).as('duplicateCommand');
  cy.intercept({
    method: 'DELETE',
    url: '/centreon/api/latest/configuration/commands/*'
  }).as('deleteCommand');
});

after(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('the admin user is on the commands Configuration page', () => {
  cy.visit(PAGES.configuration.commands);
  cy.wait('@getCommandsList');
});

When('the admin user creates a command', () => {
  // Click on the "Add" button
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
  cy.contains('Add a command').should('be.visible');
  cy.addOrUpdateCommands(data.check);
  // Click on the "Save" button
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@getCommandsList');
  cy.exportConfig();
});

Then('the command is displayed in the list', () => {
  // Search for the command
  cy.searchForCommandsByName(data.check.name);
});

When('the admin user changes the properties of a command', () => {
  // Search for the already created check command
  cy.searchForCommandsByName(data.check.name);
  // Click on the command
  cy.contains(data.check.name).click();
  cy.wait('@getCommandDetails');
  cy.contains('Modify a command').should('be.visible');
  cy.addOrUpdateCommands(data.miscellaneous);
  // Click on the "Save" button
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@getCommandsList');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  // Search for the already updated miscellaneous command
  cy.searchForCommandsByName(data.miscellaneous.name);
  // Click on the command
  cy.contains(data.miscellaneous.name).click();
  cy.wait('@getCommandDetails');
  cy.contains('Modify a command').should('be.visible');
  cy.checkValuesOfCommands(data.miscellaneous.name, data.miscellaneous);
});

When('the admin user duplicates a command', () => {
  // Search for the already existing miscellaneous command
  cy.searchForCommandsByName(data.miscellaneous.name);
  // Click on the "Duplicate" icon to duplicate the command
  cy.get('#Duplicate').eq(0).click();
  cy.get('button[type="submit"][aria-label="Duplicate"]')
    .should('be.visible')
    .click();
  cy.wait('@duplicateCommand');
  cy.exportConfig();
});

Then('the new command has the same properties', () => {
  // Click on the duplicated command
  cy.contains(`${data.miscellaneous.name}_`).click();
  cy.checkValuesOfCommands(`${data.miscellaneous.name}_`, data.miscellaneous);
});

When('the admin user deletes a command', () => {
  // Search for the already existing miscellaneous command
  cy.searchForCommandsByName(data.miscellaneous.name);
  // Click on the "Delete" icon to delete the command
  cy.get('#Delete').eq(0).click();
  cy.get('button[type="submit"][aria-label="Delete"]')
    .should('be.visible')
    .click();
  cy.wait('@deleteCommand');
  cy.exportConfig();
});

Then('the deleted command is not displayed in the list', () => {
  cy.get('body').should('not.have.text', data.miscellaneous.name);
});

When('the admin user creates a {string} command', (type: string) => {
  // Click on the "Add" button
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
  cy.contains('Add a command').should('be.visible');
  if (type === 'notification') {
    cy.addOrUpdateCommands(data.notification);
  } else {
    cy.addOrUpdateCommands(data.discovery);
  }
  // Click on the "Save" button
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@getCommandsList');
  cy.exportConfig();
});

Then(
  'the {string} command is displayed on the listing page',
  (type: string) => {
    if (type === 'notification') {
      // Search for the command
      cy.searchForCommandsByName(data.notification.name);
    } else {
      // Search for the command
      cy.searchForCommandsByName(data.discovery.name);
    }
  }
);

Given('a service being configured', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  // Click on the "Add" button
  cy.getIframeBody().contains('a', 'Add').click();
  // Wait for the "Service description" to be in the DOM
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  // Type value on the service description field
  cy.getIframeBody().find('input[name="service_description"]').type('service2');
  // Click on the service template field
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  // Chose a template
  cy.getIframeBody().find('div[title="generic-active-host"]').click();
});

When('the admin user selects a check command on the service form', () => {
  // Click on the check command field in the form
  cy.getIframeBody().find('span[title="Check Command"]').click();
  // Chose a check command
  cy.getIframeBody().find('div[title="check_centreon_dummy"]').click();
});

Then('Arguments of this command are displayed for the service', () => {
  // Check that the first arg of the check command is displayed
  cy.getIframeBody().find('input[name="ARG1"]').should('be.visible');
  // Check that the second arg of the check command is displayed
  cy.getIframeBody().find('input[name="ARG2"]').should('be.visible');
});

Then('the admin user can configure those arguments on the service form', () => {
  // Type a value in the first arg
  cy.getIframeBody().find('input[name="ARG1"]').type('0');
  // Type a value in the second arg
  cy.getIframeBody().find('input[name="ARG2"]').type('OK');
  // Click on the first "Save" button
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});

Given('a host being configured', () => {
  cy.addNewHostAndReturnId().then((returnedHostId) => {
    cy.log(`Host ID is: ${returnedHostId}`);
    hostId = returnedHostId;
  });
});

When('the admin user selects a check command on the host form', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.waitForElementInIframe(
    '#main-content',
    'a:contains("generic-active-host")'
  );
  // visit the host listing page
  cy.visit(`/centreon/main.php?p=60101&o=c&host_id=${hostId}`);
  cy.waitForElementInIframe('#main-content', '#command_command_id');
  cy.getIframeBody().find('span[title="Check Command"]').click();
  cy.getIframeBody().find('div[title="check_centreon_dummy"]').click();
});

Then('Arguments of this command are displayed for the host', () => {
  cy.getIframeBody()
    .find('input[name="command_command_id_arg1"]')
    .should('be.visible');
});

Then('the admin user can configure those arguments on the host form', () => {
  // Type a value in the command argument field
  cy.getIframeBody()
    .find('input[name="command_command_id_arg1"]')
    .type('!0!OK');
  // Click on the first "Save" button
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});

Given('a check command being configured', () => {
  // Click on the "Add" button
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
  cy.contains('Add a command').should('be.visible');
  cy.addOrUpdateCommands(data.check);
  // Click on the "Save" button
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@getCommandsList');
  cy.exportConfig();
});

Given('a check command is configured', () => {
  // Search for the command
  cy.searchForCommandsByName(data.check.name);
});

Given('a service is configured', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getTimeZone');
  // Wait for the "Configured Service" to be in the DOM
  cy.waitForElementInIframe('#main-content', 'a:contains("service2")');
});

When('the admin user opens the service in edit mode', () => {
  cy.getIframeBody().contains('service2').click();
  // Wait for the "Service description" to be in the DOM
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
});

When(
  'the admin user sets the configured check command as the check command of the service',
  () => {
    cy.addCommandToResource(2, data.check.name);
  }
);

When('the admin user saves the configuration', () => {
  // Click on the first "Save" button
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});

Then(
  'the "Service uses" column for the check command is updated accordingly',
  () => {
    cy.visit(PAGES.configuration.commands);

    // Search for the command
    cy.searchForCommandsByName(data.check.name);
    cy.contains('p', '1 (0)').should('be.visible');
  }
);

Given('a host is configured', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.waitForElementInIframe(
    '#main-content',
    'a:contains("generic-active-host")'
  );
});

When('the admin user opens the host in edit mode', () => {
  cy.getIframeBody().find('a:contains("generic-active-host")').eq(0).click();
  cy.waitForElementInIframe('#main-content', '#command_command_id');
});

When(
  'the admin user sets the configured check command as the check command of the host',
  () => {
    cy.addCommandToResource(1, data.check.name);
  }
);

Then(
  'the "Host uses" column for the check command is updated accordingly',
  () => {
    cy.visit(PAGES.configuration.commands);

    // Search for the command
    cy.searchForCommandsByName(data.check.name);
    cy.get('p.MuiTypography-root')
      .filter((_index, el) => el.innerText === '1 (0)') // one for the host and one for the service
      .should('have.length', 2);
  }
);
