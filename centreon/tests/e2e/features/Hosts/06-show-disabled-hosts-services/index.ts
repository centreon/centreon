import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'commons';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { getListingRow, listingSelectors } from '../common';

const services = {
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' }
};

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
    method: 'POST',
    url: INTERCEPTORS.ajax.host_toggle
  }).as('toggleHost');
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

Given('a host with configured services', () => {
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .applyPollerConfiguration();
  checkHostsAreMonitored([{ name: services.serviceOk.host }]);
  checkServicesAreMonitored([{ name: services.serviceOk.name }]);
});

Given('the host is disabled', () => {
  cy.openHostsListing();
  // The modernized listing disables a host through its row toggle; the real
  // checkbox is 0x0 behind the slider, hence the forced click.
  getListingRow(services.serviceOk.host)
    .find(listingSelectors.rowToggle)
    .should('be.checked')
    .click({ force: true });
  cy.wait('@toggleHost').its('response.statusCode').should('eq', 200);
  cy.exportConfig();
});

When('the user visit the menu of services configuration', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getTimeZone');
});

Then('the services of disabled hosts are not displayed', () => {
  cy.getIframeBody().contains(services.serviceOk.name).should('not.exist');
});

When('the user activates the visibility filter of disabled hosts', () => {
  cy.reload();
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('label[for="statusHostFilter"]')
    .click({ force: true });
});

When('the user clicks on the Search button', () => {
  cy.getIframeBody().find('input[type="submit"][value="Search"]').click();
  cy.wait('@getTimeZone');
});

Then('the services of disabled hosts are displayed', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${services.serviceOk.name}")`
  );
  cy.getIframeBody().contains(services.serviceOk.name).should('be.visible');
});
