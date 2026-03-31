import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const pollersPage = PAGES.configuration.pollersLegacy;

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
  cy.visit(pollersPage);
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

When('the user navigates to the pollers listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with poller rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Then('the Central poller is visible', () => {
  cy.getIframeBody().find('#clTableBody').contains('Central').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific poller', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('Central');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching poller is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('Central').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length', 1);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a poller', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServerToggle.php'
  }).as('togglePoller');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Central')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@togglePoller');
});

Then('the poller toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Central')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the toggle response is successful', () => {
  cy.get('@togglePoller').its('response.statusCode').should('eq', 200);
  cy.get('@togglePoller')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the poller is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query: "UPDATE nagios_server SET ns_activate = '0' WHERE name = 'Central'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the poller', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServerToggle.php'
  }).as('togglePollerOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Central')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@togglePollerOn').its('response.statusCode').should('eq', 200);
});

Then('the poller toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Central')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

// ---------------------------------------------------------------------------
// Scenario: LED status indicators
// ---------------------------------------------------------------------------

Then('each poller row has running and configuration status indicators', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-mon-badge')
    .should('have.length.greaterThan', 0);
});

// ---------------------------------------------------------------------------
// Scenario: Tooltips
// ---------------------------------------------------------------------------

Then('poller rows have tooltips with PID, uptime and version info', () => {
  cy.getIframeBody()
    .find('#clTableBody [data-cl-tooltip]')
    .first()
    .invoke('attr', 'data-cl-tooltip')
    .should('not.be.empty');
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
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on the Central poller name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'Central').click();
});

Then('the poller edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  cy.getIframeBody().find('input[name="name"]').should('have.value', 'Central');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the pollers listing', () => {
  cy.visit(pollersPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'Central');
});
