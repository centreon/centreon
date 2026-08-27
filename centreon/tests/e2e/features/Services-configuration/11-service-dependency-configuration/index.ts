import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/services/dependency.json';
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

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.centreon_topcounter}&action=servicesStatus`
  }).as('getTopCounter');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.service_dependency_listing
  }).as('getServiceDependencies');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.servicegroup_dependency_listing
  }).as('getServiceGroupDependencies');
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

Given('some hosts and services and service groups are configured', () => {
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

Given('a service dependency is configured', () => {
  cy.visit(PAGES.configuration.servicesDependenciesLegacy);
  cy.openListingAddForm();
  cy.addServiceDependency({
    dependency: {
      comment: data.default.dependency.comment,
      description: data.default.dependency.description,
      executionFailsOnCritical:
        data.default.dependency.execution_fails_on_critical,
      executionFailsOnNone: data.default.dependency.execution_fails_on_none,
      executionFailsOnOk: data.default.dependency.execution_fails_on_ok,
      executionFailsOnPending:
        data.default.dependency.execution_fails_on_pending,
      executionFailsOnUnknown:
        data.default.dependency.execution_fails_on_unknown,
      executionFailsOnWarning:
        data.default.dependency.execution_fails_on_warning,
      name: data.default.dependency.name,
      notificationFailsOnCritical:
        data.default.dependency.notification_fails_on_critical,
      notificationFailsOnNone:
        data.default.dependency.notification_fails_on_none,
      notificationFailsOnOk: data.default.dependency.notification_fails_on_ok,
      notificationFailsOnPending:
        data.default.dependency.notification_fails_on_pending,
      notificationFailsOnUnknown:
        data.default.dependency.notification_fails_on_unknown,
      notificationFailsOnWarning:
        data.default.dependency.notification_fails_on_warning,
      parentRelationship: data.default.dependency.parent_relationship
    },
    dependentHosts: data.default.dependentHosts,
    dependentServices: data.default.dependentServices,
    services: data.default.services
  });
});

const unmatchedSearchTerm = 'no-such-dependency';

When('the user opens the service dependencies listing', () => {
  cy.visit(PAGES.configuration.servicesDependenciesLegacy);
  cy.waitForModernListing();
});

Then(
  'the AJAX listing table is displayed with the configured service dependency',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(data.default.dependency.name)
      .should('exist');
  }
);

When('the user searches for a term matching no service dependency', () => {
  // Live search (debounced AJAX) — there is no submit button, the table
  // refreshes as the user types.
  cy.getIframeBody().find('#clSearchInput').clear().type(unmatchedSearchTerm);
  cy.wait('@getServiceDependencies');
});

Then('no service dependency is displayed', () => {
  cy.listingShouldBeEmpty();
});

When('the user searches for the configured service dependency', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .clear()
    .type(data.default.dependency.name);
  cy.wait('@getServiceDependencies');
});

Then('only the matching service dependency is displayed', () => {
  cy.listingShouldContainOnly(data.default.dependency.name);
});

When('the user opens the service group dependencies listing', () => {
  cy.visit(PAGES.configuration.serviceGroupsDependenciesLegacy);
  cy.waitForModernListing();
});

Then(
  'the AJAX listing table is displayed with the configured service group dependency',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(data.defaultSGDependency.dependency.name)
      .should('exist');
  }
);

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When('the user changes the properties of a service dependency', () => {
  cy.openSidePanelForm(data.default.dependency.name, 'input[name="dep_name"]');
  cy.updateServiceDependency({
    ...data.ServDependency1,
    dependency: {
      comment: data.ServDependency1.dependency.comment,
      description: data.ServDependency1.dependency.description,
      executionFailsOnCritical:
        data.ServDependency1.dependency.execution_fails_on_critical,
      executionFailsOnNone:
        data.ServDependency1.dependency.execution_fails_on_none,
      executionFailsOnOk: data.ServDependency1.dependency.execution_fails_on_ok,
      executionFailsOnPending:
        data.ServDependency1.dependency.execution_fails_on_pending,
      executionFailsOnUnknown:
        data.ServDependency1.dependency.execution_fails_on_unknown,
      executionFailsOnWarning:
        data.ServDependency1.dependency.execution_fails_on_warning,
      name: data.ServDependency1.dependency.name,
      notificationFailsOnCritical:
        data.ServDependency1.dependency.notification_fails_on_critical,
      notificationFailsOnNone:
        data.ServDependency1.dependency.notification_fails_on_none,
      notificationFailsOnOk:
        data.ServDependency1.dependency.notification_fails_on_ok,
      notificationFailsOnPending:
        data.ServDependency1.dependency.notification_fails_on_pending,
      notificationFailsOnUnknown:
        data.ServDependency1.dependency.notification_fails_on_unknown,
      notificationFailsOnWarning:
        data.ServDependency1.dependency.notification_fails_on_warning,
      parentRelationship: data.ServDependency1.dependency.parent_relationship
    }
  });
});

Then('the properties are updated', () => {
  cy.openSidePanelForm(
    data.ServDependency1.dependency.name,
    'input[name="dep_name"]'
  );
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.ServDependency1.dependency.name);

  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.ServDependency1.dependency.description);
  cy.getSidePanelBody().find('#eWarning').should('be.checked');
  cy.getSidePanelBody().find('#eCritical').should('be.checked');
  cy.getSidePanelBody().find('#nWarning').should('be.checked');
  cy.getSidePanelBody().find('#nCritical').should('be.checked');

  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="host2 - ${data.ServDependency1.services[0]}"]`
    )
    .should('exist');

  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="host3 - ${data.ServDependency1.dependentServices[0]}"]`
    )
    .should('exist');

  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.ServDependency1.dependentHosts[0]}"]`
    )
    .should('exist');

  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.ServDependency1.dependency.comment);
});

When('the user duplicates a service dependency', () => {
  cy.runListingBulkAction(data.default.dependency.name, 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new service dependency has the same properties', () => {
  cy.openSidePanelForm(
    `${data.default.dependency.name}_1`,
    'input[name="dep_name"]'
  );
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.default.dependency.name}_1`);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.default.dependency.description);
  cy.getSidePanelBody().find('#eOk').should('be.checked');
  cy.getSidePanelBody().find('#eWarning').should('be.checked');
  cy.getSidePanelBody().find('#eCritical').should('be.checked');

  cy.getSidePanelBody().find('#nOk').should('be.checked');
  cy.getSidePanelBody().find('#nWarning').should('be.checked');
  cy.getSidePanelBody().find('#nCritical').should('be.checked');

  cy.getSidePanelBody()
    .find(`.select2-selection__choice[title="${data.default.services[0]}"]`)
    .should('exist');

  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="host2 - ${data.default.dependentServices[0]}"]`
    )
    .should('exist');

  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.default.dependentHosts[0]}"]`
    )
    .should('exist');

  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.default.dependency.comment);
});

When('the user deletes a service dependency', () => {
  cy.runListingBulkAction(data.default.dependency.name, 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted service dependency is not displayed in the list', () => {
  // The duplicate created earlier is "<name>_1", so the deleted row has to be
  // matched on its exact name rather than as a substring.
  cy.listingRowShouldNotExist(data.default.dependency.name);
});

Given('a service group dependency is configured', () => {
  cy.visit(PAGES.configuration.serviceGroupsDependenciesLegacy);
  cy.openListingAddForm();
  cy.addServiceGroupDependency({
    ...data.defaultSGDependency,
    dependency: {
      comment: data.defaultSGDependency.dependency.comment,
      description: data.defaultSGDependency.dependency.description,
      executionFailsOnCritical:
        data.defaultSGDependency.dependency.execution_fails_on_critical,
      executionFailsOnNone:
        data.defaultSGDependency.dependency.execution_fails_on_none,
      executionFailsOnOk:
        data.defaultSGDependency.dependency.execution_fails_on_ok,
      executionFailsOnPending:
        data.defaultSGDependency.dependency.execution_fails_on_pending,
      executionFailsOnUnknown:
        data.defaultSGDependency.dependency.execution_fails_on_unknown,
      executionFailsOnWarning:
        data.defaultSGDependency.dependency.execution_fails_on_warning,
      name: data.defaultSGDependency.dependency.name,
      notificationFailsOnCritical:
        data.defaultSGDependency.dependency.notification_fails_on_critical,
      notificationFailsOnNone:
        data.defaultSGDependency.dependency.notification_fails_on_none,
      notificationFailsOnOk:
        data.defaultSGDependency.dependency.notification_fails_on_ok,
      notificationFailsOnPending:
        data.defaultSGDependency.dependency.notification_fails_on_pending,
      notificationFailsOnUnknown:
        data.defaultSGDependency.dependency.notification_fails_on_unknown,
      notificationFailsOnWarning:
        data.defaultSGDependency.dependency.notification_fails_on_warning,
      parentRelationship:
        data.defaultSGDependency.dependency.parent_relationship
    },
    dependentServiceGroups: data.defaultSGDependency.dependent_service_groups,
    serviceGroups: data.defaultSGDependency.service_groups
  });
});

When('the user changes the properties of a service group dependency', () => {
  cy.openSidePanelForm(
    data.defaultSGDependency.dependency.name,
    'input[name="dep_name"]'
  );
  cy.updateServiceGroupDependency({
    ...data.SGDependency1,
    dependency: {
      comment: data.SGDependency1.dependency.comment,
      description: data.SGDependency1.dependency.description,
      executionFailsOnCritical:
        data.SGDependency1.dependency.execution_fails_on_critical,
      executionFailsOnNone:
        data.SGDependency1.dependency.execution_fails_on_none,
      executionFailsOnOk: data.SGDependency1.dependency.execution_fails_on_ok,
      executionFailsOnPending:
        data.SGDependency1.dependency.execution_fails_on_pending,
      executionFailsOnUnknown:
        data.SGDependency1.dependency.execution_fails_on_unknown,
      executionFailsOnWarning:
        data.SGDependency1.dependency.execution_fails_on_warning,
      name: data.SGDependency1.dependency.name,
      notificationFailsOnCritical:
        data.SGDependency1.dependency.notification_fails_on_critical,
      notificationFailsOnNone:
        data.SGDependency1.dependency.notification_fails_on_none,
      notificationFailsOnOk:
        data.SGDependency1.dependency.notification_fails_on_ok,
      notificationFailsOnPending:
        data.SGDependency1.dependency.notification_fails_on_pending,
      notificationFailsOnUnknown:
        data.SGDependency1.dependency.notification_fails_on_unknown,
      notificationFailsOnWarning:
        data.SGDependency1.dependency.notification_fails_on_warning,
      parentRelationship: data.SGDependency1.dependency.parent_relationship
    },
    dependentServiceGroups: data.SGDependency1.dependent_service_groups,
    serviceGroups: data.SGDependency1.service_groups
  });
});

Then('the properties of the service group dependency are updated', () => {
  cy.openSidePanelForm(
    data.SGDependency1.dependency.name,
    'input[name="dep_name"]'
  );
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.SGDependency1.dependency.name);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.SGDependency1.dependency.description);
  cy.getSidePanelBody().find('#eWarning').should('be.checked');
  cy.getSidePanelBody().find('#eCritical').should('be.checked');
  cy.getSidePanelBody().find('#nWarning').should('be.checked');
  cy.getSidePanelBody().find('#nCritical').should('be.checked');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.SGDependency1.service_groups[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.SGDependency1.dependent_service_groups[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.SGDependency1.dependency.comment);
});

When('the user duplicates a service group dependency', () => {
  cy.runListingBulkAction(
    data.defaultSGDependency.dependency.name,
    'Duplicate'
  );
  cy.wait('@getTimeZone');
});

Then('the new service group dependency has the same properties', () => {
  cy.openSidePanelForm(
    `${data.defaultSGDependency.dependency.name}_1`,
    'input[name="dep_name"]'
  );
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.defaultSGDependency.dependency.name}_1`);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.defaultSGDependency.dependency.description);
  cy.getSidePanelBody().find('#eOk').should('be.checked');
  cy.getSidePanelBody().find('#eWarning').should('be.checked');
  cy.getSidePanelBody().find('#eCritical').should('be.checked');
  cy.getSidePanelBody().find('#nOk').should('be.checked');
  cy.getSidePanelBody().find('#nWarning').should('be.checked');
  cy.getSidePanelBody().find('#nCritical').should('be.checked');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.defaultSGDependency.service_groups[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.defaultSGDependency.dependent_service_groups[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.defaultSGDependency.dependency.comment);
});

When('the user deletes a service group dependency', () => {
  cy.runListingBulkAction(data.defaultSGDependency.dependency.name, 'Delete');
  cy.wait('@getTimeZone');
});

Then(
  'the deleted service group dependency is not displayed in the list',
  () => {
    // The duplicate created earlier is "<name>_1", so the deleted row has to be
    // matched on its exact name rather than as a substring.
    cy.listingRowShouldNotExist(data.defaultSGDependency.dependency.name);
  }
);
