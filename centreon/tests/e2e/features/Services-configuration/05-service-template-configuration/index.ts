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
    cy.addServiceTemplate({
      name: "service_template",
      template: "generic-service",
    });
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
  cy.navigateTo({
    page: "Templates",
    rootItemNumber: 3,
    subMenu: "Services",
  });
 cy.enterIframe("iframe#main-content")
   .find("table tbody")
   .find("tr.list_two")
   .each(($row) => {
     cy.wrap($row)
       .find("td.ListColLeft")
       .then(($td) => {
         if ($td.text().includes("service_template")) {
           cy.wrap($row)
             .find("td.ListColPicker")
             .find("div.md-checkbox")
             .click();
         }
       });
   });
 cy.enterIframe("iframe#main-content")
   .find("table.ToolbarTable tbody")
   .find("td.Toolbar_TDSelectAction_Bottom")
   .find("select")
   .invoke(
     "attr",
     "onchange",
     "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
   );
 cy.enterIframe("iframe#main-content")
   .find("table.ToolbarTable tbody")
   .find("td.Toolbar_TDSelectAction_Bottom")
   .find("select")
   .select("Duplicate");
});

Then("the new service template has the same properties", () => {
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable")
    .find("tr.list_one")
    .find("td.ListColLeft")
    .contains("service_template_1")
    .click();
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_one")
    .find("td.FormRowValue")
    .find('input[name="service_alias"]')
    .should("have.value", "service_template_1");
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_one")
    .find("td.FormRowValue")
    .find('input[name="service_description"]')
    .should("have.value", "service_template");
});

When("the user deletes a service template", () => {
  cy.navigateTo({
    page: "Templates",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.enterIframe("iframe#main-content")
    .find("table tbody")
    .find("tr.list_two")
    .each(($row) => {
      cy.wrap($row)
        .find("td.ListColLeft")
        .then(($td) => {
          if ($td.text().includes("service_template")) {
            cy.wrap($row)
              .find("td.ListColPicker")
              .find("div.md-checkbox")
              .click();
          }
        });
    });
  cy.enterIframe("iframe#main-content")
    .find("table.ToolbarTable tbody")
    .find("td.Toolbar_TDSelectAction_Bottom")
    .find("select")
    .invoke(
      "attr",
      "onchange",
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
    );
  cy.enterIframe("iframe#main-content")
    .find("table.ToolbarTable tbody")
    .find("td.Toolbar_TDSelectAction_Bottom")
    .find("select")
    .select("Delete");
});

Then("the deleted service template is not displayed in the list", () => {
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable tbody")
    .contains("service_template")
    .should("not.exist");
});

afterEach(() => {
  cy.stopContainers();
});
