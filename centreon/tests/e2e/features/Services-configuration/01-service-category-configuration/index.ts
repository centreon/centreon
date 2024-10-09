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

Then("a service category is configured", () => {
    cy.navigateTo({
      page: "Categories",
      rootItemNumber: 3,
      subMenu: "Services",
    });
    cy.getIframeBody()
      .find("tr.ToolbarTR")
      .find('.btc.bt_success')
      .contains('Add')
      .click();
    cy.get('iframe#main-content')
        .its('0.contentDocument.body')
      .find("table > tbody > tr.list_one > td.FormRowValue")
      .find('input[name="sc_name"]')
      .type("test");
    cy.getIframeBody()
      .find("table tr.list_two td.FormRowValue")
      .find('input[name="sc_description"]')
      .type("test description");
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table tr.list_two")
      .find("td.FormRowValue")
      .find("select#sc_svcTpl")
      .next()
      .click();
    cy.getIframeBody()
      .contains('Ping-LAN')
      .click();
    cy.getIframeBody()
      .find("div#validForm")
      .find("p.oreonbutton")
      .find('.btc.bt_success[name="submitA"]')
      .click();

});

When("the user change the properties of a service category", () => {
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find('table tbody')
      .contains('test')
      .click();
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table tr.list_two td.FormRowValue")
      .find('input[name="sc_description"]')
      .clear()
      .type("test description modified");
    cy.getIframeBody()
      .find("div#validForm")
      .find("p.oreonbutton")
      .find('.btc.bt_success[name="submitC"]')
      .click();
});

Then("the properties are updated", () => {
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table tbody")
    .contains("test description modified");
});

When("the user duplicate a service category", () => {
  cy.navigateTo({
       page: "Categories",
       rootItemNumber: 3,
       subMenu: "Services",
  });
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table tbody")
    .find("tr.list_one")
    .find("td.ListColPicker")
    .find("div.md-checkbox")
    .eq(1)
    .click();
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table.ToolbarTable tbody")
      .find("td.Toolbar_TDSelectAction_Bottom")
      .find("select")
      .invoke(
        "attr",
        "onchange",
        "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
      );
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.ToolbarTable tbody")
    .find("td.Toolbar_TDSelectAction_Bottom")
    .find("select")
    .select("Duplicate");
});

Then("the new service category has the same properties", () => {
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table tbody")
    .contains("Ping_1")
    .should("be.visible");
});

When("the user delete a service category", () => {
    cy.navigateTo({
      page: "Categories",
      rootItemNumber: 3,
      subMenu: "Services",
    });
    cy.on("window:confirm", () => true);
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table tbody")
      .find("tr.list_one")
      .find("td.ListColPicker")
      .find("div.md-checkbox")
      .eq(2)
      .click();
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table.ToolbarTable tbody")
      .find("td.Toolbar_TDSelectAction_Bottom")
      .find("select")
      .invoke(
        "attr",
        "onchange",
        "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
      );
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table.ToolbarTable tbody")
      .find("td.Toolbar_TDSelectAction_Bottom")
      .find("select")
      .select("Delete");
});

Then("the deleted service category is not displayed in the list", () => {
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table tbody")
      .contains("test")
      .should("not.exist");
});
