import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

// Resources page is p=60904
const resourcesPage = '/centreon/main.php?p=60904';

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
  cy.visit(resourcesPage);
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

When('the user navigates to the resources listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with resource rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Then('the USER1 resource is visible', () => {
  cy.getIframeBody().find('#clTableBody').contains('$USER1$').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific resource', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('$USER1$');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching resource is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('$USER1$').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length', 1);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a resource', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxResourceToggle.php'
  }).as('toggleRes');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('$USER1$')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@toggleRes');
});

Then('the resource toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('$USER1$')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the toggle response is successful', () => {
  cy.get('@toggleRes').its('response.statusCode').should('eq', 200);
  cy.get('@toggleRes')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the resource is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query:
      "UPDATE cfg_resource SET resource_activate = '0' WHERE resource_name = '$USER1$'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the resource', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxResourceToggle.php'
  }).as('toggleResOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('$USER1$')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleResOn').its('response.statusCode').should('eq', 200);
});

Then('the resource toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('$USER1$')
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

When('the user selects a resource and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('$USER1$')
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

Then('a duplicated resource appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  // Duplicated resource should appear
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 1);
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a resource name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', '$USER1$').click();
});

Then('the resource edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="resource_name"]');
  cy.getIframeBody()
    .find('input[name="resource_name"]')
    .should('have.value', '$USER1$');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the resources listing', () => {
  cy.visit(resourcesPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', '$USER1$');
});
