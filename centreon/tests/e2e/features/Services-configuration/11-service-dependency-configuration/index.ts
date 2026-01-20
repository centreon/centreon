import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

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
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topcounter&action=servicesStatus'
  }).as('getTopCounter');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
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
  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');
  cy.addServiceDependency({
    dependency: {
      name: data.default.dependency.name,
      description: data.default.dependency.description,
      parentRelationship: data.default.dependency.parent_relationship,
      executionFailsOnOk: data.default.dependency.execution_fails_on_ok,
      executionFailsOnWarning:
        data.default.dependency.execution_fails_on_warning,
      executionFailsOnUnknown:
        data.default.dependency.execution_fails_on_unknown,
      executionFailsOnCritical:
        data.default.dependency.execution_fails_on_critical,
      executionFailsOnPending:
        data.default.dependency.execution_fails_on_pending,
      executionFailsOnNone: data.default.dependency.execution_fails_on_none,
      notificationFailsOnNone:
        data.default.dependency.notification_fails_on_none,
      notificationFailsOnOk: data.default.dependency.notification_fails_on_ok,
      notificationFailsOnWarning:
        data.default.dependency.notification_fails_on_warning,
      notificationFailsOnUnknown:
        data.default.dependency.notification_fails_on_unknown,
      notificationFailsOnCritical:
        data.default.dependency.notification_fails_on_critical,
      notificationFailsOnPending:
        data.default.dependency.notification_fails_on_pending,
      comment: data.default.dependency.comment
    },
    services: data.default.services,
    dependentServices: data.default.dependentServices,
    dependentHosts: data.default.dependentHosts
  });
});

When('the user changes the properties of a service dependency', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.dependency.name}")`
  );
  cy.getIframeBody().contains(data.default.dependency.name).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.updateServiceDependency({
    ...data.ServDependency1,
    dependency: {
      name: data.ServDependency1.dependency.name,
      description: data.ServDependency1.dependency.description,
      parentRelationship: data.ServDependency1.dependency.parent_relationship,
      executionFailsOnOk: data.ServDependency1.dependency.execution_fails_on_ok,
      executionFailsOnWarning:
        data.ServDependency1.dependency.execution_fails_on_warning,
      executionFailsOnUnknown:
        data.ServDependency1.dependency.execution_fails_on_unknown,
      executionFailsOnCritical:
        data.ServDependency1.dependency.execution_fails_on_critical,
      executionFailsOnPending:
        data.ServDependency1.dependency.execution_fails_on_pending,
      executionFailsOnNone:
        data.ServDependency1.dependency.execution_fails_on_none,
      notificationFailsOnNone:
        data.ServDependency1.dependency.notification_fails_on_none,
      notificationFailsOnOk:
        data.ServDependency1.dependency.notification_fails_on_ok,
      notificationFailsOnWarning:
        data.ServDependency1.dependency.notification_fails_on_warning,
      notificationFailsOnUnknown:
        data.ServDependency1.dependency.notification_fails_on_unknown,
      notificationFailsOnCritical:
        data.ServDependency1.dependency.notification_fails_on_critical,
      notificationFailsOnPending:
        data.ServDependency1.dependency.notification_fails_on_pending,
      comment: data.ServDependency1.dependency.comment
    }
  });
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.ServDependency1.dependency.name}")`
  );
  cy.getIframeBody().contains(data.ServDependency1.dependency.name).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.ServDependency1.dependency.name);

  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.ServDependency1.dependency.description);
  cy.getIframeBody().find('#eWarning').should('be.checked');
  cy.getIframeBody().find('#eCritical').should('be.checked');
  cy.getIframeBody().find('#nWarning').should('be.checked');
  cy.getIframeBody().find('#nCritical').should('be.checked');

  cy.getIframeBody()
    .find('#dep_hSvPar')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', `host2 - ${data.ServDependency1.services[0]}`);

  cy.getIframeBody()
    .find('#dep_hSvChi')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', `host3 - ${data.ServDependency1.dependentServices[0]}`);

  cy.getIframeBody()
    .find('#dep_hHostChi')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.ServDependency1.dependentHosts[0]);

  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.ServDependency1.dependency.comment);
});

When('the user duplicates a service dependency', () => {
  cy.checkFirstRowFromListing('searchSD');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new service dependency has the same properties', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.dependency.name}_1")`
  );
  cy.getIframeBody().contains(`${data.default.dependency.name}_1`).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.default.dependency.name}_1`);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.default.dependency.description);
  cy.getIframeBody().find('#eOk').should('be.checked');
  cy.getIframeBody().find('#eWarning').should('be.checked');
  cy.getIframeBody().find('#eCritical').should('be.checked');

  cy.getIframeBody().find('#nOk').should('be.checked');
  cy.getIframeBody().find('#nWarning').should('be.checked');
  cy.getIframeBody().find('#nCritical').should('be.checked');

  cy.getIframeBody()
    .find('#dep_hSvPar')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.default.services[0]);

  cy.getIframeBody()
    .find('#dep_hSvChi')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', `host2 - ${data.default.dependentServices[0]}`);

  cy.getIframeBody()
    .find('#dep_hHostChi')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.default.dependentHosts[0]);

  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.default.dependency.comment);
});

When('the user deletes a service dependency', () => {
  cy.checkFirstRowFromListing('searchSD');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted service dependency is not displayed in the list', () => {
  cy.getIframeBody().contains(data.default.dependency.name).should('not.exist');
});

Given('a service group dependency is configured', () => {
  cy.visit(PAGES.configuration.serviceGroupsDependenciesLegacy);
  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');
  cy.addServiceGroupDependency({
    ...data.defaultSGDependency,
    dependency: {
      name: data.defaultSGDependency.dependency.name,
      description: data.defaultSGDependency.dependency.description,
      parentRelationship:
        data.defaultSGDependency.dependency.parent_relationship,
      executionFailsOnOk:
        data.defaultSGDependency.dependency.execution_fails_on_ok,
      executionFailsOnWarning:
        data.defaultSGDependency.dependency.execution_fails_on_warning,
      executionFailsOnUnknown:
        data.defaultSGDependency.dependency.execution_fails_on_unknown,
      executionFailsOnCritical:
        data.defaultSGDependency.dependency.execution_fails_on_critical,
      executionFailsOnPending:
        data.defaultSGDependency.dependency.execution_fails_on_pending,
      executionFailsOnNone:
        data.defaultSGDependency.dependency.execution_fails_on_none,
      notificationFailsOnNone:
        data.defaultSGDependency.dependency.notification_fails_on_none,
      notificationFailsOnOk:
        data.defaultSGDependency.dependency.notification_fails_on_ok,
      notificationFailsOnWarning:
        data.defaultSGDependency.dependency.notification_fails_on_warning,
      notificationFailsOnUnknown:
        data.defaultSGDependency.dependency.notification_fails_on_unknown,
      notificationFailsOnCritical:
        data.defaultSGDependency.dependency.notification_fails_on_critical,
      notificationFailsOnPending:
        data.defaultSGDependency.dependency.notification_fails_on_pending,
      comment: data.defaultSGDependency.dependency.comment
    },
    serviceGroups: data.defaultSGDependency.service_groups,
    dependentServiceGroups: data.defaultSGDependency.dependent_service_groups
  });
});

When('the user changes the properties of a service group dependency', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.defaultSGDependency.dependency.name}")`
  );
  cy.getIframeBody().contains(data.defaultSGDependency.dependency.name).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.updateServiceGroupDependency({
    ...data.SGDependency1,
    dependency: {
      name: data.SGDependency1.dependency.name,
      description: data.SGDependency1.dependency.description,
      parentRelationship: data.SGDependency1.dependency.parent_relationship,
      executionFailsOnOk: data.SGDependency1.dependency.execution_fails_on_ok,
      executionFailsOnWarning:
        data.SGDependency1.dependency.execution_fails_on_warning,
      executionFailsOnUnknown:
        data.SGDependency1.dependency.execution_fails_on_unknown,
      executionFailsOnCritical:
        data.SGDependency1.dependency.execution_fails_on_critical,
      executionFailsOnPending:
        data.SGDependency1.dependency.execution_fails_on_pending,
      executionFailsOnNone:
        data.SGDependency1.dependency.execution_fails_on_none,
      notificationFailsOnNone:
        data.SGDependency1.dependency.notification_fails_on_none,
      notificationFailsOnOk:
        data.SGDependency1.dependency.notification_fails_on_ok,
      notificationFailsOnWarning:
        data.SGDependency1.dependency.notification_fails_on_warning,
      notificationFailsOnUnknown:
        data.SGDependency1.dependency.notification_fails_on_unknown,
      notificationFailsOnCritical:
        data.SGDependency1.dependency.notification_fails_on_critical,
      notificationFailsOnPending:
        data.SGDependency1.dependency.notification_fails_on_pending,
      comment: data.SGDependency1.dependency.comment
    },
    serviceGroups: data.SGDependency1.service_groups,
    dependentServiceGroups: data.SGDependency1.dependent_service_groups
  });
});

Then('the properties of the service group dependency are updated', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.SGDependency1.dependency.name}")`
  );
  cy.getIframeBody().contains(data.SGDependency1.dependency.name).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.SGDependency1.dependency.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.SGDependency1.dependency.description);
  cy.getIframeBody().find('#eWarning').should('be.checked');
  cy.getIframeBody().find('#eCritical').should('be.checked');
  cy.getIframeBody().find('#nWarning').should('be.checked');
  cy.getIframeBody().find('#nCritical').should('be.checked');
  cy.getIframeBody()
    .find('#dep_sgParents')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.SGDependency1.service_groups[0]);
  cy.getIframeBody()
    .find('#dep_sgChilds')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.SGDependency1.dependent_service_groups[0]);
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.SGDependency1.dependency.comment);
});

When('the user duplicates a service group dependency', () => {
  cy.checkFirstRowFromListing('searchSGD');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
});

Then('the new service group dependency has the same properties', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.defaultSGDependency.dependency.name}_1")`
  );
  cy.getIframeBody()
    .contains(`${data.defaultSGDependency.dependency.name}_1`)
    .click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.defaultSGDependency.dependency.name}_1`);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.defaultSGDependency.dependency.description);
  cy.getIframeBody().find('#eOk').should('be.checked');
  cy.getIframeBody().find('#eWarning').should('be.checked');
  cy.getIframeBody().find('#eCritical').should('be.checked');
  cy.getIframeBody().find('#nOk').should('be.checked');
  cy.getIframeBody().find('#nWarning').should('be.checked');
  cy.getIframeBody().find('#nCritical').should('be.checked');
  cy.getIframeBody()
    .find('#dep_sgParents')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.defaultSGDependency.service_groups[0]);
  cy.getIframeBody()
    .find('#dep_sgChilds')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.defaultSGDependency.dependent_service_groups[0]);
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.defaultSGDependency.dependency.comment);
});

When('the user deletes a service group dependency', () => {
  cy.checkFirstRowFromListing('searchSGD');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
});

Then(
  'the deleted service group dependency is not displayed in the list',
  () => {
    cy.reload();
    cy.wait('@getTimeZone');
    cy.getIframeBody()
      .contains(data.defaultSGDependency.dependency.name)
      .should('not.exist');
  }
);
