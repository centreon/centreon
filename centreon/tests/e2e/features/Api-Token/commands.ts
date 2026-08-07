import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

Cypress.Commands.add('visitApiTokens', () => {
  cy.intercept({
    method: 'GET',
    times: 1,
    url: `${INTERCEPTORS.api.administration_tokens}?*`
  }).as('getTokens');

  cy.visit(PAGES.configuration.authenticationTokens);

  cy.wait('@getTokens');

  cy.contains('h1', 'Authentication tokens').should('be.visible');
});

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      visitApiTokens: () => Cypress.Chainable;
    }
  }
}
