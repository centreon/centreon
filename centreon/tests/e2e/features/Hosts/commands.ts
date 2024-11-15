/* eslint-disable no-plusplus */
/* eslint-disable prefer-destructuring */
/* eslint-disable @typescript-eslint/method-signature-style */
/* eslint-disable @typescript-eslint/explicit-function-return-type */
/* eslint-disable @typescript-eslint/no-shadow */
/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add(
    'waitForElementInIframe',
    (iframeSelector, elementSelector) => {
      cy.waitUntil(
        () =>
          cy.get(iframeSelector).then(($iframe) => {
            const iframeBody = $iframe[0].contentDocument.body;
            if (iframeBody) {
              const $element = Cypress.$(iframeBody).find(elementSelector);
  
              return $element.length > 0 && $element.is(':visible');
            }
  
            return false;
          }),
        {
          errorMsg: 'The element is not visible within the iframe',
          interval: 5000,
          timeout: 100000
        }
      ).then((isVisible) => {
        if (!isVisible) {
          throw new Error('The element is not visible');
        }
      });
    }
  );

  Cypress.Commands.add('exportConfig', () => {
    cy.getByTestId({ testId: 'ExpandMoreIcon' }).eq(0).click();
    cy.getByTestId({ testId: 'Export configuration' }).click();
    cy.getByTestId({ testId: 'Confirm' }).click();
  });
  
  
  declare global {
    namespace Cypress {
      interface Chainable {
        waitForElementInIframe: (
          iframeSelector: string,
          elementSelector: string
        ) => Cypress.Chainable;
        exportConfig: () => Cypress.Chainable;
      }
    }
  }
  
  export {};