import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const searchWordOnHostTemplate = 'generic-host';
const searchWordOnTraps = 'ccm';

// The SNMP traps listing runs on the modernized listing framework: a live search
// field (no Search button) whose term is persisted client-side by listing.js and
// restored on the next visit. The host template listing is still the legacy one.
const trapsSearchInput = '#clSearchInput';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  // Typing in the field triggers a debounced GET carrying the term, and that
  // request is what writes the search into the persisted listing state - so it
  // is the signal to wait on before navigating away.
  cy.intercept({
    method: 'GET',
    query: { search: searchWordOnTraps },
    url: INTERCEPTORS.ajax.traps_listing
  }).as('searchTrapsListing');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('a search on the host template listing', () => {
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
  cy.waitForElementInIframe('#main-content', 'input[name="searchHT"]');
  cy.getIframeBody()
    .find('input[name="searchHT"]')
    .clear()
    .type(searchWordOnHostTemplate);
  cy.getIframeBody().find('input[value="Search"]').click();
});

When('the user changes page', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
});

When('the user goes back to the host template listing', () => {
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
});

Then(
  'the search on the host template page is filled with the previous search',
  () => {
    cy.waitForElementInIframe('#main-content', 'input[name="searchHT"]');
    cy.getIframeBody()
      .find('input[name="searchHT"]')
      .should('have.value', searchWordOnHostTemplate);
  }
);

Given('a search on the traps listing', () => {
  cy.visit(PAGES.configuration.snmpTrapsLegacy);
  cy.waitForElementInIframe('#main-content', trapsSearchInput);
  cy.getIframeBody().find(trapsSearchInput).clear().type(searchWordOnTraps);
  cy.wait('@searchTrapsListing');
});

When('the user goes back to the traps listing', () => {
  cy.visit(PAGES.configuration.snmpTrapsLegacy);
});

Then('the search on the traps page is filled with the previous search', () => {
  cy.waitForElementInIframe('#main-content', trapsSearchInput);
  cy.getIframeBody()
    .find(trapsSearchInput)
    .should('have.value', searchWordOnTraps);
});
