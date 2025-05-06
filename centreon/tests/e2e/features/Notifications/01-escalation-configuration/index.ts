/* eslint-disable no-script-url */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import metaServices from '../../../fixtures/services/meta_service.json';
import servicesData from '../../../fixtures/services/service.json';
import data from '../../../fixtures/notifications/escalation.json';

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

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod&action=list*'
  }).as('getTimePeriods');
});

after(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('some service groups are configured', () => {
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
    .addServiceGroup({
      hostsAndServices: [[services.serviceOk.host, services.serviceOk.name]],
      name: servicesData.service_group.service2.name
    })
    .applyPollerConfiguration();

  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceCritical.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addServiceGroup({
      hostsAndServices: [
        [services.serviceCritical.host, services.serviceWarning.name]
      ],
      name: servicesData.service_group.service1.name
    })
    .applyPollerConfiguration();
});

Given('some meta services are configured', () => {
  cy.navigateTo({
    page: 'Meta Services',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
  cy.addMetaService(metaServices.metaService1);
  cy.addMetaService(metaServices.metaService2);
});

When('the user fills all the properties of an escalation', () => {
  cy.visit('/centreon/main.php?p=60401');
  cy.waitForElementInIframe('#main-content', 'input[name="searchE"]');
  cy.getIframeBody().contains('a', 'Add').eq(0).click();
  cy.addEscalation(data.default);
});

When('the user clicks on save', () => {
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the escalation is displayed on the listing', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.name}")`
  );
  cy.getIframeBody().contains(data.default.name).should('exist');
});

When('the user changes the properties of the configured escalation', () => {
  cy.visit('/centreon/main.php?p=60401');
  cy.getIframeBody().contains(data.default.name).click();
  cy.updateEscalation(data.escalation1);
});

Then('the properties are updated', () => {
  cy.checkValuesOfEscalation(data.escalation1.name, data.escalation1);
});

When('the user duplicates the configured escalation', () => {
  cy.visit('/centreon/main.php?p=60401');
  cy.checkFirstRowFromListing('searchE');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new escalation is created with identical properties', () => {
  cy.checkValuesOfEscalation(`${data.escalation1.name}_1`, data.escalation1);
});

When('the user deletes the configured escalation', () => {
  cy.visit('/centreon/main.php?p=60401');
  cy.checkFirstRowFromListing('searchE');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted escalation is not displayed in the list of escalations',
  () => {
    cy.getIframeBody().find('a[href*="esc_id=1"]').should('not.exist');
  }
);
