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
    url: '/centreon/main.php?p=60802&type=*'
  }).as('getCommandsPage');
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
  cy.visit('/centreon/main.php?p=60802&type=2');
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.getIframeBody().contains('a', '+ ADD').click();
  cy.addCommands(data.check);
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getCommandsPage');
});

Then('the command is displayed in the list', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.reload();
  cy.getIframeBody().contains(data.check.name).should('exist');
});

When('the user changes the properties of a command', () => {
  cy.visit('/centreon/main.php?p=60802&type=2');
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.getIframeBody().contains(data.check.name).click();
  cy.updateCommands(data.miscellaneous);
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getCommandsPage');
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.reload();
  cy.getIframeBody().contains(data.miscellaneous.name).should('exist');
  cy.getIframeBody().contains(data.miscellaneous.name).click();
  cy.checkValuesOfCommands(data.miscellaneous.name, data.miscellaneous);
});

When('the user duplicates a command', () => {
  cy.visit('/centreon/main.php?p=60802&type=3');
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.getIframeBody().find('[alt="Duplicate"]').eq(1).click();
});

Then('the new command has the same properties', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.miscellaneous.name}_1")`
  );
  cy.getIframeBody().contains('a', `${data.miscellaneous.name}_1`).click();
  cy.checkValuesOfCommands(`${data.miscellaneous.name}_1`, data.miscellaneous);
});

When('the user deletes a command', () => {
  cy.visit('/centreon/main.php?p=60802&type=3');
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.getIframeBody()
    .contains('a', `${data.miscellaneous.name}`)
    .parents('tr')
    .find('[alt="Delete"]')
    .click();
});

Then('the deleted command is not displayed in the list', () => {
  cy.reload();
  cy.getIframeBody().should('not.have.text', data.miscellaneous.name);
});

When('the user creates a {string} command', (type: string) => {
  const { type: pageType, data: commandData } = commandTypeMap[type];
  cy.visit(`/centreon/main.php?p=60802&type=${pageType}`);
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.getIframeBody().contains('a', '+ ADD').click();
  cy.addCommands(commandData);
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getCommandsPage');
  cy.exportConfig();
});

Then('the command is displayed on the {string} page', (type: string) => {
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
  cy.getIframeBody().contains('a', 'Add').click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  cy.getIframeBody().find('input[name="service_description"]').type('service2');
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find('div[title="generic-active-host"]').click();
});

When('the user selects a check command on the service form', () => {
  cy.getIframeBody().find('span[title="Check Command"]').click();
  cy.getIframeBody().find('div[title="check_centreon_dummy"]').click();
});

Then('Arguments of this command are displayed for the service', () => {
  cy.getIframeBody().find('input[name="ARG1"]').should('be.visible');
  cy.getIframeBody().find('input[name="ARG2"]').should('be.visible');
});

Then('the user can configure those arguments on the service form', () => {
  cy.getIframeBody().find('input[name="ARG1"]').type('0');
  cy.getIframeBody().find('input[name="ARG2"]').type('OK');
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
  cy.getIframeBody()
    .find('input[name="command_command_id_arg1"]')
    .type('!0!OK');
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});
