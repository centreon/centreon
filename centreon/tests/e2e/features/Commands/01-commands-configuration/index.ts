import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/commands/command.json';

const commandTypeMap = {
  check: { type: 2, data: data.check },
  notification: { type: 1, data: data.notification },
  discovery: { type: 4, data: data.discovery },
  miscellaneous: { type: 3, data: data.miscellaneous }
};


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
});

Then('the command is displayed in the list', () => {
  cy.wait('@getCommandsPage');
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
});

Then('the properties are updated', () => {
  cy.wait('@getCommandsPage');
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
  cy.getIframeBody().should('not.contain', data.miscellaneous.name);
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
});

Then('the command is displayed on the {string} page', (type: string) => {
  cy.wait('@getCommandsPage');
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  cy.reload();
  cy.getIframeBody().contains(commandTypeMap[type].data.name).should('exist');
});
