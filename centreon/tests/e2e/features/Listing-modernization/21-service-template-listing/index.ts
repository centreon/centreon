import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const stPage = PAGES.configuration.servicesTemplatesLegacy;

const templateAlpha = 'st_test_alpha';
const templateBeta = 'st_test_beta';
const duplicatedTemplateAlpha = `${templateAlpha}_1`;
const templateLocked = 'st_test_locked';

// The Locked filter sits in the advanced-filters popover, which has to be
// opened before its fields are reachable. The button is a toggle and the popover
// stays open across a search, so clicking it unconditionally closes the panel
// the second time — taking the Search button out of reach with it.
const openAdvancedFilters = () =>
  cy
    .getIframeBody()
    .find('#clAdvPanel')
    .then(($panel) => {
      if ($panel.hasClass('open')) {
        return;
      }

      cy.getIframeBody()
        .find('.cl-adv-icon-btn[data-cl-adv-panel="clAdvPanel"]')
        .click();
    });

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

Given('several service templates exist', () => {
  cy.addServiceTemplate({
    name: templateAlpha,
    template: 'generic-service'
  });
  cy.addServiceTemplate({
    name: templateBeta,
    template: 'generic-service'
  });
});

// The locked flag is what the Plugin Packs set on the templates they install:
// neither the UI nor CLAPI exposes it, and no pack is installed on the test
// platform, so the rows these two scenarios assert on have to be seeded in the
// database. Kept out of the Background on purpose — the other scenarios then
// stay runnable on a platform with no database handle.
Given('a locked service template exists', () => {
  cy.addServiceTemplate({
    name: templateLocked,
    template: 'generic-service'
  });
  cy.requestOnDatabase({
    database: 'centreon',
    query: `UPDATE service SET service_locked = '1' WHERE service_description = '${templateLocked}'`
  });
});

// ---------------------------------------------------------------------------
// Scenario: Listing loads via AJAX
// ---------------------------------------------------------------------------

When('the user navigates to the service templates listing', () => {
  cy.visitListingAndWait(stPage);
});

Then('the AJAX listing table is displayed with service template rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.waitForListingToShow(templateAlpha);
});

// ---------------------------------------------------------------------------
// Scenario: Search
// ---------------------------------------------------------------------------

When('the user searches for a specific service template', () => {
  // Live search (the listing declares liveSearch): the Search button lives in
  // the advanced-filters popover and is visibility:hidden while it is closed.
  cy.getIframeBody().find('#clSearchInput').clear().type(templateAlpha);
  cy.waitForListingRefresh();
});

Then('only the matching service template is displayed', () => {
  cy.waitForListingToShow(templateAlpha);
  cy.waitForListingToDrop(templateBeta);
});

// ---------------------------------------------------------------------------
// Scenario: Icon display
// ---------------------------------------------------------------------------

Then('each row displays a service icon', () => {
  // A template with no media icon of its own renders the default pictogram as an
  // inline svg; only a template carrying one renders an <img>.
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('img[src*="service"], svg')
    .should('exist');
});

// ---------------------------------------------------------------------------
// Scenario: Template chain
// ---------------------------------------------------------------------------

Then('service template rows show the parent template chain as links', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(templateAlpha)
    .parents('tr')
    .find('a[href*="p=60206"]')
    .should('have.length.greaterThan', 0);
});

// ---------------------------------------------------------------------------
// Scenario: Scheduling intervals
// ---------------------------------------------------------------------------

Then('service template rows show scheduling intervals', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(templateAlpha)
    .parents('tr')
    .invoke('text')
    .should('match', /min|sec/);
});

// ---------------------------------------------------------------------------
// Scenario: Locked checkbox
// ---------------------------------------------------------------------------

When('the locked checkbox is checked', () => {
  openAdvancedFilters();
  cy.getIframeBody()
    .find('#displayLocked')
    .then(($cb) => {
      if (!$cb.is(':checked')) {
        cy.wrap($cb).click({ force: true });
        cy.getIframeBody().find('#clSearchBtn').click();
        cy.waitForListingRefresh();
      }
    });
});

Then('locked service templates are visible', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .its('length')
    .as('countWithLocked');
});

When('the user unchecks the locked checkbox and searches', () => {
  openAdvancedFilters();
  cy.getIframeBody().find('#displayLocked').uncheck({ force: true });
  cy.getIframeBody().find('#clSearchBtn').click();
  cy.waitForListingRefresh();
});

Then('locked service templates are hidden', () => {
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
// Scenario: No toggle
// ---------------------------------------------------------------------------

Then('no toggle switch is present in the listing', () => {
  cy.getIframeBody().find('#clTableBody .cl-toggle').should('not.exist');
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

When('the user selects a service template and duplicates it', () => {
  // One query, not a chain: the listing auto-refreshes every 30s and a
  // contains -> parents -> find chain loses its subject when the table is
  // replaced mid-way. Cypress retries a single find() atomically.
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${templateAlpha}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody()
    .find('select[name="o1"]')
    .select('Duplicate', { force: true });
});

Then('a duplicated service template appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.waitForListingToShow(duplicatedTemplateAlpha);
});

// ---------------------------------------------------------------------------
// Scenario: Bulk deletion
// ---------------------------------------------------------------------------

When('the user selects a service template and deletes it', () => {
  // One query, not a chain: the listing auto-refreshes every 30s and a
  // contains -> parents -> find chain loses its subject when the table is
  // replaced mid-way. Cypress retries a single find() atomically.
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${templateBeta}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody()
    .find('select[name="o1"]')
    .select('Delete', { force: true });
});

Then('the service template is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.waitForListingToDrop(templateBeta);
});

// ---------------------------------------------------------------------------
// Scenario: Click navigates to edit form
// ---------------------------------------------------------------------------

When('the user clicks on a service template name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', templateAlpha).click();
});

Then('the service template edit form is displayed', () => {
  cy.getListingSidePanelBody()
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible')
    .and('have.value', templateAlpha);
});

// ---------------------------------------------------------------------------
// Scenario: Session state persistence
// ---------------------------------------------------------------------------

When('the user navigates back to the service templates listing', () => {
  cy.visit(stPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', templateAlpha);
});
