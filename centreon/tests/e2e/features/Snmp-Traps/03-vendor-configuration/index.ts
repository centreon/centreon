/* eslint-disable no-nested-ternary */
/* eslint-disable no-script-url */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/snmp-traps/vendor.json';
import traps from '../../../fixtures/snmp-traps/snmp-trap.json';
import { AddOrUpdateVendor, CheckVendorFieldsValues } from '../common';

const services = {
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' }
};

before(() => {
  cy.startContainers();
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
});

beforeEach(() => {
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

after(() => {
  cy.stopContainers();
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the user goes to "Configuration > SNMP Traps > Manufacturer"', () => {
  cy.navigateTo({
    page: 'Manufacturer',
    rootItemNumber: 3,
    subMenu: 'SNMP Traps'
  });
});

When('the user adds a new vendor', () => {
  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');
  AddOrUpdateVendor(data.default);
});

Then('the vendor configuration is added to the listing page', () => {
  cy.getIframeBody().contains(data.default.name).should('be.visible');
});

Given('a vendor {string} is configured', (step) => {
  cy.navigateTo({
    page: 'Manufacturer',
    rootItemNumber: 3,
    subMenu: 'SNMP Traps'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${step === 'update' ? data.default.name : step === 'duplicate' || step === 'delete' ? data.vendor.name : ''}")`
  );
  cy.getIframeBody()
    .contains(
      step === 'update'
        ? data.default.name
        : step === 'duplicate' || step === 'delete'
          ? data.vendor.name
          : ''
    )
    .should('be.visible');
});

When('the user changes the properties of the vendor', () => {
  cy.getIframeBody().contains(data.default.name).click();
  cy.wait('@getTimeZone');
  AddOrUpdateVendor(data.vendor);
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.vendor.name}")`
  );
  cy.getIframeBody().contains(`${data.vendor.name}`).click();
  cy.wait('@getTimeZone');
  CheckVendorFieldsValues(data.vendor.name, data.vendor);
});

When('the user duplicates the vendor', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchTM"]');
  cy.getIframeBody()
    .find('input[name="searchTM"]')
    .clear()
    .type(data.vendor.name);
  cy.getIframeBody().find('input[value="Search"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.vendor.name}")`
  );
  cy.checkFirstRowFromListing('searchTM');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new duplicated vendor has the same properties', () => {
  cy.getIframeBody().contains(`${data.vendor.name}`).click();
  cy.wait('@getTimeZone');
});

When('the user deletes the vendor', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchTM"]');
  cy.getIframeBody()
    .find('input[name="searchTM"]')
    .clear()
    .type(`${data.vendor.name}_1`);
  cy.getIframeBody().find('input[value="Search"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.vendor.name}_1")`
  );
  cy.checkFirstRowFromListing('searchTM');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted object is not displayed in the list', () => {
  cy.reload();
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(`${data.vendor.name}_1`).should('not.exist');
});

Given('a passive service is linked to the vendor', () => {
  cy.setPassiveResource('/centreon/api/latest/configuration/services/27');
  cy.navigateTo({
    page: 'Services',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(services.serviceOk.name).click();
  cy.waitForElementInIframe('#main-content', 'a:contains("Relations")');
  cy.getIframeBody().contains('a', 'Relations').click();
  cy.get('body').click(0, 0);
  cy.waitForElementInIframe('#main-content', '#service_traps');
  cy.getIframeBody()
    .find('input[placeholder="Service Trap Relation"]')
    .click({ force: true });
  cy.getIframeBody()
    .find(`div[title="${data.vendor.name} - ${traps.snmp1.name}"]`)
    .click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.exportConfig();
});

Given('an SNMP Trap is linked to the vendor', () => {
  cy.navigateTo({
    page: 'SNMP Traps',
    rootItemNumber: 3,
    subMenu: 'SNMP Traps'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
  cy.getIframeBody()
    .find('input[name="traps_name"]')
    .clear()
    .type(traps.snmp1.name);
  cy.getIframeBody()
    .find('input[name="traps_oid"]')
    .clear()
    .type(traps.snmp1.oid);
  cy.getIframeBody()
    .find('input[name="traps_args"]')
    .clear()
    .type(traps.snmp1.output);
  cy.getIframeBody().find('span[title="Vendor Name"]').click();
  cy.getIframeBody().find(`div[title="${data.vendor.name}"]`).click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.exportConfig();
  cy.wait('@getTimeZone');
});

When('the user goes to "Configuration > SNMP Traps > Generate"', () => {
  cy.navigateTo({
    page: 'Generate',
    rootItemNumber: 3,
    subMenu: 'SNMP Traps'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select[name="host"]');
});

When('the user clicks on "Generate"', () => {
  cy.getIframeBody().find('input[value="Generate"]').click();
  cy.wait('@getTimeZone');
  cy.wait('@getTopCounter');
});

Then(
  'a message indicates that the "Database generation with success" is displayed on the page',
  () => {
    cy.waitForElementInIframe('#main-content', '#tab1 .ListTable');
    cy.getIframeBody()
      .find('#tab1 .ListTable')
      .contains('Poller (id:1): Sqlite database successfully created')
      .should('be.visible');
  }
);
