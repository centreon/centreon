/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

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

Given('several hosts have been created with mandatory properties', () => {
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: 'host2',
    template: 'generic-host'
  }).applyPollerConfiguration();
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: 'host3',
    template: 'generic-host'
  }).applyPollerConfiguration();
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: 'host4',
    template: 'generic-host'
  }).applyPollerConfiguration();
});

When('the user has applied "Mass Change" operation on several hosts', () => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(0).click();
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
  checkHostsProperties('host2');
  cy.waitForElementInIframe('#main-content', 'a[href*="host_id=16"]');
  checkHostsProperties('host3');
  cy.waitForElementInIframe('#main-content', 'a[href*="host_id=17"]');
  checkHostsProperties('host4');
});
