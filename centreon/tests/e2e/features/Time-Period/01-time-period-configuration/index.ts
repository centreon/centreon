import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

import {
  setTimePeriod,
  navigateToTimePeriodsAndInitiateAddition,
  submitForm,
} from "../common";

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

When('a user creates a time period with separated holidays dates excluded', () => {
  navigateToTimePeriodsAndInitiateAddition();
  setTimePeriod();
});

Then('all properties of my time period are saved', () => {
  submitForm();
});

When("a user creates a time period with a range of dates to exclude", () => {
  navigateToTimePeriodsAndInitiateAddition();
  cy.getIframeBody().find('input[name="tp_name"]').type("timePeriodName");
  cy.getIframeBody().find('input[name="tp_alias"]').type("timePeriodAlias");
  cy.getIframeBody().find('input[name="tp_sunday"]').type("14:00-16:00");
  cy.getIframeBody()
    .find('input[name="tp_monday"]')
    .type("07:00-12:00,13:00-18:00");
  cy.getIframeBody().find('input[name="tp_tuesday"]').type("07:00-18:00");
  cy.getIframeBody()
    .find('input[name="tp_wednesday"]')
    .type("07:00-12:00,13:00-17:00");
  cy.getIframeBody()
    .find('input[name="tp_thursday"]')
    .type("14:00-16:00");
  cy.getIframeBody().find('input[name="tp_friday"]').type("07:00-18:00");
  cy.getIframeBody().find('input[name="tp_saturday"]').type("10:00-16:00");
  cy.getIframeBody().find("li#c2").click();
  cy.getIframeBody().contains("+ Add new entry").click();
  cy.getIframeBody().find("input#exceptionInput_0").type("august 1 - 31");
  cy.getIframeBody().find("input#exceptionTimerange_0").type("00:00-24:00");
});

Then("all properties of my time period are saved with the exclusions", () => {
  submitForm();
});

Given("an existing time period", () => {
  navigateToTimePeriodsAndInitiateAddition();
  setTimePeriod();
  submitForm();
});

When("a user duplicates the time period", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchTP"]');
  cy.enterIframe("iframe#main-content")
    .find('tr[class*="list_"]')
    .each(($row) => {
      cy.wrap($row)
        .find("td.ListColLeft")
        .then(($td) => {
          if ($td.text().includes("timePeriodName")) {
            cy.wrap($row)
              .find("td.ListColPicker")
              .find("div.md-checkbox")
              .click();
          }
        });
    });

  cy.enterIframe("iframe#main-content")
    .find("table.ToolbarTable tbody")
    .find("tr.ToolbarTR")
    .eq(1)
    .find("select")
    .contains("More actions...")
    .parent()
    .invoke(
      "attr",
      "onchange",
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
    );
  cy.enterIframe("iframe#main-content")
    .find("table.ToolbarTable tbody")
    .find("tr.ToolbarTR")
    .eq(1)
    .find("select")
    .contains("More actions...")
    .parent()
    .select("Duplicate");
});

Then(
  "a new time period is created with identical properties except the name",
  () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchTP"]');
   cy.enterIframe("iframe#main-content")
     .find("table.ListTable")
     .find("tr.list_one")
     .find("td.ListColLeft")
     .contains("timePeriodName_1")
     .click();
  cy.waitForElementInIframe("#main-content", 'input[name="tp_name"]');
  cy.getIframeBody().find('input[name="tp_name"]').should("have.value", "timePeriodName_1");
  cy.getIframeBody().find('input[name="tp_alias"]').should("have.value", "timePeriodAlias");
  cy.getIframeBody()
    .find('input[name="tp_sunday"]')
    .should("have.value", "14:00-16:00");
  cy.getIframeBody()
    .find('input[name="tp_monday"]')
    .should("have.value", "07:00-12:00,13:00-18:00");
  cy.getIframeBody()
    .find('input[name="tp_tuesday"]')
    .should("have.value", "07:00-18:00");
  cy.getIframeBody()
    .find('input[name="tp_wednesday"]')
    .should("have.value", "07:00-12:00,13:00-17:00");
  cy.getIframeBody()
    .find('input[name="tp_thursday"]')
    .should("have.value", "14:00-16:00");
  cy.getIframeBody()
    .find('input[name="tp_friday"]')
    .should("have.value", "07:00-18:00");
  cy.getIframeBody()
    .find('input[name="tp_saturday"]')
    .should("have.value", "10:00-16:00");
  },
);

When("a user deletes the time period", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchTP"]');
  cy.getIframeBody()
    .find('tr[class*="list_"]')
    .each(($row) => {
      cy.wrap($row)
        .find("td.ListColLeft")
        .then(($td) => {
          if ($td.text().includes("timePeriodName")) {
            cy.wrap($row)
              .find("td.ListColPicker")
              .find("div.md-checkbox")
              .click();
          }
        });
    });

  cy.enterIframe("iframe#main-content")
    .find("table.ToolbarTable tbody")
    .find("tr.ToolbarTR")
    .eq(1)
    .find("select")
    .contains("More actions...")
    .parent()
    .invoke(
      "attr",
      "onchange",
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }",
    );
  cy.enterIframe("iframe#main-content")
    .find("table.ToolbarTable tbody")
    .find("tr.ToolbarTR")
    .eq(1)
    .find("select")
    .contains("More actions...")
    .parent()
    .select("Delete");
});

Then("the time period disappears from the time periods list", () => {
   cy.waitForElementInIframe("#main-content", 'input[name="searchTP"]');
   cy.enterIframe("iframe#main-content")
     .find("table.ListTable")
     .find("tr.list_one")
     .find("td.ListColLeft")
     .contains("timePeriodName")
     .should("not.exist");
});


afterEach(() => {
  cy.stopContainers();
});