/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

const searchWordOnHostTemplate = 'generic-host';
const searchWordOnTraps = 'ccm';

const navigateToSpecificPage = (subMenu, index, page) => {
  cy.navigateTo({
    page,
    rootItemNumber: index,
    subMenu
  });
};

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
  navigateToSpecificPage('Hosts', 3, 'Templates');
  cy.waitForElementInIframe('#main-content', 'input[name="searchHT"]');
  cy.getIframeBody()
    .find('input[name="searchHT"]')
    .clear()
    .type(searchWordOnHostTemplate);
  cy.getIframeBody().find('input[value="Search"]').click();
});

When('the user changes page', () => {
  navigateToSpecificPage('Hosts', 3, 'Categories');
});

When('the user goes back to the host template listing', () => {
  navigateToSpecificPage('Hosts', 3, 'Templates');
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
  navigateToSpecificPage('SNMP Traps', 3, 'SNMP Traps');
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody()
    .find('input[name="searchT"]')
    .clear()
    .type(searchWordOnTraps);
  cy.getIframeBody().find('input[value="Search"]').click();
});

When('the user goes back to the traps listing', () => {
  navigateToSpecificPage('SNMP Traps', 3, 'SNMP Traps');
});

Then('the search on the traps page is filled with the previous search', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody()
    .find('input[name="searchT"]')
    .should('have.value', searchWordOnTraps);
});
