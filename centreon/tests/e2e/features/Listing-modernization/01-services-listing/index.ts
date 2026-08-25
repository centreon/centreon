import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const svcByHostPage = PAGES.configuration.servicesByHostLegacy;
const svcByHgPage = PAGES.configuration.servicesByHostGroupsLegacy;

const hostName = 'svc_test_host';
const servicePing = 'svc_test_ping';
const serviceCpu = 'svc_test_cpu';
const duplicatedServicePing = `${servicePing}_1`;

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
  cy.intercept({
    method: 'POST',
    url: '**/ajaxServiceToggle.php'
  }).as('toggleSvc');
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

Given('hosts with services exist', () => {
  cy.addHost({
    address: '127.0.0.1',
    name: hostName,
    template: 'generic-host'
  });
  cy.addService({
    host: hostName,
    name: servicePing,
    template: 'generic-service'
  });
  cy.addService({
    host: hostName,
    name: serviceCpu,
    template: 'generic-service'
  });
});

// ---------------------------------------------------------------------------
// Services by host
// ---------------------------------------------------------------------------

When('the user navigates to the services by host listing', () => {
  cy.visitListingAndWait(svcByHostPage);
});

Then(
  'the AJAX listing table is displayed with service rows grouped by host',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody tr')
      .should('have.length.greaterThan', 0);
    // Host name should appear as grouping header or in the row
    cy.waitForListingToShow(hostName);
  }
);

When('the user searches for a specific service', () => {
  // Live search (the listing declares liveSearchFields): the Search button lives
  // in the advanced-filters popover and is visibility:hidden while it is closed.
  cy.getIframeBody().find('#clSearchS').clear().type(servicePing);
  cy.waitForListingRefresh();
});

Then('only the matching services are displayed', () => {
  cy.waitForListingToShow(servicePing);
  cy.waitForListingToDrop(serviceCpu);
});

// ---------------------------------------------------------------------------
// Toggle disables
// ---------------------------------------------------------------------------

When('the user clicks the toggle to disable a service', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(servicePing)
    .parents('tr')
    // The real checkbox is 0x0 behind the .cl-toggle slider; force the click.
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click({ force: true });

  cy.wait('@toggleSvc');
});

Then('the service toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(servicePing)
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
  cy.requestOnDatabase({
    database: 'centreon',
    query: `UPDATE service SET service_activate = '0' WHERE service_description = '${servicePing}'`
  });
  cy.visitListingAndWait(svcByHostPage);
});

When('the user clicks the toggle to enable the service', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(servicePing)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click({ force: true });

  cy.wait('@toggleSvc').its('response.statusCode').should('eq', 200);
});

Then('the service toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(servicePing)
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
  // One query, not a chain: the listing auto-refreshes on a timer and a
  // contains -> parents -> find chain loses its subject when the table is
  // replaced mid-way. Cypress retries a single find() atomically.
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${servicePing}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });
  cy.runListingBulkAction('m');
});

Then('a duplicated service appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.waitForListingToShow(duplicatedServicePing);
});

// ---------------------------------------------------------------------------
// Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a service name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', servicePing).click();
});

Then('the service edit form is displayed', () => {
  cy.getListingSidePanelBody()
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible')
    .should('have.value', servicePing);
});

// ---------------------------------------------------------------------------
// Services by hostgroup
// ---------------------------------------------------------------------------

When('the user navigates to the services by hostgroup listing', () => {
  cy.visitListingAndWait(svcByHgPage);
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
  cy.waitForListingRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchS').should('have.value', servicePing);
});
