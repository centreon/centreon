import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'e2e/fixtures/shared/constants/interceptors';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  openEventLogsPageAsRestrictedUser,
  openHostFilterDropdown
} from '../common';

const serviceTemplate = 'SNMP-Linux-Load-Average';

const monitoredHosts = {
  denied: { hostName: 'host-denied', serviceName: 'service-denied' },
  granted: { hostName: 'host-granted', serviceName: 'service-granted' }
};

const baseAclProfile = 'resources/clapi/config-ACL/event-logs-acl-user.json';
const resourceAclProfile =
  'resources/clapi/config-ACL/event-logs-acl-resource.json';

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

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('monitored resources have generated events', () => {
  cy.setUserTokenApiV1();

  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: monitoredHosts.granted.hostName,
    template: 'generic-host'
  }).addService({
    activeCheckEnabled: false,
    host: monitoredHosts.granted.hostName,
    maxCheckAttempts: 1,
    name: monitoredHosts.granted.serviceName,
    template: serviceTemplate
  });

  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: monitoredHosts.denied.hostName,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: monitoredHosts.denied.hostName,
      maxCheckAttempts: 1,
      name: monitoredHosts.denied.serviceName,
      template: serviceTemplate
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([
    { name: monitoredHosts.granted.hostName },
    { name: monitoredHosts.denied.hostName }
  ]);
  checkServicesAreMonitored([
    { name: monitoredHosts.granted.serviceName },
    { name: monitoredHosts.denied.serviceName }
  ]);

  cy.submitResults([
    {
      host: monitoredHosts.granted.hostName,
      output: 'submit_status_2',
      service: monitoredHosts.granted.serviceName,
      status: 'critical'
    },
    {
      host: monitoredHosts.denied.hostName,
      output: 'submit_status_2',
      service: monitoredHosts.denied.serviceName,
      status: 'critical'
    }
  ]);

  checkServicesAreMonitored([
    { name: monitoredHosts.granted.serviceName, status: 'critical' },
    { name: monitoredHosts.denied.serviceName, status: 'critical' }
  ]);
});

Given('a restricted user is granted access to the Event Logs menu only', () => {
  cy.applyAclProfile(baseAclProfile);
});

Given('the restricted user is granted access to specific resources', () => {
  cy.applyAclProfile(resourceAclProfile);
});

When('the restricted user opens the Event Logs page', () => {
  openEventLogsPageAsRestrictedUser();
});

Then('no event is displayed to the restricted user', () => {
  openHostFilterDropdown();

  cy.getIframeBody()
    .find('.select2-results-header__nb-elements-value')
    .should('have.text', '0');
});

Then(
  'only the events of the granted resources are displayed to the restricted user',
  () => {
    openHostFilterDropdown();

    cy.getIframeBody()
      .find('ul.select2-results__options')
      .should('contain.text', monitoredHosts.granted.hostName)
      .and('not.contain.text', monitoredHosts.denied.hostName);
  }
);

afterEach(() => {
  cy.stopContainers();
});
