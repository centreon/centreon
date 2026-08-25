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

// Read back in before(): the passive-service step addresses the service by id,
// and cy.addService() does not return the one it just created.
let serviceOkId: string;

const secondVendorName = 'OtherVendor';
const centralPollerId = '1';

// This feature shares one container across its scenarios (before/after, not
// beforeEach), so nothing resets between them: a name seeded twice is a CLAPI
// 409, and the vendor form rejects duplicates too ("Name is already in use").
// Every scenario therefore owns its vendor. Both the listing search and the
// bulk-action helper match on a substring, so no name here may be contained in
// another one, nor in the "_1" copy the duplication scenario leaves behind.
const vendorNames = {
  associate: 'VendorToAssociate',
  create: 'VendorToCreate',
  delete: 'VendorToDelete',
  duplicate: 'VendorToDuplicate',
  listing: 'VendorForListing',
  renamed: 'VendorRenamed',
  search: 'VendorForSearch',
  update: 'VendorToUpdate'
};

before(() => {
  cy.startContainers();
  // executeActionViaClapi reads the token straight from localStorage, so without
  // this the CLAPI calls below go out with a `centreon-auth-token: null` header.
  cy.setUserTokenApiV1();
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

  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SHOW',
      object: 'SERVICE',
      values: `${services.serviceOk.host};${services.serviceOk.name}`
    }
  }).then((response) => {
    serviceOkId = response.body.result[0].id;
  });
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
      values: `${vendorNames.listing};${data.default.alias}`
    }
  });
});

Given('two vendors are configured through the API', () => {
  cy.setUserTokenApiV1();
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'VENDOR',
      values: `${vendorNames.search};${data.default.alias}`
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
    .contains(vendorNames.listing)
    .should('exist');
});

// Scenario: The search filters the vendors by name
When('the user searches for the first vendor', () => {
  cy.searchInTrapsListing(vendorNames.search);
});

Then('only the matching vendor is displayed', () => {
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(vendorNames.search)
    .should('exist');
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(secondVendorName)
    .should('not.exist');
});

// Scenario: Create a new vendor
When('the user adds a new vendor', () => {
  cy.openTrapsAddForm('input[name="name"]');
  AddOrUpdateVendor({ ...data.default, name: vendorNames.create });
});

Then('the vendor configuration is added to the listing page', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(vendorNames.create)
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
  cy.openTrapsRowForm(vendorNames.update, 'input[name="name"]');
  AddOrUpdateVendor({ ...data.vendor, name: vendorNames.renamed });
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.openTrapsRowForm(vendorNames.renamed, 'input[name="name"]');
  CheckVendorFieldsValues(vendorNames.renamed, data.vendor);
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
  cy.selectTrapSidePanelOption('Vendor Name', vendorNames.associate);
  cy.getTrapSidePanelBody().find(saveButton).first().click();
  cy.exportConfig();
  cy.wait('@getTimeZone');
});

Given('a passive service is linked to the vendor', () => {
  expect(serviceOkId, 'the service id was read back in before()').to.be.a(
    'string'
  );

  // make the already created service a passive service
  cy.setPassiveResource(
    `/centreon/api/latest/configuration/services/${serviceOkId}`
  );
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
  // The picker is fed by a paginated AJAX datasource (60 entries per page,
  // ordered by vendor name), so the entry this scenario needs is not in the page
  // rendered on open. Type the trap name to let the datasource filter it in.
  cy.getIframeBody()
    .find('input[placeholder="Service Trap Relation"]')
    .type(traps.snmp1.name);
  cy.getIframeBody()
    .find(`div[title="${vendorNames.associate} - ${traps.snmp1.name}"]`)
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
