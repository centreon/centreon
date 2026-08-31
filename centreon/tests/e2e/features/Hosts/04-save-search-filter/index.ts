import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { listingSelectors, waitForListingRefresh } from '../common';

const searchWordOnHostTemplate = 'generic-host';
const searchWordOnTraps = 'ccm';

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
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.host_template_listing
  }).as('getHostTemplateListing');
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
  // The modernized listing searches as you type — there is no submit button to
  // click, and the term is what gets persisted, in sessionStorage.
  cy.openHostTemplatesListing();
  cy.getIframeBody()
    .find(listingSelectors.searchInput)
    .clear()
    .type(searchWordOnHostTemplate);
  // The term is only persisted from inside fetch(), behind a 300ms debounce, so
  // the next navigation must not race it.
  waitForListingRefresh('@getHostTemplateListing');
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
    cy.waitForElementInIframe('#main-content', listingSelectors.searchInput);
    cy.getIframeBody()
      .find(listingSelectors.searchInput)
      .should('have.value', searchWordOnHostTemplate);
  }
);

Given('a search on the traps listing', () => {
  cy.visit(PAGES.configuration.snmpTrapsLegacy);
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody()
    .find('input[name="searchT"]')
    .clear()
    .type(searchWordOnTraps);
  cy.getIframeBody().find('input[value="Search"]').click();
});

When('the user goes back to the traps listing', () => {
  cy.visit(PAGES.configuration.snmpTrapsLegacy);
});

Then('the search on the traps page is filled with the previous search', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody()
    .find('input[name="searchT"]')
    .should('have.value', searchWordOnTraps);
});
