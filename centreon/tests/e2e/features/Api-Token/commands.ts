import { PAGES } from 'fixtures/shared/constants/pages';

Cypress.Commands.add('visitApiTokens', () => {
  cy.intercept({
    method: 'GET',
    times: 1,
    url: '/centreon/api/latest/administration/tokens?*'
  }).as('getTokens');

  cy.url().then((url) => {
    if (url.includes('/administration/authentication-token')) {
      cy.visit(PAGES.configuration.authentication_tokens);
    } else {
      cy.visit(PAGES.configuration.authentication_tokens);
    }
  });

  cy.wait('@getTokens');

  cy.contains('h1', 'Authentication tokens').should('be.visible');
});

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      visitApiTokens: () => Cypress.Chainable;
    }
  }
}
