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


Given("a service associated to a host is configured", () => {
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
      template: "Ping-WAN",
    });
});

Given('the user is in the "Notifications" tab', () => {
  cy.navigateTo({
    page: "Services by host",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.waitForElementInIframe("#main-content", 'input[name="searchH"]');
  cy.getIframeBody()
    .find('tr[class*="list_"]')
    .contains(`${data.services.service1.name}`)
    .click();
  cy.waitForElementInIframe("#main-content",'input[name="service_description"]');
  cy.getIframeBody().find("li#c2").click();
});

When("the user checks case yes to enable Notifications", () => {
  cy.getIframeBody()
    .find('input[name*="service_notifications_enabled"][value="1"]')
    .parent()
    .click();
});

When("the case Inherit contacts is checked", () => {
  cy.getIframeBody()
    .find('input[name*="service_use_only_contacts_from_host"][value="1"]')
    .parent()
    .click();
});

Then('the field "Implied Contacts" is disabled', () => {
  cy.getIframeBody().find('input[placeholder="Implied Contacts"]').should('be.disabled');
});

Then('the field "Implied Contact Groups" is disabled', () => {
  cy.getIframeBody().find("select#service_cgs").should("be.disabled");
});

afterEach(() => {
  cy.stopContainers();
});