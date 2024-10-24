import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

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
  cy.waitForElementInIframe("#main-content", 'input[name="searchT"]');
  cy.getIframeBody().find("a.bt_success").contains("Add").click();
  cy.waitForElementInIframe("#main-content", 'input[name="traps_name"]');
  cy.getIframeBody().find('input[name="traps_name"]').type(data.name);
  },
);

Then(
  "the trap definition is saved with its properties, especially the content of Regexp field",
  () => {

  },
);

// Scenario: Modify SNMP trap definition
When(
  "the user modifies some properties of an existing SNMP trap definition",
  () => {

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
