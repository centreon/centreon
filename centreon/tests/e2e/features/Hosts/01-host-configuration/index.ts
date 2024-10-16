/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

const hostName = 'New-Host-Name';
const services = {
  serviceCritical: {
    host: 'host3',
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

When('a host is configured', () => {
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

When('the user changes the name of a host to "New Host Name"', () => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(services.serviceOk.host).click({
    force: true
  });
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody().find('input[name="host_name"]').clear().type(hostName);
  cy.getIframeBody().find('input[name="host_alias"]').clear().type(hostName);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then('the host name is updated to "New Host Name" on the Host Page', () => {
  cy.exportConfig();
  cy.getIframeBody().contains(hostName).should('exist');
});

When('the user duplicates a host', () => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
  cy.getIframeBody()
    .find('select')
    .eq(4)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select').eq(4).select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host is created with identical fields', () => {
  cy.getIframeBody().contains(`${services.serviceOk.host}_1`).should('exist');
});

When('the user deletes the host', () => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
  cy.getIframeBody()
    .find('select')
    .eq(4)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select').eq(4).select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the host is not visible in the host list', () => {
  cy.getIframeBody().contains(services.serviceOk.host).should('not.exist');
});
