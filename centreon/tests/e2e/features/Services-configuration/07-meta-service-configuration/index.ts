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

Then("a meta service is configured", () => {
  cy.navigateTo({
    page: "Meta Services",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody().find("a.bt_success").contains("Add").click();
  cy.waitForElementInIframe("#main-content", 'input[name="meta_name"]');
  cy.getIframeBody().find('input[name="meta_name"]').type("meta_service_name");
  cy.getIframeBody().find('input[name="meta_display"]').type("meta_display");
  cy.getIframeBody().find('input[name="warning"]').type("2");
  cy.getIframeBody().find('input[name="critical"]').type("0");
  cy.getIframeBody().find('input[name="max_check_attempts"]').type("2");
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
});

When("the user changes the properties of a meta service", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody().contains("meta_service_name").click();
  cy.waitForElementInIframe("#main-content", 'input[name="meta_name"]');
  cy.getIframeBody()
    .find('input[name="meta_name"]')
    .clear()
    .type("meta_service_name_modified");
  cy.getIframeBody()
    .find('input[name="meta_display"]')
    .clear()
    .type("meta_display_modified");
  cy.getIframeBody()
    .find('input[name="warning"]')
    .clear()
    .type("0");
  cy.getIframeBody().find('input[name="critical"]').clear().type("1");
  cy.getIframeBody().find('input[name="max_check_attempts"]').clear().type("3");
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitC"]')
    .click();
});

Then("the properties are updated", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody().contains("meta_service_name").click();
  cy.waitForElementInIframe("#main-content", 'input[name="meta_name"]');
  cy.getIframeBody()
    .find('input[name="meta_name"]')
    .should("have.value", "meta_service_name_modified");
  cy.getIframeBody()
    .find('input[name="meta_display"]')
    .should("have.value", "meta_display_modified");
  cy.getIframeBody()
    .find('input[name="warning"]')
    .should("have.value", "0");
  cy.getIframeBody().find('input[name="critical"]').should("have.value", "1");
  cy.getIframeBody()
    .find('input[name="max_check_attempts"]')
    .should("have.value", "3");
});

When("the user duplicates a meta service", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody()
    .find("td.ListColLeft")
    .contains("a", "meta_service_name")
    .parents("tr")
    .within(() => {
      cy.get("td.ListColPicker").find("div.md-checkbox").click();
    });
  cy.getIframeBody()
    .find('select[name="o2"]')
    .invoke(
      "attr",
      "onchange",
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
    );
  cy.getIframeBody()
    .find('select[name="o2"]')
    .select("Duplicate");
});

Then("the new meta service has the same properties", () => {
  cy.reload();
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody()
    .find("td.ListColLeft")
    .contains("a", "meta_service_name_1")
    .click();
  cy.waitForElementInIframe("#main-content", 'input[name="meta_name"]');
  cy.getIframeBody()
    .find('input[name="meta_name"]')
    .should("have.value", "meta_service_name_1");
  cy.getIframeBody()
    .find('input[name="meta_display"]')
    .should("have.value", "meta_display");
  cy.getIframeBody().find('input[name="warning"]').should("have.value", "2");
  cy.getIframeBody().find('input[name="critical"]').should("have.value", "0");
  cy.getIframeBody()
    .find('input[name="max_check_attempts"]')
    .should("have.value", "2");
});

When("the user deletes a meta service", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody()
    .find("td.ListColLeft")
    .contains("a", "meta_service_name")
    .parents("tr")
    .within(() => {
      cy.get("td.ListColPicker").find("div.md-checkbox").click();
    });
  cy.getIframeBody()
    .find('select[name="o2"]')
    .invoke(
      "attr",
      "onchange",
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
    );
  cy.getIframeBody().find('select[name="o2"]').select("Delete");
});

Then("the deleted meta service is not displayed in the list", () => {
  cy.enterIframe("iframe#main-content")
    .find("table.ListTable tbody")
    .contains("meta_service_name")
    .should("not.exist");
});

afterEach(() => {
  cy.stopContainers();
});
