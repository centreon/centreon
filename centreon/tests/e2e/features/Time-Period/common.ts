const setTimePeriod = (): Cypress.Chainable => {
  cy.getIframeBody().find('input[name="tp_name"]').type("timePeriodName");
  cy.getIframeBody().find('input[name="tp_alias"]').type("timePeriodAlias");
  const weekdays = [
    "sunday",
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
    "saturday",
  ];
  const timeRanges = {
    sunday: "14:00-16:00",
    monday: "07:00-12:00,13:00-18:00",
    tuesday: "07:00-18:00",
    wednesday: "07:00-12:00,13:00-17:00",
    thursday: "14:00-16:00",
    friday: "07:00-18:00",
    saturday: "10:00-16:00",
  };

  weekdays.forEach((day) => {
    cy.getIframeBody().find(`input[name="tp_${day}"]`).type(timeRanges[day]);
  });
  cy.getIframeBody().find("li#c2").click();
  const exceptions = [
    { date: "december 25", timeRange: "00:00-22:59,23:00-24:00" },
    { date: "january 1", timeRange: "00:00-24:00" },
    { date: "july 14", timeRange: "00:00-24:00" },
    { date: "may 25", timeRange: "00:00-24:00" },
  ];

  exceptions.forEach((exception, index) => {
    if (index > 0) {
      cy.getIframeBody().contains("+ Add new entry").click();
    }
    cy.getIframeBody()
      .find(`input#exceptionInput_${index}`)
      .type(exception.date);
    cy.getIframeBody()
      .find(`input#exceptionTimerange_${index}`)
      .type(exception.timeRange);
  });
};

function navigateToTimePeriodsAndInitiateAddition() {
  cy.navigateTo({
    page: "Time Periods",
    rootItemNumber: 3,
    subMenu: "Users",
  });
  cy.waitForElementInIframe("#main-content", 'input[name="searchTP"]');
  cy.getIframeBody().find("a.bt_success").contains("Add").click();
  cy.waitForElementInIframe("#main-content", 'input[name="tp_name"]');
}

function submitForm() {
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
}

export { setTimePeriod, navigateToTimePeriodsAndInitiateAddition, submitForm };
