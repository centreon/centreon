import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

beforeEach(() => {
//   cy.startContainers();
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
  cy.setUserTokenApiV1();
  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: "check_centreon_cpu",
    hostGroup: '',
    name: 'host_1',
    template: "generic-host",
  })
    .addService({
      activeCheckEnabled: false,
      host: "host_1",
      maxCheckAttempts: 1,
      name: "test",
      template: "Ping-LAN",
    })
    .addServiceGroup({
      name: "sg",
      hostsAndServices: null,
    });
});

When("the user changes the properties of a service group", () => {});

Then("the properties of the service group are updated", () => {

});

// Scenario: Duplicate one existing service group
When("the user duplicates a service group", () => {});

Then("the new service group has the same properties", () => {

});

// Scenario: Delete one existing service group
When("the user deletes a service group", () => {});

Then(
  "the deleted service group is not displayed in the service group list",
  () => {

  },
);


afterEach(() => {
  cy.stopContainers();
});
