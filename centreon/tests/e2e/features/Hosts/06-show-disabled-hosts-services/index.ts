/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

const services = {
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' }
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
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('img[alt="Disabled"]').eq(1).click();
  cy.exportConfig();
});

When('the user visit the menu of services configuration', () => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
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
  cy.getIframeBody().contains(services.serviceOk.name).should('be.visible');
});
