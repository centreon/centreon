import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

// Engine config page is p=60903
const enginePage = '/centreon/main.php?p=60903';

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
  cy.visit(enginePage);
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

When('the user navigates to the engine config listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with engine config rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length.greaterThan', 0);
});

Then('the Central engine config is visible', () => {
  cy.getIframeBody().find('#clTableBody').contains('Centreon Engine').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific engine config', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('Centreon Engine');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching engine config is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('Centreon Engine').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length', 1);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable an engine config', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxNagiosToggle.php'
  }).as('toggleEngine');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Centreon Engine')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@toggleEngine');
});

Then('the engine config toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Centreon Engine')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the toggle response is successful', () => {
  cy.get('@toggleEngine')
    .its('response.statusCode')
    .should('eq', 200);
  cy.get('@toggleEngine')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the engine config is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query: "UPDATE cfg_nagios SET nagios_activate = '0' WHERE nagios_name LIKE '%Centreon Engine%'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the engine config', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxNagiosToggle.php'
  }).as('toggleEngineOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Centreon Engine')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleEngineOn')
    .its('response.statusCode')
    .should('eq', 200);
});

Then('the engine config toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Centreon Engine')
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

When('the user selects an engine config and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Centreon Engine')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated engine config appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('Centreon Engine_1').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on an engine config name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'Centreon Engine').click();
});

Then('the engine config edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="nagios_name"]');
  cy.getIframeBody().find('input[name="nagios_name"]').should('contain.value', 'Centreon Engine');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the engine config listing', () => {
  cy.visit(enginePage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'Centreon Engine');
});
