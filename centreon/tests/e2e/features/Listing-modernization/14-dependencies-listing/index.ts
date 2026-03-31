import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const hostDepPage = PAGES.configuration.hostsDependenciesLegacy;
const hgDepPage = PAGES.configuration.hostGroupsDependenciesLegacy;
const svcDepPage = PAGES.configuration.servicesDependenciesLegacy;
const sgDepPage = PAGES.configuration.serviceGroupsDependenciesLegacy;
const msDepPage = PAGES.configuration.metaServicesDependenciesLegacy;

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

Given('a host dependency exists', () => {
  cy.addHostDependency({
    name: 'dep_host_alpha',
    description: 'Test host dependency'
  });
  cy.addHostDependency({
    name: 'dep_host_beta',
    description: 'Test host dependency 2'
  });
});

Given('a hostgroup dependency exists', () => {
  cy.addHostGroupDependency({
    name: 'dep_hg_alpha',
    description: 'Test HG dependency'
  });
});

Given('a service dependency exists', () => {
  cy.addServiceDependency({
    name: 'dep_svc_alpha',
    description: 'Test service dependency'
  });
});

Given('a servicegroup dependency exists', () => {
  cy.addServiceGroupDependency({
    name: 'dep_sg_alpha',
    description: 'Test SG dependency'
  });
});

Given('a metaservice dependency exists', () => {
  cy.addMetaServiceDependency({
    name: 'dep_ms_alpha',
    description: 'Test meta dependency'
  });
});

// ---------------------------------------------------------------------------
// Host dependency
// ---------------------------------------------------------------------------

When('the user navigates to the host dependencies listing', () => {
  visitAndWait(hostDepPage);
});

Then('the AJAX listing table is displayed with dependency rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length.greaterThan', 0);
});

When('the user searches for a specific dependency', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('dep_host_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching dependency is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('dep_host_alpha').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('dep_host_beta').should('not.exist');
});

When('the user selects a dependency and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('dep_host_alpha')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated dependency appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('dep_host_alpha_1').should('exist');
});

// ---------------------------------------------------------------------------
// Hostgroup dependency
// ---------------------------------------------------------------------------

When('the user navigates to the hostgroup dependencies listing', () => {
  visitAndWait(hgDepPage);
});

// ---------------------------------------------------------------------------
// Service dependency
// ---------------------------------------------------------------------------

When('the user navigates to the service dependencies listing', () => {
  visitAndWait(svcDepPage);
});

// ---------------------------------------------------------------------------
// Servicegroup dependency
// ---------------------------------------------------------------------------

When('the user navigates to the servicegroup dependencies listing', () => {
  visitAndWait(sgDepPage);
});

// ---------------------------------------------------------------------------
// Metaservice dependency
// ---------------------------------------------------------------------------

When('the user navigates to the metaservice dependencies listing', () => {
  visitAndWait(msDepPage);
});

// ---------------------------------------------------------------------------
// Session state persistence
// ---------------------------------------------------------------------------

When('the user clicks on a dependency name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'dep_host_alpha').click();
});

When('the user navigates back to the host dependencies listing', () => {
  cy.visit(hostDepPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'dep_host_alpha');
});
