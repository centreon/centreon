Cypress.Commands.add('createAccWithMandatoryFields', () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
    cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
    cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
    cy.contains('Central').click();
    cy.get('#Usernamevalue').type('admin');
    cy.get('#Passwordvalue').type('Abcde!2021');
    cy.get('#vCenternamevalue').clear().type('vCenter-001');
    cy.get('#URLvalue').clear().type('https://10.0.0.0/sdk');
    cy.get('#Portvalue').should('have.value', '5700');
    cy.getByLabel({ label: 'Save', tag: 'button' }).click();
    cy.wait('@addAdditionalConnector');
  });

  Cypress.Commands.add('saveAcc', () => {
    cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  });
  
  declare global {
    namespace Cypress {
      interface Chainable {
        createAccWithMandatoryFields: () => Cypress.Chainable;
        saveAcc: () => Cypress.Chainable;
      }
    }
  }
  
  export {};
  