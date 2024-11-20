Cypress.Commands.add('checkLegacyRadioButton', (label: string) => {
  cy.getIframeBody().contains('label', label)
    .should('exist')
    .then(($label) => {
      const radioId = $label.attr('for');
      cy.getIframeBody().find(`input[type="radio"][id="${radioId}"]`)
        .should('be.checked');
    });
});

declare global {
  namespace Cypress {
    interface Chainable {
      checkLegacyRadioButton: (label: string) => Cypress.Chainable;
    }
  }
}

export {};