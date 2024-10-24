import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

import data from '../../../fixtures/services/meta_service.json';

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
  cy.getIframeBody().find('input[name="meta_name"]').type(data.name);
  cy.getIframeBody()
    .find('input[name="meta_display"]')
    .type(data.output_format);
  cy.getIframeBody().find('input[name="warning"]').type(data.warning_level);
  cy.getIframeBody().find('input[name="critical"]').type(data.critical_level);
  cy.getIframeBody().find('select[name="calcul_type"]').select(data.calculation_type);
  cy.getIframeBody().find('select[name="data_source_type"]').select(data.data_source_type);
  cy.getIframeBody()
    .find(`input[name*="meta_select_mode"][value=${data.selection_mode}]`)
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="regexp_str"]')
    .type(data.sql_like_clause_expression);
  cy.getIframeBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .click();
  cy.getIframeBody()
    .find(`div[title=${data.check_period}]`)
    .click();
  cy.getIframeBody()
    .find('input[name="max_check_attempts"]')
    .type(data.max_check_attempts);
  cy.getIframeBody().find('input[name="normal_check_interval"]').type(data.normal_check_interval);
  cy.getIframeBody().find('input[name="retry_check_interval"]').type(data.retry_check_interval);
  cy.getIframeBody()
    .find(`input[name*="notifications_enabled"][value=${data.notification_enabled}]`)
    .parent()
    .click();
  cy.getIframeBody().find('input[placeholder="Implied Contacts"]').click();
  cy.getIframeBody().contains(data.contacts).click();
  cy.getIframeBody()
    .find('input[placeholder = "Linked Contact Groups"]')
    .click();
  cy.getIframeBody().contains(data.contact_groups).click();
  cy.getIframeBody().find('input[name="notification_interval"]').type(data.notification_interval);
  cy.getIframeBody()
    .find("span#select2-notification_period-container")
    .click();
  cy.getIframeBody().contains(data.notification_period).click();
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .type(data.geo_coordinates);
  cy.getIframeBody().find('select[name="graph_id"]').select(data.graph_template);
  cy.getIframeBody()
    .find('textarea[name="meta_comment"]')
    .type("metaServiceComments");
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
});

When("the user changes the properties of a meta service", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody().contains(data.name).click();
  cy.waitForElementInIframe("#main-content", 'input[name="meta_name"]');
  cy.getIframeBody()
    .find('input[name="meta_name"]')
    .clear()
    .type("metaServiceNameChanged");
  cy.getIframeBody()
    .find('input[name="meta_display"]')
    .clear()
    .type("metaServiceOutputFormatChanged");
  cy.getIframeBody().find('input[name="warning"]').clear().type("50");
  cy.getIframeBody().find('input[name="critical"]').clear().type("75");
  cy.getIframeBody().find('select[name="calcul_type"]').select("Max");
  cy.getIframeBody().find('select[name="data_source_type"]').select("COUNTER");
  cy.getIframeBody()
    .find('input[name*="meta_select_mode"][value="1"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="regexp_str"]')
    .clear()
    .type("metaServiceExpressionChanged");
  cy.getIframeBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .click();
  cy.getIframeBody().contains("nonworkhours").click();
  cy.getIframeBody().find('input[name="max_check_attempts"]').clear().type("5");
  cy.getIframeBody().find('input[name="normal_check_interval"]').clear().type("10");
  cy.getIframeBody().find('input[name="retry_check_interval"]').clear().type("20");
  cy.getIframeBody()
    .find('input[name*="notifications_enabled"][value="2"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find(`li[title=${data.contact_groups}]`)
    .find('span[class*="choice__remove"]')
    .click();
  cy.getIframeBody().contains("Supervisors").click();
  cy.getIframeBody()
    .find(`li[title=${data.contacts}]`)
    .find('span[class*="choice__remove"]')
    .click();
  cy.getIframeBody().find(`div[title=${data.contact_groups}]`)
    .click();
  cy.getIframeBody().find('input[name="notification_interval"]').clear().type("12");
  cy.getIframeBody().find("span#select2-notification_period-container").click();
  cy.getIframeBody().contains("24x7").click();
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .clear()
    .type("2.3522219,48.856614");
  cy.getIframeBody().find('select[name="graph_id"]').select("Memory");
  cy.getIframeBody()
    .find('textarea[name="meta_comment"]')
    .clear()
    .type("metaServiceCommentsChanged");
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitC"]')
    .click();
});

Then("the properties are updated", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody().contains("metaServiceNameChanged").click();
  cy.waitForElementInIframe("#main-content", 'input[name="meta_name"]');
  cy.getIframeBody()
    .find('input[name="meta_name"]')
    .should("have.value", "metaServiceNameChanged");
  cy.getIframeBody()
    .find('input[name="meta_display"]')
    .should("have.value", "metaServiceOutputFormatChanged");
  cy.getIframeBody().find('input[name="warning"]').should("have.value", "50");
  cy.getIframeBody().find('input[name="critical"]').should("have.value", "75");
  cy.getIframeBody()
    .find('select[name="calcul_type"]')
    .find("option:selected")
    .should("have.value", "MAX");
  cy.getIframeBody()
    .find('select[name="data_source_type"]')
    .find("option:selected")
    .should("have.value", "1");
  cy.getIframeBody()
    .find('input[name*="meta_select_mode"][value="1"]')
    .should("be.checked");
  cy.getIframeBody()
    .find('input[name="regexp_str"]')
    .should("have.value", "metaServiceExpressionChanged");
  cy.getIframeBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .contains("nonworkhours")
    .should("be.visible");
  cy.getIframeBody().find('input[name="max_check_attempts"]').should("have.value", "5");
  cy.getIframeBody()
    .find('input[name="normal_check_interval"]')
    .should("have.value", "10");
  cy.getIframeBody()
    .find('input[name="retry_check_interval"]')
    .should("have.value", "20");
  cy.getIframeBody()
    .find('input[name*="notifications_enabled"][value="2"]')
    .should("be.checked");
  cy.getIframeBody().find(`li[title=Guest]`).contains("Guest").should("exist");
  cy.getIframeBody()
    .find('li[title="Supervisors"]')
    .contains("Supervisors")
    .should("exist");;
  cy.getIframeBody()
    .find('input[name="notification_interval"]')
    .should("have.value", "12");
  cy.getIframeBody()
    .find("span#select2-notification_period-container")
    .contains("24x7")
    .should("be.visible");
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .should("have.value", "2.3522219,48.856614");
  cy.getIframeBody()
    .find('select[name="graph_id"]')
    .find("option:selected")
    .should("have.value", "4");
  cy.getIframeBody()
    .find('textarea[name="meta_comment"]')
    .should("have.value", "metaServiceCommentsChanged");
});

When("the user duplicates a meta service", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody()
    .find("td.ListColLeft")
    .contains("a", data.name)
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
    .contains("a", "metaServiceName_1")
    .click();
  cy.waitForElementInIframe("#main-content", 'input[name="meta_name"]');
  cy.getIframeBody()
    .find('input[name="meta_name"]')
    .should("have.value", "metaServiceName_1");
  cy.getIframeBody()
    .find('input[name="meta_display"]')
    .should("have.value", data.output_format);
  cy.getIframeBody()
    .find('input[name="warning"]')
    .should("have.value", data.warning_level);
  cy.getIframeBody().find('input[name="critical"]').should("have.value", data.critical_level);
  cy.getIframeBody()
    .find('select[name="calcul_type"]')
    .find("option:selected")
    .should("have.value", "SOM");
  cy.getIframeBody()
    .find('select[name="data_source_type"]')
    .find("option:selected")
    .should("have.value", "3");
  cy.getIframeBody()
    .find('input[name*="meta_select_mode"][value="2"]')
    .should("be.checked");
  cy.getIframeBody()
    .find('input[name="regexp_str"]')
    .should("have.value", data.sql_like_clause_expression);
  cy.getIframeBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .contains(data.check_period)
    .should("be.visible");
  cy.getIframeBody()
    .find('input[name="max_check_attempts"]')
    .should("have.value", data.max_check_attempts);
  cy.getIframeBody()
    .find('input[name="normal_check_interval"]')
    .should("have.value", data.normal_check_interval);
  cy.getIframeBody()
    .find('input[name="retry_check_interval"]')
    .should("have.value", data.retry_check_interval);
  cy.getIframeBody()
    .find('input[name*="notifications_enabled"][value="1"]')
    .should("be.checked");
  cy.getIframeBody().find(`li[title=${data.contacts}]`).contains(data.contacts).should("exist");
  cy.getIframeBody()
    .find(`li[title=${data.contact_groups}]`)
    .contains(data.contact_groups)
    .should("exist");
  cy.getIframeBody()
    .find('input[name="notification_interval"]')
    .should("have.value", data.notification_interval);
  cy.getIframeBody()
    .find("span#select2-notification_period-container")
    .contains(data.notification_period)
    .should("be.visible");
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .should("have.value", data.geo_coordinates);
  cy.getIframeBody()
    .find('select[name="graph_id"]')
    .find("option:selected")
    .should("have.value", "2");
  cy.getIframeBody()
    .find('textarea[name="meta_comment"]')
    .should("have.value", data.comments);
});

When("the user deletes a meta service", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchMS"]');
  cy.getIframeBody()
    .find("td.ListColLeft")
    .contains("a", data.name)
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
    .contains(data.name)
    .should("not.exist");
});

afterEach(() => {
  cy.stopContainers();
});
