 Cypress.Commands.add('FillCMAMandatoryFields', (body: CMA) => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
    cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
    cy.contains(body.pollerName).click();
    cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(body.publicCertfFileName);
    cy.getByLabel({ label: 'Private key file name', tag: 'input' }).type(body.privateKFileName);
    cy.getByLabel({ label: 'CA file name', tag: 'input' })
        .eq(0)
        .type(body.caFileName);
 });

 Cypress.Commands.add('FillTelegrafMandatoryFields', (body: Telegraf) => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
    cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
    cy.contains(body.pollerName).click();
    cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(body.publicCertfFileName);
    cy.getByLabel({ label: 'CA file name', tag: 'input' }).type(body.caFileName);
    cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(0)
    .type(body.privateKFileName);
    cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
    cy.getByLabel({ label: 'Certificate file name', tag: 'input' }).type(body.certfFileName);
    cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(1)
    .type(body.privateKFileName);
 });

 Cypress.Commands.add('FillOnlySomeCMAMandatoryFields', (body: CMA) => {
     cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(body.publicCertfFileName);
     cy.getByLabel({ label: 'Private key file name', tag: 'input' })
      .eq(0)
      .type(body.privateKFileName);
 });

 Cypress.Commands.add('FillOnlySomeTelegrafMandatoryFields', (body: Telegraf) => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
    cy.getByLabel({ label: 'Public certificate file name', tag: 'input' }).type(body.publicCertfFileName);
    cy.getByLabel({ label: 'CA file name', tag: 'input' }).type(body.caFileName);
    cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
    cy.getByLabel({ label: 'Private key file name', tag: 'input' })
    .eq(1)
    .type(body.privateKFileName);
 });

 interface Telegraf {
    name: string,
    pollerName: string,
    publicCertfFileName: string,
    caFileName: string,
    privateKFileName: string,
    certfFileName: string
  }

  interface CMA {
    name: string,
    pollerName: string,
    publicCertfFileName: string,
    caFileName: string,
    privateKFileName: string
  }

 declare global {
    namespace Cypress {
      interface Chainable {
        FillCMAMandatoryFields: (body: CMA) => Cypress.Chainable;
        FillTelegrafMandatoryFields: (body: Telegraf) => Cypress.Chainable;
        FillOnlySomeCMAMandatoryFields: (body: CMA) => Cypress.Chainable;
        FillOnlySomeTelegrafMandatoryFields: (body: Telegraf) => Cypress.Chainable;
      }
    }
  }
  
  export {};