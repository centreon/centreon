import { PAGES } from 'fixtures/shared/constants/pages';

Cypress.Commands.add('visitHostTemplatesListing', () => {
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
  cy.wait('@getTimeZone');
});
Cypress.Commands.add('visitServiceTemplatesListing', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesTemplatesLegacy);
});

Cypress.Commands.add('visitServicesListing', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
});

Cypress.Commands.add(
  'fillMacros',
  (isUpdate: boolean, normalMacro: Macro, passMacro: Macro) => {
    if (!isUpdate) {
      cy.getFormBody().find('#macro_add').click();
      cy.getFormBody().find('#macro_add').click();
      cy.getFormBody().find('#macroInput_0').clear().type(normalMacro.name);
      cy.getFormBody().find('#macroInput_1').clear().type(passMacro.name);
    }
    // Add/Update a normal macro
    cy.getFormBody().find('#macroValue_0').clear().type(normalMacro.value);
    // Add/Update a macro of type password
    cy.getFormBody().find('#macroValue_1').clear().type(passMacro.value);
    if (!isUpdate) {
      cy.getFormBody().find('#macroPassword_1').click({ force: true });
    }
  }
);

Cypress.Commands.add(
  'checkMacrosFieldsValues',
  (normalMacro: Macro, passMacro: Macro) => {
    // Verify the save of the macros
    cy.getFormBody()
      .find('#macroInput_0')
      .should('have.value', normalMacro.name);
    cy.getFormBody()
      .find('#macroValue_0')
      .should('have.value', normalMacro.value);

    cy.getFormBody().find('#macroInput_1').should('have.value', passMacro.name);
    // Check that the value of the password macro contains just *
    cy.getFormBody()
      .find('#macroValue_1')
      .invoke('val')
      .then((value) => {
        expect(value).to.not.be.empty;
        expect(value).to.match(/^\*+$/);
      });
  }
);

Cypress.Commands.add('fillHostBasicsInfos', (name: string, alias: string) => {
  cy.getFormBody().find('input[name="host_name"]').clear().type(name);
  cy.getFormBody().find('input[name="host_alias"]').clear().type(alias);
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
        `${normalMacro.name}\\s+raw::${normalMacro.value}`
      );
      expect(output).to.match(regexNormal);
      const regexPassword = new RegExp(
        `${passMacro.name}\\s+encrypt::[A-Za-z0-9+/=]+`
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
    cy.getFormBody()
      .find('input[name="service_description"]')
      .clear()
      .type(name);
    cy.openFormSelect2('service_hPars');
    cy.wait('@getHostsList');
    cy.getFormBody()
      .find('.select2-results__option', { timeout: 20_000 })
      .contains(host)
      .click({ force: true });
    cy.selectFormOption('command_command_id', cmd);
  }
);

interface Macro {
  name: string;
  value: string;
}

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      visitServiceTemplatesListing: () => Cypress.Chainable;
      visitServicesListing: () => Cypress.Chainable;
      visitHostTemplatesListing: () => Cypress.Chainable;
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
