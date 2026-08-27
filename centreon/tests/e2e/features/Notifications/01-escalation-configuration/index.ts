import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/notifications/escalation.json';
import metaServices from '../../../fixtures/services/meta_service.json';
import servicesData from '../../../fixtures/services/service.json';

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

const unmatchedSearchTerm = 'no-such-escalation';

const escalationProperties = (
  properties: typeof data.default | typeof data.escalation1
) => ({
  ...properties,
  contactGroups: properties.contactgroups,
  escalationPeriod: properties.escalation_period,
  firstNotification: properties.first_notification,
  hostGroupInheritanceToServices: properties.hostgroup_inheritance_to_services,
  hostGroups: properties.hostgroups,
  hostInheritanceToServices: properties.host_inheritance_to_services,
  lastNotification: properties.last_notification,
  metaServices: properties.metaservices,
  notificationInterval: properties.notification_interval,
  serviceGroups: properties.servicegroups
});

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.centreon_configuration_timeperiod}&action=list*`
  }).as('getTimePeriods');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.escalation_listing
  }).as('getEscalations');
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
  cy.visit(PAGES.configuration.metaServicesLegacy);
  cy.wait('@getTimeZone');
  cy.addMetaService({
    ...metaServices.metaService1,
    maxCheckAttempts: metaServices.metaService1.max_check_attempts
  });
  cy.addMetaService({
    ...metaServices.metaService2,
    maxCheckAttempts: metaServices.metaService2.max_check_attempts
  });
});

When('the user fills all the properties of an escalation', () => {
  cy.visit(PAGES.configuration.escalationsLegacy);
  cy.waitForModernListing();
  // The +Add button opens the form in the side panel, not in #main-content.
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.addEscalation(escalationProperties(data.default));
});

When('the user clicks on save', () => {
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the escalation is displayed on the listing', () => {
  cy.waitForModernListing();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.default.name)
    .should('exist');
});

When('the user opens the escalations listing', () => {
  cy.visit(PAGES.configuration.escalationsLegacy);
  cy.waitForModernListing();
});

Then(
  'the AJAX listing table is displayed with the configured escalation',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(data.default.name)
      .should('exist');
  }
);

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When('the user searches for a term matching no escalation', () => {
  // Live search (debounced AJAX) — there is no submit button, the table
  // refreshes as the user types.
  cy.getIframeBody().find('#clSearchInput').clear().type(unmatchedSearchTerm);
  cy.wait('@getEscalations');
});

Then('no escalation is displayed', () => {
  cy.listingShouldBeEmpty();
});

When('the user searches for the configured escalation', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type(data.default.name);
  cy.wait('@getEscalations');
});

Then('only the matching escalation is displayed', () => {
  cy.listingShouldContainOnly(data.default.name);
});

When('the user opens the escalation form and comes back to the listing', () => {
  cy.openSidePanelForm(data.default.name, 'input[name="esc_name"]');
  cy.visit(PAGES.configuration.escalationsLegacy);
  cy.waitForModernListing();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', data.default.name);
});

When('the user changes the properties of the configured escalation', () => {
  cy.visit(PAGES.configuration.escalationsLegacy);
  cy.openSidePanelForm(data.default.name, 'input[name="esc_name"]');
  cy.updateEscalation(escalationProperties(data.escalation1));
});

Then('the properties are updated', () => {
  cy.checkValuesOfEscalation(
    data.escalation1.name,
    escalationProperties(data.escalation1)
  );
});

When('the user duplicates the configured escalation', () => {
  cy.visit(PAGES.configuration.escalationsLegacy);
  cy.runListingBulkAction(data.escalation1.name, 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new escalation is created with identical properties', () => {
  cy.checkValuesOfEscalation(
    `${data.escalation1.name}_1`,
    escalationProperties(data.escalation1)
  );
});

When('the user deletes the configured escalation', () => {
  cy.visit(PAGES.configuration.escalationsLegacy);
  cy.runListingBulkAction(data.escalation1.name, 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted escalation is not displayed in the list of escalations',
  () => {
    // The duplicate created earlier is "<name>_1", so the deleted row has to be
    // matched on its exact name rather than as a substring.
    cy.listingRowShouldNotExist(data.escalation1.name);
  }
);
