import { PAGES } from 'fixtures/shared/constants/pages';

Cypress.Commands.add('fillCmaMandatoryFields', (body: Cma) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains(body.pollerName).click();
  // Click outside to close the pollers dropdown list
  cy.contains('h6', 'Pollers').click();
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  }).type(body.publicCertificationFileName);
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' }).type(
    body.privateKeyFileName
  );
  cy.getByLabel({ label: 'CA (.crt, .cert, .cer)', tag: 'input' })
    .eq(0)
    .type(body.caFileName);
  cy.getByTestId({ testId: 'Select existing CMA token(s)' }).click();
  cy.wait('@getTokens');
  cy.contains('CMA-Token-001').click();
});

Cypress.Commands.add('fillTelegrafMandatoryFields', (body: Telegraf) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains(body.pollerName).click();
  // Click outside to close the pollers dropdown list
  cy.contains('h6', 'Pollers').click();
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  })
    .eq(0)
    .type(body.publicCertificationFileName);
  cy.getByLabel({ label: 'CA (.crt, .cert, .cer)', tag: 'input' }).type(
    body.caFileName
  );
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(0)
    .type(body.privateKeyFileName);
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  })
    .eq(1)
    .type(body.certificateFileName);
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(1)
    .type(body.privateKeyFileName);
});

Cypress.Commands.add('fillOnlySomeCmaMandatoryFields', (body: Cma) => {
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  }).type(body.publicCertificationFileName);
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(0)
    .type(body.privateKeyFileName);
});

Cypress.Commands.add(
  'fillOnlySomeTelegrafMandatoryFields',
  (body: Telegraf) => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
    cy.getByLabel({
      label: 'Public certificate (.crt, .cert, .cer)',
      tag: 'input'
    })
      .eq(0)
      .type(body.publicCertificationFileName);
    cy.getByLabel({ label: 'CA (.crt, .cert, .cer)', tag: 'input' }).type(
      body.caFileName
    );
    cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
    cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
      .eq(1)
      .type(body.privateKeyFileName);
  }
);

Cypress.Commands.add('addTelegrafAgent', (body: Telegraf) => {
  cy.get('*[role="dialog"]').should('be.visible');
  cy.get('*[role="dialog"]').contains('Add agent configuration');
  cy.getByLabel({ label: 'Agent type', tag: 'input' }).click();
  cy.get('*[role="listbox"]').contains('Telegraf').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Central').click();
  // Click outside to close the pollers dropdown list
  cy.contains('h6', 'Pollers').click();
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  })
    .eq(0)
    .type(body.publicCertificationFileName);
  cy.getByLabel({ label: 'CA (.crt, .cert, .cer)', tag: 'input' }).type(
    body.caFileName
  );
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(0)
    .type(body.privateKeyFileName);
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  })
    .eq(1)
    .type(body.certificateFileName);
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(1)
    .type(body.privateKeyFileName);
});

Cypress.Commands.add('updateTelegrafAgent', (body: Telegraf) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type(body.name);
  cy.getByLabel({ label: 'Pollers', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  // Click outside to close the pollers dropdown list
  cy.contains('h6', 'Pollers').click();
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  })
    .eq(0)
    .clear()
    .type(body.publicCertificationFileName);
  cy.getByLabel({ label: 'CA (.crt, .cert, .cer)', tag: 'input' })
    .clear()
    .type(body.caFileName);
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(0)
    .clear()
    .type(body.privateKeyFileName);
  cy.getByLabel({ label: 'Port', tag: 'input' }).should('have.value', '1443');
  cy.getByLabel({
    label: 'Public certificate (.crt, .cert, .cer)',
    tag: 'input'
  })
    .eq(1)
    .clear()
    .type(body.certificateFileName);
  cy.getByLabel({ label: 'Private key (.key)', tag: 'input' })
    .eq(1)
    .clear()
    .type(body.privateKeyFileName);
});

Cypress.Commands.add('addCmaToken', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
  cy.visit(PAGES.configuration.authenticationTokens);
  cy.getByLabel({ label: 'create' }).click();
  cy.contains('Create authentication token').should('be.visible');
  cy.get('#Name').type('CMA-Token-001');
  cy.getByTestId({ testId: 'Type' }).click();
  cy.contains('Centreon monitoring agent').click();
  cy.contains('button', 'Generate token').click();
  cy.wait('@addToken');

  cy.get('body').then((body) => {
    if (
      body.find(':contains("The token name \'CMA-Token-001\' already exists")')
        .length > 0
    ) {
      return;
    }

    cy.contains('button', 'Done').click();
    cy.logout();
  });
});

interface Telegraf {
  name: string;
  pollerName: string;
  publicCertificationFileName: string;
  caFileName: string;
  privateKeyFileName: string;
  certificateFileName: string;
}

interface Cma {
  name: string;
  pollerName: string;
  publicCertificationFileName: string;
  caFileName: string;
  privateKeyFileName: string;
}

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      fillCmaMandatoryFields: (body: Cma) => Cypress.Chainable;
      fillTelegrafMandatoryFields: (body: Telegraf) => Cypress.Chainable;
      fillOnlySomeCmaMandatoryFields: (body: Cma) => Cypress.Chainable;
      fillOnlySomeTelegrafMandatoryFields: (
        body: Telegraf
      ) => Cypress.Chainable;
      addTelegrafAgent: (body: Telegraf) => Cypress.Chainable;
      updateTelegrafAgent: (body: Telegraf) => Cypress.Chainable;
      addCmaToken: () => Cypress.Chainable;
    }
  }
}
