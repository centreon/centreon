interface Cmd {
  name: string;
  type: string;
  globalMacros: string;
  standardMacros: string;
  installedPlugins: string;
  isShell: boolean;
  connectorName: number;
  comments: string;
}

interface Ctr {
  name: string;
  description: string;
  commandLine: string;
  usedByCommand: string;
  isEnabled: number;
}

Cypress.Commands.add(
  'fillCommandLine',
  (index: number, request: string, value: string, label: string) => {
    cy.getByLabel({ label: 'Open', tag: 'button' }).eq(index).click();
    cy.wait(`@${request}`);
    cy.contains('.MuiAutocomplete-option p', value).click();
    cy.getByTestId({ testId: label, tag: 'button' }).click();
  }
);

Cypress.Commands.add('searchForCommandsByName', (name: string) => {
  cy.get('#searchbar').clear().type(name);
  cy.wait('@getCommandsList');
  cy.contains('p', name).should('be.visible');
});

Cypress.Commands.add('addOrUpdateCommands', (body: Cmd) => {
  // Type a value on the "Command Name" input
  cy.get('#Name').clear().type(body.name);
  // Chose a "Command Type"
  cy.get(`input[value="${body.type}"]`).click();
  // Type a value on the "Command Line" textarea
  cy.get('#Commandline').clear();
  cy.fillCommandLine(
    0,
    'getGlobalMacros',
    body.globalMacros,
    'Insert global marco'
  );
  cy.fillCommandLine(
    1,
    'getPlugins',
    body.standardMacros,
    'Insert installed plugin'
  );
  cy.fillCommandLine(
    2,
    'getStandardMacros',
    body.installedPlugins,
    'Insert standard marco'
  );
  // Enable/ Disable shell
  cy.get('span[data-testid="enable-shell-syntax"]').then(($el) => {
    if (body.isShell === true && $el.length) {
      cy.wrap($el).click();
    }
  });
  // Chose a connector
  cy.getByLabel({ label: 'Open', tag: 'button' }).eq(3).click();
  cy.wait('@getConnectors');
  cy.contains(body.connectorName).click();
  // Write a comment
  cy.get('#Comments').clear().type(body.comments);
});

Cypress.Commands.add('checkValuesOfCommands', (name: string, body: Cmd) => {
  // Check that the "Command Name" input contains right value
  cy.get('#Name').invoke('val').should('include', name);
  // Check that the "Command Type" input contains right value
  cy.get(`input[type="radio"][value="${body.type}"]`).should('be.checked');

  // Check that the "Command Line" input contains right value
  [body.globalMacros, body.standardMacros, body.installedPlugins].forEach(
    (value) => {
      cy.get('#Commandline').invoke('val').should('include', value);
    }
  );
  // Check that the "Enable Shell" checkbox contains right value
  cy.get('span[data-testid="enable-shell-syntax"] input[type="checkbox"]').then(
    ($checkbox) => {
      if (body.isShell === true) {
        cy.wrap($checkbox).should('be.checked');
      } else {
        cy.wrap($checkbox).should('not.be.checked');
      }
    }
  );
  // Check that the "Connectors" contains right value
  cy.get('#Selectanoptimizationconnector').should(
    'have.value',
    body.connectorName
  );
  // Check that the "Comments" contains right value
  cy.get('#Comments').should('have.value', body.comments);
});

Cypress.Commands.add(
  'addCommandToResource',
  (index: number, command: string) => {
    cy.getIframeBody().find('span[title="Clear field"]').eq(index).click();
    // Click on the check command field in the form
    cy.getIframeBody().find('span[title="Check Command"]').click();
    // Chose a check command
    cy.getIframeBody().find(`div[title="${command}"]`).click();
  }
);

Cypress.Commands.add('addConnectors', (body: Ctr) => {
  // Wait for the "Connector Name" input to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="connector_name"]');
  // Type a value on the "Connector Name" input
  cy.getIframeBody().find('input[name="connector_name"]').type(body.name);
  // Type a value on the "Connector Description" input
  cy.getIframeBody()
    .find('input[name="connector_description"]')
    .type(body.description);
  // Type a value on the "Command Line" textarea
  cy.getIframeBody().find('textarea[id="command_line"]').type(body.commandLine);
  // Type a value on the "Used by command" input
  cy.getIframeBody()
    .find('input[placeholder="Used by command"]')
    .type(body.usedByCommand);
  // Select the command used by the connector
  cy.getIframeBody().find(`div[title="${body.usedByCommand}"]`).click();
  // Enable if needed the connector (default value is disabled)
  cy.getIframeBody()
    .find('input[name="connector_status[connector_status]"][value="1"]')
    .then(($val) => {
      if (body.isEnabled === 1) {
        cy.wrap($val).click({ force: true });
      }
    });
});

Cypress.Commands.add('updateConnectors', (body: Ctr) => {
  // Wait for the "Connector Name" input to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="connector_name"]');
  // Update the value of the "Connector Name"
  cy.getIframeBody()
    .find('input[name="connector_name"]')
    .clear()
    .type(body.name);
  // Update the value of the "Connector Description"
  cy.getIframeBody()
    .find('input[name="connector_description"]')
    .clear()
    .type(body.description);
  // Update the value of the "Command Line"
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .clear()
    .type(body.commandLine);
  // Clear the value on the "Used by command" input
  cy.getIframeBody().find('span[title="Clear field"]').click({ force: true });
  // Update a value on the "Used by command" input
  cy.getIframeBody()
    .find('input[placeholder="Used by command"]')
    .type(body.usedByCommand);
  // Select the command used by the connector
  cy.getIframeBody().find(`div[title="${body.usedByCommand}"]`).click();
  // Update the value of the "Connector Status"
  cy.getIframeBody()
    .find('input[name="connector_status[connector_status]"][value="1"]')
    .then(($val) => {
      if (body.isEnabled === 1) {
        cy.wrap($val).click({ force: true });
      }
    });
});

Cypress.Commands.add('checkValuesOfConnectors', (name: string, body: Ctr) => {
  // Wait for the "Connector Name" input to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="connector_name"]');
  // Check that the "Connector Name" input contains right value
  cy.getIframeBody()
    .find('input[name="connector_name"]')
    .should('have.value', `${name}`);
  // Check that the "Connector Description" input contains right value
  cy.getIframeBody()
    .find('input[name="connector_description"]')
    .should('have.value', body.description);
  // Check that the "Command Line" input contains right value
  cy.getIframeBody()
    .find('textarea[id="command_line"]')
    .should('have.value', body.commandLine);
  // Check that the "Used by command" input contains right value
  cy.getIframeBody()
    .find('select[id="command_id"]')
    .then(($val) => {
      // If the name of the connector ends with "_1", it means the connector is duplicated then the value should be empty
      if (name.endsWith('_1')) {
        cy.wrap($val).should('have.text', '');
      }
      // Else, the value should be the one chose during the creation/update of the connector
      else {
        cy.wrap($val).should('have.text', body.usedByCommand);
      }
    });
  // Check that the "Connector Status" contains right value
  cy.getIframeBody()
    .find(
      `input[name="connector_status[connector_status]"][value="${body.isEnabled}"]`
    )
    .should('be.checked');
});

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      fillCommandLine: (
        index: number,
        request: string,
        value: string,
        label: string
      ) => Cypress.Chainable;
      searchForCommandsByName: (name: string) => Cypress.Chainable;
      addOrUpdateCommands: (body: Cmd) => Cypress.Chainable;
      checkValuesOfCommands: (name: string, body: Cmd) => Cypress.Chainable;
      addCommandToResource: (
        index: number,
        command: string
      ) => Cypress.Chainable;
      addConnectors: (body: Ctr) => Cypress.Chainable;
      updateConnectors: (body: Ctr) => Cypress.Chainable;
      checkValuesOfConnectors: (name: string, body: Ctr) => Cypress.Chainable;
    }
  }
}

export {};
