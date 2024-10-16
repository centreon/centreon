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

Then("a service template is configured", () => {

});

When("the user changes the properties of a service template", () => {

});

Then("the properties are updated", () => {

});

When("the user duplicates a service template", () => {

});

Then("the new service template has the same properties", () => {

});

When("the user deletes a service template", () => {

});

Then("the deleted service template is not displayed in the list", () => {

});

afterEach(() => {
  cy.stopContainers();
});
