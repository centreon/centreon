import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import data from '../../../fixtures/snmp-traps/snmp-trap.json';
import {
  listingPagination,
  listingSearchInput,
  listingTable,
  listingTableBody,
  submitForm,
  trapsSnmpConfiguration,
  UpdateTrapsSnmpConfiguration
} from '../common';

// Must not contain data.snmp1.name: the listing search is a LIKE %term%, so a
// superstring would come back with the first trap and the "only the matching
// trap" assertion could never hold.
const secondTrapName = 'otherTrapDefinition';

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
    url: INTERCEPTORS.ajax.traps_listing
  }).as('listTrapsAjax');
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('an SNMP trap definition is configured', () => {
  cy.setUserTokenApiV1();
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'TRAP',
      values: `${data.snmp1.name};${data.snmp1.oid}`
    }
  });
});

Given('two SNMP trap definitions are configured', () => {
  cy.setUserTokenApiV1();
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'TRAP',
      values: `${data.snmp1.name};${data.snmp1.oid}`
    }
  });
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'TRAP',
      values: `${secondTrapName};1.2.3.4.5`
    }
  });
});

// Scenario: The SNMP traps listing loads through the AJAX framework
When('the user opens the SNMP traps listing', () => {
  cy.openTrapsSnmpListing();
  cy.wait('@listTrapsAjax');
});

Then('the AJAX listing table is displayed with the configured trap', () => {
  cy.get('@listTrapsAjax').its('response.statusCode').should('eq', 200);
  cy.getIframeBody().find(listingTable).should('exist');
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.snmp1.name)
    .should('exist');
});

// Scenario: The search filters the SNMP traps by name
When('the user searches for the first trap', () => {
  cy.searchInTrapsListing(data.snmp1.name);
});

Then('only the matching trap is displayed', () => {
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.snmp1.name)
    .should('exist');
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(secondTrapName)
    .should('not.exist');
});

// Scenario: The listing shows pagination information
Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find(listingPagination)
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

// Scenario: The search term persists across navigation
When('the user opens the trap form and comes back to the listing', () => {
  cy.openTrapsRowForm(data.snmp1.name, 'input[name="traps_name"]');
  cy.openTrapsSnmpListing();
  cy.wait('@listTrapsAjax');
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find(listingSearchInput)
    .should('have.value', data.snmp1.name);
});

// Scenario: Creating SNMP trap with advanced matching rule
When(
  'the user adds a new SNMP trap definition with an advanced matching rule',
  () => {
    cy.openTrapsSnmpListing();
    cy.openTrapsAddForm('input[name="traps_name"]');
    trapsSnmpConfiguration({
      name: data.snmp1.name,
      oid: data.snmp1.oid,
      output: data.snmp1.output,
      regexp: data.snmp1.rule.regexp,
      severity: data.snmp1.rule.status,
      string: data.snmp1.rule.string,
      vendor: data.snmp1.vendor
    });
  }
);

Then(
  'the trap definition is saved with its properties, especially the content of Regexp field',
  () => {
    submitForm();
    cy.waitForElementInIframe('#main-content', listingTable);
    cy.openTrapsRowForm(data.snmp1.name, 'input[name="traps_name"]');
    cy.getTrapSidePanelBody()
      .find('input[name="traps_name"]')
      .should('have.value', data.snmp1.name);
    cy.getTrapSidePanelBody()
      .find('input[name="traps_oid"]')
      .should('have.value', data.snmp1.oid);
    // The vendor select2 keeps its selected value as a plain <option>.
    cy.getTrapSidePanelBody()
      .find('#manufacturer_id option:selected')
      .should('contain', data.snmp1.vendor);
    cy.getTrapSidePanelBody()
      .find('input[name="traps_args"]')
      .should('have.value', data.snmp1.output);
    cy.getTrapSidePanelBody().find('div#matchingrules_add').click();
    cy.getTrapSidePanelBody()
      .find('input#rule_0')
      .should('have.value', data.snmp1.rule.string);
    cy.getTrapSidePanelBody()
      .find('input#regexp_0')
      .should('have.value', data.snmp1.rule.regexp);
    cy.getTrapSidePanelBody()
      .find('select#rulestatus_0')
      .should('have.value', '2');
  }
);

// Scenario: Modify SNMP trap definition
When(
  'the user modifies some properties of an existing SNMP trap definition',
  () => {
    cy.setUserTokenApiV1();
    cy.addHost({
      activeCheckEnabled: false,
      address: '1.2.3.4',
      alias: data.snmp2.hostName,
      checkCommand: 'check_centreon_cpu',
      name: data.snmp2.hostName,
      template: 'generic-host'
    });
    cy.addService({
      activeCheckEnabled: false,
      host: data.snmp2.hostName,
      maxCheckAttempts: 1,
      name: data.snmp2.serviceName,
      template: 'Ping-LAN'
    });
    cy.addServiceTemplate({
      name: data.snmp2.service_templates,
      template: 'generic-service'
    });
    cy.openTrapsSnmpListing();
    cy.openTrapsAddForm('input[name="traps_name"]');
    trapsSnmpConfiguration({
      name: data.snmp1.name,
      oid: data.snmp1.oid,
      output: data.snmp1.output,
      regexp: data.snmp1.rule.regexp,
      severity: data.snmp1.rule.status,
      string: data.snmp1.rule.string,
      vendor: data.snmp1.vendor
    });
    submitForm();
    cy.waitForElementInIframe('#main-content', listingTable);
    cy.openTrapsRowForm(data.snmp1.name, 'input[name="traps_name"]');
    UpdateTrapsSnmpConfiguration({
      behavior: data.snmp2.behavior,
      comments: data.snmp2.comments,
      customCode: data.snmp2.custom_code,
      executionInterval: data.snmp2.execution_interval,
      filterServices: data.snmp2.filter_services,
      mode: data.snmp2.mode,
      name: data.snmp2.name,
      oid: data.snmp2.oid,
      output: data.snmp2.output,
      outputTransform: data.snmp2.output_transform,
      regexp: data.snmp2.rule.regexp,
      routingDefinition: data.snmp2.routing_definition,
      serviceName: data.snmp2.serviceName,
      serviceTemplates: data.snmp2.service_templates,
      severity: data.snmp2.rule.status,
      specialCommand: data.snmp2.special_command,
      status: data.snmp2.status,
      string: data.snmp2.rule.string,
      timeout: data.snmp2.timeout,
      vendor: data.snmp2.vendor
    });
    submitForm();
  }
);

Then('all changes are saved', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.openTrapsRowForm(data.snmp2.name, 'input[name="traps_name"]');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_name"]')
    .should('have.value', data.snmp2.name);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_oid"]')
    .should('have.value', data.snmp2.oid);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_args"]')
    .should('have.value', data.snmp2.output);
  cy.getTrapSidePanelBody()
    .find('select[name="traps_status"]')
    .find('option:selected')
    .should('have.value', '2');
  cy.getTrapSidePanelBody()
    .find('select[name="traps_advanced_treatment_default"]')
    .should('have.value', '2');
  cy.getTrapSidePanelBody().find('div#matchingrules_add').click();
  cy.getTrapSidePanelBody()
    .find('input#rule_0')
    .should('have.value', data.snmp2.rule.string);
  cy.getTrapSidePanelBody()
    .find('input#regexp_0')
    .should('have.value', data.snmp2.rule.regexp);
  cy.getTrapSidePanelBody()
    .find('select#rulestatus_0')
    .should('have.value', '2');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_reschedule_svc_enable"]')
    .should('be.checked');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_execution_command_enable"]')
    .should('be.checked');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_execution_command"]')
    .should('have.value', data.snmp2.special_command);
  cy.getTrapSidePanelBody()
    .find('textarea[name="traps_comments"]')
    .should('have.value', data.snmp2.comments);
  // The relation and advanced fields are rendered in the same page as the main
  // ones (the tab nav only scrolls), so no tab click is needed here.
  cy.getTrapSidePanelBody()
    .find(`.select2-selection__choice[title*="${data.snmp2.serviceName}"]`)
    .should('exist');
  cy.getTrapSidePanelBody()
    .find(
      `.select2-selection__choice[title*="${data.snmp2.service_templates}"]`
    )
    .should('exist');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_routing_mode"]')
    .should('be.checked');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_routing_value"]')
    .should('have.value', data.snmp2.routing_definition);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_routing_filter_services"]')
    .should('have.value', data.snmp2.filter_services);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_timeout"]')
    .should('have.value', data.snmp2.timeout);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_exec_interval"]')
    .should('have.value', data.snmp2.execution_interval);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_output_transform"]')
    .should('have.value', data.snmp2.output_transform);
  cy.getTrapSidePanelBody()
    .find('textarea[name="traps_customcode"]')
    .should('have.value', data.snmp2.custom_code);
});

// Scenario: Duplicate SNMP trap definition
When('the user has duplicated one existing SNMP trap definition', () => {
  cy.openTrapsSnmpListing();
  cy.openTrapsAddForm('input[name="traps_name"]');
  trapsSnmpConfiguration({
    name: data.snmp1.name,
    oid: data.snmp1.oid,
    output: data.snmp1.output,
    regexp: data.snmp1.rule.regexp,
    severity: data.snmp1.rule.status,
    string: data.snmp1.rule.string,
    vendor: data.snmp1.vendor
  });
  submitForm();
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.runTrapsBulkAction(data.snmp1.name, 'Duplicate');
});

Then('all SNMP trap properties are unchanged except the name', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.openTrapsRowForm(`${data.snmp1.name}_1`, 'input[name="traps_name"]');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_name"]')
    .should('have.value', `${data.snmp1.name}_1`);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_oid"]')
    .should('have.value', data.snmp1.oid);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_args"]')
    .should('have.value', data.snmp1.output);
});

// Scenario: Delete SNMP trap definition
When('the user has deleted one existing SNMP trap definition', () => {
  cy.openTrapsSnmpListing();
  cy.openTrapsAddForm('input[name="traps_name"]');
  trapsSnmpConfiguration({
    name: data.snmp1.name,
    oid: data.snmp1.oid,
    output: data.snmp1.output,
    regexp: data.snmp1.rule.regexp,
    severity: data.snmp1.rule.status,
    string: data.snmp1.rule.string,
    vendor: data.snmp1.vendor
  });
  submitForm();
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.runTrapsBulkAction(data.snmp1.name, 'Delete');
});

Then('this definition disappears from the SNMP trap list', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.snmp1.name)
    .should('not.exist');
});
