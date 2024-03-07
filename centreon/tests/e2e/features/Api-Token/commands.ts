Cypress.Commands.add('enableAPITokensFeature', () => {
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 3@"dashboard": 4@' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      enableAPITokensFeature: () => Cypress.Chainable;
    }
  }
}

export {};
