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

Then("a service group is configured", () => {

});

When("I change the properties of a service group", () => {

});

Then("the properties of the service group are updated", () => {

});

// Scenario: Duplicate one existing service group
When("I duplicate a service group", () => {

});

Then("the new service group has the same properties", () => {

});

// Scenario: Delete one existing service group
When("I delete a service group", () => {

});

Then(
  "the deleted service group is not displayed in the service group list",
  () => {

  },
);


afterEach(() => {
  cy.stopContainers();
});
