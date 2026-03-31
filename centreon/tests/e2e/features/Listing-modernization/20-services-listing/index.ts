import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const svcByHostPage = PAGES.configuration.servicesByHostLegacy;
const svcByHgPage = PAGES.configuration.servicesByHostGroupsLegacy;

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

function visitAndWait(page: string): void {
  cy.visit(page);
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

Given('hosts with services exist', () => {
  cy.addHost({
    address: '127.0.0.1',
    name: 'svc_test_host',
    template: 'generic-host'
  });
  cy.addService({
    host: 'svc_test_host',
    name: 'svc_test_ping',
    template: 'generic-service'
  });
  cy.addService({
    host: 'svc_test_host',
    name: 'svc_test_cpu',
    template: 'generic-service'
  });
});

// ---------------------------------------------------------------------------
// Services by host
// ---------------------------------------------------------------------------

When('the user navigates to the services by host listing', () => {
  visitAndWait(svcByHostPage);
});

Then(
  'the AJAX listing table is displayed with service rows grouped by host',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody tr')
      .should('have.length.greaterThan', 0);
    // Host name should appear as grouping header or in the row
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('svc_test_host')
      .should('exist');
  }
);

When('the user searches for a specific service', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('svc_test_ping');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching services are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_ping')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_cpu')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a service', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceToggle.php'
  }).as('toggleSvc');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_ping')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();

  cy.wait('@toggleSvc');
});

Then('the service toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_ping')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the toggle response is successful', () => {
  cy.get('@toggleSvc').its('response.statusCode').should('eq', 200);
  cy.get('@toggleSvc')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// Toggle enables
// ---------------------------------------------------------------------------

When('the service is disabled', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query:
      "UPDATE service SET service_activate = '0' WHERE service_description = 'svc_test_ping'"
  });
  visitAndWait(svcByHostPage);
});

When('the user clicks the toggle to enable the service', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceToggle.php'
  }).as('toggleSvcOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_ping')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click();

  cy.wait('@toggleSvcOn').its('response.statusCode').should('eq', 200);
});

Then('the service toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_ping')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

// ---------------------------------------------------------------------------
// RTM badges
// ---------------------------------------------------------------------------

Then('service rows have monitoring status badges', () => {
  // Badges may show "-" if no monitoring data, but the column should exist
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('td')
    .should('have.length.greaterThan', 3);
});

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

Then('the pagination info shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

// ---------------------------------------------------------------------------
// Bulk duplication
// ---------------------------------------------------------------------------

When('the user selects a service and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_ping')
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

Then('a duplicated service appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('svc_test_ping_1')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a service name', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'svc_test_ping')
    .click();
});

Then('the service edit form is displayed', () => {
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .should('have.value', 'svc_test_ping');
});

// ---------------------------------------------------------------------------
// Services by hostgroup
// ---------------------------------------------------------------------------

When('the user navigates to the services by hostgroup listing', () => {
  visitAndWait(svcByHgPage);
});

Then('the AJAX listing table is displayed with hostgroup service rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

// ---------------------------------------------------------------------------
// Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the services by host listing', () => {
  cy.visit(svcByHostPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'svc_test_ping');
});
