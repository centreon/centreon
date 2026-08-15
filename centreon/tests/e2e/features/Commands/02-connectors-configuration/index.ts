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

/**
 * The listing is AJAX-driven: the table and its "Loading..." placeholder are
 * server-rendered, so waiting on the table alone would let assertions run
 * against an empty tbody — a negative assertion would pass on the placeholder.
 */
const openConnectorsListing = (): void => {
  cy.visit(PAGES.configuration.commandsConnectorsLegacy);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.wait('@listConnectors');
  cy.getIframeBody().find('#clTableBody td').should('not.contain', 'Loading');
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

/**
 * Run a bulk action through the control the user actually clicks: the styled
 * .cl-more-actions menu and the confirmation modal, whose wording comes from
 * the translated data-* attributes of the o1 select. Driving the hidden native
 * select instead would leave menu, modal and translations uncovered.
 */
const selectRowAndRunBulkAction = (
  name: string,
  action: 'm' | 'd',
  expectedTitle: string
): void => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', name)
    .parents('tr')
    // Scoped to the picker: the row also holds the activation toggle checkbox.
    .find('.cl-col-picker input[type="checkbox"]')
    .click({ force: true });

  cy.getIframeBody().find('.cl-more-actions-btn').click();
  cy.getIframeBody()
    .find(`.cl-more-actions-item[data-value="${action}"]`)
    .click();

  cy.getIframeBody().find('.cl-confirm-title').should('have.text', expectedTitle);
  cy.getIframeBody().find('.cl-confirm-body').should('contain.text', name);
  cy.getIframeBody().find('.cl-confirm-confirm-btn').click();
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
  openConnectorsListing();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', data.connector.name)
    .should('exist');
  // The listing truncates the command line at 70 characters while the form
  // keeps the full value; the fixture is longer than that on purpose.
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', data.connector.name)
    .parents('tr')
    .should('contain.text', `${data.connector.command_line.slice(0, 70)}...`);
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
  selectRowAndRunBulkAction(
    data.connectorUpdated.name,
    'm',
    'Duplicate connector'
  );
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
    // Set the state the example asks for rather than blindly toggling, so both
    // rows of the outline are independent of the order they run in.
    const shouldBeEnabled = type === 'Enabled';
    rowToggle(data.connectorUpdated.name).then(($toggle) => {
      if ($toggle.prop('checked') === shouldBeEnabled) {
        return;
      }
      // The activation toggle posts to ajaxConnectorToggle.php; the previous
      // enabled/disabled icons went through a full page reload.
      cy.wrap($toggle).click({ force: true });
      cy.wait('@toggleConnector').then(({ response }) => {
        expect(response?.statusCode).to.equal(200);
        expect(response?.body).to.have.property('success', true);
      });
    });
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
  selectRowAndRunBulkAction(data.connectorUpdated.name, 'd', 'Delete connector');
});

Then('the deleted connector is not displayed in the list', () => {
  openConnectorsListing();
  // Anchored: contains() is substring-based and would still match the
  // '<name>_1' left behind by the duplication scenario.
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', new RegExp(`^${data.connectorUpdated.name}$`))
    .should('not.exist');
});
