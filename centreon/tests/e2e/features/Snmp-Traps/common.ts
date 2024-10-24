const TrapsSNMPConfiguration = (): Cypress.Chainable => {

};


function submitForm() {
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
}

export { submitForm };
