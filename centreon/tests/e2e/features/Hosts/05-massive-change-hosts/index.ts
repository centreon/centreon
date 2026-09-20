import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const hostNames = ['host2', 'host3', 'host4'];

const checkHostsProperties = (hostName) => {
  cy.getIframeBody().contains(hostName).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
    .find('span[id="select2-host_location-container"]')
    .should('have.attr', 'title', 'Africa/Algiers');

  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_http');
  cy.getIframeBody()
    .find('input[name="host_retry_check_interval"]')
    .should('have.value', '3');
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(1).click();
  cy.wait('@getTimeZone');
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

Given('several hosts have been created with mandatory properties', () => {
  hostNames.forEach((name) => {
    cy.addHost({
      hostGroup: 'Linux-Servers',
      name,
      template: 'generic-host'
    }).applyPollerConfiguration();
  });
});

When('the user has applied "Mass Change" operation on several hosts', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  // Tick the fixture rows by name, never the header check-all: that box selects
  // every row on the page, so the mass change silently rewrote the platform's
  // own hosts (Centreon-Server included) along with the three under test.
  hostNames.forEach((name) => {
    cy.getIframeBody()
      .contains('tr', name)
      .find('div.md-checkbox.md-checkbox-inline')
      .click();
  });
  cy.getIframeBody().find('select[name="o1"]').select('Mass Change');
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('span[id="select2-host_location-container"]').click();
  cy.getIframeBody().find('div[title="Africa/Algiers"]').click();
  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .click();
  cy.getIframeBody().find('div[title="check_http"]').click();
  cy.getIframeBody().find('input[name="host_retry_check_interval"]').type('3');
  cy.getIframeBody()
    .find('input.btc.bt_success[name="submitMC"]')
    .eq(1)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('all the selected hosts are updated with the same values', () => {
  // Wait on the next host's listing link rather than a hard-coded host_id:
  // the ids depend on what the dataset already holds.
  checkHostsProperties('host2');
  cy.waitForElementInIframe('#main-content', 'a:contains("host3")');
  checkHostsProperties('host3');
  cy.waitForElementInIframe('#main-content', 'a:contains("host4")');
  checkHostsProperties('host4');
});
