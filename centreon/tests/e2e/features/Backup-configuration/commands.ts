Cypress.Commands.add('enterIframe', (iframeSelector) => {
    cy.get(iframeSelector)
      .its('0.contentDocument')
      .should('exist')
      .its('body')
      .should('not.be.undefined')
      .then(cy.wrap);
  });

  Cypress.Commands.add(
    'waitForElementInIframe',
    (iframeSelector, elementSelector) => {
      cy.waitUntil(
        () =>
          cy
            .get(iframeSelector)
            .its('0.contentDocument.body')
            .should('not.be.empty')
            .then(cy.wrap)
            .within(() => {
              const element = Cypress.$(elementSelector);

              return element.length > 0 && element.is(':visible');
            }),
        {
          errorMsg: 'The element is not visible',
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

  Cypress.Commands.add('selectCurrentDayCheckbox', () => {
    const days = [
      'Sunday',
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday'
    ];
    const currentDay = new Date().getDay();

    cy.enterIframe('#main-content').within(() => {
      cy.get('.md-checkbox-inline label')
        .contains(days[currentDay])
        .prev('input')
        .check({ force: true });
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
        enterIframe: (iframeSelector: string) => Cypress.Chainable;
        exportConfig: () => Cypress.Chainable;
        selectCurrentDayCheckbox: () => Cypress.Chainable;
        waitForElementInIframe: (
          iframeSelector: string,
          elementSelector: string
        ) => Cypress.Chainable;
        waitForElementToBeVisible(
          selector: string,
          timeout?: number,
          interval?: number
        ): Cypress.Chainable;
      }
    }
  }

  export {};