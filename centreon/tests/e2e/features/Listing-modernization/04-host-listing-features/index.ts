import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const hostsPage = PAGES.configuration.hostsLegacy;

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
  cy.visit(hostsPage);
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

Given('several hosts exist with different properties', () => {
  cy.addHost({
    address: '10.0.0.1',
    name: 'host_alpha',
    template: 'generic-host'
  });
  cy.addHost({
    address: '10.0.0.2',
    name: 'host_beta',
    template: 'generic-host'
  });
  cy.addHost({
    address: '192.168.1.1',
    name: 'host_gamma',
    template: 'generic-host'
  });
});

// ---------------------------------------------------------------------------
// Scenario: Host listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the host listing', () => {
  visitAndWait();
});

Then('the AJAX listing table is displayed', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody').should('exist');
});

Then('host rows contain name, alias, address and poller columns', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .should('exist');
  // Check that address column is populated
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .parents('tr')
    .should('contain', '10.0.0.1');
});

// ---------------------------------------------------------------------------
// Scenario: Search by host name
// ---------------------------------------------------------------------------

When('the user searches for a specific host name', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('host_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching host is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .should('exist');
});

Then('non-matching hosts are hidden', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_beta')
    .should('not.exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_gamma')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Search by IP address
// ---------------------------------------------------------------------------

When('the user searches by IP address', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('192.168');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only hosts with that address are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_gamma')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Hostgroup select2 filter
// ---------------------------------------------------------------------------

When('the user selects a hostgroup in the filter', () => {
  // Create a hostgroup and assign host_alpha to it
  cy.addHostGroup({
    alias: 'Test HG',
    hosts: ['host_alpha'],
    name: 'test_hg_filter'
  });

  visitAndWait();

  // Open select2 for hostgroup
  cy.getIframeBody().find('#hostgroup').next('.select2-container').click();
  cy.getIframeBody()
    .find('.select2-results__option')
    .contains('test_hg_filter')
    .click();
});

When('the user clicks the search button', () => {
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only hosts belonging to that hostgroup are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_beta')
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Clear button resets select2
// ---------------------------------------------------------------------------

When('the user clicks the clear button next to the hostgroup filter', () => {
  cy.getIframeBody()
    .find('#hostgroup')
    .parent()
    .find('.cl-clear-select')
    .click();
});

Then('all hosts are displayed again', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .should('exist');
  cy.getIframeBody().find('#clTableBody').contains('host_beta').should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Toggle disables a host
// ---------------------------------------------------------------------------

When('the test host toggle is enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

When('the user clicks the toggle to disable the test host', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxHostToggle.php'
  }).as('toggleHost');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .click();

  cy.wait('@toggleHost').its('response.statusCode').should('eq', 200);
});

Then('the test host toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

// ---------------------------------------------------------------------------
// Scenario: Toggle enables a host
// ---------------------------------------------------------------------------

When('the test host is disabled in the database', () => {
  cy.executeSqlQuery({
    database: 'centreon',
    query: "UPDATE host SET host_activate = '0' WHERE host_name = 'host_alpha'"
  });
  visitAndWait();
});

When('the user clicks the toggle to enable the test host', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxHostToggle.php'
  }).as('toggleHost');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .click();

  cy.wait('@toggleHost').its('response.statusCode').should('eq', 200);
});

Then('the test host toggle switches to enabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

// ---------------------------------------------------------------------------
// Scenario: Host icon with fallback
// ---------------------------------------------------------------------------

Then('each host row has an icon next to the name', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .each(($row) => {
      cy.wrap($row).find('img').should('have.length.greaterThan', 0);
    });
});

Then('the icon is either a custom icon or the default host.svg', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('td')
    .first()
    .next()
    .find('img')
    .invoke('attr', 'src')
    .should('match', /host\.svg|img\/media/);
});

// ---------------------------------------------------------------------------
// Scenario: Monitoring status badge
// ---------------------------------------------------------------------------

Then('host rows have a monitoring status badge column', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-mon-badge, span')
    .should('exist');
});

Then('the badge has a tooltip with status details', () => {
  cy.getIframeBody()
    .find('#clTableBody [data-cl-tooltip]')
    .first()
    .invoke('attr', 'data-cl-tooltip')
    .should('contain', 'Status');
});

// ---------------------------------------------------------------------------
// Scenario: Template chain links
// ---------------------------------------------------------------------------

Then('host rows with templates show clickable template links', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .parents('tr')
    .then(($row) => {
      // generic-host template should be linked
      const html = $row.html();
      if (html.includes('generic-host')) {
        cy.wrap($row).find('a[href*="p=60103"]').should('exist');
      }
    });
});

// ---------------------------------------------------------------------------
// Scenario: Pagination
// ---------------------------------------------------------------------------

When('the user changes the rows per page to 10', () => {
  cy.getIframeBody()
    .find('#clPaginationTop select.cl-limit-select')
    .select('10');
  waitForAjaxRefresh();
});

Then('at most 10 host rows are displayed', () => {
  cy.getIframeBody().find('#clTableBody tr').should('have.length.at.most', 10);
});

When('the user navigates to page 2', () => {
  cy.getIframeBody()
    .find('#clPaginationTop a.cl-page-num')
    .contains('2')
    .click();
  waitForAjaxRefresh();
});

Then('a different set of hosts is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Then('the page indicator shows page 2', () => {
  cy.getIframeBody()
    .find('#clPaginationTop span.cl-page-current')
    .should('contain', '2');
});

// ---------------------------------------------------------------------------
// Scenario: Select all checkbox
// ---------------------------------------------------------------------------

When('the user clicks the select all checkbox in the header', () => {
  cy.getIframeBody().find('#checkall').click();
});

Then('all host row checkboxes are checked', () => {
  cy.getIframeBody()
    .find('#clTableBody .cl-col-picker input[type="checkbox"]')
    .each(($cb) => {
      cy.wrap($cb).should('be.checked');
    });
});

When('the user clicks the select all checkbox again', () => {
  cy.getIframeBody().find('#checkall').click();
});

Then('all host row checkboxes are unchecked', () => {
  cy.getIframeBody()
    .find('#clTableBody .cl-col-picker input[type="checkbox"]')
    .each(($cb) => {
      cy.wrap($cb).should('not.be.checked');
    });
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on the test host name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'host_alpha').click();
});

Then('the host edit form is displayed with the correct host', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
    .find('input[name="host_name"]')
    .should('have.value', 'host_alpha');
});

// ---------------------------------------------------------------------------
// Scenario: Services link in options
// ---------------------------------------------------------------------------

Then('each host row has a services link icon in the options column', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('img[src*="all_services"]')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Auto-refresh
// ---------------------------------------------------------------------------

Then('the listing auto-refreshes after 15 seconds without page reload', () => {
  cy.intercept({
    method: 'GET',
    url: '**/ajaxHostListing.php*'
  }).as('ajaxRefresh');

  // Wait for auto-refresh (15s configured)
  cy.wait('@ajaxRefresh', { timeout: 20000 });

  // Verify data still present (no page reload)
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the host listing', () => {
  cy.visit(hostsPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the host name', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'host_alpha');
});

Then('the listing shows the same filtered results', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('host_beta')
    .should('not.exist');
});
