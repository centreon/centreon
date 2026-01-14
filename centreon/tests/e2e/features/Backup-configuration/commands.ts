Cypress.Commands.add('enterIframe', (iframeSelector) => {
  cy.get(iframeSelector)
    .its('0.contentDocument')
    .should('exist')
    .its('body')
    .should('not.be.undefined')
    .then(cy.wrap);
});

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
  cy.getByLabel({ label: 'Pollers', tag: 'button' }).click();
  cy.getByTestId({ testId: 'Export configuration' }).click();
  cy.getByTestId({ testId: 'Confirm' }).click();
});

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      enterIframe: (iframeSelector: string) => Cypress.Chainable;
      exportConfig: () => Cypress.Chainable;
      selectCurrentDayCheckbox: () => Cypress.Chainable;
    }
  }
}

export {};
