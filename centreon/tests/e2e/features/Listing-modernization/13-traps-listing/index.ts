import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const trapsPage = PAGES.configuration.snmpTrapsLegacy;
const mnftrPage = PAGES.configuration.snmpTrapsManufacturerLegacy;
const groupsPage = PAGES.configuration.snmpTrapsGroupsLegacy;

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

Given('a trap definition exists', () => {
  cy.addTrap({
    name: 'trap_test_alpha',
    oid: '.1.3.6.1.4.1.99999.1'
  });
  cy.addTrap({
    name: 'trap_test_beta',
    oid: '.1.3.6.1.4.1.99999.2'
  });
});

Given('a manufacturer exists', () => {
  cy.addManufacturer({
    name: 'mnftr_test_alpha',
    alias: 'Test Manufacturer'
  });
  cy.addManufacturer({
    name: 'mnftr_test_beta',
    alias: 'Test Manufacturer 2'
  });
});

Given('a trap group exists', () => {
  cy.addTrapGroup({
    name: 'tg_test_alpha'
  });
  cy.addTrapGroup({
    name: 'tg_test_beta'
  });
});

// ---------------------------------------------------------------------------
// SNMP Traps listing
// ---------------------------------------------------------------------------

When('the user navigates to the traps listing', () => {
  visitAndWait(trapsPage);
});

Then('the AJAX listing table is displayed with trap rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length.greaterThan', 0);
});

When('the user searches for a specific trap', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('trap_test_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching trap is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('trap_test_alpha').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('trap_test_beta').should('not.exist');
});

When('the user selects a trap and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('trap_test_alpha')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated trap appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('trap_test_alpha_1').should('exist');
});

When('the user selects a trap and deletes it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('trap_test_beta')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then('the trap is removed from the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('trap_test_beta').should('not.exist');
});

When('the user clicks on the trap name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'trap_test_alpha').click();
});

When('the user navigates back to the traps listing', () => {
  cy.visit(trapsPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody().find('#clSearchInput').should('have.value', 'trap_test_alpha');
});

// ---------------------------------------------------------------------------
// Manufacturers listing
// ---------------------------------------------------------------------------

When('the user navigates to the manufacturers listing', () => {
  visitAndWait(mnftrPage);
});

Then('the AJAX listing table is displayed with manufacturer rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length.greaterThan', 0);
});

When('the user searches for a specific manufacturer', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('mnftr_test_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching manufacturer is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('mnftr_test_alpha').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('mnftr_test_beta').should('not.exist');
});

When('the user selects a manufacturer and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('mnftr_test_alpha')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated manufacturer appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('mnftr_test_alpha_1').should('exist');
});

// ---------------------------------------------------------------------------
// Trap groups listing
// ---------------------------------------------------------------------------

When('the user navigates to the trap groups listing', () => {
  visitAndWait(groupsPage);
});

Then('the AJAX listing table is displayed with trap group rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('#clTableBody tr').should('have.length.greaterThan', 0);
});

When('the user searches for a specific trap group', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('tg_test_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching trap group is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('tg_test_alpha').should('exist');
  cy.getIframeBody().find('#clTableBody').contains('tg_test_beta').should('not.exist');
});

When('the user selects a trap group and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('tg_test_alpha')
    .parents('tr')
    .find('.cl-col-picker input[type="checkbox"]')
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a duplicated trap group appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody().find('#clTableBody').contains('tg_test_alpha_1').should('exist');
});
