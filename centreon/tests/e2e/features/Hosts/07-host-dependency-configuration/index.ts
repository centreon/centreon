import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

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
  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');
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

When('the user changes the properties of a host dependency', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.name}")`
  );
  cy.getIframeBody().contains(data.default.name).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
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
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.HostDependency1.name}")`
  );
  cy.getIframeBody().contains(data.HostDependency1.name).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.HostDependency1.name);

  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.HostDependency1.description);
  cy.getIframeBody().find('#eUp').should('be.checked');
  cy.getIframeBody().find('#nDown').should('be.checked');
  cy.getIframeBody()
    .find('#dep_hostParents')
    .find('option:selected')
    .should('have.length', 2)
    .then((options) => {
      const selectedTexts = Array.from(options).map((option) =>
        (option.textContent || '').trim()
      );
      expect(selectedTexts).to.include.members([
        data.HostDependency1.hostNames[0],
        data.HostDependency1.hostNames[1]
      ]);
    });
  cy.getIframeBody()
    .find('#dep_hostChilds')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.HostDependency1.dependentHostNames[0]);
  cy.getIframeBody()
    .find('#dep_hSvChi')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', `host2 - ${data.HostDependency1.dependentServices[0]}`);
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.HostDependency1.comment);
});

When('the user duplicates a host dependency', () => {
  cy.checkFirstRowFromListing('searchHD');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new host dependency has the same properties', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.name}_1")`
  );
  cy.getIframeBody().contains(`${data.default.name}_1`).click();
  cy.wait('@getTopCounter');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.default.name}_1`);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.default.description);
  cy.getIframeBody().find('#eDown').should('be.checked');
  cy.getIframeBody().find('#nPending').should('be.checked');
  cy.getIframeBody()
    .find('#dep_hostParents')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.default.hostNames[0]);
  cy.getIframeBody()
    .find('#dep_hostChilds')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.default.dependentHostNames[0]);
  cy.getIframeBody()
    .find('#dep_hSvChi')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.default.dependentServices[0]);
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.default.comment);
});

When('the user deletes a host dependency', () => {
  cy.checkFirstRowFromListing('searchHD');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted host dependency is not displayed in the list', () => {
  cy.getIframeBody().contains(data.default.name).should('not.exist');
});
