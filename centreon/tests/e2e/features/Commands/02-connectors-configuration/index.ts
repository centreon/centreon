import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/commands/connector.json';

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

When('the user creates a connector', () => {
  cy.navigateTo({
    page: 'Connectors',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addConnectors(data.connector);
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});

Then('the connector is displayed in the list', () => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select[name="o1"]');
  cy.getIframeBody().contains(data.connector.name).should('exist');
});

When('the user changes the properties of a connector', () => {
  cy.navigateTo({
    page: 'Connectors',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.waitForElementInIframe(
    '#main-content',
    'select[name="o1"]'
  );
  cy.getIframeBody().contains(data.connector.name).click();
  cy.updateConnectors(data.connectorUpdated);
  cy.getIframeBody()
    .find('input[class="btc bt_success"][name^="submit"]')
    .eq(0)
    .click();
});

Then('the properties are updated', () => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    'select[name="o1"]'
  );
  cy.getIframeBody().contains(data.connectorUpdated.name).should('exist');
  cy.getIframeBody().contains(data.connectorUpdated.name).click();
  cy.checkValuesOfConnectors(data.connectorUpdated.name, data.connectorUpdated);
});

When('the user duplicates a connector', () => {
  cy.navigateTo({
    page: 'Connectors',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.waitForElementInIframe(
    '#main-content',
    'select[name="o1"]'
  );
  cy.getIframeBody()
    .contains(data.connectorUpdated.name)
    .parents('tr')
    .find('input[type="checkbox"]')
    .check({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select("Duplicate");
});

Then('the new connector has the same properties', () => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.connectorUpdated.name}_1")`
  );
  cy.getIframeBody().contains('a', `${data.connectorUpdated.name}_1`).click();
  cy.checkValuesOfConnectors(`${data.connectorUpdated.name}_1`, data.connectorUpdated);
});

When('the user updates the status of a connector to {string}', (type: string) => {
  cy.navigateTo({
    page: 'Connectors',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.waitForElementInIframe(
    '#main-content',
    'select[name="o1"]'
  );
  switch (type) {
    case 'Disabled':
      cy.getIframeBody()
        .contains(data.connectorUpdated.name)
        .parents('tr')
        .find('img[alt="Disabled"]')
        .click({ force: true });
      break;
    case 'Enabled':
      cy.getIframeBody()
        .contains(data.connectorUpdated.name)
        .parents('tr')
        .find('img[alt="Enabled"]')
        .click({ force: true });
      break;
  }
});

Then('the new connector is updated with {string} status', (type: string) => {
  cy.reload();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    'select[name="o1"]'
  );
  switch (type) {
    case 'Disabled':
      cy.getIframeBody()
        .contains(data.connectorUpdated.name)
        .parents('tr')
        .find('img[alt="Enabled"]')
        .should('exist');
      cy.getIframeBody()
        .contains(data.connectorUpdated.name)
        .parents('tr')
        .find('span[class="badge service_critical"]')
        .should('have.text', type);
      break;
    case 'Enabled':
      cy.getIframeBody()
        .contains(data.connectorUpdated.name)
        .parents('tr')
        .find('img[alt="Disabled"]')
        .should('exist');
      cy.getIframeBody()
        .contains(data.connectorUpdated.name)
        .parents('tr')
        .find('span[class="badge service_ok"]')
        .should('have.text', type);
      break;
  }
});

When('the user deletes a connector', () => {
  cy.navigateTo({
    page: 'Connectors',
    rootItemNumber: 3,
    subMenu: 'Commands'
  });
  cy.waitForElementInIframe(
    '#main-content',
    'select[name="o1"]'
  );
  cy.getIframeBody()
    .contains(data.connectorUpdated.name)
    .parents('tr')
    .find('input[type="checkbox"]')
    .check({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select("Delete");
});

Then('the deleted connector is not displayed in the list', () => {
  cy.wait('@getTimeZone');
  cy.getIframeBody().should('not.have.text', data.connectorUpdated.name);
});