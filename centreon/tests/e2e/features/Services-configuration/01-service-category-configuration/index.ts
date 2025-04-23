import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

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

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Then("a service category is configured", () => {
  cy.navigateTo({
    page: "Categories",
    rootItemNumber: 3,
    subMenu: "Services",
  });
  cy.waitForElementInIframe("#main-content", 'input[name="searchSC"]');
  cy.getIframeBody()
    .contains("Add")
    .click();
  cy.waitForElementInIframe("#main-content", 'input[name="sc_name"]');
  cy.getIframeBody()
    .find('input[name="sc_name"]')
    .type("test");

  cy.getIframeBody()
    .find('input[name="sc_description"]')
    .type("test description");
  cy.getIframeBody().find("select#sc_svcTpl")
    .next()
    .click();
  cy.getIframeBody().contains("Ping-LAN").click();
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
});

When("the user change the properties of a service category", () => {
    cy.waitForElementInIframe("#main-content", 'input[name="searchSC"]');
    cy.getIframeBody()
      .contains('test')
      .click();
    cy.waitForElementInIframe("#main-content", 'input[name="sc_description"]');
    cy.getIframeBody()
      .find('input[name="sc_description"]')
      .clear()
      .type("test description modified");
    cy.getIframeBody()
      .find("div#validForm")
      .find("p.oreonbutton")
      .find('.btc.bt_success[name="submitC"]')
      .click();
});

Then("the properties are updated", () => {
  cy.waitForElementInIframe("#main-content", 'input[name="searchSC"]');
  cy.getIframeBody()
    .contains("test description modified");
});

When('the user duplicate a service category', () => {
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table tbody")
    .find("tr.list_one, tr.list_two")
    .contains("td", "Ping")
    .parent()
    .find("td.ListColPicker")
    .find("div.md-checkbox")
    .click();
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

Then("the new service category has the same properties", () => {
  cy.wait("@getTimeZone");
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table tbody")
    .contains("Ping_1")
    .should("be.visible");
  cy.getIframeBody()
    .contains("Ping_1")
    .click();
  cy.waitForElementInIframe("#main-content", 'input[name="sc_description"]');
  cy.getIframeBody()
    .find('input[value="Ping_1"]')
    .should('exist');
  cy.getIframeBody()
    .find('input[value="ping"]')
    .should('exist');
  cy.getIframeBody().contains('Ping-LAN').should('exist');
  cy.getIframeBody().contains('Ping-WAN').should('exist');
});

When("the user delete a service category", () => {
    cy.get("iframe#main-content")
      .its("0.contentDocument.body")
      .find("table tbody")
      .find("tr.list_one, tr.list_two")
      .contains("td", "test")
      .parent()
      .find("td.ListColPicker")
      .find("div.md-checkbox")
      .click();
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

Then("the deleted service category is not displayed in the list", () => {
  cy.wait("@getTimeZone");
  cy.window().then((win) => win.location.reload());
  cy.get("iframe#main-content")
    .its("0.contentDocument.body")
    .find("table.ListTable tbody")
    .children()
    .should("have.length", 5);
  cy.getIframeBody()
    .contains('test')
    .should('not.exist');
});

afterEach(() => {
  cy.stopContainers();
});
