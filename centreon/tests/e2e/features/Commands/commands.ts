Cypress.Commands.add("addCommands", (body: Cmd) => {
  // Wait for the "Command Name" input to be charged on the DOM
  cy.waitForElementInIframe("#main-content", 'input[name="command_name"]');
  // Type a value on the "Command Name" input
  cy.getIframeBody().find('input[name="command_name"]').type(body.name);
  // Chose a "Command Type"
  cy.getIframeBody()
    .find(`input[name="command_type[command_type]"][value="${body.type}"]`)
    .click({ force: true });
  // Type a value on the "Command Line" textarea
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .type(body.command_line);
  // Enable/ Disable shell
  cy.getIframeBody()
    .find('input[name="enable_shell"]')
    .then(($val) => {
      if (body.is_shell === true) {
        cy.wrap($val).click();
      }
    });
  // Type a value on the "Argument Example" input
  cy.getIframeBody()
    .find('input[name="command_example"]')
    .type(body.argument_example);
  // Chose a connector
  cy.getIframeBody()
    .find('select[name="connectors"]')
    .select(`${body.connector_id}`);
  // Chose a graph
  cy.getIframeBody()
    .find('select[name="graph_id"]')
    .select(`${body.graph_template_id}`);
});

Cypress.Commands.add("updateCommands", (body: Cmd) => {
  // Wait for the "Command Name" input to be charged on the DOM
  cy.waitForElementInIframe("#main-content", 'input[name="command_name"]');
  // Update the value of the "Command Name"
  cy.getIframeBody().find('input[name="command_name"]').clear().type(body.name);
  // Update the value of the "Command Type"
  cy.getIframeBody()
    .find(`input[name="command_type[command_type]"][value="${body.type}"]`)
    .click({ force: true });
  // Update the value of the "Command Line"
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .clear()
    .type(body.command_line);
  // Update the value of the "Enable shell"
  cy.getIframeBody()
    .find('input[name="enable_shell"]')
    .then(($val) => {
      if (body.is_shell === true) {
        cy.wrap($val).click();
      }
    });
  // Update the value of the "Argument Example"
  cy.getIframeBody()
    .find('input[name="command_example"]')
    .clear()
    .type(body.argument_example);
  // Update the value of the "Connectors"
  cy.getIframeBody()
    .find('select[name="connectors"]')
    .select(`${body.connector_id}`);
  // Update the value of the "Graph"
  cy.getIframeBody()
    .find('select[name="graph_id"]')
    .select(`${body.graph_template_id}`);
});

Cypress.Commands.add("checkValuesOfCommands", (name: string, body: Cmd) => {
  // Wait for the "Command Name" input to be charged on the DOM
  cy.waitForElementInIframe("#main-content", 'input[name="command_name"]');
  // Check that the "Command Name" input contains right value
  cy.getIframeBody()
    .find('input[name="command_name"]')
    .should("have.value", `${name}`);
  // Check that the "Command Type" input contains right value
  cy.getIframeBody()
    .find(`input[name="command_type[command_type]"][value="${body.type}"]`)
    .should("be.checked");
  // Check that the "Command Line" input contains right value
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .should("have.value", body.command_line);
  // Check that the "Enable Shell" checkbox contains right value
  cy.getIframeBody()
    .find('input[name="enable_shell"]')
    .then(($val) => {
      if (body.is_shell === true) {
        cy.wrap($val).should("be.checked");
      } else {
        cy.wrap($val).should("not.be.checked");
      }
    });
  // Check that the "Argument Example" input contains right value
  cy.getIframeBody()
    .find('input[name="command_example"]')
    .should("have.value", body.argument_example);
  // Check that the "Connectors" contains right value
  cy.getIframeBody()
    .find(`select[name="connectors"]`)
    .find(`option[value="${body.connector_id}"]`)
    .should("be.selected");
  // Check that the "Graph" contains right value
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