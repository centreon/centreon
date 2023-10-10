/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add(
  'waitDashboardModalToCharge',
  (accessRightsTestId, expectedElementCount) => {
    const openModalAndCheck: () => boolean = () => {
      cy.getByTestId({ testId: accessRightsTestId }).invoke('show').click();
      cy.getByTestId({ testId: 'role-input' }).eq(1).should('be.visible');

      return cy
        .get('[data-testid="role-input"]')
        .should('be.visible')
        .then(($element) => {
          if ($element.length === expectedElementCount) {
            cy.getByTestId({ testId: 'CloseIcon' }).click();
            return cy.wrap(true);
          }
          cy.getByTestId({ testId: 'CloseIcon' }).click();

          return openModalAndCheck();
        });
    };

    return cy.waitUntil(openModalAndCheck, {
      errorMsg: 'The element does not exist',
      interval: 3000,
      timeout: 30000
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      waitDashboardModalToCharge: (
        accessRightsTestId: string,
        expectedElementCount: number
      ) => Cypress.Chainable;
    }
  }
}
