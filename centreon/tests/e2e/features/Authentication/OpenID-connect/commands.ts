/* eslint-disable @typescript-eslint/method-signature-style */
/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable newline-before-return */
/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add(
  'waitForElementToBeVisible',
  (selector, timeout = 50000, interval = 2000) => {
    cy.waitUntil(
      () =>
        cy.get('body').then(($body) => {
          const element = $body.find(selector);

          return element.length > 0 && element.is(':visible');
        }),
      {
        errorMsg: `The element '${selector}' is not visible`,
        interval,
        timeout
      }
    ).then((isVisible) => {
      if (!isVisible) {
        throw new Error(`The element '${selector}' is not visible`);
      }
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      waitForElementToBeVisible(
        selector: string,
        timeout?: number,
        interval?: number
      ): Cypress.Chainable;
    }
  }
}

export {};
