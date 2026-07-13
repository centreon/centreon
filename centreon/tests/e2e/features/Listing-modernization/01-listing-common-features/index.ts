import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const serviceTemplatesPage = PAGES.configuration.servicesTemplatesLegacy;

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
// Background
// ---------------------------------------------------------------------------

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('some service templates exist', () => {
  // The default Centreon install includes many service templates (generic-active-service, etc.)
  // Add a custom one for targeted testing
  cy.addServiceTemplate({
    name: 'test_listing_template',
    template: 'generic-active-service'
  });
  cy.addServiceTemplate({
    name: 'test_listing_other',
    template: 'generic-active-service'
  });
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function visitListingAndWait(): void {
  cy.visit(serviceTemplatesPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  // Wait for AJAX data to load (tbody should have real rows, not just loading)
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
}

function waitForAjaxRefresh(): void {
  // Wait for table body to be populated after an AJAX call
  cy.getIframeBody()
    .find('#clTableBody tr td')
    .should('not.contain', 'Loading');
}

// ---------------------------------------------------------------------------
// Scenario: AJAX listing loads data without page reload
// ---------------------------------------------------------------------------

When('the user navigates to the service templates listing', () => {
  visitListingAndWait();
});

Then('the listing table is rendered via AJAX', () => {
  // The modern listing uses cl-listing-table class and #clTableBody
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody').should('exist');
});

Then('the table contains service template rows', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
  // Verify our test template is visible
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_listing_template')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search filters listing results
// ---------------------------------------------------------------------------

When('the user types a search term in the search field', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .clear()
    .type('test_listing_template');
});

When('the user clicks the search button', () => {
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only matching service templates are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_listing_template')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_listing_other')
    .should('not.exist');
});

Then('the pagination reflects the filtered count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .should('contain', '1-');
});

// ---------------------------------------------------------------------------
// Scenario: Pagination navigates between pages
// ---------------------------------------------------------------------------

Then('the pagination shows the total number of items', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When('the user clicks page 2', () => {
  cy.getIframeBody()
    .find('#clPaginationTop a.cl-page-num')
    .contains('2')
    .click();
  waitForAjaxRefresh();
});

Then('the listing shows the second page of results', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Then('the current page indicator shows page 2', () => {
  cy.getIframeBody()
    .find('#clPaginationTop span.cl-page-current')
    .should('contain', '2');
});

// ---------------------------------------------------------------------------
// Scenario: Rows per page selector changes limit
// ---------------------------------------------------------------------------

When('the user changes the rows per page to 10', () => {
  cy.getIframeBody()
    .find('#clPaginationTop select.cl-limit-select')
    .select('10');
  waitForAjaxRefresh();
});

Then('the listing shows at most 10 rows', () => {
  cy.getIframeBody().find('#clTableBody tr').should('have.length.at.most', 10);
});

Then('the pagination is recalculated', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /1-\d+ of \d+/);
});

// ---------------------------------------------------------------------------
// Scenario: Locked elements checkbox
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

Then('locked service templates are visible in the listing', () => {
  // With locked checked, count the total rows
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

Then('locked service templates are hidden from the listing', () => {
  // Without locked, the count should be less or equal
  cy.getIframeBody()
    .find('#clTableBody tr')
    .its('length')
    .then((countWithoutLocked) => {
      cy.get('@countWithLocked').then((countWithLocked) => {
        expect(countWithoutLocked).to.be.at.most(
          countWithLocked as unknown as number
        );
      });
    });
});

// ---------------------------------------------------------------------------
// Scenario: Locked templates have disabled checkboxes and dup inputs
// ---------------------------------------------------------------------------

Then('locked rows have disabled selection checkboxes', () => {
  // Find a row with a disabled checkbox (locked row)
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
// Scenario: Bulk duplication
// ---------------------------------------------------------------------------

When('the user selects a service template checkbox', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_listing_template')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
});

When('the user selects Duplicate from the More actions dropdown', () => {
  // Override onchange to avoid confirm dialog
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated service template appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_listing_template_1')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects Delete from the More actions dropdown', () => {
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then('the service template is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_listing_template')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a service template name', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'test_listing_template')
    .click();
});

Then('the service template edit form is displayed', () => {
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .should('have.value', 'test_listing_template');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the service templates listing', () => {
  cy.visit(serviceTemplatesPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'test_listing_template');
});

Then('the listing shows the same filtered results', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_listing_template')
    .should('exist');
});
