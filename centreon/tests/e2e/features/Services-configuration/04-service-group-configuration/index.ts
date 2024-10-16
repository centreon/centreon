import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

import data from "../../../fixtures/services/service.json";

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
  cy.setUserTokenApiV1();
  cy.addHostGroup({
    name: data.hostGroups.hostGroup1.name,
  })
    .addHost({
      activeCheckEnabled: false,
      checkCommand: "check_centreon_cpu",
      hostGroup: data.hostGroups.hostGroup1.name,
      name: data.hosts.host1.name,
      template: "generic-host",
    })
    .addService({
      activeCheckEnabled: false,
      host: data.hosts.host1.name,
      maxCheckAttempts: 1,
      name: data.services.service1.name,
      template: "Ping-LAN",
    })
    .addServiceGroup({
      name: data.service_group.service1.name,
      hostsAndServices: [[data.hosts.host1.name, data.services.service1.name]],
    });
});

When("the user changes the properties of a service group", () => {
  cy.navigateTo({
    page: "Service Groups",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable")
    .find("tr.list_one")
    .find("td.ListColLeft")
    .contains(data.service_group.service1.name)
    .click();
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_one")
    .find("td.FormRowValue")
    .find('input[name="sg_name"]')
    .clear()
    .type("test_modified");
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_two")
    .find("td.FormRowValue")
    .find('input[name="sg_alias"]')
    .clear()
    .type("description_modified");
  cy.enterIframe("iframe#main-content")
    .find("td.FormRowValue")
    .find("select#sg_hServices")
    .next()
    .click();
  cy.getIframeBody().contains("Centreon-Server - Memory").click();
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitC"]')
    .click();
});

Then("the properties of the service group are updated", () => {
  cy.navigateTo({
    page: "Service Groups",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable")
    .find("tr.list_one")
    .find("td.ListColLeft")
    .contains("test_modified")
    .click();
 cy.enterIframe("iframe#main-content")
   .find("table.formTable")
   .find("tr.list_one")
   .find("td.FormRowValue")
   .find('input[name="sg_name"]')
   .should("have.value", "test_modified");
 cy.enterIframe("iframe#main-content")
   .find("table.formTable")
   .find("tr.list_two")
   .find("td.FormRowValue")
   .find('input[name="sg_alias"]')
   .should("have.value", "description_modified");
  cy.enterIframe("iframe#main-content")
    .find("table tr.list_one")
    .find("td.FormRowValue")
    .find("select#sg_hServices")
    .contains("Centreon-Server - Memory")
    .should("exist");
});

When("the user duplicates a service group", () => {
  cy.navigateTo({
    page: "Service Groups",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.enterIframe("iframe#main-content")
    .find("table tbody")
    .find("tr.list_one")
    .each(($row) => {
      cy.wrap($row)
        .find("td.ListColLeft")
        .then(($td) => {
          if ($td.text().includes(data.service_group.service1.name)) {
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

Then("the new service group has the same properties", () => {
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable")
    .find("tr.list_two")
    .find("td.ListColLeft")
    .contains(`${data.service_group.service1.name}_1`)
    .click();
  cy.enterIframe("iframe#main-content")
    .find("table.formTable")
    .find("tr.list_one")
    .find("td.FormRowValue")
    .find('input[name="sg_name"]')
    .should("have.value", `${data.service_group.service1.name}_1`);
 cy.enterIframe("iframe#main-content")
   .find("table.formTable")
   .find("tr.list_two")
   .find("td.FormRowValue")
   .find('input[name="sg_alias"]')
   .should("have.value", `${data.service_group.service1.name}`);
 cy.enterIframe("iframe#main-content")
   .find("table tr.list_one")
   .find("td.FormRowValue")
   .find("select#sg_hServices")
   .contains(`${data.hosts.host1.name}`)
   .should("exist");
});

When("the user deletes a service group", () => {
  cy.navigateTo({
    page: "Service Groups",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.enterIframe("iframe#main-content")
    .find("table tbody")
    .find("tr.list_one")
    .each(($row) => {
      cy.wrap($row)
        .find("td.ListColLeft")
        .then(($td) => {
          if ($td.text().includes(data.service_group.service1.name)) {
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

Then(
  "the deleted service group is not displayed in the service group list",
  () => {
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable tbody")
    .contains(data.service_group.service1.name)
    .should("not.exist");
  },
);

afterEach(() => {
  cy.stopContainers();
});
