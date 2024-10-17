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

Then("a service template is configured", () => {
    // cy.setUserTokenApiV1();
    // cy.addServiceTemplate({
    //   name: "service_template",
    //   template: "generic-service",
    // });
});

When("the user changes the properties of a service template", () => {
  cy.navigateTo({
    page: "Templates",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable")
    .find("tr.list_two")
    .find("td.ListColLeft")
    .contains("service_template")
    .click();
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_one")
    .find("td.FormRowValue")
    .find('input[name="service_alias"]')
    .clear()
    .type("service_template_modified");
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_two")
    .find("td.FormRowValue")
    .find('input[name="service_description"]')
    .clear()
    .type("template_desp_modified");
  cy.enterIframe("iframe#main-content")
    .find("td.FormRowValue")
    .find("select#service_template_model_stm_id")
    .next()
    .click();
  cy.getIframeBody().contains("Ping-WAN").click();
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitC"]')
    .click();
});

Then("the properties are updated", () => {
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable")
    .find("tr.list_two")
    .find("td.ListColLeft")
    .contains("service_template_modified")
    .click();
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_one")
    .find("td.FormRowValue")
    .find('input[name="service_alias"]')
    .should("have.value", "service_template_modified");
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_two")
    .find("td.FormRowValue")
    .find('input[name="service_description"]')
    .should("have.value", "template_desp_modified");
  cy.enterIframe("iframe#main-content")
    .find("table tr.list_one")
    .find("td.FormRowValue")
    .find("select#service_template_model_stm_id")
    .contains("Ping-WAN")
    .should("exist");
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
