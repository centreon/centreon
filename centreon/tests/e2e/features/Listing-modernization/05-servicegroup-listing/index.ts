import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const sgPage = PAGES.configuration.servicesGroupsLegacy;

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

Given('an admin user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('several service groups exist', () => {
  cy.addServiceGroup({
    alias: 'Service Group Alpha',
    name: 'sg_alpha'
  });
  cy.addServiceGroup({
    alias: 'Service Group Beta',
    name: 'sg_beta'
  });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the service groups listing', () => {
  cy.visitListingAndWait(sgPage);
});

Then('the AJAX listing table is displayed with service group rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('sg_alpha').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('sg_beta').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search filters
// ---------------------------------------------------------------------------

When('the user searches for a specific service group', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('sg_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  cy.waitForListingRefresh();
});

Then('only the matching service group is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('sg_alpha').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a service group', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceGroupToggle.php'
  }).as('toggleSg');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@toggleSg');
});

Then('the toggle switches to disabled state', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the AJAX response is successful', () => {
  cy.get('@toggleSg').its('response.statusCode').should('eq', 200);
  cy.get('@toggleSg')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables
// ---------------------------------------------------------------------------

When('the service group is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query:
      "UPDATE servicegroup SET sg_activate = '0' WHERE sg_name = 'sg_alpha'"
  });
  cy.visitListingAndWait(sgPage);
});

When('the user clicks the toggle to enable the service group', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceGroupToggle.php'
  }).as('toggleSgOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleSgOn').its('response.statusCode').should('eq', 200);
});

Then('the toggle switches to enabled state', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

// ---------------------------------------------------------------------------
// Scenario: CSRF rotation — two consecutive toggles
// ---------------------------------------------------------------------------

When('the user toggles a service group off then on', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceGroupToggle.php'
  }).as('toggle1');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .click();

  cy.wait('@toggle1').its('response.statusCode').should('eq', 200);

  cy.wait(500);

  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceGroupToggle.php'
  }).as('toggle2');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .click();

  cy.wait('@toggle2');
});

Then('both toggle requests succeed', () => {
  cy.get('@toggle2').its('response.statusCode').should('eq', 200);
  cy.get('@toggle2')
    .its('response.body')
    .should('have.property', 'success', true);
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
  cy.waitForListingRefresh();
});

Then('at most 10 rows are displayed', () => {
  cy.getIframeBody().find('#clTableBody tr').should('have.length.at.most', 10);
});

// ---------------------------------------------------------------------------
// Scenario: Bulk duplication
// ---------------------------------------------------------------------------

When('the user selects a service group and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha')
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

Then('a duplicated service group appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_alpha_1')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects a service group and deletes it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_beta')
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

Then('the service group is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('sg_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a service group name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'sg_alpha').click();
});

Then('the service group edit form is displayed', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="sg_name"]');
  cy.getIframeBody()
    .find('input[name="sg_name"]')
    .should('have.value', 'sg_alpha');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the service groups listing', () => {
  cy.visit(sgPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'sg_alpha');
});
