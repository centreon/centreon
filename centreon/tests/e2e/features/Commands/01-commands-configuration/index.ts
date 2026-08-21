import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/commands/command.json';

let hostId = 0;

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.commands_configuration}?page=*`
  }).as('getCommandsList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.global_macros_configuration}?*`
  }).as('getGlobalMacros');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.standard_macros_configuration}?page=*`
  }).as('getStandardMacros');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.plugins_configuration}?page=*`
  }).as('getPlugins');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.plugins_configuration}?page=*`
  }).as('getConnectors');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.commands_configuration}/*`
  }).as('getCommandDetails');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.commands_configuration}/_duplicate*`
  }).as('duplicateCommand');
  cy.intercept({
    method: 'DELETE',
    url: `${INTERCEPTORS.api.commands_configuration}/*`
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
  cy.addOrUpdateCommands(data.check_updated);
  // Click on the "Save" button
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@getCommandsList');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  // Search for the already updated check command
  cy.searchForCommandsByName(data.check_updated.name);
  // Click on the command
  cy.contains(data.check_updated.name).click();
  cy.wait('@getCommandDetails');
  cy.contains('Modify a command').should('be.visible');
  cy.checkValuesOfCommands(data.check_updated.name, data.check_updated);
});

When('the admin user duplicates a command', () => {
  // Search for the already existing check command
  cy.searchForCommandsByName(data.check_updated.name);
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
  cy.contains(`${data.check_updated.name}_`).click();
  cy.checkValuesOfCommands(`${data.check_updated.name}_`, data.check_updated);
});

When('the admin user deletes a command', () => {
  // Search for the already existing check command
  cy.searchForCommandsByName(`${data.check_updated.name}_`);
  // Click on the "Delete" icon to delete the command
  cy.get('#Delete').eq(0).click();
  cy.get('button[type="submit"][aria-label="Delete"]')
    .should('be.visible')
    .click();
  cy.wait('@deleteCommand');
  cy.exportConfig();
});

Then('the deleted command is not displayed in the list', () => {
  cy.get('body').should(
    'not.contain.text',
    new RegExp(`${data.check_updated.name}_\\d+`)
  );
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
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  // Click on the "Add" button, which opens the form in the side panel
  cy.clickListingAddButton();
  // Wait for the "Service description" to be in the DOM
  cy.getFormBody()
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible')
    // Type value on the service description field
    .type('service2');
  // Click on the service template field
  cy.getFormBody().find('input[class="select2-search__field"]').eq(0).click();
  // Chose a template
  cy.getFormBody().find('div[title="generic-active-host"]').click();
});

When('the admin user selects a check command on the service form', () => {
  // Click on the check command field in the form
  cy.getFormBody().find('span[title="Check Command"]').click();
  // Chose a check command
  cy.getFormBody().find('div[title="check_centreon_dummy"]').click();
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

Given('a check command is configured', () => {
  // Search for the command
  cy.searchForCommandsByName(data.check_updated.name);
});

Given('a service is configured', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  // Wait for the "Configured Service" to be in the DOM
  cy.getIframeBody().find('#clTableBody').contains('a', 'service2');
});

When('the admin user opens the service in edit mode', () => {
  // Wait for the "Service description" to be in the DOM of the side panel
  cy.openListingRowForm('service2')
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible');
});

When(
  'the admin user sets the configured check command as the check command of the service',
  () => {
    cy.addCommandToResource(2, data.check_updated.name);
  }
);

When('the admin user saves the configuration', () => {
  // Click on the first "Save" button
  cy.getFormBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .first()
    .click();
});

Then(
  'the "Used by services" column for the check command is updated accordingly',
  () => {
    cy.visit(PAGES.configuration.commands);

    // Search for the command
    cy.searchForCommandsByName(data.check_updated.name);
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
    cy.addCommandToResource(1, data.check_updated.name);
  }
);

Then(
  'the "Used by hosts" column for the check command is updated accordingly',
  () => {
    cy.visit(PAGES.configuration.commands);

    // Search for the command
    cy.searchForCommandsByName(data.check_updated.name);
    cy.get('p.MuiTypography-root')
      .filter((_index, el) => el.innerText === '1 (0)') // one for the host and one for the service
      .should('have.length', 2);
  }
);
