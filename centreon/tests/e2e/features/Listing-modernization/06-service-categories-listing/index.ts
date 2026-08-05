import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const scPage = PAGES.configuration.servicesCategoriesLegacy;

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
  cy.visit(scPage);
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

Given('several service categories exist', () => {
  cy.addServiceCategory({
    description: 'Service Category Alpha',
    name: 'sc_alpha'
  });
  cy.addServiceCategory({
    description: 'Service Category Beta',
    name: 'sc_beta'
  });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the service categories listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with service category rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('sc_alpha').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('sc_beta').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search filters
// ---------------------------------------------------------------------------

When('the user searches for a specific service category', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('sc_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching service category is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('sc_alpha').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a service category', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceCategoriesToggle.php'
  }).as('toggleSc');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@toggleSc');
});

Then('the toggle switches to disabled state', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the AJAX toggle response is successful', () => {
  cy.get('@toggleSc').its('response.statusCode').should('eq', 200);
  cy.get('@toggleSc')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the service category is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query:
      "UPDATE service_categories SET sc_activate = '0' WHERE sc_name = 'sc_alpha'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the service category', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceCategoriesToggle.php'
  }).as('toggleScOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleScOn').its('response.statusCode').should('eq', 200);
});

Then('the toggle switches to enabled state', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_alpha')
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

When('the user changes the rows per page to 10', () => {
  cy.getIframeBody()
    .find('#clPaginationTop select.cl-limit-select')
    .select('10');
  waitForAjaxRefresh();
});

Then('at most 10 rows are displayed', () => {
  cy.getIframeBody().find('#clTableBody tr').should('have.length.at.most', 10);
});

// ---------------------------------------------------------------------------
// Scenario: Bulk duplication
// ---------------------------------------------------------------------------

When('the user selects a service category and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_alpha')
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
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated service category appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_alpha_1')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects a service category and deletes it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_beta')
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
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then('the service category is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sc_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a service category name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'sc_alpha').click();
});

Then('the service category edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="sc_name"]');
  cy.getIframeBody()
    .find('input[name="sc_name"]')
    .should('have.value', 'sc_alpha');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the service categories listing', () => {
  cy.visit(scPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'sc_alpha');
});
