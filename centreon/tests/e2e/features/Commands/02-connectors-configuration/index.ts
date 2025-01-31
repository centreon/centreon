import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/commands/connector.json';

// before(() => {
//   cy.startContainers();
// });

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/main.php?p=60806'
  }).as('getConnectorsPage');
});

// after(() => {
//   cy.stopContainers();
// });

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the user creates a connector', () => {
  cy.visit('/centreon/main.php?p=60806');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  cy.getIframeBody().contains('a', '+ ADD').click();
  cy.addConnectors(data.connector);
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});

Then('the connector is displayed in the list', () => {
  cy.wait('@getConnectorsPage');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  cy.reload();
  cy.getIframeBody().contains(data.connector.name).should('exist');
});

When('the user changes the properties of a connector', () => {
  cy.visit('/centreon/main.php?p=60806');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  cy.getIframeBody().contains(data.connector.name).click();
  cy.updateConnectors(data.connectorUpdated);
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});

Then('the properties are updated', () => {
  cy.wait('@getConnectorsPage');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  cy.reload();
  cy.getIframeBody().contains(data.connectorUpdated.name).should('exist');
  cy.getIframeBody().contains(data.connectorUpdated.name).click();
  cy.checkValuesOfConnectors(data.connectorUpdated.name, data.connectorUpdated);
});

When('the user duplicates a connector', () => {
  cy.visit('/centreon/main.php?p=60806');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  // cy.getIframeBody().find('[alt="Duplicate"]').eq(1).click();
});

Then('the new connector has the same properties', () => {
  // cy.waitForElementInIframe(
  //   '#main-content',
  //   `a:contains("${data.connectorUpdated.name}_1")`
  // );
  // cy.getIframeBody().contains('a', `${data.connectorUpdated.name}_1`).click();
  // cy.checkValuesOfCommands(`${data.connectorUpdated.name}_1`, data.connectorUpdated);
});

When('the user update the status of a connector to {string}', (type: string) => {
  cy.visit('/centreon/main.php?p=60806');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  // cy.getIframeBody().contains('a', '+ ADD').click();
  // cy.addCommands(commandData);
  // cy.getIframeBody()
  //   .find('input[class="btc bt_success"][name^="submit"]')
  //   .eq(0)
  //   .click();
});

Then('the new connector is updated with {string} status', (type: string) => {
  // cy.wait('@getCommandsPage');
  // cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  // cy.reload();
  // cy.getIframeBody().contains(commandTypeMap[type].data.name).should('exist');
});


When('the user deletes a connector', () => {
  cy.visit('/centreon/main.php?p=60806');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  // cy.getIframeBody()
  //   .contains('a', `${data.miscellaneous.name}`)
  //   .parents('tr')
  //   .find('[alt="Delete"]')
  //   .click();
});

Then('the deleted connector is not displayed in the list', () => {
  // cy.reload();
  // cy.getIframeBody().should('not.have.text', data.miscellaneous.name);
});