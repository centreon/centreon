import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/commands/connector.json';

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
    url: INTERCEPTORS.ajax.connector_listing
  }).as('listConnectors');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.ajax.connector_toggle
  }).as('toggleConnector');
});

after(() => {
  cy.stopContainers();
});

/** The listing is AJAX-driven: wait for the table, not for the legacy markup. */
const openConnectorsListing = (): void => {
  cy.visit(PAGES.configuration.commandsConnectorsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
};

/** Both add and edit open the form in the side panel instead of navigating. */
const openConnectorForm = (name: string): void => {
  cy.getIframeBody().find('#clTableBody').contains('a', name).click();
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_name"]', { timeout: 20_000 })
    .should('be.visible');
};

const submitConnectorForm = (): void => {
  // A multi-select dropdown stays open after a pick and covers the action bar.
  cy.getConnectorSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click({ force: true });
};

const selectRowAndRunBulkAction = (name: string, action: string): void => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(name)
    .parents('tr')
    // Scoped to the picker: the row also holds the activation toggle checkbox.
    .find('.cl-col-picker input[type="checkbox"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  // The native o1 select is hidden behind the .cl-more-actions menu; the
  // overridden onchange turns a value change into setO + submit.
  cy.getIframeBody().find('select[name="o1"]').select(action, { force: true });
};

const rowToggle = (name: string) =>
  cy
    .getIframeBody()
    .find('#clTableBody')
    .contains(name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]');

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the user creates a connector', () => {
  openConnectorsListing();
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.addConnectors({
    ...data.connector,
    commandLine: data.connector.command_line,
    isEnabled: data.connector.is_enabled,
    usedByCommand: data.connector.used_by_command
  });
  submitConnectorForm();
});

Then('the connector is displayed in the list', () => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', data.connector.name)
    .should('exist');
});

When('the user changes the properties of a connector', () => {
  openConnectorsListing();
  openConnectorForm(data.connector.name);
  cy.updateConnectors({
    ...data.connectorUpdated,
    commandLine: data.connectorUpdated.command_line,
    isEnabled: data.connectorUpdated.is_enabled,
    usedByCommand: data.connectorUpdated.used_by_command
  });
  submitConnectorForm();
});

Then('the properties are updated', () => {
  cy.wait('@getTimeZone');
  openConnectorsListing();
  openConnectorForm(data.connectorUpdated.name);
  cy.checkValuesOfConnectors(data.connectorUpdated.name, {
    ...data.connectorUpdated,
    commandLine: data.connectorUpdated.command_line,
    isEnabled: data.connectorUpdated.is_enabled,
    usedByCommand: data.connectorUpdated.used_by_command
  });
});

When('the user duplicates a connector', () => {
  openConnectorsListing();
  selectRowAndRunBulkAction(data.connectorUpdated.name, 'Duplicate');
  cy.wait('@getTimeZone');
});

Then('the new connector has the same properties', () => {
  openConnectorsListing();
  openConnectorForm(`${data.connectorUpdated.name}_1`);
  cy.checkValuesOfConnectors(`${data.connectorUpdated.name}_1`, {
    ...data.connectorUpdated,
    commandLine: data.connectorUpdated.command_line,
    isEnabled: data.connectorUpdated.is_enabled,
    usedByCommand: data.connectorUpdated.used_by_command
  });
});

When(
  'the user updates the status of a connector to {string}',
  (type: string) => {
    openConnectorsListing();
    // The activation toggle posts to ajaxConnectorToggle.php; the previous
    // enabled/disabled icons went through a full page reload.
    rowToggle(data.connectorUpdated.name).click({ force: true });
    cy.wait('@toggleConnector').then(({ response }) => {
      expect(response?.statusCode).to.equal(200);
      expect(response?.body).to.have.property('success', true);
    });
    cy.log(`connector switched to ${type}`);
  }
);

Then('the new connector is updated with {string} status', (type: string) => {
  openConnectorsListing();
  rowToggle(data.connectorUpdated.name).should(
    type === 'Enabled' ? 'be.checked' : 'not.be.checked'
  );
});

When('the user deletes a connector', () => {
  openConnectorsListing();
  selectRowAndRunBulkAction(data.connectorUpdated.name, 'Delete');
  cy.wait('@getTimeZone');
});

Then('the deleted connector is not displayed in the list', () => {
  openConnectorsListing();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.connectorUpdated.name)
    .should('not.exist');
});
