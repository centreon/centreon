Cypress.Commands.add('enableVaultFeature', () => {
    return cy.execInContainer({
      command: `sed -i 's/"vault": [0-3]/"vault": 3/' /usr/share/centreon/config/features.json`,
      name: 'web'
    });
  });

  declare global {
    namespace Cypress {
      interface Chainable {
        enableVaultFeature: () => Cypress.Chainable;
      }
    }
  }
  
  export {};