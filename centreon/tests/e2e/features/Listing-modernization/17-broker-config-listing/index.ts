import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

// Broker config page is p=60909
const brokerPage = '/centreon/main.php?p=60909';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getUserTimezone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function visitAndWait(): void {
  cy.visit(brokerPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
}

function waitForAjaxRefresh(): void {
  cy.getIframeBody()
    .find('#clTableBody tr td')
    .should('not.contain', 'Loading');
}

// ---------------------------------------------------------------------------
// Background
// ---------------------------------------------------------------------------

Given('an admin user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the broker config listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with broker config rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length.greaterThan', 0);
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific broker config', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('central-broker');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching broker config is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('central-broker').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a broker config', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxBrokerToggle.php'
  }).as('toggleBroker');

  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-toggle input[type="checkbox"]')
    .then($toggle => {
      if ($toggle.is(':checked')) {
        cy.wrap($toggle).click();
      }
    });

  cy.wait('@toggleBroker');
});

Then('the broker config toggle switches to disabled', () => {
  cy.get('@toggleBroker')
    .its('response.statusCode')
    .should('eq', 200);
});

Then('the toggle response is successful', () => {
  cy.get('@toggleBroker')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the broker config is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query: "UPDATE cfg_centreonbroker SET config_activate = '0' WHERE config_name LIKE '%central-broker%'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the broker config', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxBrokerToggle.php'
  }).as('toggleBrokerOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('central-broker')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleBrokerOn')
    .its('response.statusCode')
    .should('eq', 200);
});

Then('the broker config toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('central-broker')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

// ---------------------------------------------------------------------------
// Scenario: Pagination
// ---------------------------------------------------------------------------

Then('the pagination info shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

// ---------------------------------------------------------------------------
// Scenario: Bulk duplication
// ---------------------------------------------------------------------------

When('the user selects a broker config and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated broker config appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  // Duplicated config gets _1 suffix
  cy.getIframeBody().find('#clTableBody tr').should('have.length.greaterThan', 3);
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a broker config name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'central-broker').click();
});

Then('the broker config edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  cy.getIframeBody().find('input[name="name"]').should('contain.value', 'central-broker');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the broker config listing', () => {
  cy.visit(brokerPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'central-broker');
});
