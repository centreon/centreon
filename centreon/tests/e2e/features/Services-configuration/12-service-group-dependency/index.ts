/* eslint-disable no-script-url */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

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
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .addServiceGroup({
      hostsAndServices: [
        [services.serviceCritical.host, services.serviceCritical.name]
      ],
      name: servicesData.service_group.service1.name
    })
    .applyPollerConfiguration();
});

Given('a service group dependency is configured', () => {
  cy.navigateTo({
    page: 'Service Groups',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
  cy.getIframeBody().contains('a', 'Add').click({ force: true });
  cy.addServiceGroupDependency(data.defaultSGDependency);
});

When('the user changes the properties of a service group dependency', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.defaultSGDependency.dependency.name}")`
  );
  cy.getIframeBody().contains(data.defaultSGDependency.dependency.name).click();
  cy.updateServiceGroupDependency(data.SGDependency1);
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.SGDependency1.dependency.name}")`
  );
  cy.getIframeBody().contains(data.SGDependency1.dependency.name).click();
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
