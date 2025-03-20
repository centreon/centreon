/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from "@badeball/cypress-cucumber-preprocessor";

const hostName = "New-Host-Name";

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: "GET",
    url: "/centreon/api/internal.php?object=centreon_topology&action=navigationList",
  }).as("getNavigationList");
  cy.intercept({
    method: "GET",
    url: "/centreon/include/common/userTimezone.php",
  }).as("getTimeZone");
});

afterEach(() => {
  cy.stopContainers();
});

Given("a user is logged in a Centreon server", () => {
  cy.loginByTypeOfUser({
    jsonName: "admin",
    loginViaApi: false,
  });
});

When("a host inheriting from a host template", () => {
  cy.setUserTokenApiV1();
   cy.addHost({
     hostGroup: "Linux-Servers",
     name: hostName,
     template: "Printers",
   })
   .applyPollerConfiguration();
});

Then("the user configures the host", () => {
  cy.navigateTo({
    page: "Hosts",
    rootItemNumber: 3,
    subMenu: "Hosts",
  });
  cy.wait("@getTimeZone");
  cy.waitForElementInIframe("#main-content", `input[name="searchH"]`);
  cy.getIframeBody().contains(`${hostName}`).click();
  cy.waitForElementInIframe("#main-content", `input[name="host_name"]`);
});

Then("the user can configure directly its parent template", () => {
  cy.getIframeBody()
    .find('img[title="Edit template"]')
    .then(($el) => {
      cy.window().then((win) => {
        // Get the hostId and build the correct URL
        const hostId = $el.siblings("select").val();
        if (hostId !== "") {
          // Use relative URL to avoid hardcoding protocol and port
          const baseUrl = win.location.origin;
          const path = "/centreon/main.php";
          const params = new URLSearchParams({
            p: "60103",
            o: "c",
            host_id: hostId.toString(),
            min: "1"
          });

          // Perform redirection in the same tab
          win.location.href = `${baseUrl}${path}?${params.toString()}`;
        } else {
          // Handle the case when no parent template is selected
          cy.log("No parent template found to edit");
          throw new Error("No parent template found to edit");
        }
        });
    });
  cy.waitForElementInIframe("#main-content", `input[name="host_name"]`);
  cy.getIframeBody().find('input[name="host_name"]').click();
  cy.getIframeBody().find('input[name="submitC"]').first().click();
});

When("a host template inheriting from a host template", () => {
  cy.navigateTo({
    page: "Templates",
    rootItemNumber: 3,
    subMenu: "Hosts",
  });
  cy.wait("@getTimeZone");
});

When("the user configures the host template", () => {
 cy.waitForElementInIframe("#main-content", `input[name="searchHT"]`);
 //parent host template already configured : generic-host
 cy.getIframeBody().contains('Printers').click();
 cy.waitForElementInIframe("#main-content", `input[name="host_name"]`);
});
