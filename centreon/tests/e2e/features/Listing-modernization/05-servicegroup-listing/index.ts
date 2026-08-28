import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const sgPage = PAGES.configuration.servicesGroupsLegacy;

const serviceGroupAlpha = 'sg_alpha';
const serviceGroupBeta = 'sg_beta';
const duplicatedServiceGroupAlpha = `${serviceGroupAlpha}_1`;

// The real checkbox is 0x0 behind the .cl-toggle slider, so every click on it
// has to be forced.
const serviceGroupToggle = () =>
  cy
    .getIframeBody()
    .find('#clTableBody')
    .contains(serviceGroupAlpha)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]');

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
    url: '**/ajaxServiceGroupToggle.php'
  }).as('toggleSg');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('several service groups exist', () => {
  // hostsAndServices is required by cy.addServiceGroup (it maps over it); these
  // groups only need to exist for the listing, so they carry no service.
  cy.addServiceGroup({
    alias: 'Service Group Alpha',
    hostsAndServices: [],
    name: serviceGroupAlpha
  });
  cy.addServiceGroup({
    alias: 'Service Group Beta',
    hostsAndServices: [],
    name: serviceGroupBeta
  });
});

When('the user navigates to the service groups listing', () => {
  cy.visitListingAndWait(sgPage);
});

Then('the AJAX listing table is displayed with service group rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.waitForListingToShow(serviceGroupAlpha);
  cy.waitForListingToShow(serviceGroupBeta);
});

When('the user searches for a specific service group', () => {
  // Live search (debounced AJAX): this listing has no advanced-filter panel, so
  // no #clSearchBtn to submit — the table refreshes on type.
  cy.getIframeBody().find('#clSearchInput').clear().type(serviceGroupAlpha);
  cy.waitForListingRefresh();
});

Then('only the matching service group is displayed', () => {
  cy.waitForListingToShow(serviceGroupAlpha);
  cy.waitForListingToDrop(serviceGroupBeta);
});

When('the user clicks the toggle to disable a service group', () => {
  serviceGroupToggle().should('be.checked').click({ force: true });

  cy.wait('@toggleSg');
});

Then('the toggle switches to disabled state', () => {
  serviceGroupToggle().should('not.be.checked');
});

Then('the AJAX response is successful', () => {
  cy.get('@toggleSg').its('response.statusCode').should('eq', 200);
  cy.get('@toggleSg')
    .its('response.body')
    .should('have.property', 'success', true);
});

When('the service group is disabled', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `UPDATE servicegroup SET sg_activate = '0' WHERE sg_name = '${serviceGroupAlpha}'`
  });
  cy.visitListingAndWait(sgPage);
});

When('the user clicks the toggle to enable the service group', () => {
  serviceGroupToggle().should('not.be.checked').click({ force: true });

  cy.wait('@toggleSg').its('response.statusCode').should('eq', 200);
});

Then('the toggle switches to enabled state', () => {
  serviceGroupToggle().should('be.checked');
});

When('the user toggles a service group off then on', () => {
  // Two consecutive waits on the same alias consume the two requests in order,
  // and asserting the checkbox state in between replaces the arbitrary sleep:
  // the second click only happens once the first round trip is reflected.
  serviceGroupToggle().click({ force: true });
  cy.wait('@toggleSg').its('response.statusCode').should('eq', 200);
  serviceGroupToggle().should('not.be.checked');

  serviceGroupToggle().click({ force: true });
  cy.wait('@toggleSg').its('response.statusCode').should('eq', 200);
  serviceGroupToggle().should('be.checked');
});

Then('both toggle requests succeed', () => {
  cy.get('@toggleSg')
    .its('response.body')
    .should('have.property', 'success', true);
});

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

When('the user selects a service group and duplicates it', () => {
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${serviceGroupAlpha}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });

  cy.runListingBulkAction('m');
});

Then('a duplicated service group appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.waitForListingToShow(duplicatedServiceGroupAlpha);
});

When('the user selects a service group and deletes it', () => {
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${serviceGroupBeta}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });

  cy.runListingBulkAction('d');
});

Then('the service group is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.waitForListingToDrop(serviceGroupBeta);
});

When('the user clicks on a service group name', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', serviceGroupAlpha)
    .click();
});

Then('the service group edit form is displayed', () => {
  cy.getListingSidePanelBody()
    .find('input[name="sg_name"]', { timeout: 20_000 })
    .should('be.visible')
    .and('have.value', serviceGroupAlpha);
});

When('the user navigates back to the service groups listing', () => {
  cy.visit(sgPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', serviceGroupAlpha);
});
