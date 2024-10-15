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

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: "admin" });
});

When('a service is configured', () => {
  cy.setUserTokenApiV1();
  cy.addHost({
  hostGroup: "Linux-Servers",
  name: "host_1",
  template: "generic-host",
  })
  .addService({
  activeCheckEnabled: false,
  host: "host_1",
  maxCheckAttempts: 1,
  name: "test",
  template: "Ping-LAN",
});
});

When('the user changes the properties of a service', () => {
  cy.navigateTo({
    page: "Services by host",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.ListTable")
    .find("tr.list_one")
    .find("td.ListColLeft")
    .contains("test")
    .click();
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.formTable")
    .find("tr.list_two")
    .find("td.FormRowValue")
    .find('input[name="service_description"]')
    .clear()
    .type("test_modified");
  cy.getIframeBody()
    .find("table tr.list_one")
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

Then('the properties are updated', () => {
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table.ListTable")
      .find("tr.list_one")
      .find("td.ListColLeft")
      .contains("test")
      .click();
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table.formTable")
      .find("tr.list_two")
      .find("td.FormRowValue")
      .find('input[name="service_description"]')
      .should('have.value',"test_modified");
  cy.getIframeBody()
    .find("table tr.list_one")
    .find("td.FormRowValue")
    .find("select#service_template_model_stm_id")
    .contains("Ping-WAN")
    .should("exist");
});

When('the user duplicates a service', () => {
  cy.navigateTo({
    page: "Services by host",
    rootItemNumber: 3,
    subMenu: "Services",
  });
cy.get("iframe#main-content")
  .its("0.contentDocument.body")
  .find("table tbody")
  .find("tr.list_one")
  .each(($row) => {
    cy.wrap($row)
      .find("td.ListColLeft")
      .then(($td) => {
        if ($td.text().includes("host_1")) {
          cy.wrap($row)
            .find("td.ListColPicker")
            .find("div.md-checkbox")
            .click();
        }
      });
  });
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

Then('the new service has the same properties', () => {
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.ListTable")
    .find("tr.list_two")
    .find("td.ListColLeft")
    .contains("test_1")
    .click();
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table.formTable")
      .find("tr.list_two")
      .find("td.FormRowValue")
      .find('input[name="service_description"]')
      .should("have.value", "test_1");
    cy.getIframeBody()
      .find("table tr.list_one")
      .find("td.FormRowValue")
      .find("select#service_template_model_stm_id")
      .contains("Ping-WAN")
      .should("exist");
});

When('the user deletes a service', () => {
  cy.navigateTo({
    page: "Services by host",
    rootItemNumber: 3,
    subMenu: "Services",
  });
cy.get("iframe#main-content")
  .its("0.contentDocument.body")
  .find("table tbody")
  .find("tr.list_one")
  .each(($row) => {
    cy.wrap($row)
      .find("td.ListColLeft")
      .then(($td) => {
        if ($td.text().includes("host_1")) {
          cy.wrap($row)
            .find("td.ListColPicker")
            .find("div.md-checkbox")
            .click();
        }
      });
  });
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

Then('the deleted service is not displayed in the service list', () => {
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.ListTable tbody")
    .contains("test")
    .should("not.exist");
});

afterEach(() => {
  cy.stopContainers();
});
