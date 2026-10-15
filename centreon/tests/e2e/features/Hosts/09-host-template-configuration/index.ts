import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const hostName = 'New-Host-Name';

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

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('a host inheriting from a host template', () => {
  cy.setUserTokenApiV1();
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: hostName,
    template: 'Printers'
  }).applyPollerConfiguration();
});

Then('the user configures the host', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', `input[name="searchH"]`);
  cy.getIframeBody().contains(`${hostName}`).click();
  cy.waitForElementInIframe('#main-content', `input[name="host_name"]`);
});

Then('the user can configure directly its parent template', () => {
  cy.getIframeBody()
    .find('img[title="Edit template"]')
    .then((el) => {
      // Navigate with cy.visit rather than driving win.location: Cypress then
      // owns the page load and the next wait cannot race the navigation.
      const templateId = el.siblings('select').val();
      if (
        templateId === '' ||
        templateId === undefined ||
        templateId === null
      ) {
        throw new Error('No parent template found to edit');
      }
      cy.visit(`/centreon/main.php?p=60103&o=c&min=1&host_id=${templateId}`);
    });
  cy.waitForElementInIframe('#main-content', `input[name="host_name"]`);
  cy.getIframeBody().find('input[name="host_name"]').click();
  cy.getIframeBody().find('input[name="submitC"]').first().click();
});

When('a host template inheriting from a host template', () => {
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
  cy.wait('@getTimeZone');
});

When('the user configures the host template', () => {
  cy.waitForElementInIframe('#main-content', `input[name="searchHT"]`);
  //parent host template already configured : generic-host
  cy.getIframeBody().contains('Printers').click();
  cy.waitForElementInIframe('#main-content', `input[name="host_name"]`);
});
