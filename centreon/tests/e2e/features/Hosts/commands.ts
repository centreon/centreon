Cypress.Commands.add('checkLegacyRadioButton', (label: string) => {
  cy.getIframeBody().contains('label', label)
    .should('exist')
    .then(($label) => {
      const radioId = $label.attr('for');
      cy.getIframeBody().find(`input[type="radio"][id="${radioId}"]`)
        .should('be.checked');
    });
});

Cypress.Commands.add('exportConfig', () => {
  cy.getByTestId({ testId: 'ExpandMoreIcon' }).eq(0).click();
  cy.getByTestId({ testId: 'Export configuration' }).click();
  cy.getByTestId({ testId: 'Confirm' }).click();
});

declare global {
  namespace Cypress {
    interface Chainable {
      checkLegacyRadioButton: (label: string) => Cypress.Chainable;
      exportConfig: () => Cypress.Chainable;
    }
  }
}

export {};