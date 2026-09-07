import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

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
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

When('the user opens the legacy host add form', () => {
  cy.visit('/centreon/main.php?p=60101&o=a');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
});

Then('the host form renders its select2 fields', () => {
  // select2 is initialized by the shared centreon-select2.js. Its containers
  // rendering proves the legacy form JS still works after the abandoned
  // listing/form framework was removed.
  cy.getIframeBody()
    .find('.select2-container')
    .its('length')
    .should('be.greaterThan', 0);
});
