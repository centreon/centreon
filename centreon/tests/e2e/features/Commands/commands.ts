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

Cypress.Commands.add("addConnectors", (body: Ctr) => {
  // Wait for the "Connector Name" input to be charged on the DOM
  cy.waitForElementInIframe("#main-content", 'input[name="connector_name"]');
  // Type a value on the "Connector Name" input
  cy.getIframeBody().find('input[name="connector_name"]').type(body.name);
  // Type a value on the "Connector Description" input
  cy.getIframeBody().find('input[name="connector_description"]').type(body.description);
  // Type a value on the "Command Line" textarea
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .type(body.command_line);
  // Type a value on the "Used by command" input
  cy.getIframeBody()
    .find('input[placeholder="Used by command"]')
    .type(body.used_by_command);
  // Select the command used by the connector
  cy.getIframeBody()
    .find(`div[title="${body.used_by_command}"]`)
    .click();
  // Enable if needed the connector (default value is disabled)
  cy.getIframeBody()
    .find('input[name="connector_status[connector_status]"][value="1"]')
    .then(($val) => {
      if (body.is_enabled === 1) {
        cy.wrap($val).click({ force: true });
      }
    });
});

Cypress.Commands.add("updateConnectors", (body: Ctr) => {
  // Wait for the "Connector Name" input to be charged on the DOM
  cy.waitForElementInIframe("#main-content", 'input[name="connector_name"]');
  // Update the value of the "Connector Name"
  cy.getIframeBody().find('input[name="connector_name"]').clear().type(body.name);
  // Update the value of the "Connector Description"
  cy.getIframeBody().find('input[name="connector_description"]').clear().type(body.description);
  // Update the value of the "Command Line"
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .clear()
    .type(body.command_line);
  // Clear the value on the "Used by command" input
  cy.getIframeBody()
    .find('span[title="Clear field"]')
    .click({ force: true });
  // Update a value on the "Used by command" input
  cy.getIframeBody()
    .find('input[placeholder="Used by command"]')
    .type(body.used_by_command);
  // Select the command used by the connector
  cy.getIframeBody()
    .find(`div[title="${body.used_by_command}"]`)
    .click();
  // Update the value of the "Connector Status"
  cy.getIframeBody()
    .find('input[name="connector_status[connector_status]"][value="1"]')
    .then(($val) => {
      if (body.is_enabled === 1) {
        cy.wrap($val).click({ force: true });
      }
    });
});

Cypress.Commands.add("checkValuesOfConnectors", (name: string, body: Ctr) => {
  // Wait for the "Connector Name" input to be charged on the DOM
  cy.waitForElementInIframe("#main-content", 'input[name="connector_name"]');
  // Check that the "Connector Name" input contains right value
  cy.getIframeBody()
    .find('input[name="connector_name"]')
    .should("have.value", `${name}`);
  // Check that the "Connector Description" input contains right value
  cy.getIframeBody()
    .find('input[name="connector_description"]')
    .should("have.value", body.description);
  // Check that the "Command Line" input contains right value
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .should("have.value", body.command_line);
  // Check that the "Used by command" input contains right value
  cy.getIframeBody()
    .find('select[id="command_id"]')
    .then(($val) => {
      // If the name of the connector ends with "_1", it means the connector is duplicated then the value should be empty
      if (name.endsWith("_1")) {
        cy.wrap($val).should("have.text", "");
      }
      // Else, the value should be the one chose during the creation/update of the connector
      else {
        cy.wrap($val).should("have.text", body.used_by_command);
      }
    }
    );
  // Check that the "Connector Status" contains right value
  cy.getIframeBody()
    .find(`input[name="connector_status[connector_status]"][value="${body.is_enabled}"]`)
    .should("be.checked");
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

interface Ctr {
  name: string;
  description: string;
  command_line: string;
  used_by_command: string;
  is_enabled: number;
}

declare global {
  namespace Cypress {
    interface Chainable {
      addCommands: (body: Cmd) => Cypress.Chainable;
      updateCommands: (body: Cmd) => Cypress.Chainable;
      checkValuesOfCommands: (name: string, body: Cmd) => Cypress.Chainable;
      addConnectors: (body: Ctr) => Cypress.Chainable;
      updateConnectors: (body: Ctr) => Cypress.Chainable;
      checkValuesOfConnectors: (name: string, body: Ctr) => Cypress.Chainable;
    }
  }
}

export { };
