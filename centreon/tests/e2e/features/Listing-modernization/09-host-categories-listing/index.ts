import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const hcPage = PAGES.configuration.hostCategoriesLegacy;

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
  cy.visit(hcPage);
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

Given('several host categories exist', () => {
  cy.addHostCategory({
    name: 'hc_alpha',
    alias: 'Host Category Alpha'
  });
  cy.addHostCategory({
    name: 'hc_beta',
    alias: 'Host Category Beta'
  });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the host categories listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with host category rows', () => {
  cy.getIframeBody()
    .find('table.cl-listing-table')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_beta')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific host category', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .clear()
    .type('hc_alpha');
  cy.getIframeBody()
    .find('#clSearchBtn')
    .click();
  waitForAjaxRefresh();
});

Then('only the matching host category is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a host category', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxHostCategoriesToggle.php'
  }).as('toggleHc');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@toggleHc');
});

Then('the toggle switches to disabled state', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the toggle response is successful', () => {
  cy.get('@toggleHc')
    .its('response.statusCode')
    .should('eq', 200);
  cy.get('@toggleHc')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the host category is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query: "UPDATE hostcategories SET hc_activate = '0' WHERE hc_name = 'hc_alpha'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the host category', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxHostCategoriesToggle.php'
  }).as('toggleHcOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleHcOn')
    .its('response.statusCode')
    .should('eq', 200);
});

Then('the toggle switches to enabled state', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha')
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

When('the user selects a host category and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();

  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody()
    .find('select[name="o1"]')
    .select('Duplicate');
});

Then('a duplicated host category appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_alpha_1')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects a host category and deletes it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_beta')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();

  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody()
    .find('select[name="o1"]')
    .select('Delete');
});

Then('the host category is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('hc_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a host category name', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'hc_alpha')
    .click();
});

Then('the host category edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .should('have.value', 'hc_alpha');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the host categories listing', () => {
  cy.visit(hcPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'hc_alpha');
});
