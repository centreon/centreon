/* eslint-disable no-script-url */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/services/dependency.json';

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
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .applyPollerConfiguration();
});

Given('a service dependency is configured', () => {
  cy.navigateTo({
    page: 'Services',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
  cy.getIframeBody().contains('a', 'Add').click({ force: true });
  cy.addServiceDependency(data.default);
});

When('the user changes the properties of a service dependency', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.name}")`
  );
  cy.getIframeBody().contains(data.default.name).click();
  cy.updateServiceDependency(data.ServDependency1);
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.ServDependency1.name}")`
  );
  cy.getIframeBody().contains(data.ServDependency1.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.ServDependency1.name);

  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.ServDependency1.description);
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
    .should('have.value', data.ServDependency1.comment);
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
    `a:contains("${data.default.name}_1")`
  );
  cy.getIframeBody().contains(`${data.default.name}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.default.name}_1`);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.default.description);
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
    .should('have.value', data.default.comment);
});

When('the user deletes a service dependency', () => {
  cy.checkFirstRowFromListing('searchSD');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted service dependency is not displayed in the list', () => {
  cy.getIframeBody().contains(data.default.name).should('not.exist');
});
