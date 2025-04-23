import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/commands/command.json';

const commandTypeMap = {
  check: { data: data.check, type: 2 },
  discovery: { data: data.discovery, type: 4 },
  miscellaneous: { data: data.miscellaneous, type: 3 },
  notification: { data: data.notification, type: 1 }
};

let hostID = 0;

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

When('the user creates a command', () => {
  cy.navigateTo({
    page: 'Checks',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.wait('@getTimeZone');
  // Click on the "ADD" button
  cy.getIframeBody().contains('a', '+ ADD').click();
  cy.addCommands(data.check);
  // Click on the first "Save" button
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the command is displayed in the list', () => {
  // Wait for the created command to be charged on the DOM
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.check.name}")`
  );
  cy.getIframeBody().contains(data.check.name).should('exist');
});

When('the user changes the properties of a command', () => {
  cy.navigateTo({
    page: 'Checks',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.check.name}")`
  );
  // Click on the command
  cy.getIframeBody().contains(data.check.name).click();
  cy.updateCommands(data.miscellaneous);
  // Click on the first "Save" button
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  // Refresh the page
  cy.reload();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.miscellaneous.name}")`
  );
  cy.getIframeBody().contains(data.miscellaneous.name).should('exist');
  // Click on the command
  cy.getIframeBody().contains(data.miscellaneous.name).click();
  cy.checkValuesOfCommands(data.miscellaneous.name, data.miscellaneous);
});

When('the user duplicates a command', () => {
  cy.navigateTo({
    page: 'Miscellaneous',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.wait('@getTimeZone');
  // Wait for the "Command" search field to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  // Click on the "Duplicate" icon to duplicate the command
  cy.getIframeBody().find('[alt="Duplicate"]').eq(1).click();
  cy.exportConfig();
});

Then('the new command has the same properties', () => {
  // Wait for the duplicated command to be charged on the DOM
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.miscellaneous.name}_1")`
  );
  // Click on the duplicated command
  cy.getIframeBody().contains('a', `${data.miscellaneous.name}_1`).click();
  cy.checkValuesOfCommands(`${data.miscellaneous.name}_1`, data.miscellaneous);
});

When('the user deletes a command', () => {
  // Go to "Configuration > Commands > Miscellaneous"
  cy.navigateTo({
    page: 'Miscellaneous',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.miscellaneous.name}")`
  );
  // Click on the "Delete" icon to delete the command
  cy.getIframeBody()
    .contains('a', `${data.miscellaneous.name}`)
    .parents('tr')
    .find('[alt="Delete"]')
    .click();
  cy.exportConfig();
});

Then('the deleted command is not displayed in the list', () => {
  cy.getIframeBody().should('not.have.text', data.miscellaneous.name);
});

When('the user creates a {string} command', (type: string) => {
  const { type: pageType, data: commandData } = commandTypeMap[type];
  // visit a command page
  cy.visit(`/centreon/main.php?p=60802&type=${pageType}`);
  // Wait for the "Command" search field to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  // Click on the "ADD" button
  cy.getIframeBody().contains('a', '+ ADD').click();
  cy.addCommands(commandData);
  // Click on the first "Save" button
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the command is displayed on the {string} page', (type: string) => {
  // Wait for the command to be in the DOM
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${commandTypeMap[type].data.name}")`
  );
  cy.getIframeBody().contains(commandTypeMap[type].data.name).should('exist');
});

Given('a service being configured', () => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
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

When('the user selects a check command on the service form', () => {
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

Then('the user can configure those arguments on the service form', () => {
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
  cy.addNewHostAndReturnId().then((hostId) => {
    cy.log(`Host ID is: ${hostId}`);
    hostID = hostId;
  });
});

When('the user selects a check command on the host form', () => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.waitForElementInIframe(
    '#main-content',
    'a:contains("generic-active-host")'
  );
  // visit the host listing page
  cy.visit(`/centreon/main.php?p=60101&o=c&host_id=${hostID}`);
  cy.waitForElementInIframe('#main-content', '#command_command_id');
  cy.getIframeBody().find('span[title="Check Command"]').click();
  cy.getIframeBody().find('div[title="check_centreon_dummy"]').click();
});

Then('Arguments of this command are displayed for the host', () => {
  cy.getIframeBody()
    .find('input[name="command_command_id_arg1"]')
    .should('be.visible');
});

Then('the user can configure those arguments on the host form', () => {
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
