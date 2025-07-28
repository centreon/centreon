/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add('visitApiTokens', () => {
  cy.intercept({
    method: 'GET',
    times: 1,
    url: '/centreon/api/latest/administration/tokens?*'
  }).as('getTokens');

  cy.url().then((url) => {
    if (url.includes('/administration/authentication-token')) {
      cy.visit('/centreon/administration/authentication-token');
    } else {
      cy.navigateTo({
        page: 'Authentication Tokens',
        rootItemNumber: 4
      });
    }
  });

  cy.wait('@getTokens');

  cy.contains('h1', 'Authentication tokens').should('be.visible');
});

declare global {
  namespace Cypress {
    interface Chainable {
      visitApiTokens: () => Cypress.Chainable;
    }
  }
}

export {};
