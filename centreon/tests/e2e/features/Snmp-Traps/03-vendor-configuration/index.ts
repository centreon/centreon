import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import traps from '../../../fixtures/snmp-traps/snmp-trap.json';
import data from '../../../fixtures/snmp-traps/vendor.json';
import {
  AddOrUpdateVendor,
  CheckVendorFieldsValues,
  generateAdvancedSummary,
  generateApplyButton,
  generatePollersField,
  listingTable,
  listingTableBody,
  saveButton
} from '../common';

const services = {
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' }
};

const secondVendorName = 'OtherVendor';
const centralPollerId = '1';

// This feature shares one container across its scenarios (before/after, not
// beforeEach), and both the listing search and the bulk-action helper match on
// a substring. Each scenario therefore gets a name that is not contained in any
// other one, nor in the "_1" copy the duplication scenario leaves behind.
const vendorNames = {
  delete: 'VendorToDelete',
  duplicate: 'VendorToDuplicate',
  update: data.default.name
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
    url: INTERCEPTORS.ajax.trap_vendors_listing
  }).as('listVendorsAjax');
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

Given('a vendor is configured through the API', () => {
  cy.setUserTokenApiV1();
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'VENDOR',
      values: `${data.default.name};${data.default.alias}`
    }
  });
});

Given('two vendors are configured through the API', () => {
  cy.setUserTokenApiV1();
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'VENDOR',
      values: `${data.default.name};${data.default.alias}`
    }
  });
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'VENDOR',
      values: `${secondVendorName};${secondVendorName}Alias`
    }
  });
});

When('the user goes to "Configuration > SNMP Traps > Manufacturer"', () => {
  cy.openTrapVendorsListing();
});

// Scenario: The vendors listing loads through the AJAX framework
Then('the AJAX listing table is displayed with the configured vendor', () => {
  cy.wait('@listVendorsAjax').its('response.statusCode').should('eq', 200);
  cy.getIframeBody().find(listingTable).should('exist');
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.default.name)
    .should('exist');
});

// Scenario: The search filters the vendors by name
When('the user searches for the first vendor', () => {
  cy.searchInTrapsListing(data.default.name);
});

Then('only the matching vendor is displayed', () => {
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.default.name)
    .should('exist');
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(secondVendorName)
    .should('not.exist');
});

// Scenario: Create a new vendor
When('the user adds a new vendor', () => {
  cy.openTrapsAddForm('input[name="name"]');
  AddOrUpdateVendor(data.default);
});

Then('the vendor configuration is added to the listing page', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.default.name)
    .should('be.visible');
});

Given('a vendor {string} is configured', (step) => {
  const vendorName = vendorNames[step];

  expect(vendorName, `no vendor name declared for "${step}"`).to.be.a('string');

  cy.setUserTokenApiV1();
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'VENDOR',
      values: `${vendorName};${data.default.alias}`
    }
  });
  cy.openTrapVendorsListing();
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(vendorName)
    .should('be.visible');
});

// Scenario: Change the properties of a vendor
When('the user changes the properties of the vendor', () => {
  cy.openTrapsRowForm(data.default.name, 'input[name="name"]');
  AddOrUpdateVendor(data.vendor);
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.openTrapsRowForm(data.vendor.name, 'input[name="name"]');
  CheckVendorFieldsValues(data.vendor.name, data.vendor);
});

// Scenario: Duplicate one existing vendor
When('the user duplicates the vendor', () => {
  cy.searchInTrapsListing(vendorNames.duplicate);
  cy.runTrapsBulkAction(vendorNames.duplicate, 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new duplicated vendor has the same properties', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.openTrapsRowForm(`${vendorNames.duplicate}_1`, 'input[name="name"]');
  cy.getTrapSidePanelBody()
    .find('input[name="name"]')
    .should('have.value', `${vendorNames.duplicate}_1`);
  cy.getTrapSidePanelBody()
    .find('input[name="alias"]')
    .should('have.value', data.default.alias);
});

// Scenario: Delete one existing vendor
When('the user deletes the vendor', () => {
  cy.searchInTrapsListing(vendorNames.delete);
  cy.runTrapsBulkAction(vendorNames.delete, 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted object is not displayed in the list', () => {
  cy.openTrapVendorsListing();
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(vendorNames.delete)
    .should('not.exist');
});

Given('an SNMP Trap is linked to the vendor', () => {
  cy.openTrapsSnmpListing();
  cy.openTrapsAddForm('input[name="traps_name"]');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_name"]')
    .clear()
    .type(traps.snmp1.name);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_oid"]')
    .clear()
    .type(traps.snmp1.oid);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_args"]')
    .clear()
    .type(traps.snmp1.output);
  cy.selectTrapSidePanelOption('Vendor Name', data.default.name);
  cy.getTrapSidePanelBody().find(saveButton).first().click();
  cy.exportConfig();
  cy.wait('@getTimeZone');
});

Given('a passive service is linked to the vendor', () => {
  // make the already created service a passive service
  cy.setPassiveResource('/centreon/api/latest/configuration/services/31');
  cy.visit(PAGES.configuration.servicesByHostLegacy);
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
    .find(`div[title="${data.default.name} - ${traps.snmp1.name}"]`)
    .click();
  cy.getIframeBody().find(saveButton).eq(0).click();
  cy.exportConfig();
});

When('the user goes to "Configuration > SNMP Traps > Generate"', () => {
  cy.visit(PAGES.configuration.snmpTrapsGenerateLegacy);
  cy.waitForElementInIframe('#main-content', generatePollersField);
});

When('the user applies the trap configuration on the central poller', () => {
  // Restrict the run to the database generation: applying the configuration and
  // signalling centreontrapd are covered by the poller deployment tests.
  cy.getIframeBody().find(generateAdvancedSummary).click();
  cy.getIframeBody().find('#napply').click({ force: true });
  cy.getIframeBody().find('#nsignal').click({ force: true });
  cy.selectTrapsGeneratePoller('Central');
  cy.getIframeBody().find(generateApplyButton).click();
});

Then('the generation console reports a successful run', () => {
  cy.getIframeBody()
    .find(`#gen-log-${centralPollerId}`, { timeout: 60_000 })
    .find('span.ok')
    .should('exist');
  cy.getIframeBody().find('#genPct').should('have.text', '100%');
});
