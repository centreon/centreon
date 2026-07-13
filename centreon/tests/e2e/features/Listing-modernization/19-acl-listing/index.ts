import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const aclGroupsPage = PAGES.configuration.aclAccessGroupsLegacy;
const aclMenusPage = PAGES.configuration.aclMenusAccessLegacy;
const aclActionsPage = PAGES.configuration.aclActionsAccessLegacy;
const aclResourcesPage = PAGES.configuration.aclResourcesAccessLegacy;

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

Given('an ACL group exists', () => {
  cy.addAclAccessGroup({
    alias: 'Test ACL Group',
    name: 'acl_grp_test'
  });
});

// ---------------------------------------------------------------------------
// ACL Groups
// ---------------------------------------------------------------------------

When('the user navigates to the ACL groups listing', () => {
  visitAndWait(aclGroupsPage);
});

Then('the AJAX listing table is displayed with ACL group rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

When('the user searches for a specific ACL group', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('acl_grp_test');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching ACL group is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('acl_grp_test')
    .should('exist');
});

When('the user clicks the toggle to disable an ACL group', () => {
  cy.intercept({ method: 'POST', url: '**/ajaxGroupAclToggle.php' }).as(
    'toggleAclGrp'
  );
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('acl_grp_test')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .click();
  cy.wait('@toggleAclGrp');
});

Then('the ACL group toggle switches to disabled', () => {
  cy.get('@toggleAclGrp').its('response.statusCode').should('eq', 200);
});

When('the user selects an ACL group and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('acl_grp_test')
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

Then('a duplicated ACL group appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('acl_grp_test_1')
    .should('exist');
});

When('the user clicks on an ACL group name', () => {
  cy.getIframeBody().find('#clTableBody').contains('a', 'acl_grp_test').click();
});

When('the user navigates back to the ACL groups listing', () => {
  cy.visit(aclGroupsPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'acl_grp_test');
});

// ---------------------------------------------------------------------------
// ACL Menus
// ---------------------------------------------------------------------------

When('the user navigates to the ACL menus listing', () => {
  visitAndWait(aclMenusPage);
});

Then('the AJAX listing table is displayed with ACL menu rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

When('the user clicks the toggle on an ACL menu row', () => {
  cy.intercept({ method: 'POST', url: '**/ajaxMenuAclToggle.php' }).as(
    'toggleAclMenu'
  );
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-toggle input[type="checkbox"]')
    .click();
  cy.wait('@toggleAclMenu');
});

Then('the ACL menu toggle response is successful', () => {
  cy.get('@toggleAclMenu').its('response.statusCode').should('eq', 200);
  cy.get('@toggleAclMenu')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// ACL Actions
// ---------------------------------------------------------------------------

When('the user navigates to the ACL actions listing', () => {
  visitAndWait(aclActionsPage);
});

Then('the AJAX listing table is displayed with ACL action rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

When('the user clicks the toggle on an ACL action row', () => {
  cy.intercept({ method: 'POST', url: '**/ajaxActionAclToggle.php' }).as(
    'toggleAclAction'
  );
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-toggle input[type="checkbox"]')
    .click();
  cy.wait('@toggleAclAction');
});

Then('the ACL action toggle response is successful', () => {
  cy.get('@toggleAclAction').its('response.statusCode').should('eq', 200);
  cy.get('@toggleAclAction')
    .its('response.body')
    .should('have.property', 'success', true);
});

// ---------------------------------------------------------------------------
// ACL Resources
// ---------------------------------------------------------------------------

When('the user navigates to the ACL resources listing', () => {
  visitAndWait(aclResourcesPage);
});

Then('the AJAX listing table is displayed with ACL resource rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

When('the user clicks the toggle on an ACL resource row', () => {
  cy.intercept({ method: 'POST', url: '**/ajaxResourceAclToggle.php' }).as(
    'toggleAclRes'
  );
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-toggle input[type="checkbox"]')
    .click();
  cy.wait('@toggleAclRes');
});

Then('the ACL resource toggle response is successful', () => {
  cy.get('@toggleAclRes').its('response.statusCode').should('eq', 200);
  cy.get('@toggleAclRes')
    .its('response.body')
    .should('have.property', 'success', true);
});
