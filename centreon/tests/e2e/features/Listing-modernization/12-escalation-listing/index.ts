import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const escPage = PAGES.configuration.escalationsLegacy;

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
  cy.visit(escPage);
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

Given('an escalation exists', () => {
  cy.addEscalation({
    name: 'esc_test_alpha'
  });
  cy.addEscalation({
    name: 'esc_test_beta'
  });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the escalations listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with escalation rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('esc_test_alpha').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific escalation', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('esc_test_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching escalation is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('esc_test_alpha').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('esc_test_beta').should('not.exist');
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

When('the user selects an escalation and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('esc_test_alpha')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated escalation appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('esc_test_alpha_1').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects an escalation and deletes it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('esc_test_beta')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then('the escalation is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('esc_test_beta').should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on an escalation name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'esc_test_alpha').click();
});

Then('the escalation edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="esc_name"]');
  cy.getIframeBody().find('input[name="esc_name"]').should('have.value', 'esc_test_alpha');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the escalations listing', () => {
  cy.visit(escPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'esc_test_alpha');
});
