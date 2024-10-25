import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

import data from '../../../fixtures/snmp-traps/snmp-trap.json'
import {
  TrapsSNMPConfiguration,
  submitForm,
  UpdateTrapsSNMPConfiguration
} from "../common";

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: "GET",
    url: "/centreon/include/common/userTimezone.php",
  }).as("getUserTimezone");
  cy.intercept({
    method: "GET",
    url: "/centreon/api/internal.php?object=centreon_topology&action=navigationList",
  }).as("getNavigationList");
});

Given("a user is logged in Centreon", () => {
  cy.loginByTypeOfUser({ jsonName: "admin" });
});

// Scenario: Creating SNMP trap with advanced matching rule
When(
  "the user adds a new SNMP trap definition with an advanced matching rule",
  () => {
  cy.navigateTo({
    page: "SNMP Traps",
    rootItemNumber: 3,
    subMenu: "SNMP Traps",
  });
  cy.waitForElementInIframe("#main-content", 'input[name="searchT"]');
  cy.getIframeBody().find("a.bt_success").contains("Add").click();
  TrapsSNMPConfiguration({
    name: data.snmp1.name,
    vendor: data.snmp1.vendor,
    oid: data.snmp1.oid,
    output: data.snmp1.output,
    string: data.snmp1.rule.string,
    regexp: data.snmp1.rule.regexp,
    severity: data.snmp1.rule.severity,
  });
  submitForm();
  },
);

Then(
  "the trap definition is saved with its properties, especially the content of Regexp field",
  () => {
  submitForm();
  cy.waitForElementInIframe("#main-content", 'input[name="traps_name"]');
  cy.getIframeBody()
   .contains(data.snmp2.name,)
   .click();
  },
);

// Scenario: Modify SNMP trap definition
When(
  "the user modifies some properties of an existing SNMP trap definition",
  () => {
  cy.setUserTokenApiV1();
  cy.addHost({
    activeCheckEnabled: false,
    alias: data.snmp2.hostName,
    name: data.snmp2.hostName,
    address: "1.2.3.4",
    checkCommand: "check_centreon_cpu",
    template: "generic-host",
  });
  cy.addService({
    activeCheckEnabled: false,
    host: data.snmp2.hostName,
    maxCheckAttempts: 1,
    name: data.snmp2.serviceName,
    template: "Ping-LAN",
  });
  cy.addServiceTemplate({
    name: data.snmp2.service_templates
    template: "generic-service",
  });
  cy.navigateTo({
    page: "SNMP Traps",
    rootItemNumber: 3,
    subMenu: "SNMP Traps",
  });
  cy.waitForElementInIframe("#main-content", 'input[name="searchT"]');
  cy.getIframeBody().find("a.bt_success").contains("Add").click();
  TrapsSNMPConfiguration({
    name: data.snmp1.name,
    vendor: data.snmp1.vendor,
    oid: data.snmp1.oid,
    output: data.snmp1.output,
    string: data.snmp1.rule.string,
    regexp: data.snmp1.rule.regexp,
    severity: data.snmp1.rule.severity,
  });
  submitForm();
 cy.getIframeBody()
   .contains(data.snmp1.name)
   .click();
  UpdateTrapsSNMPConfiguration({
    name: data.snmp2.name,
    vendor: data.snmp2.vendor,
    oid: data.snmp2.oid,
    output: data.snmp2.output,
    mode: data.snmp2.mode,
    status: data.snmp2.status,
    behavior: data.snmp2.behavior,
    string: data.snmp2.rule.string,
    regexp: data.snmp2.rule.regexp,
    severity: data.snmp2.rule.severity,
    special_command: data.snmp2.special_command,
    comments: data.snmp2.comments,
    serviceName: data.snmp2.serviceName,
    service_templates: data.snmp2.service_templates,
    routing_definition: data.snmp2.routing_definition,
    filter_services: data.snmp2.filter_services,
    timeout: data.snmp2.timeout,
    execution_interval: data.snmp2.execution_interval,
    output_transform: data.snmp2.output_transform,
    custom_code: data.snmp2.custom_code,
  });
  },
);

Then("all changes are saved", () => {

});

// Scenario: Duplicate SNMP trap definition
When("the user has duplicated one existing SNMP trap definition", () => {

});

Then("all SNMP trap properties are updated", () => {

});

// Scenario: Delete SNMP trap definition
When("the user has deleted one existing SNMP trap definition", () => {

});

Then("this definition disappears from the SNMP trap list", () => {

});
