import { Given, When, Then } from "@badeball/cypress-cucumber-preprocessor";

import data from "../../../fixtures/notifications/data-for-notification.json";

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: "GET",
    url: "/centreon/include/common/userTimezone.php",
  }).as("getUserTimezone");
});

Given("An admin user is logged in Centreon", () => {
  cy.loginByTypeOfUser({ jsonName: "admin" });
});

Given("a service with notifications enabled", () => {
    cy.setUserTokenApiV1();
     cy.addHostGroup({
       name: data.hostGroups.hostGroup1.name,
     });

     cy.addHost({
       activeCheckEnabled: false,
       checkCommand: "check_centreon_cpu",
       hostGroup: data.hostGroups.hostGroup1.name,
       name: data.hosts.host1.name,
       template: "generic-host",
     }).addService({
       activeCheckEnabled: false,
         host: data.hosts.host1.name,
       maxCheckAttempts: 1,
       name: data.services.service1.name,
       template: "Ping-LAN",
     });
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

      cy.getIframeBody().find("h4").contains("Poller").should("exist");
      cy.getIframeBody()
        .find('button[name="apply_configuration"]')
        .should("be.visible");
      cy.getIframeBody().find('button[name="apply_configuration"]').click();

      cy.url().should("include", "poller=");
      cy.wait("@getUserTimezone");
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
         cy.getIframeBody().contains('Warning').should("be.visible");
         return cy
           .getIframeBody()
           .find('label[id="progressPct"]')
           .invoke("text")
           .then((text) => text === "100%");
       },
       { interval: 6000, timeout: 10000 }
     );
});
