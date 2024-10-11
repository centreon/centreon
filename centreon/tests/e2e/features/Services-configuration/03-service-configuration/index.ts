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

Given('a user is logged in to Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: "admin" });
});

When('a service is configured', () => {
cy.addHost({
  hostGroup: "Linux-Servers",
  name: "host_1",
  template: "generic-host",
}).addService({
  activeCheckEnabled: false,
  host: "host_1",
  maxCheckAttempts: 1,
  name: "service",
  template: "Ping-LAN",
});
});

When('the user changes the properties of a service', () => {
  cy.navigateTo({
    page: "Services by host",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  //check properties of the service created
});

Then('the properties are updated', () => {

});

When('the user duplicates a service', () => {

});

Then('the new service has the same properties', () => {

});

When('the user deletes a service', () => {

});

Then('the deleted service is not displayed in the service list', () => {

});

afterEach(() => {
  cy.stopContainers();
});