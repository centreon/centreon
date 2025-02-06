/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

const services = {
  serviceCritical: {
    host: 'host2',
    name: 'service3',
    template: 'SNMP-Linux-Load-Average'
  },
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
  serviceWarning: {
    host: 'host2',
    name: 'service2',
    template: 'SNMP-Linux-Memory'
  }
};
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceCritical.name,
    status: 'critical'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceWarning.name,
    status: 'warning'
  }
];

const checkServicesProperties = (name) => {
  cy.getIframeBody().contains(name).click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );

  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_http');
  cy.getIframeBody()
    .find('input[name="service_max_check_attempts"]')
    .should('have.value', '2');
  cy.getIframeBody()
    .find('input[name="service_retry_check_interval"]')
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

Given('several services have been created with mandatory properties', () => {
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
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([{ name: services.serviceOk.host }]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name }
  ]);
  cy.submitResults(resultsToSubmit);
});

When('the user has applied "Mass Change" operation on several services', () => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(10).click();
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(11).click();
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(12).click();

  cy.getIframeBody().find('select[name="o1"]').select('Mass Change');
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .click();
  cy.getIframeBody().find('div[title="check_http"]').click();
  cy.getIframeBody().find('input[name="service_max_check_attempts"]').type('2');
  cy.getIframeBody()
    .find('input[name="service_retry_check_interval"]')
    .type('3');
  cy.getIframeBody()
    .find('input.btc.bt_success[name="submitMC"]')
    .eq(1)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('all selected services are updated with the same values', () => {
  checkServicesProperties(services.serviceOk.name);
  cy.waitForElementInIframe('#main-content', 'a[href*="service_id=28"]');
  checkServicesProperties(services.serviceWarning.name);
  cy.waitForElementInIframe('#main-content', 'a[href*="service_id=29"]');
  checkServicesProperties(services.serviceCritical.name);
});
