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

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: "admin" });
});

When('a service is configured', () => {
//   cy.setUserTokenApiV1();
//   cy.addHost({
//   hostGroup: "Linux-Servers",
//   name: "host_1",
//   template: "generic-host",
//   })
//   .addService({
//   activeCheckEnabled: false,
//   host: "host_1",
//   maxCheckAttempts: 1,
//   name: "kiwi",
//   template: "Ping-LAN",
// });
});

When('the user changes the properties of a service', () => {
    // cy.addService({
    //   activeCheckEnabled: true, // Enable active checks
    //   checkCommand: "check_http", // Set a check command
    //   checkPeriod: "24x7", // Set the check period
    //   host: "host_1", // Name of the host
    //   maxCheckAttempts: 3, // Set maximum check attempts
    //   name: "service", // Name of the service
    //   passiveCheckEnabled: false, // Disable passive checks
    //   template: "generic-service", // Leave the template empty as you're not adding a new service
    // });
  cy.navigateTo({
    page: "Services by host",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  //check properties of the service created
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.ListTable")
    .find("tr.list_one")
    .find("td.ListColLeft")
    .contains("kiwi")
    .click();
  cy.getIframeBody()
    .find("table.formTable")
    .find("tr.list_two")
    .find("td.FormRowValue")
    .find('input[name="service_description"]')
    .clear()
    .type("kiwi_modified");
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table tr.list_two")
    .find("td.FormRowValue")
    .find("select#service_template_model_stm_id")
    .next()
    .click();
  cy.getIframeBody().contains("Ping-WAN").click();
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
});

Then('the properties are updated', () => {
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table tbody")
      .contains("kiwi")
      .click();
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table tr.list_two td.FormRowValue")
      .find('input[name="service_description"]')
      .contains("kiwi_modified");

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