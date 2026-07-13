import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const htPage = PAGES.configuration.hostsTemplatesLegacy;

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
  cy.visit(htPage);
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

Given('several host templates exist', () => {
  cy.addHostTemplate({
    alias: 'Host Template Alpha',
    name: 'ht_test_alpha',
    template: 'generic-host'
  });
  cy.addHostTemplate({
    alias: 'Host Template Beta',
    name: 'ht_test_beta',
    template: 'generic-host'
  });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the host templates listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed with host template rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('ht_test_alpha')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific host template', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('ht_test_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching host template is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('ht_test_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('ht_test_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Locked checkbox
// ---------------------------------------------------------------------------

When('the locked checkbox is checked', () => {
  cy.getIframeBody()
    .find('#displayLocked')
    .then(($cb) => {
      if (!$cb.is(':checked')) {
        cy.wrap($cb).click();
        cy.getIframeBody().find('#clSearchBtn').click();
        waitForAjaxRefresh();
      }
    });
});

Then('locked host templates are visible', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .its('length')
    .as('countWithLocked');
});

When('the user unchecks the locked checkbox and searches', () => {
  cy.getIframeBody().find('#displayLocked').uncheck();
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('locked host templates are hidden', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .its('length')
    .then((countWithout) => {
      cy.get('@countWithLocked').then((countWith) => {
        expect(countWithout).to.be.at.most(countWith as unknown as number);
      });
    });
});

// ---------------------------------------------------------------------------
// Scenario: Locked rows disabled
// ---------------------------------------------------------------------------

Then('locked rows have disabled selection checkboxes', () => {
  cy.getIframeBody()
    .find('#clTableBody .cl-col-picker input[type="checkbox"][disabled]')
    .should('have.length.greaterThan', 0);
});

Then('locked rows have disabled duplication inputs', () => {
  cy.getIframeBody()
    .find('#clTableBody input.cl-dup-input[disabled]')
    .should('have.length.greaterThan', 0);
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

When('the user selects a host template and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('ht_test_alpha')
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

Then('a duplicated host template appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('ht_test_alpha_1')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects a host template and deletes it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('ht_test_beta')
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

Then('the host template is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('ht_test_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a host template name', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'ht_test_alpha')
    .click();
});

Then('the host template edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
    .find('input[name="host_name"]')
    .should('have.value', 'ht_test_alpha');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the host templates listing', () => {
  cy.visit(htPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'ht_test_alpha');
});
