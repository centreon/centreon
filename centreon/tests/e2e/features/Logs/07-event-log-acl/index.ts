import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  type ActionClapi,
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';

const serviceTemplate = 'SNMP-Linux-Load-Average';

const monitoredHosts = {
  denied: { hostName: 'host-denied', serviceName: 'service-denied' },
  granted: { hostName: 'host-granted', serviceName: 'service-granted' }
};

const baseAclProfile = 'resources/clapi/config-ACL/event-logs-acl-user.json';
const resourceAclProfile =
  'resources/clapi/config-ACL/event-logs-acl-resource.json';
const restrictedUserFixture = 'event-logs-restricted-user';

const applyAclProfile = (fixturePath: string): void => {
  cy.fixture(fixturePath).then((actions: Array<ActionClapi>) => {
    actions.forEach((action) => {
      cy.executeActionViaClapi({ bodyContent: action });
    });
  });
  cy.applyAcl();
};

const openEventLogsPageAsRestrictedUser = (): void => {
  cy.logout();
  cy.loginByTypeOfUser({ jsonName: restrictedUserFixture, loginViaApi: false });
  cy.visit(PAGES.monitoring.eventLogsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select#host_filter');
};

const openHostFilterDropdown = (): void => {
  cy.getIframeBody()
    .find('select#host_filter')
    .siblings('span.select2-container')
    .click();
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
  applyAclProfile(baseAclProfile);
});

Given('the restricted user is granted access to specific resources', () => {
  applyAclProfile(resourceAclProfile);
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
