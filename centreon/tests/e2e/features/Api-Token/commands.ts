/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add('visitApiTokens', () => {
  cy.intercept({
    method: 'GET',
    times: 1,
    url: '/centreon/api/latest/administration/tokens?*'
  }).as('getTokens');

  cy.url().then((url) => {
    if (url.includes('/administration/api-token')) {
      cy.visit('/centreon/administration/api-token');
    } else {
      cy.navigateTo({
        page: 'API Tokens',
        rootItemNumber: 4
      });
    }
  });

  cy.wait('@getTokens');

  cy.contains('h6', 'API tokens').should('be.visible');
});

declare global {
  namespace Cypress {
    interface Chainable {
      visitApiTokens: () => Cypress.Chainable;
    }
  }
}

export {};
