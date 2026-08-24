import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/commands/connector.json';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/connectors-acl-user-readonly-rights.json'
  );
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
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/delete-connectors-acl-user-readonly-rights.json'
  );
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

/** contains() is substring-based: an unanchored '<name>' also matches '<name>_1'. */
const listingRowAnchor = (name: string): RegExp =>
  new RegExp(`^${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`);

/** Editing opens the form in the side panel instead of navigating. */
const openConnectorForm = (name: string): void => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', listingRowAnchor(name))
    .click();
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_name"]', { timeout: 20_000 })
    .should('be.visible');
};

const submitConnectorForm = (): void => {
  // A multi-select dropdown stays open after a pick and covers the action bar.
  cy.getConnectorSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .click({ force: true });
  // The POST navigates the nested iframe, which Cypress does not track. The panel
  // is closed from inside that frame once the listing re-renders, so the class
  // dropping is the signal that the round trip landed.
  cy.getIframeBody().find('#cfSidePanel').should('not.have.class', 'open');
};

/**
 * Driving the hidden native select instead of the .cl-more-actions menu would
 * leave the menu, the confirmation modal and their translations uncovered.
 */
const selectRowAndRunBulkAction = (
  name: string,
  action: 'm' | 'd',
  expectedTitle: string
): void => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', listingRowAnchor(name))
    .closest('tr')
    // Scoped to the picker: the row also holds the activation toggle checkbox.
    .find('.cl-col-picker input[type="checkbox"]')
    .click({ force: true });

  cy.getIframeBody().find('.cl-more-actions-btn').click();
  cy.getIframeBody()
    .find(`.cl-more-actions-item[data-value="${action}"]`)
    .click();

  cy.getIframeBody()
    .find('.cl-confirm-title')
    .should('have.text', expectedTitle);
  // The name is interpolated in bold from data-msg-*: asserting on <strong>
  // proves the translated message rendered, and not merely that the row name
  // appears somewhere in the modal.
  cy.getIframeBody().find('.cl-confirm-body strong').should('have.text', name);
  cy.getIframeBody().find('.cl-confirm-confirm-btn').click();
  cy.wait('@listConnectors');
};

const rowToggle = (name: string) =>
  cy
    .getIframeBody()
    .find('#clTableBody')
    .contains('a', listingRowAnchor(name))
    .closest('tr')
    .find('.cl-toggle input[type="checkbox"]');

const AjaxBase = '/centreon/include/configuration/configObject/connector/';

const searchListing = (term: string): void => {
  cy.getIframeBody().find('#clSearchInput').clear().type(term);
  cy.wait('@listConnectors');
};

const expectOnlyRowListed = (name: string): void => {
  cy.getIframeBody().find('#clTableBody tr').should('have.length', 1);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', listingRowAnchor(name))
    .should('exist');
};

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
    .contains('a', listingRowAnchor(data.connector.name))
    .should('exist');
  // The cell is clipped by CSS, not by the endpoint: the whole command line has
  // to be both rendered and reachable through the tooltip. The fixture is longer
  // than the visible width on purpose.
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', listingRowAnchor(data.connector.name))
    .closest('tr')
    .find('td span[title]')
    .should('have.attr', 'title', data.connector.command_line)
    .and('have.text', data.connector.command_line);
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
    const shouldBeEnabled = type === 'Enabled';
    // Assert the starting state instead of skipping when it already matches: a
    // change to the creation default would otherwise turn the outline into a
    // green no-op that toggles nothing.
    rowToggle(data.connectorUpdated.name).should(
      shouldBeEnabled ? 'not.be.checked' : 'be.checked'
    );
    rowToggle(data.connectorUpdated.name).click({ force: true });
    cy.wait('@toggleConnector').then(({ response }) => {
      expect(response?.statusCode).to.equal(200);
      expect(response?.body).to.have.property('success', true);
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
  selectRowAndRunBulkAction(
    data.connectorUpdated.name,
    'd',
    'Delete connector'
  );
});

Then('the deleted connector is not displayed in the list', () => {
  openConnectorsListing();
  // Anchored on the duplicate left behind by an earlier scenario, so the absence
  // below is proven against a populated table rather than an empty or errored one.
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', listingRowAnchor(`${data.connectorUpdated.name}_1`))
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', listingRowAnchor(data.connectorUpdated.name))
    .should('not.exist');
});

Given(
  'a connector with a distinctive description and command line exists',
  () => {
    openConnectorsListing();
    cy.getIframeBody().find('a.cl-btn-add').click();
    cy.addConnectors({
      ...data.connectorForSearch,
      commandLine: data.connectorForSearch.command_line,
      isEnabled: data.connectorForSearch.is_enabled,
      usedByCommand: data.connectorForSearch.used_by_command
    });
    submitConnectorForm();
  }
);

When(
  'the user searches the listing by name, by description and by command line',
  () => {
    openConnectorsListing();
    // More than one row to filter out, otherwise a search that does nothing at
    // all would still leave the expected connector on screen.
    cy.getIframeBody()
      .find('#clTableBody tr')
      .should('have.length.greaterThan', 1);

    searchListing(data.connectorForSearch.name);
    expectOnlyRowListed(data.connectorForSearch.name);

    searchListing(data.connectorForSearch.description);
    expectOnlyRowListed(data.connectorForSearch.name);

    searchListing('searchable-command-token');
    expectOnlyRowListed(data.connectorForSearch.name);
  }
);

Then('each search returns only that connector', () => {
  // Clearing brings the other rows back, so the filtering above was the search
  // doing its job and not the listing having lost its content.
  searchListing('connector-');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 1);
});

When('a user with read-only rights displays the connectors', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'connectors-acl-user-readonly-rights',
    loginViaApi: false
  });
  openConnectorsListing();
});

Then(
  'no write control is offered and the server refuses a forged status change',
  () => {
    cy.getIframeBody().find('a.cl-btn-add').should('not.exist');
    cy.getIframeBody().find('.cl-more-actions-btn').should('not.exist');
    cy.getIframeBody().find('.cl-dup-input').should('not.exist');
    cy.getIframeBody()
      .find('.cl-toggle input[type="checkbox"]')
      .first()
      .should('be.disabled');

    // Hiding the controls proves nothing on its own: the endpoint has to refuse
    // the same action when it is called directly, with a token it accepts.
    cy.request({
      failOnStatusCode: false,
      method: 'GET',
      url: `${AjaxBase}ajaxConnectorListing.php`
    }).then((listing) => {
      expect(listing.status).to.equal(200);
      expect(listing.body.rows).to.have.length.greaterThan(0);

      cy.request({
        body: {
          action: 'u',
          centreon_token: listing.body.centreon_token,
          id: listing.body.rows[0].id
        },
        failOnStatusCode: false,
        form: true,
        method: 'POST',
        url: `${AjaxBase}ajaxConnectorToggle.php`
      }).then((toggle) => {
        expect(toggle.status).to.equal(403);
        expect(toggle.body).to.have.property('error', 'Write access denied');
      });
    });
  }
);

When('the status change of a connector fails on the server', () => {
  openConnectorsListing();
  cy.intercept(
    { method: 'POST', url: INTERCEPTORS.ajax.connector_toggle },
    { body: { error: 'Internal error' }, statusCode: 500 }
  ).as('toggleFailure');
  rowToggle(data.connectorUpdated.name).should('be.checked').click({
    force: true
  });
  cy.wait('@toggleFailure');
});

Then(
  'the toggle returns to its previous state and an error is displayed',
  () => {
    cy.getIframeBody()
      .find('.cl-toast.error')
      .should('be.visible')
      .and('contain.text', 'Could not change status');
    rowToggle(data.connectorUpdated.name).should('be.checked');
  }
);
