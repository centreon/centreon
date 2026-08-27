import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/hosts-dependency/host-dependency.json';

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
    method: 'GET',
    url: `${INTERCEPTORS.api.centreon_topcounter}&action=servicesStatus`
  }).as('getTopCounter');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.host_dependency_listing
  }).as('getHostDependencies');
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('some hosts and services are configured', () => {
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

  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceCritical.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: 'service2',
      template: services.serviceWarning.template
    })
    .applyPollerConfiguration();
});

Given('a host dependency is configured', () => {
  cy.visit(PAGES.configuration.hostsDependenciesLegacy);
  cy.openListingAddForm();
  cy.addHostDependency({
    comment: data.default.comment,
    dependentHostNames: data.default.dependentHostNames,
    dependentServices: data.default.dependentServices,
    description: data.default.description,
    executionFailsOnDown: data.default.execution_fails_on_down,
    executionFailsOnNone: data.default.notification_fails_on_none,
    executionFailsOnOk: data.default.execution_fails_on_ok,
    executionFailsOnPending: data.default.execution_fails_on_pending,
    executionFailsOnUnreachable: data.default.execution_fails_on_unreachable,
    hostNames: data.default.hostNames,
    name: data.default.name,
    notificationFailsOnDown: data.default.notification_fails_on_down,
    notificationFailsOnNone: data.default.notification_fails_on_none,
    notificationFailsOnOk: data.default.notification_fails_on_ok,
    notificationFailsOnPending: data.default.notification_fails_on_pending,
    notificationFailsOnUnreachable:
      data.default.notification_fails_on_unreachable,
    parentRelationship: data.default.parent_relationship
  });
});

const unmatchedSearchTerm = 'no-such-dependency';

When('the user opens the host dependencies listing', () => {
  cy.visit(PAGES.configuration.hostsDependenciesLegacy);
  cy.waitForModernListing();
});

Then(
  'the AJAX listing table is displayed with the configured host dependency',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(data.default.name)
      .should('exist');
  }
);

When('the user searches for a term matching no host dependency', () => {
  // Live search (debounced AJAX) — there is no submit button, the table
  // refreshes as the user types.
  cy.getIframeBody().find('#clSearchInput').clear().type(unmatchedSearchTerm);
  cy.wait('@getHostDependencies');
});

Then('no host dependency is displayed', () => {
  cy.listingShouldBeEmpty();
});

When('the user searches for the configured host dependency', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type(data.default.name);
  cy.wait('@getHostDependencies');
});

Then('only the matching host dependency is displayed', () => {
  cy.listingShouldContainOnly(data.default.name);
});

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When('the user changes the properties of a host dependency', () => {
  cy.visit(PAGES.configuration.hostsDependenciesLegacy);
  cy.openSidePanelForm(data.default.name, 'input[name="dep_name"]');
  cy.updateHostDependency({
    comment: data.HostDependency1.comment,
    dependentHostNames: data.HostDependency1.dependentHostNames,
    dependentServices: data.HostDependency1.dependentServices,
    description: data.HostDependency1.description,
    executionFailsOnDown: data.HostDependency1.execution_fails_on_down,
    executionFailsOnNone: data.HostDependency1.execution_fails_on_none,
    executionFailsOnOk: data.HostDependency1.execution_fails_on_ok,
    executionFailsOnPending: data.HostDependency1.execution_fails_on_pending,
    executionFailsOnUnreachable:
      data.HostDependency1.execution_fails_on_unreachable,
    hostNames: data.HostDependency1.hostNames,
    name: data.HostDependency1.name,
    notificationFailsOnDown: data.HostDependency1.notification_fails_on_down,
    notificationFailsOnNone: data.HostDependency1.notification_fails_on_none,
    notificationFailsOnOk: data.HostDependency1.notification_fails_on_ok,
    notificationFailsOnPending:
      data.HostDependency1.notification_fails_on_pending,
    notificationFailsOnUnreachable:
      data.HostDependency1.notification_fails_on_unreachable,
    parentRelationship: data.HostDependency1.parent_relationship
  });
});

Then('the properties are updated', () => {
  cy.visit(PAGES.configuration.hostsDependenciesLegacy);
  cy.openSidePanelForm(data.HostDependency1.name, 'input[name="dep_name"]');
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.HostDependency1.name);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.HostDependency1.description);
  cy.getSidePanelBody().find('#eUp').should('be.checked');
  cy.getSidePanelBody().find('#nDown').should('be.checked');
  // Selections show up as select2 chips, not as selected <option> elements.
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.HostDependency1.hostNames[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.HostDependency1.hostNames[1]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.HostDependency1.dependentHostNames[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="host2 - ${data.HostDependency1.dependentServices[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.HostDependency1.comment);
});

When('the user duplicates a host dependency', () => {
  cy.visit(PAGES.configuration.hostsDependenciesLegacy);
  cy.runListingBulkAction(data.default.name, 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new host dependency has the same properties', () => {
  cy.visit(PAGES.configuration.hostsDependenciesLegacy);
  cy.openSidePanelForm(`${data.default.name}_1`, 'input[name="dep_name"]');
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.default.name}_1`);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.default.description);
  cy.getSidePanelBody().find('#eDown').should('be.checked');
  cy.getSidePanelBody().find('#nPending').should('be.checked');
  cy.getSidePanelBody()
    .find(`.select2-selection__choice[title="${data.default.hostNames[0]}"]`)
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.default.dependentHostNames[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.default.dependentServices[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.default.comment);
});

When('the user deletes a host dependency', () => {
  cy.visit(PAGES.configuration.hostsDependenciesLegacy);
  cy.runListingBulkAction(data.default.name, 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted host dependency is not displayed in the list', () => {
  // The duplicate created earlier is "<name>_1", so the deleted row has to be
  // matched on its exact name rather than as a substring.
  cy.listingRowShouldNotExist(data.default.name);
});
