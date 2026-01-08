/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

const searchWordOnHostTemplate = 'generic-host';
const searchWordOnTraps = 'ccm';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
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
