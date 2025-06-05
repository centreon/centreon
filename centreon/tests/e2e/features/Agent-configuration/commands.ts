Cypress.Commands.add('FillCMAMandatoryFields', (body: Cma) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains(body.pollerName).click();
  cy.getByLabel({ label: 'Public certificate', tag: 'input' }).type(
    body.publicCertfFileName
  );
  cy.getByLabel({ label: 'Private key', tag: 'input' }).type(
    body.privateKFileName
  );
  cy.getByLabel({ label: 'CA', tag: 'input' }).eq(0).type(body.caFileName);
  cy.getByTestId({ testId: 'Select existing CMA token(s)' }).click();
  cy.wait('@getTokens');
  cy.contains('CMA-Token-001').click();
});

Cypress.Commands.add('FillTelegrafMandatoryFields', (body: Telegraf) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains(body.pollerName).click();
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(0)
    .type(body.publicCertfFileName);
  cy.getByLabel({ label: 'CA', tag: 'input' }).type(body.caFileName);
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(0)
    .type(body.privateKFileName);
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(1)
    .type(body.certfFileName);
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(1)
    .type(body.privateKFileName);
});

Cypress.Commands.add('FillOnlySomeCMAMandatoryFields', (body: Cma) => {
  cy.getByLabel({ label: 'Public certificate', tag: 'input' }).type(
    body.publicCertfFileName
  );
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(0)
    .type(body.privateKFileName);
});

Cypress.Commands.add(
  'FillOnlySomeTelegrafMandatoryFields',
  (body: Telegraf) => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
    cy.getByLabel({ label: 'Public certificate', tag: 'input' })
      .eq(0)
      .type(body.publicCertfFileName);
    cy.getByLabel({ label: 'CA', tag: 'input' }).type(body.caFileName);
    cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
    cy.getByLabel({ label: 'Private key', tag: 'input' })
      .eq(1)
      .type(body.privateKFileName);
  }
);

Cypress.Commands.add('addTelegrafAgent', (body: Telegraf) => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add poller/agent configuration');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(0)
    .type(body.publicCertfFileName);
  cy.getByLabel({ label: 'CA', tag: 'input' }).type(body.caFileName);
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(0)
    .type(body.privateKFileName);
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(1)
    .type(body.certfFileName);
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(1)
    .type(body.privateKFileName);
});

Cypress.Commands.add('updateTelegrafAgent', (body: Telegraf) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(0)
    .clear()
    .type(body.publicCertfFileName);
  cy.getByLabel({ label: 'CA', tag: 'input' }).clear().type(body.caFileName);
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(0)
    .clear()
    .type(body.privateKFileName);
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({ label: 'Public certificate', tag: 'input' })
    .eq(1)
    .clear()
    .type(body.certfFileName);
  cy.getByLabel({ label: 'Private key', tag: 'input' })
    .eq(1)
    .clear()
    .type(body.privateKFileName);
});

Cypress.Commands.add('addCMAToken', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
  cy.navigateTo({
    page: 'Authentication Tokens',
    rootItemNumber: 1
  });
  cy.getByTestId({ testId: 'Add' }).click();
  cy.contains('Create authentication token').should('be.visible');
  cy.get('#Name').type('CMA-Token-001');
  cy.getByTestId({ testId: 'Type' }).click();
  cy.contains('Centreon monitoring agent').click();
  cy.contains('button', 'Generate token').click();
  cy.wait('@getTokens');
  cy.contains('button', 'Done').click();
  cy.logout();
});

interface Telegraf {
  name: string;
  pollerName: string;
  publicCertfFileName: string;
  caFileName: string;
  privateKFileName: string;
  certfFileName: string;
}

interface Cma {
  name: string;
  pollerName: string;
  publicCertfFileName: string;
  caFileName: string;
  privateKFileName: string;
}

declare global {
  namespace Cypress {
    interface Chainable {
      FillCMAMandatoryFields: (body: Cma) => Cypress.Chainable;
      FillTelegrafMandatoryFields: (body: Telegraf) => Cypress.Chainable;
      FillOnlySomeCMAMandatoryFields: (body: Cma) => Cypress.Chainable;
      FillOnlySomeTelegrafMandatoryFields: (
        body: Telegraf
      ) => Cypress.Chainable;
      addTelegrafAgent: (body: Telegraf) => Cypress.Chainable;
      updateTelegrafAgent: (body: Telegraf) => Cypress.Chainable;
      addCMAToken: () => Cypress.Chainable;
    }
  }
}

export {};
