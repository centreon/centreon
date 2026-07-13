import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const contactsPage = PAGES.configuration.contactsUsersLegacy;
const contactTemplatesPage = PAGES.configuration.contactTemplatesLegacy;
const contactGroupsPage = PAGES.configuration.contactGroupsLegacy;

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

Given('test contacts and groups exist', () => {
  cy.addContact({
    alias: 'Alpha Contact',
    email: 'alpha@test.com',
    name: 'test_contact_alpha',
    password: 'Centreon!2021'
  });
  cy.addContact({
    alias: 'Beta Contact',
    email: 'beta@test.com',
    name: 'test_contact_beta',
    password: 'Centreon!2021'
  });
});

// ---------------------------------------------------------------------------
// Contacts listing
// ---------------------------------------------------------------------------

When('the user navigates to the contacts listing', () => {
  visitAndWait(contactsPage);
});

Then('the AJAX listing table is displayed with contact rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha')
    .should('exist');
});

When('the user searches for a specific contact', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('test_contact_alpha');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching contact is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_beta')
    .should('not.exist');
});

When('the user clicks the toggle to disable a contact', () => {
  cy.intercept({ method: 'POST', url: '**/ajaxContactToggle.php' }).as(
    'toggleContact'
  );
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click();
  cy.wait('@toggleContact');
});

Then('the contact toggle switches to disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

Then('the toggle response is successful', () => {
  cy.get('@toggleContact').its('response.statusCode').should('eq', 200);
  cy.get('@toggleContact')
    .its('response.body')
    .should('have.property', 'success', true);
});

Then('the admin user toggle is disabled and not clickable', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('admin')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.disabled');
});

When('the user selects a contact and duplicates it', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha')
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

Then('a duplicated contact appears in the listing', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha_1')
    .should('exist');
});

When('the user clicks on the contact name', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'test_contact_alpha')
    .click();
});

When('the user navigates back to the contacts listing', () => {
  cy.visit(contactsPage);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForAjaxRefresh();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'test_contact_alpha');
});

// ---------------------------------------------------------------------------
// Contact templates listing
// ---------------------------------------------------------------------------

When('the user navigates to the contact templates listing', () => {
  visitAndWait(contactTemplatesPage);
});

Then('the AJAX listing table is displayed with contact template rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

When('the user searches for a specific contact template', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('contact_template');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching contact template is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .each(($row) => {
      cy.wrap($row)
        .invoke('text')
        .should('match', /contact_template/i);
    });
});

When('the user clicks the toggle to disable a contact template', () => {
  cy.intercept({ method: 'POST', url: '**/ajaxContactTemplateToggle.php' }).as(
    'toggleCt'
  );
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .find('.cl-toggle input[type="checkbox"]')
    .then(($toggle) => {
      if ($toggle.is(':checked')) {
        cy.wrap($toggle).click();
      }
    });
  cy.wait('@toggleCt');
});

Then('the contact template toggle switches to disabled', () => {
  cy.get('@toggleCt').its('response.statusCode').should('eq', 200);
});

// ---------------------------------------------------------------------------
// Contact groups listing
// ---------------------------------------------------------------------------

When('the user navigates to the contact groups listing', () => {
  visitAndWait(contactGroupsPage);
});

Then('the AJAX listing table is displayed with contact group rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

When('the user searches for a specific contact group', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type('Guest');
  cy.getIframeBody().find('#clSearchBtn').click();
  waitForAjaxRefresh();
});

Then('only the matching contact group is displayed', () => {
  cy.getIframeBody().find('#clTableBody').contains('Guest').should('exist');
});

When('the user clicks the toggle to disable a contact group', () => {
  cy.intercept({ method: 'POST', url: '**/ajaxContactGroupToggle.php' }).as(
    'toggleCg'
  );
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('Guest')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .click();
  cy.wait('@toggleCg');
});

Then('the contact group toggle switches to disabled', () => {
  cy.get('@toggleCg').its('response.statusCode').should('eq', 200);
});
