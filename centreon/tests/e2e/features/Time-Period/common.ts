const setTimePeriod = (): Cypress.Chainable => {
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
  cy.getIframeBody()
    .contains("+ Add new entry")
    .click();
  cy.getIframeBody().find("input#exceptionInput_0").type("december 25");
  cy.getIframeBody()
    .find("input#exceptionTimerange_0")
    .type("00:00-22:59,23:00-24:00");
  cy.getIframeBody()
    .contains("+ Add new entry")
    .click();
  cy.getIframeBody().find("input#exceptionInput_1").type("january 1");
  cy.getIframeBody().find("input#exceptionTimerange_1").type("00:00-24:00");
  cy.getIframeBody()
    .contains("+ Add new entry")
    .click();
  cy.getIframeBody().find("input#exceptionInput_2").type("july 14");
  cy.getIframeBody().find("input#exceptionTimerange_2").type("00:00-24:00");
  cy.getIframeBody()
    .contains("+ Add new entry")
    .click();
  cy.getIframeBody().find("input#exceptionInput_3").type("may 25");
  return cy
    .getIframeBody()
    .find("input#exceptionTimerange_3")
    .type("00:00-24:00");
};


export {
  setTimePeriod,
};
