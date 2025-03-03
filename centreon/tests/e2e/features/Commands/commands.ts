Cypress.Commands.add("addCommands", (body: Cmd) => {
  cy.waitForElementInIframe("#main-content", 'input[name="command_name"]');
  cy.getIframeBody().find('input[name="command_name"]').type(body.name);
  cy.getIframeBody()
    .find(`input[name="command_type[command_type]"][value="${body.type}"]`)
    .click({ force: true });
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .type(body.command_line);
  cy.getIframeBody()
    .find('input[name="enable_shell"]')
    .then(($val) => {
      if (body.is_shell === true) {
        cy.wrap($val).click();
      }
    });
  cy.getIframeBody()
    .find('input[name="command_example"]')
    .type(body.argument_example);
  cy.getIframeBody()
    .find('select[name="connectors"]')
    .select(`${body.connector_id}`);
  cy.getIframeBody()
    .find('select[name="graph_id"]')
    .select(`${body.graph_template_id}`);
});

Cypress.Commands.add("updateCommands", (body: Cmd) => {
  cy.waitForElementInIframe("#main-content", 'input[name="command_name"]');
  cy.getIframeBody().find('input[name="command_name"]').clear().type(body.name);
  cy.getIframeBody()
    .find(`input[name="command_type[command_type]"][value="${body.type}"]`)
    .click({ force: true });
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .clear()
    .type(body.command_line);
  cy.getIframeBody()
    .find('input[name="enable_shell"]')
    .then(($val) => {
      if (body.is_shell === true) {
        cy.wrap($val).click();
      }
    });
  cy.getIframeBody()
    .find('input[name="command_example"]')
    .clear()
    .type(body.argument_example);
  cy.getIframeBody()
    .find('select[name="connectors"]')
    .select(`${body.connector_id}`);
  cy.getIframeBody()
    .find('select[name="graph_id"]')
    .select(`${body.graph_template_id}`);
});

Cypress.Commands.add("checkValuesOfCommands", (name: string, body: Cmd) => {
  cy.waitForElementInIframe("#main-content", 'input[name="command_name"]');
  cy.getIframeBody()
    .find('input[name="command_name"]')
    .should("have.value", `${name}`);
  cy.getIframeBody()
    .find(`input[name="command_type[command_type]"][value="${body.type}"]`)
    .should("be.checked");
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .should("have.value", body.command_line);
  cy.getIframeBody()
    .find('input[name="enable_shell"]')
    .then(($val) => {
      if (body.is_shell === true) {
        cy.wrap($val).should("be.checked");
      } else {
        cy.wrap($val).should("not.be.checked");
      }
    });
  cy.getIframeBody()
    .find('input[name="command_example"]')
    .should("have.value", body.argument_example);
  cy.getIframeBody()
    .find(`select[name="connectors"]`)
    .find(`option[value="${body.connector_id}"]`)
    .should("be.selected");
  cy.getIframeBody()
    .find(`select[name="graph_id"]`)
    .find(`option[value="${body.graph_template_id}"]`)
    .should("be.selected");
});

interface Cmd {
  name: string;
  type: number;
  command_line: string;
  is_shell: boolean;
  argument_example: string;
  arguments: string[];
  macros: string[];
  connector_id: number;
  graph_template_id: number;
}

declare global {
  namespace Cypress {
    interface Chainable {
      addCommands: (body: Cmd) => Cypress.Chainable;
      updateCommands: (body: Cmd) => Cypress.Chainable;
      checkValuesOfCommands: (name: string, body: Cmd) => Cypress.Chainable;
    }
  }
}

export { };
