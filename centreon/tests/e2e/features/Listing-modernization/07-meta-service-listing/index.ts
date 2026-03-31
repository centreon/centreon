import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const metaPage = PAGES.configuration.metaServicesLegacy;

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
  cy.visit(metaPage);
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

Given('a meta service exists', () => {
  cy.addMetaService({
    maxCheckAttempts: '3',
    name: 'meta_alpha'
  });
  cy.addMetaService({
    maxCheckAttempts: '3',
    name: 'meta_beta'
  });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the meta services listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with meta service rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific meta service', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('meta_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching meta service is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a meta service', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxMetaServiceToggle.php'
  }).as('toggleMeta');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@toggleMeta');
});

Then('the meta service toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the toggle response is successful', () => {
  cy.get('@toggleMeta').its('response.statusCode').should('eq', 200);
  cy.get('@toggleMeta')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the meta service is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query:
      "UPDATE meta_service SET meta_activate = '0' WHERE meta_name = 'meta_alpha'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the meta service', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxMetaServiceToggle.php'
  }).as('toggleMetaOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleMetaOn').its('response.statusCode').should('eq', 200);
});

Then('the meta service toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

// ---------------------------------------------------------------------------
// Scenario: Pagination
// ---------------------------------------------------------------------------

Then('the pagination info is displayed', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

// ---------------------------------------------------------------------------
// Scenario: Bulk duplication
// ---------------------------------------------------------------------------

When('the user selects a meta service and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha')
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

Then('a duplicated meta service appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_alpha_1')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects a meta service and deletes it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_beta')
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

Then('the meta service is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('meta_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a meta service name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'meta_alpha').click();
});

Then('the meta service edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="meta_name"]');
  cy.getIframeBody()
    .find('input[name="meta_name"]')
    .should('have.value', 'meta_alpha');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the meta services listing', () => {
  cy.visit(metaPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'meta_alpha');
});
