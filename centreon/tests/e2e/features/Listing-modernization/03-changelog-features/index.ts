import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const changelogPage = PAGES.configuration.logsLegacy;

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
  cy.visit(changelogPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
}

// ---------------------------------------------------------------------------
// Background
// ---------------------------------------------------------------------------

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

// ---------------------------------------------------------------------------
// Scenario: Changelog loads with infinite scroll
// ---------------------------------------------------------------------------

When('the user navigates to the changelog page', () => {
  visitAndWait();
});

Then('the changelog listing is displayed', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Then('no pagination controls are shown', () => {
  // Infinite scroll mode: no page number links
  cy.getIframeBody().find('#clPaginationTop a.cl-page-num').should('not.exist');
});

Then('a scroll info counter is displayed', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /\d+ \/ \d+/);
});

// ---------------------------------------------------------------------------
// Scenario: Search filters by object name
// ---------------------------------------------------------------------------

When('the user searches for an object name in the changelog', () => {
  // First, create a known object to search for
  cy.addHost({
    address: '127.0.0.1',
    name: 'changelog_test_host',
    template: 'generic-host'
  });

  // Revisit changelog
  visitAndWait();

  cy.getIframeBody().find('#clSearchInput').clear().type('changelog_test_host');
  cy.getIframeBody().find('#clSearchBtn').click();

  // Wait for reload
  cy.getIframeBody()
    .find('#clTableBody tr td')
    .should('not.contain', 'Loading');
});

Then('only matching changelog entries are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .each(($row) => {
      cy.wrap($row).should('contain', 'changelog_test_host');
    });
});

// ---------------------------------------------------------------------------
// Scenario: Object type filter
// ---------------------------------------------------------------------------

When('the user selects an object type filter', () => {
  cy.getIframeBody().find('#clSearchType').select('host');

  cy.getIframeBody()
    .find('#clTableBody tr td')
    .should('not.contain', 'Loading');
});

Then('only entries of that type are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .each(($row) => {
      cy.wrap($row).find('td').eq(3).should('contain', 'host');
    });
});

// ---------------------------------------------------------------------------
// Scenario: Inline diff expand
// ---------------------------------------------------------------------------

When('an Added or Changed entry exists', () => {
  // The host creation above should have produced an "Added" entry
  cy.getIframeBody().find('#clTableBody').contains('Added').should('exist');
});

When('the user clicks the expand button on that entry', () => {
  cy.intercept({
    method: 'GET',
    url: '**/ajaxChangelogDetail.php*'
  }).as('detailRequest');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Added')
    .parents('tr')
    .find('.cl-expand-btn:not(.disabled)')
    .first()
    .click();

  cy.wait('@detailRequest').its('response.statusCode').should('eq', 200);
});

Then('a diff panel appears below the row', () => {
  cy.getIframeBody().find('.cl-detail-row').should('exist');
  cy.getIframeBody().find('.cl-diff-container').should('exist');
});

Then('the diff shows field names and values', () => {
  cy.getIframeBody()
    .find('.cl-diff-container .cl-diff-row')
    .should('have.length.greaterThan', 0);
  cy.getIframeBody()
    .find('.cl-diff-container .cl-diff-field')
    .first()
    .invoke('text')
    .should('not.be.empty');
});

// ---------------------------------------------------------------------------
// Scenario: Collapse diff
// ---------------------------------------------------------------------------

When('the user expands a changelog entry', () => {
  cy.getIframeBody()
    .find('#clTableBody .cl-expand-btn:not(.disabled)')
    .first()
    .click();
  cy.getIframeBody().find('.cl-detail-row').should('exist');
});

When('the user clicks the expand button again', () => {
  cy.getIframeBody().find('#clTableBody .cl-expand-btn.open').first().click();
});

Then('the diff panel is removed', () => {
  cy.getIframeBody().find('.cl-detail-row').should('not.exist');
});

// ---------------------------------------------------------------------------
// Scenario: Disabled entries cannot be expanded
// ---------------------------------------------------------------------------

When('an Enabled or Disabled entry exists', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .then(($body) => {
      const hasEnabled =
        $body.text().includes('Enabled') || $body.text().includes('Disabled');
      expect(hasEnabled).to.be.true;
    });
});

Then('its expand button is grayed out and not clickable', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Enabled')
    .parents('tr')
    .find('.cl-expand-btn.disabled')
    .should('exist')
    .and('have.css', 'pointer-events', 'none');
});

// ---------------------------------------------------------------------------
// Scenario: Timeline detail page
// ---------------------------------------------------------------------------

When('the user clicks on an object name link', () => {
  cy.getIframeBody().find('#clTableBody td a').first().click();
});

Then('the timeline detail page is displayed', () => {
  cy.waitForElementInIframe('#main-content', '.cld-wrapper');
  cy.getIframeBody().find('.cld-timeline').should('exist');
  cy.getIframeBody().find('.cld-entry').should('have.length.greaterThan', 0);
});

Then('a back button returns to the changelog listing', () => {
  cy.getIframeBody().find('.cld-back-btn').click();
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});
