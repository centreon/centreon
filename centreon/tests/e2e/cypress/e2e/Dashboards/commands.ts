/* eslint-disable @typescript-eslint/no-namespace */

import 'cypress-wait-until';

Cypress.Commands.add('customWaitUntil', (accessRightsTestId) => {
  const openModalAndCheck: () => Cypress.Chainable<boolean> = () => {
    cy.getByTestId({ testId: accessRightsTestId }).invoke('show').click();
    cy.getByTestId({ testId: 'role-input' }).eq(1).should('be.visible');

    return cy
      .get('[data-testid="role-input"]')
      .should('be.visible')
      .then(($element) => {
        if ($element.length === 3) {
          cy.getByTestId({ testId: 'CloseIcon' }).click();

          return cy.wrap(true);
        }
        cy.getByTestId({ testId: 'CloseIcon' }).click();

        return openModalAndCheck();
      });
  };

  return cy.waitUntil(openModalAndCheck, {
    errorMsg: "L'élément n'est pas devenu visible dans les 30 secondes.",
    interval: 3000,
    timeout: 30000
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      customWaitUntil: (accessRightsTestId: string) => Cypress.Chainable;
    }
  }
}
