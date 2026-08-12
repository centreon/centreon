import { PAGES } from 'fixtures/shared/constants/pages';

/**
 * Body of the form currently on screen.
 *
 * The host and host template forms open in the side panel — an iframe nested
 * inside the page iframe — while the service and service template forms are
 * still rendered full-page in #main-content. Resolving this per call is what
 * lets the macro commands below serve both without a flag at every call site.
 *
 * Keyed on `#cfSidePanel.open` rather than on the frame existing: the
 * modernized listings always ship `#cfSidePanelFrame` in their markup and only
 * set its src and add the open class when a panel is actually opened.
 */
const getFormBody = (): Cypress.Chainable<JQuery<HTMLElement>> =>
  cy
    .getIframeBody()
    .then(($body) =>
      $body.find('#cfSidePanel.open').length > 0
        ? cy.getSidePanelBody()
        : cy.wrap($body)
    );

Cypress.Commands.add('visitHostTemplatesListing', () => {
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
  cy.wait('@getTimeZone');
});
Cypress.Commands.add('visitServiceTemplatesListing', () => {
  cy.visit(PAGES.configuration.servicesTemplatesLegacy);
  cy.wait('@getTimeZone');
});

Cypress.Commands.add('visitServicesListing', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getTimeZone');
});

Cypress.Commands.add(
  'fillMacros',
  (isUpdate: boolean, normalMacro: Macro, passMacro: Macro) => {
    if (!isUpdate) {
      getFormBody().find('#macro_add').click();
      getFormBody().find('#macro_add').click();
      getFormBody().find('#macroInput_0').clear().type(normalMacro.name);
      getFormBody().find('#macroInput_1').clear().type(passMacro.name);
    }
    // Add/Update a normal macro
    getFormBody().find('#macroValue_0').clear().type(normalMacro.value);
    // Add/Update a macro of type password
    getFormBody().find('#macroValue_1').clear().type(passMacro.value);
    if (!isUpdate) {
      getFormBody().find('#macroPassword_1').click({ force: true });
    }
  }
);

Cypress.Commands.add(
  'checkMacrosFieldsValues',
  (normalMacro: Macro, passMacro: Macro) => {
    // Verify the save of the macros
    getFormBody().find('#macroInput_0').should('have.value', normalMacro.name);
    getFormBody().find('#macroValue_0').should('have.value', normalMacro.value);

    getFormBody().find('#macroInput_1').should('have.value', passMacro.name);
    // Check that the value of the password macro contains just *
    getFormBody()
      .find('#macroValue_1')
      .invoke('val')
      .then((value) => {
        expect(value).to.not.be.empty;
        expect(value).to.match(/^\*+$/);
      });
  }
);

Cypress.Commands.add('fillHostBasicsInfos', (name: string, alias: string) => {
  getFormBody().find('input[name="host_name"]').clear().type(name);
  getFormBody().find('input[name="host_alias"]').clear().type(alias);
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

export { getFormBody };

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
