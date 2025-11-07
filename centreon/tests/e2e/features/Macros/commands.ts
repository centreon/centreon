Cypress.Commands.add('visitHostTemplatesListing', (index: number) => {
  cy.navigateTo({
    page: 'Templates',
    rootItemNumber: index,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
});
Cypress.Commands.add('visitServiceTemplatesListing', (index: number) => {
  cy.navigateTo({
    page: 'Templates',
    rootItemNumber: index,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
});

Cypress.Commands.add('visitServicesListing', (index: number) => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: index,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
});

Cypress.Commands.add(
  'fillMacros',
  (isUpdate: boolean, normalMacro: Macro, passMacro: Macro) => {
    if (!isUpdate) {
      cy.getIframeBody().find('#macro_add').click();
      cy.getIframeBody().find('#macro_add').click();
      cy.getIframeBody().find('#macroInput_0').clear().type(normalMacro.name);
      cy.getIframeBody().find('#macroInput_1').clear().type(passMacro.name);
    }
    // Add/Update a normal macro
    cy.getIframeBody().find('#macroValue_0').clear().type(normalMacro.value);
    // Add/Update a macro of type password
    cy.getIframeBody().find('#macroValue_1').clear().type(passMacro.value);
    if (!isUpdate) {
      cy.getIframeBody().find('#macroPassword_1').click({ force: true });
    }
  }
);

Cypress.Commands.add(
  'checkMacrosFieldsValues',
  (normalMacro: Macro, passMacro: Macro) => {
    // Verify the save of the macros
    cy.getIframeBody()
      .find('#macroInput_0')
      .should('have.value', normalMacro.name);
    cy.getIframeBody()
      .find('#macroValue_0')
      .should('have.value', normalMacro.value);

    cy.getIframeBody()
      .find('#macroInput_1')
      .should('have.value', passMacro.name);
    // Check that the value of the password macro contains just *
    cy.getIframeBody()
      .find('#macroValue_1')
      .invoke('val')
      .then((value) => {
        expect(value).to.not.be.empty;
        expect(value).to.match(/^\*+$/);
      });
  }
);

Cypress.Commands.add('fillHostBasicsInfos', (name: string, alias: string) => {
  cy.getIframeBody().find('input[name="host_name"]').clear().type(name);
  cy.getIframeBody().find('input[name="host_alias"]').clear().type(alias);
});

Cypress.Commands.add(
  'checkMacrosFromTheExportFile',
  (
    fileName: string,
    isInherited: boolean,
    normalMacro: Macro,
    passMacro: Macro
  ) => {
    cy.execInContainer({
      command: `cat ${fileName}`,
      name: 'web'
    }).then((result) => {
      expect(result.exitCode).to.eq(0);
      const output = result.output;
      const regexNormal = new RegExp(
        `${normalMacro.name}\\s+${normalMacro.value}`
      );
      expect(output).to.match(regexNormal);
      const regexPassword = new RegExp(
        `${passMacro.name}\\s+${passMacro.value}`
      );
      if (!isInherited) {
        expect(output).to.match(regexPassword);
      } else {
        expect(output).not.to.match(regexPassword);
      }
    });
  }
);

Cypress.Commands.add(
  'fillServiceMandatoryField',
  (name: string, host: string, cmd: string) => {
    cy.getIframeBody()
      .find('input[name="service_description"]')
      .clear()
      .type(name);
    cy.getIframeBody().find('input[placeholder="Hosts"]').click();
    cy.wait('@getHostsList');
    cy.getIframeBody().contains('div', host).click();
    cy.getIframeBody().find('span[title="Check Command"]').click();
    cy.getIframeBody().contains('div', cmd).click();
  }
);

interface Macro {
  name: string;
  value: string;
}

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      visitServiceTemplatesListing: (index: number) => Cypress.Chainable;
      visitServicesListing: (index: number) => Cypress.Chainable;
      visitHostTemplatesListing: (index: number) => Cypress.Chainable;
      fillMacros: (
        isUpdate: boolean,
        normalMacro: Macro,
        passMacro: Macro
      ) => Cypress.Chainable;
      checkMacrosFieldsValues: (
        normalMacro: Macro,
        passMacro: Macro
      ) => Cypress.Chainable;
      fillHostBasicsInfos: (name: string, alias: string) => Cypress.Chainable;
      checkMacrosFromTheExportFile: (
        fileName: string,
        isInherited: boolean,
        normalMacro: Macro,
        passMacro: Macro
      ) => Cypress.Chainable;
      fillServiceMandatoryField: (
        name: string,
        host: string,
        cmd: string
      ) => Cypress.Chainable;
    }
  }
}

export {};
