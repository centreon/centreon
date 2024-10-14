/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

import hostCategories from '../../../fixtures/host-categories/category.json';

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
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceCritical.name,
    status: 'critical'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceWarning.name,
    status: 'warning'
  }
];

const checkFirstHostCategoryFromListing = () => {
  cy.navigateTo({
    page: 'Categories',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select')
    .eq(0)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
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
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([{ name: services.serviceOk.host }]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name }
  ]);
  cy.submitResults(resultsToSubmit);
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('a host category is configured', () => {
  cy.request({
    body: hostCategories.default,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'POST',
    url: '/centreon/api/beta/configuration/hosts/categories'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
});

When('the user changes the properties of a host category', () => {
  cy.navigateTo({
    page: 'Categories',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(hostCategories.default.name).click();
  cy.waitUntil(
    () => {
      return cy
        .getByLabel({ label: 'Up status hosts', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text !== '2') {
            cy.exportConfig();
          }

          return text === '2';
        });
    },
    { interval: 20000, timeout: 100000 }
  );
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .clear()
    .type(hostCategories.forTest.name);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .clear()
    .type(hostCategories.forTest.alias);
  cy.getIframeBody().find('input[placeholder="Linked Hosts"]').click();
  cy.getIframeBody().find('div[title="host2"]').click();
  cy.getIframeBody().find('input[placeholder="Linked Host Template"]').click();
  cy.getIframeBody().find('div[title="generic-host"]').click();
  cy.getIframeBody().contains('label', 'Disabled').click();
  cy.getIframeBody()
    .find('textarea[name="hc_comment"]')
    .clear()
    .type(hostCategories.forTest.comment);

  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  cy.getIframeBody().contains(hostCategories.forTest.name).should('exist');
  cy.getIframeBody().contains(hostCategories.forTest.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .should('have.value', hostCategories.forTest.name);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .should('have.value', hostCategories.forTest.alias);
  cy.getIframeBody()
    .find('span.select2-content')
    .eq(0)
    .should('have.attr', 'title', services.serviceOk.host);
  cy.getIframeBody()
    .find('span.select2-content')
    .eq(1)
    .should('have.attr', 'title', 'generic-host');
  cy.checkLegacyRadioButton('Disabled');
  cy.getIframeBody()
    .find('textarea[name="hc_comment"]')
    .should('have.value', hostCategories.forTest.comment);
});

When('the user duplicates a host category', () => {
  checkFirstHostCategoryFromListing();
  cy.getIframeBody().find('select').eq(0).select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host category is created with identical properties', () => {
  cy.getIframeBody()
    .contains(`${hostCategories.default.name}_1`)
    .should('exist');
  cy.getIframeBody().contains(`${hostCategories.default.name}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .should('have.value', `${hostCategories.default.name}_1`);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .should('have.value', hostCategories.default.alias);
  cy.checkLegacyRadioButton('Enabled');
  cy.getIframeBody()
    .find('textarea[name="hc_comment"]')
    .should('have.value', hostCategories.default.comment);
});

When('the user deletes a host category', () => {
  checkFirstHostCategoryFromListing();
  cy.getIframeBody().find('select').eq(0).select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted host category is not visible anymore on the host category page',
  () => {
    cy.getIframeBody()
      .contains(hostCategories.default.name)
      .should('not.exist');
    cy.getIframeBody()
      .find('table.ListTable tbody tr')
      .should('have.length', 1);
  }
);
