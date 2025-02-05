import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

import data from "../../../fixtures/notifications/data-for-notification.json";

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

Given("An admin user is logged in Centreon", () => {
  cy.loginByTypeOfUser({ jsonName: "admin" });
});

Given("a service with notifications enabled", () => {
  cy.addHostGroup({
    name: data.hostGroups.hostGroup1.name,
  });

  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: "check_centreon_cpu",
    hostGroup: data.hostGroups.hostGroup1.name,
    name: data.hosts.host1.name,
    template: "generic-host",
  });
  cy.navigateTo({
    page: "Services by host",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.getIframeBody()
    .find("tr.ToolbarTR")
    .find(".btc.bt_success")
    .contains("Add")
    .click();
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.formTable")
    .find("td.FormRowValue")
    .find('input[name="service_description"]')
    .type("service");
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.formTable")
    .find("td.FormRowValue")
    .find("p.oreonbutton")
    .find("span.selection")
    .find('input[placeholder="Hosts"]')
    .click();
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("button.btc.bt_info")
    .click();
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("button.btc.bt_success")
    .click();
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find('span[aria-labelledby="select2-command_command_id-container"]')
    .click();
  cy.getIframeBody().contains("check_centreon_ping").click();
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
  cy.setUserTokenApiV1();
  cy.setServiceParameters({
    name: data.hosts.host1.name,
    paramName: "notifications_enabled",
    paramValue: "1",
  });
});

Given("the service has no notification period", () => {
  cy.setServiceParameters({
    name: data.hosts.host1.name,
    paramName: "notification_period",
    paramValue: "none",
  });
});

When("the configuration is exported", () => {
  cy.navigateTo({ page: "Pollers", rootItemNumber: 3, subMenu: "Pollers" });
  cy.wait("@getUserTimezone");
  cy.waitForElementInIframe("#main-content", 'input[name="searchP"]');
  cy.getIframeBody()
    .find("h4")
    .contains("Poller")
    .should("exist");
  cy.getIframeBody()
    .find('#exportConfigurationLink')
    .should("be.visible");
  cy.getIframeBody().find('#exportConfigurationLink').click();

  cy.url().should("include", "poller=");
  cy.wait("@getUserTimezone");
  cy.waitForElementInIframe(
    "#main-content",
    'input.select2-search__field[placeholder="Pollers"]',
  );
  cy.getIframeBody()
    .find('input.select2-search__field[placeholder="Pollers"]')
    .click();
  cy.getIframeBody().contains("Central").click();

  cy.getIframeBody().find('input[name="move"]').parent().click();
  cy.getIframeBody().find('input[name="restart"]').parent().click();
  cy.getIframeBody().find('input[id="exportBtn"]').click();
});

Then("a warning message is printed", () => {
  cy.waitUntil(
    () => {
      cy.getIframeBody().find('div[id="console"]').should("be.visible");
      return cy
        .getIframeBody()
        .find('label[id="progressPct"]')
        .invoke("text")
        .then((text) => text === "100%");
    },
    { interval: 6000, timeout: 10000 }
  );
  cy.getIframeBody()
    .find("div#debug_1")
    .contains("Warning")
    .should("be.visible");
});

afterEach(() => {
  cy.stopContainers();
});
